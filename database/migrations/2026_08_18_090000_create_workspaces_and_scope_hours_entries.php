<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('default_break_minutes')->default(30);
            $table->unsignedSmallInteger('weekly_target_minutes')->default(2400);
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('owner');
            $table->string('position', 100);
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('current_workspace_id')->nullable()->after('current_team_id')->constrained('workspaces')->nullOnDelete();
        });

        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'work_date']);
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id', 'work_date'], 'hours_entries_workspace_user_date_unique');
            $table->index(['workspace_id', 'user_id', 'work_date'], 'hours_entries_workspace_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->dropUnique('hours_entries_workspace_user_date_unique');
            $table->dropIndex('hours_entries_workspace_period_index');
            $table->dropConstrainedForeignId('workspace_id');
            $table->unique(['user_id', 'work_date']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_workspace_id'));
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
