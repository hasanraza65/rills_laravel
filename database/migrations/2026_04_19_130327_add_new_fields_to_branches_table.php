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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('branch_code', 3)->nullable()->after('branch_name'); // e.g. AK
            $table->date('campus_start_date')->nullable()->after('branch_code');
            $table->string('campus_phone')->nullable()->after('campus_start_date');
            $table->string('campus_email')->nullable()->after('campus_phone');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'branch_code',
                'campus_start_date',
                'campus_phone',
                'campus_email'
            ]);
        });
    }
};
