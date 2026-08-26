<?php

use App\Http\Middleware\EnsureCurrentWorkspace;
use App\Http\Middleware\EnsureEmailCodeVerified;
use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureWorkspaceIsWritable;
use App\Services\BillingWebhookTracker;
use App\Services\DatabaseSchemaIncident;
use App\Services\UnexpectedApplicationIncident;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'email-code.verified' => EnsureEmailCodeVerified::class,
            'workspace' => EnsureCurrentWorkspace::class,
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
            'feature' => EnsureFeatureAccess::class,
            'workspace.writable' => EnsureWorkspaceIsWritable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception): void {
            if (! app()->bound('request')) {
                return;
            }

            $request = app('request');
            if ($request instanceof Request && $request->routeIs('cashier.webhook')) {
                app(BillingWebhookTracker::class)->failed($request, $exception);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (QueryException $exception, Request $request) {
            $incident = app(DatabaseSchemaIncident::class);
            if (! $incident->matches($exception)) {
                return null;
            }

            $reference = $incident->record($exception, $request);
            $message = "We couldn’t complete this action because an application update is still being applied. Your data was not saved. Administrators have been notified. Reference: {$reference}.";

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'reference' => $reference,
                ], 503);
            }

            if (! $request->isMethodSafe()) {
                return redirect()->back()
                    ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'code', 'recovery_code', 'token']))
                    ->withErrors(['service' => $message]);
            }

            return response()->view('errors.schema-mismatch', compact('reference'), 503);
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof HttpExceptionInterface
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof ModelNotFoundException) {
                return null;
            }

            $reference = app(UnexpectedApplicationIncident::class)->record($exception, $request);
            $message = "We couldn’t complete this request. The error has been logged and administrators have been notified. Reference: {$reference}.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'reference' => $reference], 500);
            }

            if (! $request->isMethodSafe()) {
                return redirect()->back()
                    ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password', 'code', 'recovery_code', 'token']))
                    ->withErrors(['service' => $message]);
            }

            return response()->view('errors.unexpected', compact('reference'), 500);
        });
    })->create();
