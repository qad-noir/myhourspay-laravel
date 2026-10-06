<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->unsignedSmallInteger('contracted_daily_minutes')->nullable();
            $table->string('overtime_basis', 6)->default('weekly');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['contracted_daily_minutes', 'overtime_basis']));
    }
};
