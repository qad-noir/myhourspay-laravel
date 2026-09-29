<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MobileErrorResponseTest extends TestCase
{
    public function test_missing_mobile_routes_hide_debug_details_with_or_without_debug(): void
    {
        foreach ([true, false] as $debug) {
            config(['app.debug' => $debug]);
            foreach (['/api/v1/mobile', '/api/v1/mobile/', '/api/v1/mobile/missing'] as $path) {
                $response = $this->get($path)->assertNotFound()
                    ->assertJsonPath('code', 'not_found');
                foreach (['exception', 'file', 'line', 'trace'] as $key) {
                    $response->assertJsonMissingPath($key);
                }
                $this->assertStringNotContainsString(base_path(), $response->getContent());
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            }
        }
    }

    public function test_http_exceptions_do_not_expose_sensitive_messages(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/mobile/test-error', fn () => abort(500, 'secret SQL and filesystem path'));
        $this->get('/api/v1/mobile/test-error')->assertStatus(500)
            ->assertExactJson(['message' => 'Unable to complete this request.', 'code' => 'server_error']);
    }

    public function test_wrong_method_is_a_safe_json_error(): void
    {
        config(['app.debug' => true]);
        $this->get('/api/v1/mobile/auth/login')->assertStatus(405)
            ->assertExactJson(['message' => 'Method not allowed.', 'code' => 'method_not_allowed']);
    }
}
