<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Section, per day, per period within a generated Timetable.
        // class_subject_id / teacher_id / timetable_activity_id are nullable (blank until
        // an admin picks a subject+teacher or activity for that cell) and use nullOnDelete
        // rather than cascade, so deleting a subject/activity clears the assignment instead
        // of destroying the timetable's period structure.
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained('timetables')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // ISO-8601: 1=Mon ... 7=Sun
            $table->unsignedTinyInteger('period_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('class_subject_id')->nullable()->constrained('class_subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('timetable_activity_id')->nullable()->constrained('timetable_activities')->nullOnDelete();
            $table->timestamps();

            $table->unique(['timetable_id', 'section_id', 'day_of_week', 'period_number'], 'timetable_slots_unique_cell');
            $table->index(['teacher_id', 'day_of_week']);
            $table->index(['timetable_id', 'section_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
