<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qb_topic_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('qb_topics')->cascadeOnDelete();
            $table->text('objective');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qb_topic_objectives');
    }
};