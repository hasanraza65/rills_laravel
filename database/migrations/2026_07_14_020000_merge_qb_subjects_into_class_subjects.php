<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The four tables that reference a "subject" via a subject_id column.
     * Each originally pointed at qb_subjects.
     */
    private array $tables = ['qb_topics', 'qb_questions', 'lesson_plan_subjects', 'lesson_plan_done_topics'];

    /**
     * qb_subjects and class_subjects were two separate "subject" catalogues —
     * one teacher-less (backing Lesson Plan / Question Bank), one with exactly
     * one teacher per row (backing Diary / Syllabus / Attendance). They modeled
     * the same real-world thing. class_subjects is the more established of the
     * two, so qb_topics, qb_questions, lesson_plan_subjects, and
     * lesson_plan_done_topics now point at it instead. Both tables were empty
     * at the time of this migration, so there is no data to carry over.
     *
     * Uses raw SQL rather than $table->dropForeign()/foreign(), and checks
     * information_schema directly rather than trusting Laravel's naming-
     * convention shortcut: on this MariaDB version, ALTER TABLE ... ADD
     * CONSTRAINT ... FOREIGN KEY can silently leave behind a same-named index
     * without the actual constraint when run inside a rapid drop/recreate
     * sequence, which then makes the conventional dropForeign() shorthand fail
     * on a later run claiming the constraint "doesn't exist" (the index does,
     * the constraint doesn't). Checking real state avoids relying on that.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            $this->dropSubjectIdConstraintAndIndex($table);
        }

        Schema::dropIfExists('qb_subjects');

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `class_subjects` (`id`) ON DELETE CASCADE");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $this->dropSubjectIdConstraintAndIndex($table);
        }

        Schema::create('qb_subjects', function ($table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `qb_subjects` (`id`) ON DELETE CASCADE");
        }
    }

    /**
     * Drops whatever currently exists on {table}.subject_id under the
     * conventional constraint name — a real FK constraint, a stray index left
     * over from a previous failed constraint creation, or nothing at all.
     */
    private function dropSubjectIdConstraintAndIndex(string $table): void
    {
        $name = "{$table}_subject_id_foreign";

        $hasConstraint = DB::selectOne(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = "FOREIGN KEY"',
            [$table, $name]
        );
        if ($hasConstraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }

        $hasIndex = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $name]
        );
        if ($hasIndex) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
        }
    }
};
