<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_group_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_group_id')->constrained('exam_groups')->cascadeOnDelete();
            $table->string('name'); // e.g. "Final Assessment Session"
            $table->boolean('publish_exam')->default(false);
            $table->boolean('publish_schedule')->default(false);
            $table->boolean('publish_result')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_group_exams');
    }
};
