<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use App\Services\MobileAuthentication;
use App\Support\MobileResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    public function login(Request $request, MobileAuthentication $auth)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:4096', 'device_name' => 'required|string|max:100']);
        $user = User::where('email', Str::lower(trim($data['email'])))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            MobileResponse::fail('invalid_credentials', 'The credentials are incorrect.', 422);
        }

        return response()->json($auth->begin($user, $data['device_name']));
    }

    public function register(Request $request, CreateNewUser $create, MobileAuthentication $auth)
    {
        $request->validate(['device_name' => 'required|string|max:100']);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $user = $create->create($request->all());

        return response()->json($auth->issue($user, $request->string('device_name')->toString()), 201);
    }

    public function twoFactor(Request $request, MobileAuthentication $auth)
    {
        $data = $request->validate(['challenge_token' => 'required|string|size:64',
            'code' => 'required_without:recovery_code|nullable|digits:6',
            'recovery_code' => 'required_without:code|nullable|string|max:100']);

        return response()->json($auth->complete($data['challenge_token'], $data['code'] ?? null, $data['recovery_code'] ?? null));
    }

    public function me(Request $request, MobileAuthentication $auth)
    {
        return response()->json(['data' => $auth->user($request->user())]);
    }

    public function verify(Request $request, EmailVerificationCodeService $codes, MobileAuthentication $auth)
    {
        $data = $request->validate(['code' => 'required|digits:6']);

        return DB::transaction(function () use ($request, $codes, $auth, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! $user->email_verified_at) {
                if (! $codes->verify($user, $data['code'])) {
                    // Do not throw: failed verification attempt count must commit.
                    return response()->json(['code' => 'invalid_verification_code', 'message' => 'The verification code is invalid or expired.'], 422);
                }
                event(new Verified($user));
            }
            $token = $request->user()->currentAccessToken();
            $device = substr($token->name, strlen('mobile:'));
            $token->delete();

            return response()->json($auth->issue($user, $device));
        });
    }

    public function resend(Request $request, EmailVerificationCodeService $codes)
    {
        return DB::transaction(function () use ($request, $codes) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! $user->email_verified_at) {
                if (! $codes->canResend($user)) {
                    MobileResponse::fail('rate_limited', 'Please wait before requesting another code.', 429);
                }
                $codes->issue($user);
            }

            return response()->json(['message' => 'Verification request processed.']);
        });
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        Password::sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return response()->json(['message' => 'If the account exists, a password reset link has been sent.']);
    }

    public function resetPassword(Request $request, ResetUserPassword $reset)
    {
        $data = $request->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => 'required|string|confirmed', 'password_confirmation' => 'required|string']);
        $status = Password::reset($data, function (User $user, string $password) use ($reset, $data): void {
            $reset->reset($user, $data);
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            MobileResponse::fail('invalid_reset_token', 'The password reset token is invalid or expired.', 422);
        }

        return response()->json(['message' => 'Password reset. Sign in again.']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function sessions(Request $request)
    {
        return response()->json(['data' => $request->user()->tokens()->where('name', 'like', 'mobile:%')
            ->get(['id', 'name', 'last_used_at', 'expires_at'])->map(fn ($token) => [
                'id' => $token->id, 'device_name' => substr($token->name, 7),
                'last_used_at' => $token->last_used_at?->toISOString(), 'expires_at' => $token->expires_at?->toISOString(),
                'current' => $token->id === $request->user()->currentAccessToken()->id,
            ])]);
    }

    public function revoke(Request $request, int $session)
    {
        $request->user()->tokens()->where('name', 'like', 'mobile:%')->findOrFail($session)->delete();

        return response()->noContent();
    }
}
