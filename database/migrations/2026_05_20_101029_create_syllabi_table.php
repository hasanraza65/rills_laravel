<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    
    public function up(): void
    {
        Schema::create('syllabuses', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('subject_id');

            $table->unsignedBigInteger('branch_id');

            $table->date('month'); // storing month as date (YYYY-MM-01)

            $table->longText('content');

            $table->string('status')->default('Pending');

            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();

            $table->timestamps();

            // Foreign Key
            $table->foreign('subject_id')
                ->references('id')
                ->on('class_subjects')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabuses');
    }
};