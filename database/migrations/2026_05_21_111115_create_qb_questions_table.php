<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qb_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('qb_subjects')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('qb_topics')->nullOnDelete();
            // Question types: MCQ | BLANKS | TRUE_FALSE | SHORT | LONG | PIC | MATCH_COLUMN
            $table->string('type');
            $table->text('question');
            $table->text('before_blank')->nullable();  // for BLANKS/PIC type
            $table->text('after_blank')->nullable();   // for BLANKS/PIC type
            $table->text('ans')->nullable();           // answer
            // MCQ options
            $table->string('opt1')->nullable();
            $table->string('opt2')->nullable();
            $table->string('opt3')->nullable();
            $table->string('opt4')->nullable();
            // MATCH_COLUMN
            $table->json('column_a')->nullable();
            $table->json('column_b')->nullable();
            // PIC
            $table->string('pic1')->nullable();
            $table->integer('marks')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qb_questions');
    }
};