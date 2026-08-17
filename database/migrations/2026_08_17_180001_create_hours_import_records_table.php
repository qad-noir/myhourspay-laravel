<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hours_import_records', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 100);
            $table->string('legacy_id', 100);
            $table->foreignId('hours_entry_id')->constrained()->cascadeOnDelete();
            $table->timestamp('imported_at');

            $table->unique(['source', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hours_import_records');
    }
};
