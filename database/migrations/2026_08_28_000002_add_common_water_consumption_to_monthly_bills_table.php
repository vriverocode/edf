<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_bills', function (Blueprint $table) {
            $table->decimal('common_water_consumption_m3', 10, 2)->nullable()->after('total_water_consumption_m3');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_bills', function (Blueprint $table) {
            $table->dropColumn('common_water_consumption_m3');
        });
    }
};
