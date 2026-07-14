<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The diary form has an "Activity" field, not a "Description" one — the UI was
     * submitting Activity into the `description` column and relabelling it on the
     * way back out. Rename the column so the name matches the thing it holds.
     *
     * Uses raw `ALTER TABLE ... CHANGE` rather than $table->renameColumn(). With
     * doctrine/dbal absent, Laravel 10 compiles renameColumn() to native
     * `RENAME COLUMN`, which needs MySQL 8.0.3+ / MariaDB 10.5.2+. This server is
     * MariaDB 10.4.28, where that syntax is a hard error. `CHANGE` works on every
     * MySQL/MariaDB version and is a single atomic statement (MySQL DDL is not
     * transactional, so a multi-statement copy-and-drop could half-apply).
     */
    public function up(): void
    {
        // Idempotent: safe to re-run after a partially applied batch.
        if (Schema::hasColumn('diaries', 'activity') || ! Schema::hasColumn('diaries', 'description')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // LONGTEXT NULL restates the type verbatim from the create migration.
            DB::statement('ALTER TABLE `diaries` CHANGE `description` `activity` LONGTEXT NULL');

            return;
        }

        // Driver-agnostic fallback (sqlite/pgsql).
        Schema::table('diaries', fn (Blueprint $table) => $table->longText('activity')->nullable());
        DB::table('diaries')->update(['activity' => DB::raw('description')]);
        Schema::table('diaries', fn (Blueprint $table) => $table->dropColumn('description'));
    }

    public function down(): void
    {
        if (Schema::hasColumn('diaries', 'description') || ! Schema::hasColumn('diaries', 'activity')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `diaries` CHANGE `activity` `description` LONGTEXT NULL');

            return;
        }

        Schema::table('diaries', fn (Blueprint $table) => $table->longText('description')->nullable());
        DB::table('diaries')->update(['description' => DB::raw('activity')]);
        Schema::table('diaries', fn (Blueprint $table) => $table->dropColumn('activity'));
    }
};
