<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alinea FK con trazabilidad: si se elimina el Pay, conservar Transaction con pay_id nulo.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['pay_id']);
            $table->foreign('pay_id')
                ->references('id')
                ->on('pays')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['pay_id']);
            $table->foreign('pay_id')
                ->references('id')
                ->on('pays')
                ->cascadeOnDelete();
        });
    }
};
