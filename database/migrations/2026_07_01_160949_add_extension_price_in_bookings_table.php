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
            $table->decimal('extension_price', 10, 2)->nullable()->after('price');
            $table->integer('has_extension')->nullable()->after('extension_price');
            $table->integer('max_time_extension')->nullable()->after('extension_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            //
        });
    }
};
