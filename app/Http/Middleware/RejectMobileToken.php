<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RejectMobileToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();
        abort_if($token && str_starts_with($token->name ?? '', 'mobile:'), 403, 'Mobile tokens may only use the mobile API.');

        return $next($request);
    }
}
