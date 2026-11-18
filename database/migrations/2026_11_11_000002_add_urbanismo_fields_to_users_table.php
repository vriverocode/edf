<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('urbanismo_id')
                ->nullable()
                ->after('id')
                ->constrained('urbanismos')
                ->nullOnDelete();

            $table->string('unidad')->nullable()->after('urbanismo_id');

            $table->enum('estado_registro', ['pendiente', 'activo', 'cuarentena', 'suspendido'])
                ->default('activo')
                ->after('unidad')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('urbanismo_id');
            $table->dropColumn(['unidad', 'estado_registro']);
        });
    }
};
