<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_activation_tokens', function (Blueprint $table) {
            $table->string('purpose', 20)
                ->default('activation')
                ->after('user_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('account_activation_tokens', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
