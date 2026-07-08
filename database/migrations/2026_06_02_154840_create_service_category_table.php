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
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('status');
            $table->timestamps();
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->renameColumn('service_category', 'service_category_id');

            $table->unsignedBigInteger('service_category_id')->change();
            $table->foreign('service_category_id')->references('id')->on('service_categories')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_category');
    }
};
