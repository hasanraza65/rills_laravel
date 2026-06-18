<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parent_profiles')->cascadeOnDelete();

            $table->decimal('amount_paid', 10, 2);
            $table->decimal('wallet_used', 10, 2)->default(0);
            $table->decimal('extra_added_to_wallet', 10, 2)->default(0);

            $table->string('payment_method')->nullable(); // cash, bank, etc

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
