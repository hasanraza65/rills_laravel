<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // No date casts on the model — kept as raw Y-m-d strings so the frontend's
            // <input type="date"> can bind to them directly (matches exam_schedules.date).
            $table->date('start_date');
            $table->date('end_date');
            // Only one session per branch should be active at a time — enforced in the
            // controller (setting one active deactivates the others), not by a DB constraint.
            $table->boolean('is_active')->default(false);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_sessions');
    }
};
