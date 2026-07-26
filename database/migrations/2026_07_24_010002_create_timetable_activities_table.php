<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Non-teaching schedule blocks (Assembly, Lunch, Break, Library, Sports, ...).
        // branch_id nullable = a global/system-wide activity visible to every campus.
        Schema::create('timetable_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            // No DB-level unique(branch_id, name): NULL != NULL means a unique index
            // wouldn't dedupe multiple global rows anyway. Name uniqueness within a
            // scope (branch or global) is enforced in the controller instead.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_activities');
    }
};
