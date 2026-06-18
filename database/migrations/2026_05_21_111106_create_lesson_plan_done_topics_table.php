<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lesson_plan_done_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('qb_topics')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('qb_subjects')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('completed_date')->nullable();
            $table->timestamps();

            $table->unique(['topic_id', 'teacher_id', 'subject_id'], 'done_topics_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_done_topics');
    }
};