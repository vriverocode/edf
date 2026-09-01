<?php

namespace App\Console\Commands;

use App\Models\MonthlyBills;
use App\Services\MonthlyQuotaService;
use Illuminate\Console\Command;

class MonthlyQuota extends Command
{
    protected $signature = 'app:monthly-quota';

    protected $description = 'Genera las cuotas del mes actual el día 20';

    public function handle(MonthlyQuotaService $service)
    {
        $period = $this->getBillingPeriod();
        $month = $period['month'];
        $year = $period['year'];

        $budgetConfig = MonthlyBills::where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $budgetConfig) {
            $this->error("No se encontró presupuesto configurado para el mes $month/$year. Abortando.");

            return;
        }

        $result = $service->generateForPeriod($month, $year, $budgetConfig);

        $this->info("Cuotas generadas exitosamente para el periodo $month/$year (generadas: {$result['generated']}, omitidas: {$result['skipped']}).");
    }

    private function getBillingPeriod(): array
    {
        return [
            'month' => (int) now()->format('n'),
            'year' => (int) now()->format('Y'),
        ];
    }
}
