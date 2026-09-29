<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_subject');
            $table->timestamps();
            $table->unique(['provider', 'provider_subject']);
            $table->unique(['user_id', 'provider']);
        });
        Schema::create('mobile_social_nonces', function (Blueprint $table): void {
            $table->string('nonce_hash', 64)->primary();
            $table->timestamp('expires_at')->index();
        });
        Schema::create('mobile_social_credentials', function (Blueprint $table): void {
            $table->string('token_hash', 64)->primary();
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_social_credentials');
        Schema::dropIfExists('mobile_social_nonces');
        Schema::dropIfExists('social_accounts');
    }
};
