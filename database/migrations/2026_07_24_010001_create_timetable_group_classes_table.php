<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: which SchoolClass levels a Time Table Group covers (e.g. "Level Six to Seven" -> Level 6 + Level 7).
        Schema::create('timetable_group_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_group_id')->constrained('timetable_groups')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['timetable_group_id', 'school_class_id'], 'timetable_group_classes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_group_classes');
    }
};
