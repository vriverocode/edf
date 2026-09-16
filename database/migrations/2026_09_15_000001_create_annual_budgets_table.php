<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_budgets', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('name');
            $table->decimal('total_monthly_budget', 15, 2)->default(0);
            $table->integer('status')->default(1); // 1=Borrador, 2=Activo, 3=Cerrado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_budgets');
    }
};
