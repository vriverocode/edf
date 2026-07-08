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
        Schema::create('finance_orders', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('order_number');
            $table->double('amount');
            $table->string('category');
            $table->integer('status');
            $table->integer('type');
            $table->unsignedBigInteger('services_vendor_id');
            $table->foreign('services_vendor_id')->nulleable()->references('id')->on('services_vendors')->onDelete('cascade');
            $table->unsignedBigInteger('pay_id');
            $table->foreign('pay_id')->nulleable()->references('id')->on('pays')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_order');
    }
};
