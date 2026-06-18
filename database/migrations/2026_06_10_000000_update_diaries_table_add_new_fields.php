<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            $table->string('page_number')->nullable()->after('description');
            $table->string('resources')->nullable()->after('page_number');
            $table->string('link')->nullable()->after('resources');
            $table->text('home_work')->nullable()->after('link');

            $table->dropColumn(['campus_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            $table->dropColumn(['page_number', 'resources', 'link', 'home_work']);

            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
        });
    }
};
