<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('exam_type');
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('class_subjects')->cascadeOnDelete();
            $table->date('date');
            $table->string('start_time'); // HH:mm
            $table->string('duration')->nullable(); // free text, e.g. "1h 30m"
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_marks');
            $table->unsignedInteger('min_marks')->default(0);
            $table->timestamps();

            // One schedule per subject, per exam type, per class section — matches how the
            // Exam Schedule bulk row-editor searches/upserts (class + section + exam type + subject).
            $table->unique(['class_id', 'section_id', 'subject_id', 'exam_type'], 'exam_schedules_unique_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};
