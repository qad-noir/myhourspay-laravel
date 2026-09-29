<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;

class MobileResponse
{
    public static function fail(string $code, string $message, int $status = 403): never
    {
        throw new HttpResponseException(response()->json(compact('code', 'message'), $status));
    }
}
