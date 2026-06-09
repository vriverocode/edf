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
        Schema::create('comun_area_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comun_area_id');
            $table->foreign('comun_area_id')->nulleable()->references('id')->on('comun_areas')->onDelete('cascade');
            $table->tinyInteger('day');
            $table->time('time_from');
            $table->time('time_to');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comun_area_schedules');
    }
};
