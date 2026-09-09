<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional. Resume safely after a partial failure.
        foreach (['payload', 'attempts', 'available_at', 'lease_until', 'dispatched_at', 'lease_token'] as $column) {
            if (! Schema::hasColumn('billing_webhook_events', $column)) {
                Schema::table('billing_webhook_events', function (Blueprint $table) use ($column) {
                    match ($column) {
                        'payload' => $table->longText($column)->nullable(),
                        'attempts' => $table->unsignedInteger($column)->default(0),
                        'lease_token' => $table->string($column, 36)->nullable(),
                        default => $table->timestamp($column)->nullable(),
                    };
                });
            }
        }
        if (! Schema::hasIndex('billing_webhook_events', ['available_at'])) {
            Schema::table('billing_webhook_events', fn (Blueprint $table) => $table->index('available_at'));
        }
        if (! Schema::hasTable('billing_payment_reviews')) {
            Schema::create('billing_payment_reviews', function (Blueprint $table) {
                $table->id();
                $table->string('stripe_object_id', 191)->unique();
                $table->string('kind', 20);
                $table->string('stripe_customer_id', 191)->nullable()->index();
                $table->string('charge_id')->nullable();
                $table->bigInteger('amount')->default(0);
                $table->string('currency', 3);
                $table->string('status');
                $table->string('incident_reference')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('billing_notification_intents')) {
            Schema::create('billing_notification_intents', function (Blueprint $table) {
                $table->id();
                $table->string('intent_key', 191)->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('payment_intent_id');
                $table->timestamp('sent_at')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('available_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('billing_checkout_confirmations')) {
            Schema::create('billing_checkout_confirmations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('stripe_session_id', 191)->unique();
                $table->string('stripe_subscription_id');
                $table->timestamps();
            });
        }
        // CREATE TABLE may have succeeded before a subsequent ADD INDEX failed.
        foreach (['billing_payment_reviews' => ['stripe_object_id', 'stripe_customer_id'], 'billing_notification_intents' => ['intent_key'], 'billing_checkout_confirmations' => ['stripe_session_id']] as $name => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasIndex($name, [$column], $column === 'stripe_customer_id' ? null : 'unique')) {
                    if (DB::table($name)->whereRaw('LENGTH('.$column.') > 191')->exists()) {
                        throw new RuntimeException('Cannot safely shorten '.$name.'.'.$column.': an existing identifier exceeds 191 bytes. No values have been truncated.');
                    }
                    Schema::table($name, function (Blueprint $table) use ($column) {
                        $definition = $table->string($column, 191);
                        if ($column === 'stripe_customer_id') {
                            $definition->nullable();
                        }
                        $definition->change();
                    });
                    Schema::table($name, function (Blueprint $table) use ($column) {
                        $column === 'stripe_customer_id' ? $table->index($column) : $table->unique($column);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_checkout_confirmations');
        Schema::dropIfExists('billing_notification_intents');
        Schema::dropIfExists('billing_payment_reviews');
        Schema::table('billing_webhook_events', fn (Blueprint $table) => $table->dropColumn(['payload', 'attempts', 'available_at', 'lease_until', 'lease_token', 'dispatched_at']));
    }
};
