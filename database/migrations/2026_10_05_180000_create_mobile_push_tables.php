<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->string('timezone', 64)->nullable());
        Schema::create('mobile_push_registration_locks', fn (Blueprint $table) => $table->unsignedInteger('id')->primary());
        DB::table('mobile_push_registration_locks')->insert(['id' => 1]);
        Schema::create('mobile_push_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->unique()->constrained('personal_access_tokens')->nullOnDelete();
            $table->text('token')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('platform', 7);
            $table->string('device_name');
            $table->boolean('enabled')->default(false)->index();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mobile_session_mutations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('personal_access_token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('request_hash', 64);
            $table->text('response');
            $table->unsignedSmallInteger('status');
            $table->timestamp('created_at');
            $table->unique(['personal_access_token_id', 'request_key'], 'mobile_session_mutation_key');
        });
        Schema::create('mobile_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('mobile_push_devices')->nullOnDelete();
            $table->date('work_date');
            $table->string('channel', 8)->default('push');
            $table->string('state', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->uuid('lease_id')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->string('reason', 32)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'workspace_id', 'work_date', 'device_id', 'channel'], 'mobile_push_delivery_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_push_deliveries');
        Schema::dropIfExists('mobile_session_mutations');
        Schema::dropIfExists('mobile_push_devices');
        Schema::dropIfExists('mobile_push_registration_locks');
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('timezone'));
    }
};
