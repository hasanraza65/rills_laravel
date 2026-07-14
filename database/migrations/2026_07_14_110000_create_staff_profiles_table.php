<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('added_by')->nullable();

            $table->string('father_husband_name')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('dob')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();

            $table->string('whatsapp_no')->nullable();
            $table->string('emergency_contact_no')->nullable();

            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
