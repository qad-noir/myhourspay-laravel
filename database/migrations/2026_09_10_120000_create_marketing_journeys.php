<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_preferences', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->boolean('consented')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->string('source', 40);
            $table->string('wording_version', 40);
            $table->string('token', 64)->unique();
            $table->string('suppression_reason', 191)->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('marketing_consent_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->boolean('consented');
            $table->string('source', 40);
            $table->string('wording_version', 40);
            $table->text('wording');
            $table->timestamp('created_at');
        });
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('kind', 20)->default('intro');
            $table->string('audience', 40);
            $table->unsignedSmallInteger('day')->default(1);
            $table->string('status', 20)->default('paused');
            $table->unsignedInteger('version')->default(1);
            $table->string('subject', 150);
            $table->string('preheader', 200);
            $table->string('heading', 150);
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('marketing_enrollments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamps();
        });
        Schema::create('marketing_deliveries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns');
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['user_id', 'campaign_id'], 'marketing_intent_unique');
            $table->string('status', 24)->default('pending')->index();
            $table->string('reason', 191)->nullable();
            $table->json('snapshot');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['marketing_deliveries', 'marketing_enrollments', 'marketing_campaigns', 'marketing_consent_events', 'marketing_preferences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
