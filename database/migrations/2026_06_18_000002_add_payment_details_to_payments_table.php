<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('payment_method');
            $table->string('reference_no')->nullable()->after('bank_name');
            $table->date('payment_date')->nullable()->after('reference_no');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'reference_no', 'payment_date']);
        });
    }
};
