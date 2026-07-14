<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Adds the Link and Page fields the syllabus form actually needs, and drops
     * campus_id/session_id: SyllabusController::store() populated them from
     * auth()->user()->campus_id / ->session_id, but neither column exists on
     * `users` — Eloquent's attribute access silently returns null for an unknown
     * column, so these always inserted NULL. Table is empty, so no data to lose.
     */
    public function up(): void
    {
        Schema::table('syllabuses', function (Blueprint $table) {
            $table->string('page')->nullable()->after('content');
            $table->string('link')->nullable()->after('page');
            $table->dropColumn(['campus_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('syllabuses', function (Blueprint $table) {
            $table->dropColumn(['page', 'link']);
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
        });
    }
};
