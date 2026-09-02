<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pays', function (Blueprint $table) {
            $table->bigInteger('pay_method')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pays', function (Blueprint $table) {
            $table->bigInteger('pay_method')->nullable(false)->change();
        });
    }
};
