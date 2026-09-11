<?php

namespace Tests\Feature;

use App\Jobs\SendMarketingEmail;
use App\Mail\ProductTipsMail;
use App\Models\MarketingCampaign;
use App\Models\MarketingDelivery;
use App\Models\MarketingEnrollment;
use App\Models\MarketingPreference;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BillingSettings;
use App\Services\MarketingCatalogue;
use App\Services\MarketingConsent;
use App\Services\MarketingJourneys;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class MarketingJourneysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 10)->setTime(10, 0));
        config(['marketing.enabled' => true, 'marketing.mailer' => 'array', 'marketing.timezone' => 'Europe/London']);
        app(MarketingCatalogue::class)->install();
    }

    private function owner(bool $consent = true): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = Workspace::forceCreate(['name' => 'Example workspace', 'owner_id' => $user->id, 'default_break_type' => 'unpaid', 'default_break_minutes' => 30, 'weekly_target_minutes' => 2400]);
        $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => 'Owner']);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        if ($consent) {
            app(MarketingConsent::class)->set($user, true, 'account');
        }

        return $user;
    }

    private function due(User $user): MarketingDelivery
    {
        MarketingCampaign::where('key', 'intro-hours')->update(['status' => 'active']);
        $this->travel(1)->days();
        app(MarketingJourneys::class)->plan($user);

        return MarketingDelivery::where('user_id', $user->id)->firstOrFail();
    }

    public function test_catalogue_is_paused_idempotent_and_preserves_edits(): void
    {
        $this->assertSame([1, 4, 8, 13, 20, 30], MarketingCampaign::orderBy('day')->pluck('day')->all());
        $this->assertSame(0, MarketingCampaign::where('status', 'active')->count());
        MarketingCampaign::first()->update(['subject' => 'Edited']);
        app(MarketingCatalogue::class)->install();
        $this->assertSame(6, MarketingCampaign::count());
        $this->assertSame('Edited', MarketingCampaign::first()->subject);
    }

    public function test_explicit_consent_only_and_no_automatic_existing_enrollment(): void
    {
        $user = $this->owner(false);
        app(MarketingJourneys::class)->plan($user);
        $this->assertDatabaseCount('marketing_enrollments', 0);
        $this->actingAs($user)->put(route('marketing.preferences.update'), ['consented' => '1'])->assertRedirect();
        $this->assertDatabaseCount('marketing_enrollments', 1);
        $this->assertDatabaseHas('marketing_consent_events', ['user_id' => $user->id, 'source' => 'account', 'consented' => true]);
    }

    public function test_unverified_or_suspended_owners_cannot_enroll(): void
    {
        $user = $this->owner(false);
        $user->forceFill(['email_verified_at' => null])->save();
        app(MarketingConsent::class)->set($user, true, 'account');
        $this->assertNull(app(MarketingJourneys::class)->enroll($user));
        $user->forceFill(['email_verified_at' => now(), 'suspended_at' => now()])->save();
        $this->assertNull(app(MarketingJourneys::class)->enroll($user));
    }

    public function test_unsubscribe_get_does_not_mutate_and_post_cancels_queued_work(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        $preference = MarketingPreference::first();
        $url = route('marketing.unsubscribe', $preference->token);
        $this->get($url)->assertOk();
        $this->assertTrue($preference->fresh()->consented);
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertFalse($preference->fresh()->consented);
        $this->assertSame('suppressed', $delivery->fresh()->status);
        $this->post($url)->assertOk();
        $this->get(route('marketing.unsubscribe', str_repeat('x', 64)))->assertNotFound();
    }

    public function test_email_change_revokes_consent_without_replaying_journey(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        $started = MarketingEnrollment::first()->started_at->toIso8601String();
        $user->update(['email' => 'changed@example.test']);
        $this->assertSame('changed@example.test', $user->fresh()->email);
        $this->assertFalse(MarketingPreference::first()->consented);
        app(MarketingConsent::class)->set($user, true, 'account');
        app(MarketingJourneys::class)->plan($user);
        $this->assertDatabaseCount('marketing_deliveries', 1);
        $this->assertSame($started, MarketingEnrollment::first()->started_at->toIso8601String());
        $this->assertSame('suppressed', $delivery->fresh()->status);
    }

    public function test_duplicate_planning_and_duplicate_jobs_submit_once(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        app(MarketingJourneys::class)->plan($user);
        $job = new SendMarketingEmail($delivery->id);
        $job->handle(app(MarketingJourneys::class));
        $job->handle(app(MarketingJourneys::class));
        $this->assertDatabaseCount('marketing_deliveries', 1);
        $this->assertSame('submitted', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_pause_unsubscribe_and_ownership_are_rechecked_by_worker(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        $delivery->campaign->update(['status' => 'paused']);
        (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
        $this->assertSame('pending', $delivery->fresh()->status);
        $delivery->campaign->update(['status' => 'active']);
        Workspace::whereKey($delivery->workspace_id)->update(['owner_id' => User::factory()->create()->id]);
        (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
        $this->assertSame('suppressed', $delivery->fresh()->status);
    }

    public function test_expired_steps_are_skipped_and_no_catchup_burst_is_sent(): void
    {
        $user = $this->owner();
        MarketingCampaign::where('key', 'intro-hours')->update(['status' => 'active']);
        $this->travel(10)->days();
        app(MarketingJourneys::class)->plan($user);
        $this->assertSame('skipped', MarketingDelivery::first()->status);
        $this->assertSame('Opportunity expired', MarketingDelivery::first()->reason);
    }

    public function test_frequency_caps_include_uncertain_and_reserved_deliveries(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        $delivery->update(['status' => 'uncertain']);
        $this->assertTrue(app(MarketingJourneys::class)->limited($user));
        $this->travel(73)->hours();
        $this->assertFalse(app(MarketingJourneys::class)->limited($user));
        $campaign = MarketingCampaign::where('key', 'intro-projects')->first();
        MarketingDelivery::create(['user_id' => $user->id, 'campaign_id' => $campaign->id, 'status' => 'submitted', 'snapshot' => [], 'available_at' => now()]);
        $this->travel(73)->hours();
        $this->assertTrue(app(MarketingJourneys::class)->limited($user));
    }

    public function test_monthly_updates_must_be_fresh_and_after_introduction(): void
    {
        $user = $this->owner();
        $template = MarketingCampaign::first()->only(['subject', 'heading', 'preheader', 'body']);
        $old = MarketingCampaign::create($template + ['key' => 'old-monthly', 'kind' => 'monthly', 'audience' => 'hours', 'status' => 'active', 'published_at' => now(), 'day' => 30]);
        $this->travel(31)->days();
        $fresh = MarketingCampaign::create($template + ['key' => 'fresh-monthly', 'kind' => 'monthly', 'audience' => 'hours', 'status' => 'active', 'published_at' => now()->subHours(2), 'day' => 30]);
        app(MarketingJourneys::class)->plan($user);
        $this->assertDatabaseMissing('marketing_deliveries', ['campaign_id' => $old->id]);
        $this->assertDatabaseHas('marketing_deliveries', ['campaign_id' => $fresh->id, 'status' => 'pending']);
        MarketingDelivery::first()->update(['status' => 'submitted']);
        $this->assertTrue(app(MarketingJourneys::class)->monthlyLimited($user));
    }

    public function test_day_schedule_preserves_ten_am_across_daylight_saving(): void
    {
        $user = $this->owner();
        $enrollment = MarketingEnrollment::first();
        $enrollment->update(['started_at' => '2026-10-24 12:00:00']);
        $due = app(MarketingJourneys::class)->dueAt($enrollment, MarketingCampaign::where('day', 1)->first());
        $this->assertSame('2026-10-25 10:00:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_admin_routes_require_admin_and_templates_escape_content(): void
    {
        $user = $this->owner();
        $campaign = MarketingCampaign::first();
        $this->actingAs($user)->get(route('admin.marketing.index'))->assertForbidden();
        $user->update(['is_admin' => true]);
        $this->get(route('admin.marketing.index'))->assertOk();
        $this->get(route('admin.marketing.edit', $campaign))->assertOk();
        $campaign->update(['body' => '<script>alert(1)</script>']);
        $this->get(route('admin.marketing.preview', $campaign))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_admin_test_email_only_targets_current_admin(): void
    {
        Mail::fake();
        $admin = $this->owner();
        $admin->update(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.marketing.test', MarketingCampaign::first()), ['email' => 'someone@example.test'])->assertRedirect();
        Mail::assertSent(ProductTipsMail::class, fn ($mail) => $mail->hasTo($admin->email) && ! $mail->hasTo('someone@example.test'));
    }

    public function test_suppression_cannot_be_cleared_by_user_opt_in(): void
    {
        $user = $this->owner();
        MarketingPreference::first()->update(['suppression_reason' => 'Complaint']);
        app(MarketingConsent::class)->set($user, true, 'account');
        $this->assertSame('Administrator suppression', app(MarketingJourneys::class)->reason($user, $user->currentWorkspace));
    }

    public function test_expired_sending_lease_becomes_uncertain_and_incident_is_recorded(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        $delivery->update(['status' => 'sending', 'lease_until' => now()->subMinute()]);
        $this->artisan('marketing:process-inbox --plan-only')->assertSuccessful();
        $this->assertSame('uncertain', $delivery->fresh()->status);
        $this->assertDatabaseHas('operational_incidents', ['event_type' => 'marketing.delivery.'.$delivery->id]);
        $this->artisan('marketing:process-inbox --plan-only')->assertSuccessful();
        $this->assertSame(1, DB::table('operational_incidents')->where('event_type', 'marketing.delivery.'.$delivery->id)->count());
    }

    public function test_pre_send_crash_recovers_but_exhausted_events_are_not_retried(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        $delivery->update(['status' => 'leased', 'attempts' => 5, 'lease_until' => now()->subMinute()]);
        $this->artisan('marketing:process-inbox --plan-only')->assertSuccessful();
        (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->submitted_at);
    }

    public function test_global_switch_prevents_submission_and_preferences_remain_available(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        config(['marketing.enabled' => false]);
        (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
        $this->assertSame('pending', $delivery->fresh()->status);
        $this->actingAs($user)->get(route('marketing.preferences'))->assertOk();
    }

    public function test_unsubscribe_does_not_change_operational_notification_preferences(): void
    {
        $user = $this->owner();
        $before = DB::table('notification_preferences')->get()->toJson();
        app(MarketingConsent::class)->set($user, false, 'unsubscribe');
        $this->assertSame($before, DB::table('notification_preferences')->get()->toJson());
    }

    public function test_upgrade_suppression_uses_local_billing_state_without_stripe_calls(): void
    {
        $user = $this->owner();
        app(BillingSettings::class)->set('checkout_enabled', true);
        $journeys = app(MarketingJourneys::class);
        $this->assertTrue($journeys->upgradeAllowed($user->fresh()));
        foreach (['active', 'trialing', 'past_due', 'incomplete', 'unpaid', 'paused'] as $status) {
            $subscription = $user->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_'.$status, 'stripe_status' => $status, 'trial_ends_at' => now()->addDays(2)]);
            $this->assertFalse($journeys->upgradeAllowed($user->fresh()), $status);
            $subscription->delete();
        }
        $grant = $user->entitlementGrants()->create(['plan_id' => Plan::where('key', 'pro')->first()->id, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(), 'reason' => 'Test grant']);
        $this->assertFalse($journeys->upgradeAllowed($user->fresh()));
        $grant->delete();
        DB::table('billing_checkout_confirmations')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'stripe_session_id' => 'cs_waiting', 'stripe_subscription_id' => 'sub_waiting', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertFalse($journeys->upgradeAllowed($user->fresh()));
    }

    public function test_completed_hours_step_is_skipped_and_routes_are_workspace_bound(): void
    {
        $user = $this->owner();
        $workspace = $user->currentWorkspace;
        $workspace->hoursEntries()->create(['user_id' => $user->id, 'work_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid', 'net_minutes' => 450]);
        $delivery = $this->due($user);
        $this->assertSame('skipped', $delivery->status);
        $this->actingAs($user)->get(route('marketing.open', ['workspace' => $workspace->id, 'destination' => 'hours.index']))->assertRedirect(route('hours.index'));
        $this->get(route('marketing.open', ['workspace' => $workspace->id, 'destination' => 'https://example.com']))->assertNotFound();
        $this->actingAs($this->owner())->get(route('marketing.open', ['workspace' => $workspace->id, 'destination' => 'hours.index']))->assertForbidden();
    }

    public function test_snapshot_and_published_monthly_content_are_immutable(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        $subject = $delivery->snapshot['subject'];
        $delivery->campaign->update(['subject' => 'Changed later']);
        $this->assertSame($subject, $delivery->fresh()->snapshot['subject']);
        $user->update(['is_admin' => true]);
        $campaign = MarketingCampaign::create(['key' => 'published-monthly', 'audience' => 'hours', 'kind' => 'monthly', 'status' => 'paused', 'day' => 30, 'published_at' => now(), 'subject' => 'Published', 'preheader' => 'Published', 'heading' => 'Published', 'body' => 'Published']);
        $this->actingAs($user)->put(route('admin.marketing.update', $campaign), ['subject' => 'Edit'])->assertStatus(409);
    }

    public function test_queue_dispatch_failure_retains_intent_and_recovers_on_next_run(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->artisan('marketing:process-inbox')->assertFailed();
        $this->assertSame('pending', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->dispatched_at);
        $this->assertDatabaseHas('operational_incidents', ['event_type' => 'marketing.processor']);
    }

    public function test_transaction_failure_does_not_leave_partial_intent(): void
    {
        $user = $this->owner();
        MarketingCampaign::where('key', 'intro-hours')->update(['status' => 'active']);
        $this->travel(1)->days();
        DB::beginTransaction();
        app(MarketingJourneys::class)->plan($user);
        $this->assertSame(1, MarketingDelivery::count());
        DB::rollBack();
        $this->assertSame(0, MarketingDelivery::count());
        app(MarketingJourneys::class)->plan($user);
        $this->assertSame(1, MarketingDelivery::count());
    }

    public function test_mail_headers_and_plain_text_include_unsubscribe_without_tracking(): void
    {
        $user = $this->owner();
        $delivery = $this->due($user);
        (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString('List-Unsubscribe=One-Click', $message->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
        $this->assertStringContainsString('/marketing/unsubscribe/', $message->getTextBody());
        $this->assertStringNotContainsString('<img', $message->getHtmlBody());
    }

    public function test_temporary_rejections_back_off_and_exhaust_without_duplicate_incidents(): void
    {
        $this->fakeFailingTransport(450);
        $user = $this->owner();
        $delivery = $this->due($user);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
            $delivery->refresh();
            $this->assertSame('failed', $delivery->status);
            $this->assertSame($attempt, $delivery->attempts);
            $this->travelTo($delivery->available_at);
        }
        (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
        $this->assertSame(5, $delivery->fresh()->attempts);
        $this->assertSame(1, DB::table('operational_incidents')->where('event_type', 'marketing.delivery.'.$delivery->id)->count());
    }

    public function test_uncertain_transport_failure_is_not_automatically_retried(): void
    {
        $this->fakeFailingTransport(0);
        $user = $this->owner();
        $delivery = $this->due($user);
        (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
        $this->assertSame('uncertain', $delivery->fresh()->status);
        $this->travel(1)->days();
        (new SendMarketingEmail($delivery->id))->handle(app(MarketingJourneys::class));
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    private function fakeFailingTransport(int $code): void
    {
        Mail::extend('marketing-test', fn () => new class($code) extends AbstractTransport
        {
            public function __construct(private int $failureCode)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Fixture failure', $this->failureCode);
            }

            public function __toString(): string
            {
                return 'marketing-test';
            }
        });
        config(['marketing.mailer' => 'marketing-test', 'mail.mailers.marketing-test' => ['transport' => 'marketing-test']]);
    }

    public function test_marketing_dates_round_trip_as_utc_with_a_non_utc_application_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-01T10:00:00+01:00'));
        $user = $this->owner();
        $this->assertSame('2026-07-01 09:00:00', DB::table('marketing_enrollments')->where('user_id', $user->id)->value('started_at'));
        $enrollment = MarketingEnrollment::first();
        $this->assertSame(9, $enrollment->started_at->hour);
        $due = app(MarketingJourneys::class)->dueAt($enrollment, MarketingCampaign::where('day', 1)->first());
        $this->assertSame('2026-07-02 09:00:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_partial_deployment_does_not_break_dashboard_or_email_changes(): void
    {
        $user = $this->owner(false);
        Schema::drop('marketing_preferences');
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Choose email preference');
        $user->update(['email' => 'safe-change@example.test']);
        $this->assertSame('safe-change@example.test', $user->fresh()->email);
    }
}
