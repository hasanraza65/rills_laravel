<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('head_frequency', 50)->default('Monthly')->after('head_name');
            // Snapshot: how much was paid on the original item BEFORE this carry was created.
            // Set once at carry-creation time, never mutated by payments.
            $table->decimal('previous_paid', 10, 2)->default(0)->after('amount');
            $table->unsignedBigInteger('carried_from_item_id')->nullable()->after('previous_paid');

            $table->foreign('carried_from_item_id')
                ->references('id')
                ->on('invoice_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['carried_from_item_id']);
            $table->dropColumn(['head_frequency', 'previous_paid', 'carried_from_item_id']);
        });
    }
};
