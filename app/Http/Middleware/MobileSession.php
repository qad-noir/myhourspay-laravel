<?php

namespace App\Http\Middleware;

use App\Support\MobileResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MobileSession
{
    public function handle(Request $request, Closure $next, string $level = 'verified')
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if (! $request->bearerToken() || ! $token instanceof PersonalAccessToken
            || ! str_starts_with($token->name, 'mobile:')
            || (! $token->can('mobile:access') && ! $token->can('mobile:verify'))) {
            MobileResponse::fail('unauthenticated', 'Sign in to the mobile app.', 401);
        }
        if ($user->suspended_at) {
            MobileResponse::fail('account_suspended', 'This account is suspended.');
        }
        if ($level === 'verified' && (! $user->email_verified_at || ! $token->can('mobile:access'))) {
            MobileResponse::fail('email_verification_required', 'Verify your email before continuing.');
        }

        return $next($request);
    }
}
