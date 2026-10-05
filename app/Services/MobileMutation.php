<?php

namespace App\Services;

use App\Models\Workspace;
use App\Support\MobileResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobileMutation
{
    /** Session writes use the same replay envelope without inventing a workspace. */
    public function runForSession(Request $request, Closure $operation)
    {
        $key = $request->header('Idempotency-Key');
        Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required|uuid'])->validate();
        $payload = $request->all();
        ksort($payload);
        // A keyed digest prevents registration secrets from appearing in stored requests.
        $hash = hash_hmac('sha256', $request->method().'|'.$request->path().'|'.json_encode($payload), config('app.key'));
        $sessionId = $request->user()->currentAccessToken()->id;

        return DB::transaction(function () use ($request, $operation, $key, $hash, $sessionId) {
            $session = $request->user()->tokens()->whereKey($sessionId)->lockForUpdate()->first();
            if (! $session || ($session->expires_at && $session->expires_at->lte(now()))) {
                MobileResponse::fail('unauthenticated', 'Sign in to the mobile app.', 401);
            }
            $query = DB::table('mobile_session_mutations')->where('personal_access_token_id', $sessionId)->where('request_key', $key);
            if ($previous = $query->first()) {
                if (! hash_equals($previous->request_hash, $hash)) {
                    MobileResponse::fail('idempotency_conflict', 'Use a new idempotency key for a different request.', 409);
                }

                return response()->json(json_decode($previous->response, true), $previous->status)->header('Idempotency-Replayed', 'true');
            }
            $response = $operation();
            DB::table('mobile_session_mutations')->insert([
                'personal_access_token_id' => $sessionId, 'request_key' => $key, 'request_hash' => $hash,
                'response' => $response->getContent(), 'status' => $response->getStatusCode(), 'created_at' => now(),
            ]);

            return $response;
        }, 3);
    }

    public function run(Request $request, Workspace $workspace, Closure $operation)
    {
        $key = $request->header('Idempotency-Key');
        Validator::make(['idempotency_key' => $key], ['idempotency_key' => 'required|uuid'])->validate();
        $payload = $request->all();
        ksort($payload);
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($payload));

        return DB::transaction(function () use ($request, $workspace, $operation, $key, $hash) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $query = DB::table('mobile_mutations')->where('user_id', $request->user()->id)->where('workspace_id', $workspace->id)->where('request_key', $key);
            if ($previous = $query->first()) {
                if (! hash_equals($previous->request_hash, $hash)) {
                    MobileResponse::fail('idempotency_conflict', 'Use a new idempotency key for a different request.', 409);
                }

                return response()->json(json_decode($previous->response, true), $previous->status)->header('Idempotency-Replayed', 'true');
            }
            $response = $operation();
            DB::table('mobile_mutations')->insert([
                'user_id' => $request->user()->id, 'workspace_id' => $workspace->id,
                'request_key' => $key, 'request_hash' => $hash, 'response' => $response->getContent(),
                'status' => $response->getStatusCode(), 'created_at' => now(),
            ]);

            return $response;
        });
    }
}
