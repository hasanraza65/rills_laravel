<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The duration matrix: for a given Periods template, how many minutes
        // does period N last on day D (different days can have different structures).
        Schema::create('timetable_period_set_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_set_id')->constrained('timetable_period_sets')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // ISO-8601: 1=Mon ... 7=Sun
            $table->unsignedTinyInteger('period_number');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();

            $table->unique(['period_set_id', 'day_of_week', 'period_number'], 'timetable_period_set_slots_unique_cell');
            $table->index(['period_set_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_period_set_slots');
    }
};
