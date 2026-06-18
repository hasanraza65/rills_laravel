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
        Schema::create('diaries', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('branch_id');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('cascade');

            $table->unsignedBigInteger('class_subject_id');

            $table->string('topic');
            $table->longText('description')->nullable();

            $table->date('date')->nullable();

            $table->string('status')->default('Pending');

            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();

            $table->timestamps();

            $table->foreign('class_subject_id')
                ->references('id')
                ->on('class_subjects')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diaries');
    }
};
