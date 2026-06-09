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
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version'); // ej: '1.0.5'
            $table->integer('version_code'); // ej: 5 (número entero para comparar más fácil)
            $table->string('download_url'); // URL pública donde alojarás el .apk
            $table->text('release_notes')->nullable(); // Cambios de la versión
            $table->boolean('force_update')->default(false); // Por si es una actualización obligatoria
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
