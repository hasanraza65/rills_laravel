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
        Schema::create('parent_profiles', function (Blueprint $table) {
            $table->id();

            $table->integer('added_by')->nullable();
            $table->integer('branch_id')->nullable();

            $table->string('type')->nullable(); // father / mother

            $table->string('name')->nullable();
            $table->string('cnic')->nullable();
            $table->string('education')->nullable();
            $table->string('occupation')->nullable();
            $table->string('contact_no')->nullable();
            $table->text('address')->nullable();

            $table->boolean('is_guardian')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_profiles');
    }
};
