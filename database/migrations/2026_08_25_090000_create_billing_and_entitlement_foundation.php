<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('tier')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->boolean('purchasable')->default(false);
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('interval', 20);
            $table->string('kind', 20)->default('base');
            $table->char('currency', 3)->default('gbp');
            $table->unsignedInteger('amount');
            $table->string('stripe_price_id', 190)->nullable()->unique();
            $table->boolean('tax_inclusive')->default(true);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->unique(['plan_id', 'interval', 'kind', 'currency'], 'plan_prices_catalog_unique');
        });

        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('category', 50)->index();
            $table->string('mode', 20)->default('free')->index();
            $table->string('value_type', 20)->default('boolean');
            $table->boolean('locked')->default(false);
            $table->timestamps();
        });

        Schema::create('feature_plan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['feature_id', 'plan_id']);
        });

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('entitlement_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at', 'expires_at'], 'entitlement_grants_active_index');
        });

        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_event_id', 190)->unique();
            $table->string('type', 100)->index();
            $table->string('status', 20)->default('received')->index();
            $table->char('payload_hash', 64);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('entitlement_version')->default(1)->after('workspace_onboarding_reset_at');
            $table->timestamp('billing_grace_ends_at')->nullable()->index()->after('entitlement_version');
        });

        $now = now();
        foreach (config('billing.plans') as $key => $plan) {
            $planId = DB::table('plans')->insertGetId([
                'key' => $key,
                'name' => $plan['name'],
                'description' => $plan['description'],
                'tier' => $plan['tier'],
                'active' => true,
                'purchasable' => $plan['purchasable'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($plan['prices'] as $priceKey => $price) {
                $kind = str_starts_with($priceKey, 'seat_') ? 'seat' : 'base';
                $interval = str_ends_with($priceKey, 'yearly') || $priceKey === 'yearly' ? 'yearly' : 'monthly';
                DB::table('plan_prices')->insert([
                    'plan_id' => $planId,
                    'interval' => $interval,
                    'kind' => $kind,
                    'currency' => config('billing.currency'),
                    'amount' => $price['amount'],
                    'stripe_price_id' => $price['stripe_price_id'],
                    'tax_inclusive' => true,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $planIds = DB::table('plans')->pluck('id', 'key');
        foreach (config('billing.features') as $key => $feature) {
            $featureId = DB::table('features')->insertGetId([
                'key' => $key,
                'name' => $feature['name'],
                'description' => $feature['description'] ?? null,
                'category' => $feature['category'],
                'mode' => $feature['mode'],
                'value_type' => $feature['value_type'] ?? 'boolean',
                'locked' => $feature['locked'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($feature['plans'] as $planKey => $value) {
                DB::table('feature_plan')->insert([
                    'feature_id' => $featureId,
                    'plan_id' => $planIds[$planKey],
                    'value' => json_encode($value),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (config('billing.switches') as $key => $value) {
            DB::table('platform_settings')->insert([
                'key' => 'billing.'.$key,
                'value' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['entitlement_version', 'billing_grace_ends_at']));
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('entitlement_grants');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('feature_plan');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
