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
        Schema::table('comun_areas', function (Blueprint $table) {
            //
            $table->integer('max_time_reserve_exclusive')->after('max_time_reserve')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comun_areas', function (Blueprint $table) {
            //
        });
    }
};
