<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->char('currency', 3)->default('GBP')->after('weekly_target_minutes');
            $table->unsignedInteger('overtime_multiplier_bps')->default(15000)->after('currency');
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('email', 190)->nullable();
            $table->text('address')->nullable();
            $table->char('currency', 3)->default('GBP');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'name']);
        });
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->unsignedInteger('hourly_rate_minor')->nullable();
            $table->char('currency', 3)->default('GBP');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'code']);
            $table->index(['workspace_id', 'active']);
        });
        Schema::create('compensation_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('hourly_rate_minor');
            $table->unsignedInteger('overtime_multiplier_bps')->default(15000);
            $table->char('currency', 3)->default('GBP');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id', 'effective_from'], 'compensation_rates_subject_date_unique');
            $table->index(['workspace_id', 'user_id', 'effective_to'], 'compensation_rates_subject_end_index');
        });
        Schema::create('expected_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(30);
            $table->string('break_type', 10)->default('unpaid');
            $table->boolean('active')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'user_id', 'active'], 'expected_schedules_subject_active_index');
        });
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->boolean('enabled')->default(true);
            $table->json('channels')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id', 'type'], 'notification_preferences_subject_type_unique');
        });
        Schema::create('calendar_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('external_account_id', 190);
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id', 'provider'], 'calendar_connections_subject_provider_unique');
        });
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 190);
            $table->string('summary', 190);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('suggested');
            $table->foreignId('hours_entry_id')->nullable()->constrained('hours_entries')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['calendar_connection_id', 'external_id'], 'calendar_events_connection_external_unique');
            $table->index(['workspace_id', 'user_id', 'status'], 'calendar_events_subject_status_index');
        });
        Schema::create('report_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->string('format', 20)->default('xlsx');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'user_id', 'name'], 'report_templates_subject_name_unique');
        });
        Schema::create('scheduled_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_template_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 20);
            $table->json('recipients');
            $table->timestamp('next_run_at')->index();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'next_run_at']);
        });
        Schema::create('client_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('number', 100);
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3)->default('GBP');
            $table->unsignedInteger('subtotal_minor')->default(0);
            $table->unsignedInteger('tax_minor')->default(0);
            $table->unsignedInteger('total_minor')->default(0);
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'status']);
        });
        Schema::create('client_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hours_entry_id')->nullable()->constrained('hours_entries')->restrictOnDelete();
            $table->string('description', 500);
            $table->unsignedInteger('quantity_minutes');
            $table->unsignedInteger('rate_minor');
            $table->unsignedInteger('amount_minor');
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->unique('hours_entry_id');
        });
        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
            $table->boolean('billable')->default(false)->after('project_id');
            $table->unsignedInteger('hourly_rate_minor')->nullable()->after('billable');
            $table->unsignedInteger('overtime_multiplier_bps')->nullable()->after('hourly_rate_minor');
            $table->char('currency', 3)->nullable()->after('overtime_multiplier_bps');
            $table->unsignedInteger('earnings_minor')->nullable()->after('currency');
            $table->index(['workspace_id', 'project_id', 'work_date'], 'hours_entries_workspace_project_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->dropIndex('hours_entries_workspace_project_date_index');
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['billable', 'hourly_rate_minor', 'overtime_multiplier_bps', 'currency', 'earnings_minor']);
        });
        Schema::dropIfExists('client_invoice_lines');
        Schema::dropIfExists('client_invoices');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('report_templates');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendar_connections');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('expected_schedules');
        Schema::dropIfExists('compensation_rates');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['currency', 'overtime_multiplier_bps']));
    }
};
