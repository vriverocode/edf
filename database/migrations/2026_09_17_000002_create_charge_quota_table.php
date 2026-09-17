<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_quota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quota_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->timestamps();

            $table->unique(['department_charge_id', 'quota_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_quota');
    }
};
