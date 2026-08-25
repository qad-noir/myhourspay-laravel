<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A failed first attempt can leave these migration-owned tables behind on
        // older MySQL installations when an index exceeds their byte limit.
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type', 100);
            $table->unsignedBigInteger('notifiable_id');
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_index');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('reference', 100);
            $table->timestamp('delivered_at');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id', 'type', 'reference'], 'notification_deliveries_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
    }
};
