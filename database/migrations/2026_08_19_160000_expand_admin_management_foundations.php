<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $foundationAlreadyStarted = Schema::hasColumn('users', 'workspace_onboarding_reset_at');

        if (! Schema::hasColumn('users', 'workspace_onboarding_reset_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('workspace_onboarding_reset_at')->nullable()->index()->after('suspended_at'));
        }
        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->softDeletes());
        }
        if (! Schema::hasColumn('workspaces', 'deleted_at')) {
            Schema::table('workspaces', fn (Blueprint $table) => $table->softDeletes());
        }
        if (! Schema::hasColumn('hours_entries', 'deleted_at')) {
            Schema::table('hours_entries', fn (Blueprint $table) => $table->softDeletes());
        }

        // A retry after the incident-table index failure has already completed
        // this foreign-key conversion, so it must not be applied twice.
        if (! $foundationAlreadyStarted) {
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->dropForeign(['admin_user_id']);
                $table->foreignId('admin_user_id')->nullable()->change();
                $table->foreign('admin_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('operational_incidents')) {
            Schema::create('operational_incidents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('reference')->unique();
                $table->string('event_type', 80)->index();
                $table->string('severity', 20)->default('error')->index();
                $table->string('submitted_name')->nullable();
                // 190 utf8mb4 characters use at most 760 bytes, remaining below
                // the 1000-byte index limit on older MySQL installations.
                $table->string('submitted_email', 190)->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('exception_class', 190)->nullable();
                $table->text('exception_message')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamp('resolved_at')->nullable()->index();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
            });

            return;
        }

        // MySQL may leave CREATE TABLE columns behind when a separately emitted
        // index statement fails. Repair that partial table on the next migrate.
        Schema::table('operational_incidents', function (Blueprint $table): void {
            $table->string('submitted_email', 190)->nullable()->change();
        });
        if (! Schema::hasIndex('operational_incidents', 'operational_incidents_submitted_email_index')) {
            Schema::table('operational_incidents', fn (Blueprint $table) => $table->index('submitted_email'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_incidents');
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['admin_user_id']);
            $table->foreignId('admin_user_id')->nullable(false)->change();
            $table->foreign('admin_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('hours_entries', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['workspace_onboarding_reset_at', 'deleted_at']));
    }
};
