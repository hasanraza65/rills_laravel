<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which subjects each individual student sits for in exams — not every student
        // in a section takes the same subjects (e.g. Bio vs Computer electives), so this
        // is tracked per student rather than inherited from the section.
        Schema::create('exam_student_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_subject_id')->constrained('class_subjects')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'class_subject_id'], 'exam_student_subjects_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_student_subjects');
    }
};
