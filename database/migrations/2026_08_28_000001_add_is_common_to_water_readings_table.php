<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_readings', function (Blueprint $table) {
            $table->boolean('is_common')->default(false)->after('is_initial');
            $table->foreignId('departament_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('water_readings', function (Blueprint $table) {
            $table->dropColumn('is_common');
            $table->foreignId('departament_id')->nullable(false)->change();
        });
    }
};
