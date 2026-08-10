<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            // Nullable + nullOnDelete: a schedule can still exist standalone (matched by
            // exam_type/class/section alone, as before), and deleting the parent Exam
            // Group Exam just unlinks it rather than destroying scheduled marks.
            $table->foreignId('exam_group_exam_id')->nullable()->after('exam_type')
                ->constrained('exam_group_exams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_group_exam_id');
        });
    }
};
