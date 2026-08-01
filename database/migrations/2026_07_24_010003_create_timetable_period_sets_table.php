<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_period_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('timetable_group_id')->constrained('timetable_groups')->cascadeOnDelete();
            $table->string('title'); // e.g. "test time periods"
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'timetable_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_period_sets');
    }
};
