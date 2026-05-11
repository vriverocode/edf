<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\MonthlyBills;
use App\Models\Quota;
use App\Models\User;
use App\Models\WaterReading;
use App\Notifications\RealtimeNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonthlyQuota extends Command
{
    /**
     * El nombre y firma del comando de consola.
     */
    protected $signature = 'app:monthly-quota';

    /**
     * La descripción del comando.
     */
    protected $description = 'Genera las cuotas del mes anterior (ej. ejecutado el 5 de mayo cobra abril)';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $departaments = $this->getAllDepartamentsId();
        $period = $this->getBillingPeriod();
        $month = $period['month'];
        $year = $period['year'];

        // Buscamos el presupuesto configurado para este mes
        $budgetConfig = MonthlyBills::where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$budgetConfig) {
            $this->error("No se encontró presupuesto configurado (CondoBudget) para el mes $month/$year. Abortando.");
            return;
        }

        foreach ($departaments as $departament) {
            $this->makeQuotaOfMonth($departament, $month, $year, $budgetConfig);
            
        }

        $this->info("Cuotas generadas exitosamente para el periodo $month/$year.");
    }

    private function getAllDepartamentsId()
    {
        // Importante: Asegúrate de que el modelo Departament tenga el campo 'participation_percentage'
        return Departament::select('id', 'number', 'participation_percentage')->has('owner')->get();
    }

    /** Periodo a facturar: mes calendario inmediatamente anterior a la fecha de ejecución. */
    private function getBillingPeriod(): array
    {
        $billing = Carbon::now()->subMonth();

        return [
            'month' => (int) $billing->format('n'),
            'year' => (int) $billing->format('Y'),
        ];
    }

    private function labelMonth($monthIndex)
    {
        $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $months[$monthIndex];
    }

    private function makeQuotaOfMonth($departament, $month, $year, $budgetConfig)
    {
        if ($this->checkIfNoPayQuota($departament->id, $month, $year)) {
            try {
                // 1. Cálculo de Mantenimiento Base por Coeficiente
                $maintenanceAmount = $budgetConfig->total_maintenance_budget * $departament->participation_percentage;

                // 2. Cálculo de Agua por Medición
                $waterReading = WaterReading::where('departament_id', $departament->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                $waterAmount = 0;
                $waterReadingId = null;

                if ($waterReading) {
                    $waterReadingId = $waterReading->id;
                    $consumption = $waterReading->current_reading - $waterReading->previous_reading;
                    // Usamos el precio por m3 definido en el presupuesto de este mes
                    $waterAmount = max(0, $consumption) * $budgetConfig->water_price_per_m3;
                }

                // 3. Monto Total
                $totalAmount = $maintenanceAmount + $waterAmount;

                // Asignamos la creación a la variable $quota
                $quota = Quota::create([
                    'departament_id'     => $departament->id,
                    'water_reading_id'   => $waterReadingId, 
                    'maintenance_amount' => $maintenanceAmount, 
                    'water_amount'       => $waterAmount,       
                    'amount'             => $totalAmount,        
                    'number'             => 'A' . substr($departament->number, -3) . '-' . $month . rand(1000, 9999),
                    'month'              => $month,
                    'due_date'           => $year . '-' . $month . '-10',
                    'type'               => 1,
                    'description'        => 'Cuota mensual: ' . $this->labelMonth($month) . ' ' . $year,
                    'status'             => 1
                ]);

                // Disparamos la notificación
                if ($quota) {
                    $this->sendNotifications($quota);
                }

            } catch (Exception $th) {
                Log::error("Error generando cuota para departamento {$departament->id}: " . $th->getMessage());
            }
        }
    }

    private function checkIfNoPayQuota($departamentId, $month, $year)
    {
        return !Quota::where('departament_id', $departamentId)
            ->where('month', $month)
            ->whereYear('created_at', $year) // O usar una columna 'year' si la tienes
            ->exists();
    }
    private function sendNotifications($quota)
    {
        $periodYear = (int) Carbon::parse($quota->due_date)->format('Y');
        $users = [
            "client" => User::find($quota->departament->user_id),
        ];
        $dataNotificaction = [
            "title" => "Cuota general mes: ".$this->labelMonth($quota->month),
            "message" => "Hola, te hacemos llegar la cuota de mantenimiento por el mes: " . $this->labelMonth($quota->month) . ' ' . $periodYear
                . ". Por favor mantenerse al día",
            "url" => "/client/quota/pay/" . $quota->id,
            "meta" =>  ['quota_id' => $quota->id, 'color'=>'amber-8'],
        ];

        try {            
            $users["client"]->notify(new RealtimeNotification(
                title: $dataNotificaction["title"],
                message: $dataNotificaction["message"],
                url: $dataNotificaction["url"],
                meta: $dataNotificaction["meta"],
            ));
        } catch (\Throwable $e) {
            Log::error("Error generando cuota para departamento 00: " . $e->getMessage());

        }
    }
}