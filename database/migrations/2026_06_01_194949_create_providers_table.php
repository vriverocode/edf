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
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre de la empresa o persona
            $table->string('tax_id')->unique(); // RUC / NIT / RUT
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('service_category'); // ej: 'Ascensores', 'Limpieza', 'Plomería'
            $table->json('bank_details')->nullable(); // Cuentas bancarias para hacerle las transferencias
            $table->integer('status')->default(1); // 1: Activo, 0: Inactivo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
