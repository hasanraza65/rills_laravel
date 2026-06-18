<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('class_subjects', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('branch_id');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('cascade');

            $table->unsignedBigInteger('class_id');

            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('teacher_id');

            $table->string('subject_name');

            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();

            $table->timestamps();

            // IMPORTANT: match your REAL tables

            $table->foreign('class_id')
                ->references('id')
                ->on('school_classes')
                ->onDelete('cascade');

            $table->foreign('section_id')
                ->references('id')
                ->on('sections')   // or your real table name
                ->onDelete('cascade');

            $table->foreign('teacher_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
    }
};
