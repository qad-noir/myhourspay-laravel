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
