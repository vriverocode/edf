<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pays', function (Blueprint $table) {
            if (Schema::hasColumn('pays', 'pay_method')) {
                $table->dropForeign(['pay_method']);
            }
            $table->unsignedBigInteger('pay_method')->nullable()->change();
            $table->foreign('pay_method')->references('id')->on('pay_methods')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('pays', function (Blueprint $table) {
            $table->dropForeign(['pay_method']);
            $table->unsignedBigInteger('pay_method')->nullable(false)->change();
            $table->foreign('pay_method')->references('id')->on('pay_methods')->onDelete('cascade');
        });
    }
};
