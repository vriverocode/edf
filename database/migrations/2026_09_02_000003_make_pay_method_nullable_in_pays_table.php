<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasFk = DB::select(
            "SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
             AND TABLE_NAME = 'pays'
             AND CONSTRAINT_NAME = 'pays_pay_method_foreign'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );

        if (($hasFk[0]->cnt ?? 0) > 0) {
            Schema::table('pays', function (Blueprint $table) {
                $table->dropForeign('pays_pay_method_foreign');
            });
        }

        Schema::table('pays', function (Blueprint $table) {
            $table->unsignedBigInteger('pay_method')->nullable()->change();
        });

        Schema::table('pays', function (Blueprint $table) {
            $table->foreign('pay_method')->references('id')->on('pay_methods')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $hasFk = DB::select(
            "SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
             AND TABLE_NAME = 'pays'
             AND CONSTRAINT_NAME = 'pays_pay_method_foreign'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );

        if (($hasFk[0]->cnt ?? 0) > 0) {
            Schema::table('pays', function (Blueprint $table) {
                $table->dropForeign('pays_pay_method_foreign');
            });
        }

        Schema::table('pays', function (Blueprint $table) {
            $table->unsignedBigInteger('pay_method')->nullable(false)->change();
        });

        Schema::table('pays', function (Blueprint $table) {
            $table->foreign('pay_method')->references('id')->on('pay_methods')->onDelete('cascade');
        });
    }
};
