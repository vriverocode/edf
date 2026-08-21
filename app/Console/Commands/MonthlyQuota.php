<?php

namespace App\Console\Commands;

use App\Models\MonthlyBills;
use App\Services\MonthlyQuotaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MonthlyQuota extends Command
{
    protected $signature = 'app:monthly-quota';

    protected $description = 'Genera las cuotas del mes anterior (ej. ejecutado el 5 de mayo cobra abril)';

    public function handle(MonthlyQuotaService $service)
    {
        $period = $this->getBillingPeriod();
        $month = $period['month'];
        $year = $period['year'];

        $budgetConfig = MonthlyBills::where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $budgetConfig) {
            $this->error("No se encontró presupuesto configurado (CondoBudget) para el mes $month/$year. Abortando.");

            return;
        }

        $result = $service->generateForPeriod($month, $year, $budgetConfig);

        $this->info("Cuotas generadas exitosamente para el periodo $month/$year (generadas: {$result['generated']}, omitidas: {$result['skipped']}).");
    }

    private function getBillingPeriod(): array
    {
        $billing = Carbon::now()->subMonth();

        return [
            'month' => (int) $billing->format('n'),
            'year' => (int) $billing->format('Y'),
        ];
    }
}
