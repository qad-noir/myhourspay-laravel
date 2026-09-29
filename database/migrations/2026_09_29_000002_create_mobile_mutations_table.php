<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_mutations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('status');
            $table->json('response');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'workspace_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_mutations');
    }
};
