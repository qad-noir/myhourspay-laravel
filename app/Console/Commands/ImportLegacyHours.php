<?php

namespace App\Console\Commands;

use App\Models\HoursEntry;
use App\Models\User;
use App\Services\HoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class ImportLegacyHours extends Command
{
    protected $signature = 'hours:import-legacy
        {--source-connection=legacy : Configured source database connection}
        {--source=codeigniter-hours : Stable source label used by the import ledger}
        {--mapping= : CSV with legacy_user_id,laravel_user_id columns}
        {--dry-run : Validate and report without writing}
        {--rollback : Delete only entries recorded in this source ledger}';

    protected $description = 'Import legacy CodeIgniter Hours records with explicit user ownership';

    public function handle(HoursCalculator $calculator): int
    {
        $sourceName = (string) $this->option('source-connection');
        $sourceLabel = (string) $this->option('source');

        if ($this->option('rollback')) {
            return $this->rollback($sourceLabel);
        }

        try {
            $source = DB::connection($sourceName);
            $source->getPdo();
            $mapping = $this->mapping($source);
        } catch (Throwable $exception) {
            $this->error('Legacy source could not be prepared: '.$exception->getMessage());

            return self::FAILURE;
        }

        $counts = ['users matched' => count($mapping), 'users missing' => 0, 'rows considered' => 0, 'rows imported' => 0, 'rows skipped as existing' => 0, 'rows rejected as invalid' => 0, 'rows with unresolved ownership' => 0];
        $missingUsers = [];
        $dryRun = (bool) $this->option('dry-run');

        $source->table('hours_entries')->orderBy('id')->chunk(500, function ($rows) use ($calculator, $mapping, $sourceLabel, $dryRun, &$counts, &$missingUsers): void {
            foreach ($rows as $row) {
                $counts['rows considered']++;
                $legacyUserId = isset($row->user_id) ? (string) $row->user_id : '';
                if ($legacyUserId === '' || ! isset($mapping[$legacyUserId])) {
                    $counts['rows with unresolved ownership']++;
                    $missingUsers[$legacyUserId === '' ? '(null)' : $legacyUserId] = true;

                    continue;
                }

                $legacyId = (string) $row->id;
                if (DB::table('hours_import_records')->where(['source' => $sourceLabel, 'legacy_id' => $legacyId])->exists()) {
                    $counts['rows skipped as existing']++;

                    continue;
                }

                try {
                    $date = (string) $row->work_date;
                    $start = substr((string) $row->start_time, 0, 5);
                    $end = substr((string) $row->end_time, 0, 5);
                    $break = (int) $row->break_minutes;
                    $notes = isset($row->notes) && trim((string) $row->notes) !== '' ? trim((string) $row->notes) : null;
                    $calculator->validateDate($date);
                    $calculator->calculateNetMinutes($start, $end, $break);
                    if ($notes !== null && mb_strlen($notes) > (int) config('hours.maximum_notes_length')) {
                        throw new InvalidArgumentException('Notes exceed the configured maximum.');
                    }
                } catch (Throwable) {
                    $counts['rows rejected as invalid']++;

                    continue;
                }

                $userId = $mapping[$legacyUserId];
                if (HoursEntry::query()->where(['user_id' => $userId, 'work_date' => $date])->exists()) {
                    $counts['rows skipped as existing']++;

                    continue;
                }

                if (! $dryRun) {
                    DB::transaction(function () use ($row, $userId, $date, $start, $end, $break, $notes, $sourceLabel, $legacyId): void {
                        $entry = new HoursEntry(['work_date' => $date, 'start_time' => $start, 'end_time' => $end, 'break_minutes' => $break, 'notes' => $notes]);
                        $entry->user_id = $userId;
                        $entry->created_at = $this->validTimestamp($row->created_at ?? null) ?? now();
                        $entry->updated_at = $this->validTimestamp($row->updated_at ?? null) ?? $entry->created_at;
                        $entry->save();
                        DB::table('hours_import_records')->insert(['source' => $sourceLabel, 'legacy_id' => $legacyId, 'hours_entry_id' => $entry->id, 'imported_at' => now()]);
                    });
                }
                $counts['rows imported']++;
            }
        });

        $counts['users missing'] = count($missingUsers);
        $this->table(['Measure', 'Count'], collect($counts)->map(fn ($count, $label) => [$label, $count])->values()->all());
        if ($missingUsers !== []) {
            $this->warn('Unresolved legacy user IDs: '.implode(', ', array_keys($missingUsers)));
        }
        $this->info($dryRun ? 'Dry run complete; no records were written.' : 'Legacy Hours import complete.');

        return $counts['rows rejected as invalid'] > 0 || $counts['rows with unresolved ownership'] > 0 ? self::INVALID : self::SUCCESS;
    }

    private function mapping(ConnectionInterface $source): array
    {
        if (is_string($this->option('mapping')) && $this->option('mapping') !== '') {
            return $this->csvMapping((string) $this->option('mapping'));
        }

        if (! $source->getSchemaBuilder()->hasTable('auth_identities')) {
            throw new InvalidArgumentException('No mapping CSV was supplied and auth_identities is unavailable.');
        }

        $laravelUsers = User::query()->get(['id', 'email'])->groupBy(fn (User $user) => mb_strtolower($user->email));
        $mapping = [];
        $identityQuery = $source->table('auth_identities')->whereNotNull('secret');
        if ($source->getSchemaBuilder()->hasColumn('auth_identities', 'type')) {
            $identityQuery->whereIn('type', ['email', 'email_password']);
        }
        $identities = $identityQuery->get(['user_id', 'secret'])
            ->filter(fn ($identity) => filter_var(trim((string) $identity->secret), FILTER_VALIDATE_EMAIL))
            ->groupBy(fn ($identity) => mb_strtolower(trim((string) $identity->secret)));
        foreach ($identities as $email => $emailIdentities) {
            $legacyIds = $emailIdentities->pluck('user_id')->unique();
            $matches = $laravelUsers->get($email);
            if ($legacyIds->count() === 1 && $matches?->count() === 1) {
                $mapping[(string) $legacyIds->first()] = (int) $matches->first()->id;
            }
        }

        return $mapping;
    }

    private function csvMapping(string $path): array
    {
        $resolved = realpath($path) ?: realpath(storage_path('app/private/'.$path));
        if ($resolved === false || ! is_readable($resolved)) {
            throw new InvalidArgumentException('The mapping CSV cannot be read.');
        }

        $handle = fopen($resolved, 'rb');
        $header = fgetcsv($handle);
        if ($header !== ['legacy_user_id', 'laravel_user_id']) {
            fclose($handle);
            throw new InvalidArgumentException('Mapping CSV headings must be legacy_user_id,laravel_user_id.');
        }

        $mapping = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== 2 || ! ctype_digit($row[1]) || ! User::query()->whereKey((int) $row[1])->exists()) {
                fclose($handle);
                throw new InvalidArgumentException('Mapping CSV contains an invalid Laravel user ID.');
            }
            if (isset($mapping[$row[0]]) && $mapping[$row[0]] !== (int) $row[1]) {
                fclose($handle);
                throw new InvalidArgumentException('Mapping CSV contains an ambiguous legacy user ID.');
            }
            $mapping[$row[0]] = (int) $row[1];
        }
        fclose($handle);

        return $mapping;
    }

    private function rollback(string $source): int
    {
        if (! Schema::hasTable('hours_import_records')) {
            $this->error('The import ledger table does not exist.');

            return self::FAILURE;
        }

        $count = DB::transaction(function () use ($source): int {
            $ids = DB::table('hours_import_records')->where('source', $source)->pluck('hours_entry_id');
            // Import rollback is an explicit reversal of machine-created rows,
            // so remove them permanently instead of placing them in admin trash.
            $deleted = HoursEntry::query()->whereKey($ids)->forceDelete();
            DB::table('hours_import_records')->where('source', $source)->delete();

            return $deleted;
        });
        $this->info("Rolled back $count imported Hours entries for source [$source].");

        return self::SUCCESS;
    }

    private function validTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value, config('hours.timezone'))->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
