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
        Schema::create('monthly_bills', function (Blueprint $table) {
            $table->id();
            $table->integer('month'); // 1-12
            $table->integer('year');  // Ej: 2026
            $table->decimal('total_maintenance_budget', 15, 2); 
            $table->decimal('water_price_per_m3', 10, 4); 
            
            // Campos opcionales para transparencia
            $table->decimal('total_water_bill_amount', 15, 2)->nullable();
            $table->decimal('total_water_consumption_m3', 10, 2)->nullable(); 
            
            // Control de estado para el Job/Task Scheduler
            $table->boolean('is_published')->default(false);
            $table->timestamp('generated_at')->nullable(); 
            
            $table->timestamps();
            
            // Unicidad para evitar presupuestos duplicados en el mismo mes/año
            $table->unique(['month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_bills');
    }
};
