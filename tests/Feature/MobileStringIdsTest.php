<?php

namespace Tests\Feature;

use App\Models\HoursEntry;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\BillingSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// Run the existing API suite again with the database hydration behaviour that
// exposed the production bug, rather than trusting SQLite's integer values.
class MobileStringIdsTest extends MobileApiTest
{
    private $originalDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(clone $this->originalDispatcher);
        foreach ([HoursEntry::class, Timesheet::class, Project::class] as $model) {
            $model::retrieved(function ($record) {
                $attributes = $record->getAttributes();
                foreach (['workspace_id', 'user_id', 'project_id', 'timesheet_id', 'reviewed_by', 'client_id'] as $field) {
                    if (isset($attributes[$field])) {
                        $attributes[$field] = (string) $attributes[$field];
                    }
                }
                $record->setRawAttributes($attributes, true);
            });
        }
    }

    protected function tearDown(): void
    {
        Model::setEventDispatcher($this->originalDispatcher);
        parent::tearDown();
    }

    public function test_string_backed_edit_is_isolated_replayable_and_keeps_numeric_nullable_ids(): void
    {
        app(BillingSettings::class)->set('paid_enforcement_enabled', false);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create(['name' => 'Test']);
        $workspace->users()->attach([$user->id => ['role' => 'owner', 'position' => 'Owner'], $other->id => ['role' => 'member', 'position' => 'Member']]);
        $another = $user->ownedWorkspaces()->create(['name' => 'Other']);
        $another->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);
        $entry = HoursEntry::create(['user_id' => $user->id, 'workspace_id' => $workspace->id,
            'work_date' => '2026-09-28', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid']);
        $raw = $entry->fresh();
        $this->assertIsString($raw->getRawOriginal('workspace_id'));
        $this->assertIsString($raw->getRawOriginal('user_id'));
        $this->assertSame($workspace->id, $raw->workspace_id);
        $token = $user->createToken('mobile:test', ['mobile:access'], now()->addDay())->plainTextToken;
        $url = '/api/v1/mobile/workspaces/'.$workspace->id.'/hours';
        $data = $this->withToken($token)->getJson($url.'?start=2026-09-28&end=2026-10-04')->assertOk()->json('data.0');
        $this->assertSame($workspace->id, $data['workspace_id']);
        $this->assertNull($data['project_id']);
        $this->assertNull($data['timesheet_id']);
        $body = array_merge($data, ['notes' => 'Edited']);
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->patchJson($url.'/'.$entry->id, $body)->assertOk();
        $this->patchJson($url.'/'.$entry->id, $body)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($url.'/'.$entry->id, $body)->assertConflict()->assertJsonPath('code', 'entry_changed');
        $this->patchJson($url.'/999999', $body)->assertNotFound();
        $this->patchJson('/api/v1/mobile/workspaces/'.$another->id.'/hours/'.$entry->id, $body)->assertNotFound();
        auth()->forgetGuards();
        $this->withToken($other->createToken('mobile:test', ['mobile:access'], now()->addDay())->plainTextToken)
            ->patchJson($url.'/'.$entry->id, $body)->assertNotFound();
        auth()->forgetGuards();
        $this->withToken($token);
        $current = $this->getJson($url.'?start=2026-09-28&end=2026-10-04')->json('data.0');
        Timesheet::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'week_start' => '2026-09-28', 'status' => 'approved']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($url.'/'.$entry->id, $current)->assertConflict()->assertJsonPath('code', 'timesheet_locked');
        $this->assertDatabaseHas('hours_entries', ['id' => $entry->id, 'notes' => 'Edited']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, $body)->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')->assertJsonPath('errors.work_date.0', 'This date already has an entry. Try editing it instead.');
    }

    public function test_nullable_and_non_null_foreign_keys_are_cast_without_changing_raw_storage(): void
    {
        foreach ([new HoursEntry, new Timesheet, new Project] as $record) {
            foreach (['workspace_id', 'user_id', 'project_id', 'timesheet_id', 'reviewed_by', 'client_id'] as $field) {
                if (! array_key_exists($field, $record->getCasts())) {
                    continue;
                }
                $record->setRawAttributes([$field => '42'], true);
                $this->assertSame('42', $record->getRawOriginal($field));
                $this->assertSame(42, $record->toArray()[$field]);
                $record->setRawAttributes([$field => null], true);
                $this->assertNull($record->toArray()[$field]);
            }
        }
    }
}
