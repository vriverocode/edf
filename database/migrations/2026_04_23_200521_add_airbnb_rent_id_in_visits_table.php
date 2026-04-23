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
        Schema::table('visits', function (Blueprint $table) {
            $table->integer('status')->after('hour')->default(1);
            $table->unsignedBigInteger('airbnb_rent_id')->nullable()->after('status');
            $table->foreign('airbnb_rent_id')->references('id')->on('airbnb_rents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {   
        Schema::table('visits', function (Blueprint $table) {
            //
        });
    }
};
