<?php

namespace App\Services;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerifyEmailCodeNotification;
use Illuminate\Support\Facades\Hash;

class EmailVerificationCodeService
{
    public function issue(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        EmailVerificationCode::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('site.verification.expires_minutes', 10)),
                'last_sent_at' => now(),
            ],
        );
        $user->notify(new VerifyEmailCodeNotification($code));

        return $code;
    }

    public function verify(User $user, string $code): bool
    {
        $record = EmailVerificationCode::query()->whereBelongsTo($user)->first();
        $maxAttempts = (int) config('site.verification.max_attempts', 5);

        if (! $record || $record->expires_at->isPast() || $record->attempts >= $maxAttempts) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return false;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $record->delete();

        return true;
    }

    public function canResend(User $user): bool
    {
        $record = EmailVerificationCode::query()->whereBelongsTo($user)->first();

        return ! $record || $record->last_sent_at->addSeconds((int) config('site.verification.resend_seconds', 60))->isPast();
    }
}
