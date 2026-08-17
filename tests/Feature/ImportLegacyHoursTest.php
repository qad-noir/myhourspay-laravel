<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportLegacyHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.legacy_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $schema = Schema::connection('legacy_test');
        $schema->create('auth_identities', function (Blueprint $table): void {
            $table->integer('user_id');
            $table->string('secret');
        });
        $schema->create('hours_entries', function (Blueprint $table): void {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('break_minutes');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_dry_run_maps_by_email_and_writes_nothing(): void
    {
        $user = User::factory()->create(['email' => 'worker@example.test']);
        $this->legacyRows($user->email);

        $this->artisan('hours:import-legacy', ['--source-connection' => 'legacy_test', '--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Dry run complete');
        $this->assertDatabaseCount('hours_entries', 0);
        $this->assertDatabaseCount('hours_import_records', 0);
    }

    public function test_import_is_idempotent_preserves_timestamps_and_rolls_back_only_imported_rows(): void
    {
        $user = User::factory()->create(['email' => 'worker@example.test']);
        $this->legacyRows($user->email);
        $native = $user->hoursEntries()->create(['work_date' => '2026-08-04', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0]);

        $arguments = ['--source-connection' => 'legacy_test', '--source' => 'test-source'];
        $this->artisan('hours:import-legacy', $arguments)->assertSuccessful();
        $this->artisan('hours:import-legacy', $arguments)->assertSuccessful();
        $this->assertDatabaseCount('hours_entries', 2);
        $this->assertDatabaseHas('hours_entries', ['work_date' => '2026-08-03', 'created_at' => '2026-08-04 10:00:00']);

        $this->artisan('hours:import-legacy', ['--source' => 'test-source', '--rollback' => true])->assertSuccessful();
        $this->assertDatabaseCount('hours_entries', 1);
        $this->assertDatabaseHas('hours_entries', ['id' => $native->id]);
    }

    public function test_unmapped_and_invalid_rows_are_reported_not_imported(): void
    {
        DB::connection('legacy_test')->table('hours_entries')->insert([
            ['id' => 1, 'user_id' => null, 'work_date' => '2026-08-03', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'notes' => null, 'created_at' => null, 'updated_at' => null],
        ]);
        $this->artisan('hours:import-legacy', ['--source-connection' => 'legacy_test', '--dry-run' => true])->assertExitCode(2)->expectsOutputToContain('Unresolved legacy user IDs');
        $this->assertDatabaseCount('hours_entries', 0);
    }

    public function test_ambiguous_legacy_email_is_not_mapped(): void
    {
        $user = User::factory()->create(['email' => 'shared@example.test']);
        DB::connection('legacy_test')->table('auth_identities')->insert([
            ['user_id' => 10, 'secret' => $user->email],
            ['user_id' => 11, 'secret' => $user->email],
        ]);
        DB::connection('legacy_test')->table('hours_entries')->insert([
            'id' => 9, 'user_id' => 10, 'work_date' => '2026-08-03', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'notes' => null, 'created_at' => null, 'updated_at' => null,
        ]);

        $this->artisan('hours:import-legacy', ['--source-connection' => 'legacy_test', '--dry-run' => true])->assertExitCode(2);
        $this->assertDatabaseCount('hours_entries', 0);
    }

    private function legacyRows(string $email): void
    {
        DB::connection('legacy_test')->table('auth_identities')->insert(['user_id' => 99, 'secret' => $email]);
        DB::connection('legacy_test')->table('hours_entries')->insert([
            'id' => 500, 'user_id' => 99, 'work_date' => '2026-08-03', 'start_time' => '09:00:00', 'end_time' => '17:30:00', 'break_minutes' => 30, 'notes' => 'legacy', 'created_at' => '2026-08-04 10:00:00', 'updated_at' => '2026-08-04 11:00:00',
        ]);
    }
}
