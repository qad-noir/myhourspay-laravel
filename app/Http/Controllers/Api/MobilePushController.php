<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobilePushDevice;
use App\Services\MobileMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobilePushController extends Controller
{
    private function device(Request $request)
    {
        return MobilePushDevice::where('user_id', $request->user()->id)
            ->where('personal_access_token_id', $request->user()->currentAccessToken()->id);
    }

    public function show(Request $request)
    {
        return response()->json(['data' => ['enabled' => (bool) $this->device($request)->value('enabled')]]);
    }

    public function update(Request $request, MobileMutation $mutation)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean:strict'],
            'token' => ['required_if:enabled,true', 'prohibited_if:enabled,false', 'string', 'min:1', 'max:4096'],
            'platform' => ['required', 'in:android,ios'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        return $mutation->runForSession($request, function () use ($request, $data) {
            // Serializes cross-account token rebinding as well as same-session rotation.
            DB::table('mobile_push_registration_locks')->where('id', 1)->lockForUpdate()->first();
            $device = $this->device($request)->first();
            $hash = $data['enabled'] ? hash('sha256', $data['token']) : null;
            if ($hash) {
                $device ??= MobilePushDevice::where('token_hash', $hash)->first();
                MobilePushDevice::where('token_hash', $hash)->when($device, fn ($q) => $q->where('id', '!=', $device->id))
                    ->update(['enabled' => false, 'token' => null, 'token_hash' => null,
                        'personal_access_token_id' => null, 'revoked_at' => now()]);
            }
            $device ??= new MobilePushDevice;
            $device->fill(['user_id' => $request->user()->id,
                'personal_access_token_id' => $request->user()->currentAccessToken()->id,
                'platform' => $data['platform'], 'device_name' => $data['device_name'],
                'enabled' => $data['enabled'], 'token' => $data['enabled'] ? $data['token'] : null,
                'token_hash' => $hash, 'registered_at' => now(), 'revoked_at' => $data['enabled'] ? null : now()])->save();

            return response()->json(['data' => ['enabled' => $device->enabled]]);
        });
    }

    public function destroy(Request $request)
    {
        DB::transaction(function () use ($request): void {
            DB::table('mobile_push_registration_locks')->where('id', 1)->lockForUpdate()->first();
            $this->device($request)->update(['enabled' => false, 'token' => null, 'token_hash' => null, 'revoked_at' => now()]);
        });

        return response()->noContent();
    }
}
