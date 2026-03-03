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
        Schema::table('peoples_x_departments', function (Blueprint $table) {
            //

            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->nulleable()->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoples_x_departments', function (Blueprint $table) {
            //
        });
    }
};
