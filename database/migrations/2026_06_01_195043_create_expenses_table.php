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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers');

            // ¿A qué presupuesto mensual pertenece este gasto?
            // Esto es CLAVE para que al generar las cuotas (MonthlyQuota) sepas cuánto cobrar.
            $table->foreignId('monthly_bill_id')->nullable()->constrained('monthly_bills');

            $table->string('invoice_number')->nullable(); // Número de factura física/electrónica
            $table->decimal('amount', 15, 2);
            $table->date('issue_date'); // Cuándo se emitió
            $table->date('due_date'); // Cuándo vence (ej: a fin de mes)

            $table->integer('expense_type'); // 1: Ordinario/Recurrente (Limpieza), 2: Extraordinario (Tubería rota)
            $table->string('location_scope')->nullable(); // Ej: "Torre 1", "Áreas Comunes", "Sótano"
            $table->string('unit')->nullable(); // Ej: "maquina", "ascensor", "escalera"

            $table->longText('description');

            $table->string('attachment_url')->nullable(); // Foto o PDF de la factura

            $table->integer('status')->default(1); // 1: Pendiente, 2: Aprobado para pago, 3: Pagado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
