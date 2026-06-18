<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lesson_plan_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // teacher
            $table->foreignId('subject_id')->constrained('qb_subjects')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'subject_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_subjects');
    }
};