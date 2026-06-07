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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description');
            $table->date('date');
            $table->time('hour');
            $table->longText('location');
            $table->longText('images');
            $table->longText('videos');
            $table->longText('files');
            $table->integer('status');
            $table->integer('type');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->nulleable()->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
