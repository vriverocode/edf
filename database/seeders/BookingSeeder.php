<?php

namespace Database\Seeders;

use App\Models\ComunArea;
use App\Models\Departament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BookingSeeder extends Seeder
{
    private const STATUS_PENDING_PAY = 1;

    private const STATUS_SUCCESS = 3;

    private const TYPE_SHARED = 1;

    private const TYPE_EXCLUSIVE = 2;

    private const AREA_ALIASES = [
        'Cine' => 'Sala de Cine',
        'Arcade' => 'Zona gamer',
    ];

    private const REPORT_PATH = 'referencias/import_reservas_new.md';

    private array $stats = [
        'read' => 0,
        'created' => 0,
        'skipped' => 0,
        'duplicated' => [],
        'area_not_found' => [],
        'departament_not_found' => [],
        'user_not_found' => [],
    ];

    private array $mapping = [];

    public function run(): void
    {
        $path = base_path('reservas_new.csv');
        if (! file_exists($path)) {
            $this->command->error("Archivo $path no encontrado.");

            return;
        }

        $rows = $this->parseCsv($path);
        $this->stats['read'] = count($rows);
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
        $this->writeReport();
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];

        $header = fgetcsv($handle);
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < 12) {
                continue;
            }

            $rows[] = [
                'id' => (int) $line[0],
                'fecha' => $line[1],
                'horario_inicio' => $line[2],
                'horario_fin' => $line[3],
                'username' => $line[4],
                'nombre_usuario' => $line[5],
                'numero_departamento' => $line[6],
                'area' => $line[7],
                'estado' => $line[8],
                'es_reserva_exclusiva' => $line[9],
                'created_at' => $this->normalizeTimestamp($line[10]),
                'updated_at' => $this->normalizeTimestamp($line[11]),
            ];
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

        if (! $departament->user_id) {
            $this->stats['user_not_found'][] = 'dpt-'.$row['numero_departamento'].' (reserva #'.$row['id'].')';
            $this->stats['skipped']++;

            return;
        }

        $duplicate = DB::table('bookings')
            ->where('departament_id', $departament->id)
            ->where('comun_area_id', $area->id)
            ->where('date', $row['fecha'])
            ->where('time_from', $row['horario_inicio'])
            ->exists();

        if ($duplicate) {
            $this->stats['duplicated'][] = $row['id'];
            $this->stats['skipped']++;

            return;
        }

        $this->mapping[$row['id']] = [
            'csv_user' => $row['username'],
            'csv_name' => $row['nombre_usuario'],
            'departament' => $departament->number,
            'user_id' => $departament->user_id,
        ];

        $isExclusive = $row['es_reserva_exclusiva'] === 't';
        $isPendingPay = $row['estado'] === 'pendiente_pago';

        DB::table('bookings')->insert([
            'user_id' => $departament->user_id,
            'departament_id' => $departament->id,
            'comun_area_id' => $area->id,
            'booking_number' => $departament->user_id.'00'.$row['id'],
            'date' => $row['fecha'],
            'time_from' => $row['horario_inicio'],
            'time_to' => $row['horario_fin'],
            'amount' => $isPendingPay ? $area->price : 0,
            'type' => $isExclusive ? self::TYPE_EXCLUSIVE : self::TYPE_SHARED,
            'status' => $isPendingPay ? self::STATUS_PENDING_PAY : self::STATUS_SUCCESS,
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

        $users = $this->stats['user_not_found'];
        if (! empty($users)) {
            $this->command->warn('Reservas omitidas por usuario inexistente (depto sin user_id):');
            foreach ($users as $item) {
                $this->command->line('  - '.$item);
            }
        }

        $duplicated = $this->stats['duplicated'];
        if (! empty($duplicated)) {
            $this->command->info('Duplicadas en DB (skip):');
            foreach ($duplicated as $item) {
                $this->command->line('  - '.$item);
            }
        }
    }

    private function printSummary(): void
    {
        $this->command->table(
            ['Metrica', 'Valor'],
            [
                ['Filas leidas', $this->stats['read']],
                ['Bookings creados', $this->stats['created']],
                ['Omitidos', $this->stats['skipped']],
                ['Duplicados', count($this->stats['duplicated'])],
                ['Areas no encontradas', count(array_unique($this->stats['area_not_found']))],
                ['Departamentos no encontrados', count(array_unique($this->stats['departament_not_found']))],
                ['Sin usuario (depto sin user_id)', count($this->stats['user_not_found'])],
            ]
        );
    }

    private function writeReport(): void
    {
        $path = base_path(self::REPORT_PATH);

        $lines = [
            '# Reporte de importación de reservas',
            '',
            'Fuente: `reservas_new.csv` | Ejecutado: '.now()->format('Y-m-d H:i:s').' | Comando: `db:seed --class=BookingSeeder`',
            '',
            '## Resumen',
            '',
            '| Metrica | Valor |',
            '| --- | --- |',
            '| Filas leidas | '.$this->stats['read'].' |',
            '| Bookings creados | '.$this->stats['created'].' |',
            '| Omitidos | '.$this->stats['skipped'].' |',
            '| Duplicados | '.count($this->stats['duplicated']).' |',
            '| Areas no encontradas | '.count(array_unique($this->stats['area_not_found'])).' |',
            '| Departamentos no encontrados | '.count(array_unique($this->stats['departament_not_found'])).' |',
            '| Sin usuario (depto sin user_id) | '.count($this->stats['user_not_found']).' |',
            '',
            '## Mapeo usuario CSV -> dueño del departamento',
            '',
            'Los usuarios del CSV (`depa405@pacifik.com`, etc.) no existen en la BD. Se usó el `user_id` (dueño) de cada departamento según `bookings` del seeder.',
            '',
            '| Reserva CSV | Usuario CSV | Nombre CSV | Departamento | Usuario asignado (id) |',
            '| --- | --- | --- | --- | --- |',
        ];

        ksort($this->mapping);
        foreach ($this->mapping as $csvId => $map) {
            $lines[] = '| '.$csvId.' | '.$map['csv_user'].' | '.$map['csv_name'].' | '.$map['departament'].' | '.$map['user_id'].' |';
        }

        $lines[] = '';
        $lines[] = '## Sin usuario (no importadas)';
        $lines[] = '';
        if (empty($this->stats['user_not_found'])) {
            $lines[] = 'Ninguna: todos los departamentos tenían `user_id` asignado.';
        } else {
            foreach ($this->stats['user_not_found'] as $item) {
                $lines[] = '- '.$item;
            }
        }

        File::put($path, implode(PHP_EOL, $lines).PHP_EOL);
        $this->command->info('Reporte generado: '.$path);
    }
}
