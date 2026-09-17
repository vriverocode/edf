<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotas', function (Blueprint $table) {
            $table->decimal('extra_amount', 15, 2)->default(0)->after('water_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quotas', function (Blueprint $table) {
            $table->dropColumn('extra_amount');
        });
    }
};
