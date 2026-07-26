<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One generated Time Table instance (a Group + Periods template applied over a date range).
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('timetable_group_id')->constrained('timetable_groups')->cascadeOnDelete();
            $table->foreignId('period_set_id')->constrained('timetable_period_sets')->cascadeOnDelete();
            $table->string('title');
            $table->date('date_from');
            $table->date('date_to');
            $table->time('school_time_from');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
            $table->index('timetable_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
