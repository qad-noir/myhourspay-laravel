<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\EmailVerificationCodeService;
use App\Services\OperationalIncidentRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
use Throwable;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        try {
            $user = DB::transaction(function () use ($input): User {
                $user = User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => Hash::make($input['password']),
                ]);
                app(EmailVerificationCodeService::class)->issue($user);

                return $user;
            });
        } catch (Throwable $exception) {
            $reference = (string) Str::uuid();

            Log::error('Registration verification email could not be sent.', [
                'reference' => $reference,
                'name' => $input['name'] ?? null,
                'email' => $input['email'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 500),
                'exception' => $exception,
            ]);
            app(OperationalIncidentRecorder::class)->record('registration.verification_email_failed', $exception, [
                'reference' => $reference,
                'name' => $input['name'] ?? null,
                'email' => $input['email'] ?? null,
            ]);

            throw ValidationException::withMessages([
                'registration' => "We’re unable to register your account right now. Please try again later or contact support and quote reference {$reference}.",
            ]);
        }

        return $user;
    }
}
