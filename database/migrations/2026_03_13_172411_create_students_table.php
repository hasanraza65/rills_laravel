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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->integer('added_by')->nullable();
            $table->integer('branch_id')->nullable();

            // Admission
            $table->string('admission_no')->unique()->nullable();
            $table->date('admission_date')->nullable();

            // Personal info
            $table->string('photo')->nullable();
            $table->string('name')->nullable();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();
            $table->text('address')->nullable();
            $table->string('home_contact')->nullable();

            // Class info
            $table->string('currently_studying')->nullable();
            $table->foreignId('class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();

            // Previous schools
            $table->json('previous_schools')->nullable();

            // Health
            $table->json('health_issues')->nullable();
            $table->text('health_details')->nullable();

            // Parent
            $table->foreignId('parent_id')->nullable()->constrained('parent_profiles')->nullOnDelete();

            // How did you know about us
            $table->string('source')->nullable();

            // Attachments
            $table->json('attachments')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
