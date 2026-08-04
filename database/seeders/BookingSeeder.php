<?php

namespace Database\Seeders;

use App\Models\ComunArea;
use App\Models\Departament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingSeeder extends Seeder
{
    private const STATUS_PENDING_PAY = 1;

    private const STATUS_SUCCESS = 3;

    private const TYPE_SHARED = 1;

    private const TYPE_EXCLUSIVE = 2;

    private const AREA_ALIASES = ['Cine' => 'Sala de Cine'];

    private array $stats = [
        'created' => 0,
        'skipped' => 0,
        'area_not_found' => [],
        'departament_not_found' => [],
    ];

    public function run(): void
    {
        $path = base_path('referencias/import_reservas.csv');
        if (! file_exists($path)) {
            $this->command->error("Archivo $path no encontrado.");

            return;
        }

        $rows = $this->parseCsv($path);
        $this->command->info('Filas leidas: '.count($rows));

        DB::transaction(function () use ($rows) {
            $bar = $this->command->getOutput()->createProgressBar(count($rows));
            $bar->start();

            foreach ($rows as $row) {
                $this->processRow($row);
                $bar->advance();
            }

            $bar->finish();
        });

        $this->command->newLine(2);
        $this->printSkips();
        $this->printSummary();
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];

        fgetcsv($handle, 0, ',', '"', '"');
        $i = 4;
        while (($line = fgetcsv($handle, 0, ',', '"', '"')) !== false) {
            if (count($line) < 1 || $line[0] === null) {
                continue;
            }

            $row = str_getcsv($line[0], ',', '"', '"');
            if (count($row) < 12) {
                continue;
            }

            $rows[] = [
                'id' => (int) $i,
                'fecha' => $row[1],
                'horario_inicio' => $row[2],
                'horario_fin' => $row[3],
                'username' => $row[4],
                'nombre_usuario' => $row[5],
                'numero_departamento' => $row[6],
                'area' => $row[7],
                'estado' => $row[8],
                'es_reserva_exclusiva' => $row[9],
                'created_at' => $this->normalizeTimestamp($row[10]),
                'updated_at' => $this->normalizeTimestamp($row[11]),
            ];
            $i++;
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeTimestamp(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $parts = explode(' ', $value);
        $time = $parts[1] ?? '';

        return $parts[0].' '.substr($time, 0, 8);
    }

    private function processRow(array $row): void
    {
        $area = ComunArea::where('name', $row['area'])->first()
            ?? ComunArea::where('name', self::AREA_ALIASES[$row['area']] ?? '__none__')->first();
        if (! $area) {
            $this->stats['area_not_found'][] = $row['area'];
            $this->stats['skipped']++;

            return;
        }

        $departament = Departament::where('number', 'dpt-'.$row['numero_departamento'])->first();
        if (! $departament) {
            $this->stats['departament_not_found'][] = 'dpt-'.$row['numero_departamento'];
            $this->stats['skipped']++;

            return;
        }

        if (DB::table('bookings')->where('id', $row['id'])->exists()) {
            $this->stats['skipped']++;

            return;
        }

        $isExclusive = $row['es_reserva_exclusiva'] === 't';

        DB::table('bookings')->insert([
            'id' => $row['id'],
            'user_id' => $departament->user_id,
            'departament_id' => $departament->id,
            'comun_area_id' => $area->id,
            'booking_number' => $departament->user_id.'00'.$row['id'],
            'date' => $row['fecha'],
            'time_from' => $row['horario_inicio'],
            'time_to' => $row['horario_fin'],
            'amount' => $isExclusive ? $area->price : 0,
            'type' => $isExclusive ? self::TYPE_EXCLUSIVE : self::TYPE_SHARED,
            'status' => $row['estado'] === 'pendiente_pago' ? self::STATUS_PENDING_PAY : self::STATUS_SUCCESS,
            'is_exclusive' => $isExclusive ? 1 : 0,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ]);

        $this->stats['created']++;
    }

    private function printSkips(): void
    {
        $areas = $this->stats['area_not_found'];
        if (! empty($areas)) {
            $this->command->warn('Areas comunes no encontradas (skip):');
            foreach (array_unique($areas) as $item) {
                $this->command->line('  - '.$item);
            }
        }

        $departaments = $this->stats['departament_not_found'];
        if (! empty($departaments)) {
            $this->command->warn('Departamentos no encontrados en DB (skip):');
            foreach (array_unique($departaments) as $item) {
                $this->command->line('  - '.$item);
            }
        }
    }

    private function printSummary(): void
    {
        $this->command->table(
            ['Metrica', 'Valor'],
            [
                ['Bookings creados', $this->stats['created']],
                ['Omitidos', $this->stats['skipped']],
                ['Areas no encontradas', count(array_unique($this->stats['area_not_found']))],
                ['Departamentos no encontrados', count(array_unique($this->stats['departament_not_found']))],
            ]
        );
    }
}
