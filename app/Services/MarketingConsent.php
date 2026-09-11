<?php

namespace App\Services;

use App\Models\MarketingDelivery;
use App\Models\MarketingPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingConsent
{
    public function set(User $user, bool $consented, string $source): MarketingPreference
    {
        return DB::transaction(function () use ($user, $consented, $source) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $preference = MarketingPreference::firstOrNew(['user_id' => $user->id]);
            $wordingVersion = config($source === 'signup' ? 'marketing.signup_consent_version' : 'marketing.consent_version');
            $wording = config($source === 'signup' ? 'marketing.signup_consent_text' : 'marketing.consent_text');
            // A user cannot clear an administrator's complaint/bounce suppression by opting in.
            $preference->fill(['email' => $user->email, 'consented' => $consented,
                'consented_at' => $consented ? now('UTC') : null, 'source' => $source,
                'wording_version' => $wordingVersion,
                'token' => $preference->token ?? Str::random(64)])->save();
            DB::table('marketing_consent_events')->insert(['user_id' => $user->id,
                'email' => $user->email, 'consented' => $consented, 'source' => $source,
                'wording_version' => $wordingVersion,
                'wording' => $wording, 'created_at' => now('UTC')]);
            if (! $consented) {
                MarketingDelivery::where('user_id', $user->id)->whereIn('status', ['pending', 'failed', 'leased'])
                    ->update(['status' => 'suppressed', 'reason' => 'Consent withdrawn', 'lease_until' => null]);
            } else {
                app(MarketingJourneys::class)->enroll($user);
            }

            return $preference;
        });
    }
}
