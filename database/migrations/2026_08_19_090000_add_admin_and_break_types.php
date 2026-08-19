<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->index()->after('email_verified_at');
            $table->timestamp('suspended_at')->nullable()->index()->after('is_admin');
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('default_break_type', 10)->default('unpaid')->after('name');
        });

        Schema::table('hours_entries', function (Blueprint $table): void {
            $table->string('break_type', 10)->default('unpaid')->after('break_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('hours_entries', fn (Blueprint $table) => $table->dropColumn('break_type'));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('default_break_type'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_admin', 'suspended_at']));
    }
};
