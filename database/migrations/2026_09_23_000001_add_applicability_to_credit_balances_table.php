<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_balances', function (Blueprint $table) {
            $table->dropUnique(['departament_id']);
            $table->unsignedTinyInteger('applicable_month')->default(0)->after('balance');
            $table->unsignedSmallInteger('applicable_year')->nullable()->after('applicable_month');
        });

        // Legacy importados (DEVOL PARA SEPT. 26) solo aplican a septiembre 2026
        DB::table('credit_balances')
            ->where('balance', '>', 0)
            ->update([
                'applicable_month' => 9,
                'applicable_year' => 2026,
            ]);

        Schema::table('credit_balances', function (Blueprint $table) {
            $table->unique(
                ['departament_id', 'applicable_month', 'applicable_year'],
                'credit_balances_dept_month_year_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('credit_balances', function (Blueprint $table) {
            $table->dropUnique('credit_balances_dept_month_year_unique');
            $table->dropColumn(['applicable_month', 'applicable_year']);
            $table->unique('departament_id');
        });
    }
};
