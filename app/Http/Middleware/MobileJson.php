<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MobileJson
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('api/v1/mobile/*')) {
            return $next($request);
        }
        $request->headers->set('Accept', 'application/json');
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $handler = app(ExceptionHandler::class);
            $handler->report($exception);
            $response = $handler->render($request, $exception);
        }
        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $body = $response->getData(true);
            $body['code'] ??= match ($response->getStatusCode()) {
                401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found',
                409 => 'conflict', 422 => 'validation_failed', 429 => 'rate_limited',
                default => 'server_error',
            };
            $response->setData($body);
        }
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
