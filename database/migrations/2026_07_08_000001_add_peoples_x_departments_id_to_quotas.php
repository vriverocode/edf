<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotas', function (Blueprint $table) {
            $table->foreignId('peoples_x_departments_id')
                ->nullable()
                ->after('departament_id')
                ->constrained('peoples_x_departments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotas', function (Blueprint $table) {
            $table->dropForeign(['peoples_x_departments_id']);
            $table->dropColumn('peoples_x_departments_id');
        });
    }
};
