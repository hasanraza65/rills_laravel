<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_groups', function (Blueprint $table) {
            // Nullable + nullOnDelete: exams created before Sessions existed (or a
            // deleted session) just leave this blank rather than breaking.
            $table->foreignId('academic_session_id')->nullable()->after('branch_id')
                ->constrained('academic_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_session_id');
        });
    }
};
