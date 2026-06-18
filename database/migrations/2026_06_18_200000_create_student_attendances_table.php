<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('section_id');
            $table->date('date');
            $table->enum('status', ['P', 'A', 'L', 'H'])->default('P');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('marked_by');
            $table->timestamps();

            $table->unique(['student_id', 'date']);

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
            $table->foreign('marked_by')->references('id')->on('users')->onDelete('cascade');

            $table->index(['section_id', 'date']);
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
