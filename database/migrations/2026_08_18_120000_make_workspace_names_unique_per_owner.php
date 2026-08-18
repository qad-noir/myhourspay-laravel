<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->unique(['owner_id', 'name'], 'workspaces_owner_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropUnique('workspaces_owner_name_unique');
        });
    }
};
