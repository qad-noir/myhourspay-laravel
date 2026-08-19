<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('workspace_onboarding_reset_at')->nullable()->index()->after('suspended_at');
            $table->softDeletes();
        });
        Schema::table('workspaces', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('hours_entries', fn (Blueprint $table) => $table->softDeletes());

        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['admin_user_id']);
            $table->foreignId('admin_user_id')->nullable()->change();
            $table->foreign('admin_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('operational_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('event_type', 80)->index();
            $table->string('severity', 20)->default('error')->index();
            $table->string('submitted_name')->nullable();
            $table->string('submitted_email')->nullable()->index();
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
