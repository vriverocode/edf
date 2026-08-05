<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'kind')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('kind')->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('table_refunds', 'type')) {
            Schema::table('table_refunds', function (Blueprint $table) {
                $table->string('type')->nullable();
            });
        }

        if (! Schema::hasColumn('table_refunds', 'kind')) {
            Schema::table('table_refunds', function (Blueprint $table) {
                $table->string('kind')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'kind')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }

        if (Schema::hasColumn('table_refunds', 'type')) {
            Schema::table('table_refunds', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        if (Schema::hasColumn('table_refunds', 'kind')) {
            Schema::table('table_refunds', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
