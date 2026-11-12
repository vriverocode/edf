<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urbanismos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo_invitacion', 12)->unique();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('moneda_principal', 3)->default('USD');
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urbanismos');
    }
};
