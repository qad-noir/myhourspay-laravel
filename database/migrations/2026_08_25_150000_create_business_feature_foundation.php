<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 190);
            $table->string('role', 30)->default('member');
            $table->string('position', 100)->nullable();
            $table->char('token_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status', 'expires_at'], 'workspace_invitations_status_index');
            $table->unique(['workspace_id', 'email', 'status'], 'workspace_invitations_email_status_unique');
        });

        Schema::create('timesheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->string('status', 20)->default('draft');
            $table->text('submission_note')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id', 'week_start']);
            $table->index(['workspace_id', 'status', 'week_start']);
        });

        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('colour', 20)->default('#8268ff');
            $table->boolean('paid')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('minutes_per_day')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status', 'starts_on'], 'leave_requests_status_start_index');
            $table->index(['workspace_id', 'user_id', 'starts_on'], 'leave_requests_user_start_index');
        });

        Schema::create('payroll_export_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('format', 10)->default('csv');
            $table->json('columns');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('workspace_brandings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('logo_path', 500)->nullable();
            $table->string('primary_colour', 20)->default('#ff6b35');
            $table->string('accent_colour', 20)->default('#8268ff');
            $table->string('email_footer', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['workspace_id', 'action', 'occurred_at'], 'workspace_activity_action_index');
            $table->index(['target_type', 'target_id'], 'workspace_activity_target_index');
        });

        Schema::create('support_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_key', 50)->default('free');
            $table->string('priority', 20)->default('normal');
            $table->string('subject', 190);
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('outbound_webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 500);
            $table->text('secret');
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'active']);
        });

        Schema::create('outbound_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('outbound_webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('response_excerpt', 1000)->nullable();
            $table->timestamp('next_attempt_at')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->foreignId('timesheet_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->index(['timesheet_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('timesheet_id');
        });
        Schema::dropIfExists('outbound_webhook_deliveries');
        Schema::dropIfExists('outbound_webhook_endpoints');
        Schema::dropIfExists('support_requests');
        Schema::dropIfExists('workspace_activity_logs');
        Schema::dropIfExists('workspace_brandings');
        Schema::dropIfExists('payroll_export_profiles');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('workspace_invitations');
    }
};
