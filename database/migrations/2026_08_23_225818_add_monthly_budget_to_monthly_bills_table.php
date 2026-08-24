<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_bills', function (Blueprint $table) {
            $table->decimal('monthly_budget', 15, 2)->default(0)->after('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_bills', function (Blueprint $table) {
            $table->dropColumn('monthly_budget');
        });
    }
};
