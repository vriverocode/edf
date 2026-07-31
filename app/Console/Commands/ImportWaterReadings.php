<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\MonthlyBills;
use App\Models\Quota;
use App\Models\WaterReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWaterReadings extends Command
{
    protected $signature = 'import:water-readings';

    protected $description = 'Importa lecturas de agua (Enero-Julio 2026) desde storage/app/water_readings.json';

    private const YEAR = 2026;

    private const FIRST_MONTH = 1;

    private const LAST_MONTH = 7;

    private const FALLBACK_M3_PRICE = 0.55;

    private const MONTH_LABELS = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO'];

    private array $stats = [
        'created' => 0,
        'depto_not_found' => [],
        'no_data' => [],
        'decreasing' => [],
        'already_exists' => [],
        'quotas_linked' => 0,
        'quotas_without_quota' => 0,
    ];

    public function handle(): int
    {
        $path = storage_path('app/water_readings.json');
        if (! file_exists($path)) {
            $this->error("Archivo $path no encontrado. Generalo primero con scripts/export_water_readings.py");

            return 1;
        }

        $rows = json_decode(file_get_contents($path), true);
        if (empty($rows)) {
            $this->warn('No hay registros para procesar.');

            return 0;
        }

        $prices = $this->getPricesByMonth();

        $this->info('Procesando '.count($rows)." registros...\n");

        DB::transaction(function () use ($rows, $prices) {
            $bar = $this->output->createProgressBar(count($rows));
            $bar->start();

            foreach ($rows as $row) {
                $this->processRow($row, $prices);
                $bar->advance();
            }

            $bar->finish();
        });

        $this->newLine(2);
        $this->printSkips();
        $this->printSummary();

        return 0;
    }

    private function getPricesByMonth(): array
    {
        $prices = [];

        MonthlyBills::where('year', self::YEAR)
            ->get(['month', 'water_price_per_m3'])
            ->each(function ($bill) use (&$prices) {
                $prices[(int) $bill->month] = (float) $bill->water_price_per_m3;
            });

        return $prices;
    }

    private function processRow(array $row, array $prices): void
    {
        $dpto = (int) $row['dpto'];

        $departament = Departament::where('number', 'dpt-'.$dpto)->first();
        if (! $departament) {
            $this->stats['depto_not_found'][] = 'dpt-'.$dpto;

            return;
        }

        $previous = null;

        for ($month = self::FIRST_MONTH; $month <= self::LAST_MONTH; $month++) {
            $value = $row['lecturas'][(string) $month] ?? null;

            if ($value === null) {
                $this->stats['no_data'][] = $departament->number.'/'.$month;

                continue;
            }

            $value = (float) $value;

            if ($previous !== null && $value < $previous) {
                $this->stats['decreasing'][] = $departament->number.'/'.$month.' ('.$previous.' -> '.$value.')';
                $previous = $value;

                continue;
            }

            $previous = $value;

            $exists = WaterReading::where('departament_id', $departament->id)
                ->where('month', $month)
                ->where('year', self::YEAR)
                ->exists();

            if ($exists) {
                $this->stats['already_exists'][] = $departament->number.'/'.$month;

                continue;
            }

            $previousReading = $month === 1 ? 0 : (float) ($row['lecturas'][(string) ($month - 1)] ?? $previous);
            $price = $prices[$month] ?? self::FALLBACK_M3_PRICE;
            $consumption = max(0, $value - $previousReading);

            $reading = WaterReading::create([
                'departament_id' => $departament->id,
                'month' => $month,
                'year' => self::YEAR,
                'previous_reading' => $previousReading,
                'current_reading' => $value,
                'm3_price' => $price,
                'amount' => round($consumption * $price, 2),
                'is_initial' => $month === 1 ? 1 : 0,
            ]);

            $this->stats['created']++;

            $linked = Quota::where('departament_id', $departament->id)
                ->where('month', $month)
                ->whereYear('due_date', self::YEAR)
                ->whereNull('water_reading_id')
                ->update([
                    'water_reading_id' => $reading->id,
                    'water_amount' => 0,
                ]);

            if ($linked > 0) {
                $this->stats['quotas_linked'] += $linked;
            } else {
                $this->stats['quotas_without_quota']++;
            }
        }
    }

    private function printSkips(): void
    {
        $decreasing = $this->stats['decreasing'];
        if (! empty($decreasing)) {
            $this->warn('Lecturas decrecientes (no importadas):');
            foreach ($decreasing as $item) {
                $this->line('  - '.$item);
            }
        }

        $noData = $this->stats['no_data'];
        if (! empty($noData)) {
            $this->warn('Sin datos en el Excel (no importadas):');
            foreach ($noData as $item) {
                $this->line('  - '.$item);
            }
        }

        $notFound = $this->stats['depto_not_found'];
        if (! empty($notFound)) {
            $this->warn('Departamentos no encontrados en DB:');
            foreach ($notFound as $item) {
                $this->line('  - '.$item);
            }
        }

        $exists = $this->stats['already_exists'];
        if (! empty($exists)) {
            $this->info('Ya existentes en DB (no tocadas):');
            foreach ($exists as $item) {
                $this->line('  - '.$item);
            }
        }
    }

    private function printSummary(): void
    {
        $this->table(
            ['Metrica', 'Valor'],
            [
                ['WaterReadings creados', $this->stats['created']],
                ['Lecturas decrecientes (skip)', count($this->stats['decreasing'])],
                ['Sin datos en Excel (skip)', count($this->stats['no_data'])],
                ['Departamentos no encontrados (skip)', count($this->stats['depto_not_found'])],
                ['Ya existentes (skip)', count($this->stats['already_exists'])],
                ['Cuotas vinculadas', $this->stats['quotas_linked']],
                ['WaterReadings sin cuota (disponibles para MonthlyQuota)', $this->stats['quotas_without_quota']],
            ]
        );
    }
}
