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
        Schema::table('multas', function (Blueprint $table) {
            $table->unsignedInteger('installments')->default(1)->after('amount');
            $table->unsignedInteger('paid_installments')->default(0)->after('installments');
            $table->unsignedSmallInteger('start_month')->nullable()->after('paid_installments');
            $table->unsignedSmallInteger('start_year')->nullable()->after('start_month');
            $table->softDeletes()->after('updated_at');
        });

        Schema::create('multa_quota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('multa_id')->constrained('multas')->cascadeOnDelete();
            $table->foreignId('quota_id')->constrained('quotas')->cascadeOnDelete();
            $table->unsignedInteger('installment_number')->default(1);
            $table->timestamps();

            $table->unique(['multa_id', 'quota_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multa_quota');

        Schema::table('multas', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['installments', 'paid_installments', 'start_month', 'start_year']);
        });
    }
};

