<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'stripe_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('stripe_id', 190)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('users', 'pm_type')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('pm_type', 50)->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'pm_last_four')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('pm_last_four', 4)->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'trial_ends_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('trial_ends_at')->nullable();
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type', 50);
                $table->string('stripe_id', 190)->unique();
                $table->string('stripe_status', 50);
                $table->string('stripe_price', 190)->nullable();
                $table->integer('quantity')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'stripe_status']);
            });
        } else {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->string('type', 50)->change();
                $table->string('stripe_status', 50)->change();
            });

            if (! Schema::hasIndex('subscriptions', ['user_id', 'stripe_status'])) {
                Schema::table('subscriptions', function (Blueprint $table): void {
                    $table->index(['user_id', 'stripe_status']);
                });
            }
        }

        if (! Schema::hasTable('subscription_items')) {
            Schema::create('subscription_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
                $table->string('stripe_id', 190)->unique();
                $table->string('stripe_product', 190);
                $table->string('stripe_price', 190);
                $table->string('meter_id', 190)->nullable();
                $table->integer('quantity')->nullable();
                $table->string('meter_event_name', 190)->nullable();
                $table->timestamps();
                $table->index(['subscription_id', 'stripe_price'], 'subscription_items_subscription_price_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        $columns = array_values(array_filter(
            ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'],
            fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                if (in_array('stripe_id', $columns, true) && Schema::hasIndex('users', ['stripe_id'])) {
                    $table->dropIndex(['stripe_id']);
                }

                $table->dropColumn($columns);
            });
        }
    }
};
