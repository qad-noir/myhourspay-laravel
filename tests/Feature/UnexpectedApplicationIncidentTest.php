<?php

namespace Tests\Feature;

use App\Models\OperationalIncident;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class UnexpectedApplicationIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_exception_reporting_is_safe_before_an_http_request_is_bound(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $request = $this->app->make('request');
        $this->app->offsetUnset('request');

        try {
            $this->assertFalse($this->app->bound('request'));
            $handler->report(new RuntimeException('Early console bootstrap failure'));
            $this->assertTrue(true);
        } finally {
            $this->app->instance('request', $request);
        }
    }

    public function test_unexpected_browser_errors_are_logged_recorded_and_rendered_safely(): void
    {
        Log::spy();
        Route::get('/_test/unexpected-application-error', fn () => throw new RuntimeException('Private diagnostic detail'))
            ->middleware('web')
            ->name('test.unexpected-application-error');

        $response = $this->get('/_test/unexpected-application-error');

        $response->assertInternalServerError()
            ->assertSee('We couldn’t complete this request')
            ->assertDontSee('Private diagnostic detail');

        $incident = OperationalIncident::query()->sole();
        $this->assertSame('application.unexpected_failure', $incident->event_type);
        $this->assertStringContainsString('/_test/unexpected-application-error', $incident->exception_message);
        $this->assertStringNotContainsString('Private diagnostic detail', $incident->exception_message);
        $response->assertSee($incident->reference);

        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'An unexpected application request failed.'
            && $context['reference'] === $incident->reference
            && $context['exception'] instanceof RuntimeException
        )->once();
    }

    public function test_unexpected_json_errors_return_a_stable_safe_payload_and_incident(): void
    {
        Route::get('/_test/unexpected-json-error', fn () => throw new RuntimeException('Private API detail'))
            ->middleware('web');

        $response = $this->getJson('/_test/unexpected-json-error');

        $response->assertInternalServerError()
            ->assertJsonStructure(['message', 'reference'])
            ->assertJsonMissing(['message' => 'Private API detail']);

        $this->assertDatabaseHas('operational_incidents', [
            'reference' => $response->json('reference'),
            'event_type' => 'application.unexpected_failure',
        ]);
    }
}
