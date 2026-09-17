<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('monthly_amount', 15, 2);
            $table->unsignedInteger('installments')->default(1);
            $table->unsignedInteger('paid_installments')->default(0);
            $table->tinyInteger('status')->default(1)->comment('1=Activo, 2=Pagado, 3=Cancelado');
            $table->unsignedTinyInteger('start_month');
            $table->unsignedSmallInteger('start_year');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['departament_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_charges');
    }
};
