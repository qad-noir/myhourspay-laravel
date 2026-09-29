<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MobileContractTest extends TestCase
{
    public function test_every_native_route_is_documented_and_every_reference_resolves(): void
    {
        $spec = json_decode(file_get_contents(base_path('docs/api/mobile.openapi.yaml')), true, flags: JSON_THROW_ON_ERROR);
        $documented = [];
        $ids = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $documented[] = strtoupper($method).' api/v1/mobile'.$path;
                $this->assertNotContains($operation['operationId'], $ids);
                $ids[] = $operation['operationId'];
            }
        }
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/mobile/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $actual[] = $method.' '.$route->uri();
            }
        }
        sort($actual);
        sort($documented);
        $this->assertSame($actual, $documented);
        $walk = function (array $node) use (&$walk, $spec): void {
            if (isset($node['$ref'])) {
                $this->assertStringStartsWith('#/components/schemas/', $node['$ref']);
                $this->assertArrayHasKey(basename($node['$ref']), $spec['components']['schemas']);
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($spec);
    }
}
