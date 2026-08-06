<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_refunds', function (Blueprint $table) {
            $table->string('vaucher')->nullable()->after('kind');
            $table->foreignId('bank_account_id')->nullable()->after('vaucher')->nullOnDelete();
            $table->json('bank_account_snapshot')->nullable()->after('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('table_refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn(['vaucher', 'bank_account_snapshot']);
        });
    }
};
