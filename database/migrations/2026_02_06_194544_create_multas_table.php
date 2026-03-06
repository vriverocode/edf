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
        Schema::create('multas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->foreign('rule_id')->nulleable()->references('id')->on('rules')->onDelete('cascade');
            $table->unsignedBigInteger('departament_id')->nullable();
            $table->foreign('departament_id')->nulleable()->references('id')->on('departaments')->onDelete('cascade');
            $table->integer('type');
            $table->longText('description');
            $table->date('incident_date');
            $table->double('amount')->nullable();
            $table->unsignedBigInteger('pay_id')->nullable();
            $table->foreign('pay_id')->nulleable()->references('id')->on('pays')->onDelete('cascade');
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multas');
    }
};
