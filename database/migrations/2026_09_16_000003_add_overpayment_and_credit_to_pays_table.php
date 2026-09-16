<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pays', function (Blueprint $table) {
            $table->decimal('overpayment_amount', 15, 2)->nullable()->after('commission_amount');
            $table->decimal('credit_applied', 15, 2)->nullable()->after('overpayment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pays', function (Blueprint $table) {
            $table->dropColumn(['overpayment_amount', 'credit_applied']);
        });
    }
};
