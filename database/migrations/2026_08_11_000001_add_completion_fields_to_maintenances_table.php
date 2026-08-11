<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('evidence_photo')->nullable()->after('photo');
            $table->longText('completion_description')->nullable()->after('evidence_photo');
            $table->timestamp('completed_at')->nullable()->after('completion_description');
            $table->foreignId('completed_by')
                ->nullable()
                ->after('completed_at')
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn(['evidence_photo', 'completion_description', 'completed_at']);
        });
    }
};
