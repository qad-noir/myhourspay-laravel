<?php

namespace Tests\Feature;

use App\Models\OperationalIncident;
use App\Models\User;
use App\Services\DatabaseSchemaIncident;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class DatabaseSchemaIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_requests_receive_a_graceful_warning_and_create_one_deduplicated_incident(): void
    {
        $exception = $this->schemaException();
        Route::post('/_test/schema-mismatch', fn () => throw $exception)->middleware('web');
        $user = User::factory()->create();
        $payload = ['work_date' => '2026-08-23', 'start_time' => '09:00', 'end_time' => '12:30'];

        $first = $this->actingAs($user)->from('/hours')->post('/_test/schema-mismatch', $payload);
        $second = $this->actingAs($user)->from('/hours')->post('/_test/schema-mismatch', $payload);

        $first->assertRedirect('/hours')->assertSessionHasErrors('service');
        $second->assertRedirect('/hours')->assertSessionHasErrors('service');
        $this->assertDatabaseCount('operational_incidents', 1);
        $this->assertDatabaseHas('operational_incidents', [
            'event_type' => DatabaseSchemaIncident::EVENT_TYPE,
            'severity' => 'critical',
            'submitted_email' => $user->email,
        ]);
        $this->assertStringNotContainsString('insert into', (string) OperationalIncident::query()->value('exception_message'));
    }

    public function test_json_requests_receive_a_safe_service_unavailable_response(): void
    {
        $exception = $this->schemaException();
        Route::post('/_test/schema-mismatch-json', fn () => throw $exception)->middleware('web');

        $this->postJson('/_test/schema-mismatch-json')
            ->assertStatus(503)
            ->assertJsonStructure(['message', 'reference'])
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Administrators have been notified'));
    }

    public function test_safe_get_requests_receive_the_branded_schema_update_page(): void
    {
        $exception = $this->schemaException();
        Route::get('/_test/schema-mismatch-page', fn () => throw $exception)->middleware('web');

        $this->get('/_test/schema-mismatch-page')
            ->assertStatus(503)
            ->assertSee('An update is still being applied')
            ->assertSee('administrators have been notified');
    }

    private function schemaException(): QueryException
    {
        $previous = new PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'net_minutes' in 'field list'", 1054);

        return new QueryException(
            'mysql',
            'insert into `hours_entries` (`net_minutes`, `week_start`) values (?, ?)',
            [210, '2026-08-17'],
            $previous,
        );
    }
}
