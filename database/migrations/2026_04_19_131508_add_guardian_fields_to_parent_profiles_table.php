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
        Schema::table('parent_profiles', function (Blueprint $table) {
            $table->string('guardian_name')->nullable();
            $table->string('guardian_cnic')->nullable();
            $table->string('guardian_education')->nullable();
            $table->string('guardian_occupation')->nullable();
            $table->string('guardian_contact_no')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parent_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'guardian_name',
                'guardian_cnic',
                'guardian_education',
                'guardian_occupation',
                'guardian_contact_no'
            ]);
        });
    }
};
