<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: which class_subjects belong to a given exam_subject_group.
        Schema::create('exam_subject_group_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_subject_group_id')->constrained('exam_subject_groups')->cascadeOnDelete();
            $table->foreignId('class_subject_id')->constrained('class_subjects')->cascadeOnDelete();
            $table->unique(['exam_subject_group_id', 'class_subject_id'], 'exam_subject_group_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subject_group_subject');
    }
};
