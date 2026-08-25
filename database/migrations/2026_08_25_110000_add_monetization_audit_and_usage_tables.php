<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admin_audit_logs', 'reason')) {
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->string('reason', 500)->nullable()->after('action');
            });
        }

        if (! Schema::hasTable('feature_usage_daily')) {
            Schema::create('feature_usage_daily', function (Blueprint $table): void {
                $table->id();
                $table->date('usage_date');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();
                $table->unique(['usage_date', 'user_id', 'workspace_id', 'feature_id'], 'feature_usage_daily_subject_unique');
                $table->index(['feature_id', 'usage_date'], 'feature_usage_daily_feature_date_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_usage_daily');
        if (Schema::hasColumn('admin_audit_logs', 'reason')) {
            Schema::table('admin_audit_logs', fn (Blueprint $table) => $table->dropColumn('reason'));
        }
    }
};
