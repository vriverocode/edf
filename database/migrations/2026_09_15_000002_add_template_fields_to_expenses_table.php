<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('provider_id')->nullable()->change();
            $table->date('issue_date')->nullable()->change();
            $table->date('due_date')->nullable()->change();
            $table->boolean('is_template')->default(false)->after('status');
            $table->foreignId('annual_budget_id')->nullable()->constrained('annual_budgets')->after('is_template');
            $table->integer('sort_order')->default(0)->after('annual_budget_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('provider_id')->constrained('providers')->change();
            $table->date('issue_date')->nullable(false)->change();
            $table->date('due_date')->nullable(false)->change();
            $table->dropColumn(['is_template', 'annual_budget_id', 'sort_order']);
        });
    }
};
