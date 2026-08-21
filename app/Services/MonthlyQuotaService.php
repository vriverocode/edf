<?php

namespace App\Services;

use App\Models\Departament;
use App\Models\MonthlyBills;
use App\Models\PeoplesXDepartaments;
use App\Models\Quota;
use App\Models\Rol;
use App\Models\WaterReading;
use App\Notifications\RealtimeNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class MonthlyQuotaService
{
    public function generateForPeriod(int $month, int $year, ?MonthlyBills $budgetConfig = null): array
    {
        $budgetConfig ??= MonthlyBills::where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $budgetConfig) {
            return ['generated' => 0, 'skipped' => 0];
        }

        $departaments = $this->getAllDepartamentsId();
        $departaments->load('peoples.user');

        $generated = 0;
        $skipped = 0;

        foreach ($departaments as $departament) {
            if ($this->makeQuotaOfMonth($departament, $month, $year, $budgetConfig)) {
                $generated++;
            } else {
                $skipped++;
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private function getAllDepartamentsId()
    {
        return Departament::select('id', 'number', 'participation_percentage', 'type')
            ->where(fn ($q) => $q->has('owner')->orWhereHas('peoples', fn ($q) => $q->where('type', Rol::INQUILINO)))
            ->get();
    }

    private function labelMonth($monthIndex)
    {
        $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return $months[$monthIndex];
    }

    private function makeQuotaOfMonth($departament, $month, $year, $budgetConfig): bool
    {
        if (! $this->checkIfNoPayQuota($departament->id, $month, $year)) {
            return false;
        }

        try {
            $activeTenantPivot = $this->findActiveTenantPivot($departament);

            $maintenanceAmount = $budgetConfig->total_maintenance_budget * $departament->participation_percentage;

            $waterReading = WaterReading::where('departament_id', $departament->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            $waterAmount = 0;
            $waterReadingId = null;

            if ($waterReading) {
                $waterReadingId = $waterReading->id;
                $consumption = $waterReading->current_reading - $waterReading->previous_reading;
                $waterAmount = max(0, $consumption) * $budgetConfig->water_price_per_m3;
            }

            $totalAmount = $maintenanceAmount + $waterAmount;

            $quota = Quota::create([
                'departament_id' => $departament->id,
                'peoples_x_departments_id' => $activeTenantPivot?->id,
                'water_reading_id' => $waterReadingId,
                'maintenance_amount' => $maintenanceAmount,
                'water_amount' => $waterAmount,
                'amount' => $totalAmount,
                'number' => 'A'.substr($departament->number, -3).'-'.$month.rand(1000, 9999),
                'month' => $month,
                'due_date' => $year.'-'.$month.'-10',
                'type' => $departament->type == 1 ? 1 : 2,
                'description' => 'Cuota mensual: '.$this->labelMonth($month).' - '.$year,
                'status' => 1,
            ]);
            if ($quota) {
                $this->sendNotifications($quota, $activeTenantPivot);
            }

            return true;
        } catch (Exception $th) {
            Log::error("Error generando cuota para departamento {$departament->id}: ".$th->getMessage());

            return false;
        }
    }

    private function findActiveTenantPivot($departament): ?PeoplesXDepartaments
    {
        return $departament->peoples
            ->where('type', Rol::INQUILINO)
            ->firstWhere(fn ($p) => $p->user && (int) $p->user->status !== 3 && ! $p->user->trashed());
    }

    private function checkIfNoPayQuota($departamentId, $month, $year)
    {
        return ! Quota::where('departament_id', $departamentId)
            ->where('month', $month)
            ->whereYear('due_date', $year)
            ->exists();
    }

    private function sendNotifications($quota, $activeTenantPivot = null)
    {
        $periodYear = (int) Carbon::parse($quota->due_date)->format('Y');
        $monthLabel = $this->labelMonth($quota->month);

        if ($activeTenantPivot) {
            $this->notifyUser(
                $activeTenantPivot->user,
                "Cuota general mes: $monthLabel",
                "Hola, te hacemos llegar la cuota de mantenimiento por el mes: $monthLabel $periodYear. Por favor mantenerse al día",
                '/client/quota/pay/'.$quota->id,
                ['quota_id' => $quota->id, 'color' => 'amber-8'],
            );

            $owner = $quota->departament->owner;
            if ($owner) {
                $this->notifyUser(
                    $owner,
                    'Cuota de tu departamento asignada a inquilino',
                    "Se generó la cuota de $monthLabel $periodYear para tu departamento {$quota->departament->number}. El inquilino {$activeTenantPivot->user->name} es responsable del pago.",
                    '/client/quota/pay/'.$quota->id,
                    ['quota_id' => $quota->id, 'color' => 'amber-8', 'is_informative' => true],
                );
            }
        } else {
            $owner = $quota->departament->owner;
            if ($owner) {
                $this->notifyUser(
                    $owner,
                    "Cuota general mes: $monthLabel",
                    "Hola, te hacemos llegar la cuota de mantenimiento por el mes: $monthLabel $periodYear. Por favor mantenerse al día",
                    '/client/quota/pay/'.$quota->id,
                    ['quota_id' => $quota->id, 'color' => 'amber-8'],
                );
            }
        }
    }

    private function notifyUser($user, $title, $message, $url, $meta): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify(new RealtimeNotification(
                title: $title,
                message: $message,
                url: $url,
                meta: $meta,
            ));
        } catch (\Throwable $e) {
            Log::error('Error enviando notificacion de cuota: '.$e->getMessage());
        }
    }
}
