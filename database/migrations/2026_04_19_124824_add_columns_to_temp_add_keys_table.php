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
        Schema::table('temp_add_keys', function (Blueprint $table) {
            $table->string('visitor_name')->nullable();
            $table->text('address')->nullable();
            $table->string('purpose')->nullable();
            $table->text('remarks')->nullable();
            $table->json('students')->nullable(); // JSON array (name + class)
        });
    }

    public function down(): void
    {
        Schema::table('temp_add_keys', function (Blueprint $table) {
            $table->dropColumn([
                'visitor_name',
                'address',
                'purpose',
                'remarks',
                'students'
            ]);
        });
    }
};
