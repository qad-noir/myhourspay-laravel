<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_webhook_events', function (Blueprint $table) {
            $table->longText('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('lease_until')->nullable();
            $table->string('lease_token', 36)->nullable();
        });
        Schema::create('billing_payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_object_id')->unique();
            $table->string('kind', 20);
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('charge_id')->nullable();
            $table->bigInteger('amount')->default(0);
            $table->string('currency', 3);
            $table->string('status');
            $table->string('incident_reference')->nullable();
            $table->timestamps();
        });
        Schema::create('billing_notification_intents', function (Blueprint $table) {
            $table->id();
            $table->string('intent_key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('payment_intent_id');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamps();
        });
        Schema::create('billing_checkout_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_session_id')->unique();
            $table->string('stripe_subscription_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_checkout_confirmations');
        Schema::dropIfExists('billing_notification_intents');
        Schema::dropIfExists('billing_payment_reviews');
        Schema::table('billing_webhook_events', fn (Blueprint $table) => $table->dropColumn(['payload', 'attempts', 'available_at', 'lease_until', 'lease_token']));
    }
};
