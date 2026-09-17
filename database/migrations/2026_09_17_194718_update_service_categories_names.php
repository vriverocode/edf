<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_categories')->where('id', 1)->update(['name' => 'Servicios Básicos']);
        DB::table('service_categories')->where('id', 2)->update(['name' => 'Admin']);
        DB::table('service_categories')->where('id', 3)->update(['name' => 'Mantenimientos Preventivos']);
        DB::table('service_categories')->where('id', 4)->update(['name' => 'Gastos Operativos / Contingencias']);
        DB::table('service_categories')->where('id', 5)->update(['name' => 'Materiales Consumibles']);
        DB::table('service_categories')->where('id', 6)->update(['name' => 'Otros Gastos Ordinarios']);
    }

    public function down(): void
    {
        DB::table('service_categories')->where('id', 1)->update(['name' => 'Sevicios']);
        DB::table('service_categories')->where('id', 2)->update(['name' => 'Servicios Básicos']);
        DB::table('service_categories')->where('id', 3)->update(['name' => 'Mantenimiento']);
        DB::table('service_categories')->where('id', 4)->update(['name' => 'Sin categoría']);
        DB::table('service_categories')->where('id', 5)->update(['name' => 'Materiales']);
        DB::table('service_categories')->where('id', 6)->update(['name' => 'Otros']);
    }
};
