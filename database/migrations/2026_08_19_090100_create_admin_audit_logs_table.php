<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL installations with a 1000-byte key limit cannot index Laravel's
        // default 255-character morph type when utf8mb4 is in use. A failed
        // CREATE TABLE may also leave the table behind before the index is added.
        if (Schema::hasTable('admin_audit_logs')) {
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->string('target_type', 100)->nullable()->change();
            });

            if (! Schema::hasIndex('admin_audit_logs', 'admin_audit_logs_target_type_target_id_index')) {
                Schema::table('admin_audit_logs', function (Blueprint $table): void {
                    $table->index(['target_type', 'target_id']);
                });
            }

            return;
        }

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 80)->index();
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->index(['target_type', 'target_id']);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
