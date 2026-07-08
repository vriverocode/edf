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
        Schema::create('airbnb_rents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('departament_id');
            $table->foreign('departament_id')->references('id')->on('departaments')->onDelete('cascade');
            $table->unsignedBigInteger('assing_to');
            $table->foreign('assing_to')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->integer('quantity');
            $table->date('init_day');
            $table->date('end_date');
            $table->integer('status');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airbnb_rents');
    }
};
