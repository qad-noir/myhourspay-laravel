<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('billing_trial_used_at')->nullable();
            $table->string('billing_trial_resolved_subscription', 190)->nullable();
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('current_period_ends_at')->nullable();
            $table->string('pending_plan_key')->nullable();
            $table->string('pending_interval')->nullable();
            $table->timestamp('pending_change_at')->nullable();
            $table->timestamp('stripe_synced_at')->nullable();
        });
        DB::table('subscriptions')->whereNotNull('trial_ends_at')->orderBy('id')->each(function ($subscription): void {
            DB::table('users')->where('id', $subscription->user_id)->whereNull('billing_trial_used_at')->update(['billing_trial_used_at' => $subscription->created_at]);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['current_period_ends_at', 'pending_plan_key', 'pending_interval', 'pending_change_at', 'stripe_synced_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['billing_trial_used_at', 'billing_trial_resolved_subscription']));
    }
};
