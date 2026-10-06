<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La tabla no tiene indice por created_at, asi que la limpieza periodica
        // de notificaciones antigua terminaba haciendo un full scan.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('created_at', 'notifications_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_created_at_index');
        });
    }
};
