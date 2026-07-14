<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A subject was previously scoped to a Class only, so every section of a class
     * shared the same subject/topic catalogue. Lesson plans need to differ per
     * section, so a subject now belongs to one specific Section. qb_subjects has
     * no existing rows, so this can be a required column with no backfill.
     */
    public function up(): void
    {
        Schema::table('qb_subjects', function (Blueprint $table) {
            $table->foreignId('section_id')->after('class_id')->constrained('sections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('qb_subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
