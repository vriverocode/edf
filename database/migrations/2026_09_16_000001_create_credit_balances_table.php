<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departament_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_balances');
    }
};
