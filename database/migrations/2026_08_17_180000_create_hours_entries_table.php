<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hours_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(30);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['user_id', 'work_date'], 'hours_entries_user_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hours_entries');
    }
};
