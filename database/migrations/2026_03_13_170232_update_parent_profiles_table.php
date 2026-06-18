<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('parent_profiles', function (Blueprint $table) {

            // remove old columns
            $table->dropColumn([
                'type',
                'name',
                'cnic',
                'education',
                'occupation',
                'contact_no',
                'is_guardian'
            ]);

            $table->integer('user_id')->nullable();

            // father fields
            $table->string('father_name')->nullable();
            $table->string('father_cnic')->nullable();
            $table->string('father_education')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_contact_no')->nullable();

            // mother fields
            $table->string('mother_name')->nullable();
            $table->string('mother_cnic')->nullable();
            $table->string('mother_education')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_contact_no')->nullable();

            // guardian
            $table->string('guardian_type')->nullable(); // father / mother
        });
    }

    public function down()
    {
        Schema::table('parent_profiles', function (Blueprint $table) {

            $table->dropColumn([
                'father_name',
                'father_cnic',
                'father_education',
                'father_occupation',
                'father_contact_no',

                'mother_name',
                'mother_cnic',
                'mother_education',
                'mother_occupation',
                'mother_contact_no',

                'guardian_type'
            ]);
        });
    }
};
