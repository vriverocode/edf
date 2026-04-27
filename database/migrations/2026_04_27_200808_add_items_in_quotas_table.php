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
        Schema::table('quotas', function (Blueprint $table) {
            $table->unsignedBigInteger('water_reading_id')->nullable()->after('departament_id');
            $table->foreign('water_reading_id')->references('id')->on('water_readings')->onDelete('cascade');
            $table->decimal('maintenance_amount', 15, 2)->default(0)->after('water_reading_id');
            $table->decimal('water_amount', 15, 2)->default(0)->after('maintenance_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotas', function (Blueprint $table) {
            //
        });
    }
};
