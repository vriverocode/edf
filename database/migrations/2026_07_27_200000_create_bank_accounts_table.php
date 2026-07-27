<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'bank' or 'yape'
            $table->string('entity')->nullable(); // bank name
            $table->string('account_number')->nullable();
            $table->string('cci')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('yape_phone')->nullable();
            $table->string('yape_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
