<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_prices', function (Blueprint $table): void {
            $table->dropUnique('plan_prices_catalog_unique');
            $table->index(
                ['plan_id', 'interval', 'kind', 'currency'],
                'plan_prices_catalog_idx',
            );
        });
    }

    public function down(): void
    {
        // The original schema could retain only one row for each catalogue slot.
        // Keep the active version when rolling back, then restore that constraint.
        DB::table('plan_prices')->where('active', false)->delete();

        Schema::table('plan_prices', function (Blueprint $table): void {
            $table->dropIndex('plan_prices_catalog_idx');
            $table->unique(
                ['plan_id', 'interval', 'kind', 'currency'],
                'plan_prices_catalog_unique',
            );
        });
    }
};
