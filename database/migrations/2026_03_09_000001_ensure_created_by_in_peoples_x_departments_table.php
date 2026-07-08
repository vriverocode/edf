<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Asegura que la columna created_by exista para registrar el usuario que creó el registro.
     */
    public function up(): void
    {
        Schema::table('peoples_x_departments', function (Blueprint $table) {
            if (! Schema::hasColumn('peoples_x_departments', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('type');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peoples_x_departments', function (Blueprint $table) {
            if (Schema::hasColumn('peoples_x_departments', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
