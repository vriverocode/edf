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
        Schema::table('rules', function (Blueprint $table) {
            $table->unsignedBigInteger('is_for_comun_area')->nullable()->after('suggest_amount'); 
            $table->unsignedBigInteger('comun_area_id')->nullable()->after('is_for_comun_area');
            $table->foreign('comun_area_id')->nulleable()->references('id')->on('comun_areas')->onDelete('cascade');
       
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            //
        });
    }
};
