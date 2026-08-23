<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->unsignedSmallInteger('net_minutes')->default(0)->after('break_type');
            $table->date('week_start')->nullable()->after('work_date');
            $table->index('work_date', 'hours_entries_work_date_index');
            $table->index(['week_start', 'workspace_id', 'user_id'], 'hours_entries_week_workspace_user_index');
        });

        DB::table('hours_entries')->orderBy('id')->chunkById(500, function ($entries): void {
            foreach ($entries as $entry) {
                $start = $this->clockMinutes((string) $entry->start_time);
                $end = $this->clockMinutes((string) $entry->end_time);
                $gross = max(0, $end - $start);
                $net = $entry->break_type === 'paid'
                    ? $gross
                    : max(0, $gross - (int) $entry->break_minutes);

                DB::table('hours_entries')->where('id', $entry->id)->update([
                    'net_minutes' => $net,
                    'week_start' => CarbonImmutable::parse($entry->work_date)->startOfWeek()->toDateString(),
                ]);
            }
        });

        if (Schema::hasIndex('hours_entries', 'hours_entries_workspace_period_index')) {
            Schema::table('hours_entries', fn (Blueprint $table) => $table->dropIndex('hours_entries_workspace_period_index'));
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX users_name_prefix_index ON users (name(190))');
        } else {
            Schema::table('users', fn (Blueprint $table) => $table->index('name', 'users_name_prefix_index'));
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_name_prefix_index'));

        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->dropIndex('hours_entries_week_workspace_user_index');
            $table->dropIndex('hours_entries_work_date_index');
            $table->dropColumn(['net_minutes', 'week_start']);
            $table->index(['workspace_id', 'user_id', 'work_date'], 'hours_entries_workspace_period_index');
        });
    }

    private function clockMinutes(string $value): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($value, 0, 5)));

        return ($hours * 60) + $minutes;
    }
};
