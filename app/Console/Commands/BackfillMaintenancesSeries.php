<?php

namespace App\Console\Commands;

use App\Models\Maintenance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BackfillMaintenancesSeries extends Command
{
    protected $signature = 'backfill:maintenances-series
                            {--dry-run : Solo muestra los grupos que se generarian, sin escribir}';

    protected $description = 'Asigna series_id a los mantenimientos multipdia existentes agrupando filas de dias consecutivos';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = Maintenance::whereNull('series_id')
            ->orderBy('comun_area_id')
            ->orderBy('time_from')
            ->orderBy('time_to')
            ->orderBy('title')
            ->orderBy('date')
            ->get(['id', 'comun_area_id', 'title', 'date', 'time_from', 'time_to']);

        if ($rows->isEmpty()) {
            $this->info('No hay mantenimientos sin series_id.');

            return 0;
        }

        $groups = $this->groupConsecutiveDays($rows);

        if ($groups === []) {
            $this->info('No se detectaron series multipdia.');

            return 0;
        }

        foreach ($groups as $group) {
            $seriesId = (string) Str::uuid();
            $ids = array_map(fn (Maintenance $m) => $m->id, $group);

            if (! $dryRun) {
                Maintenance::whereIn('id', $ids)->update(['series_id' => $seriesId]);
            }

            $this->line(sprintf(
                '  %s | %s -> %s | %d fila(s) %s [%s..%s]%s',
                $group[0]->comun_area_id,
                $group[0]->title,
                $this->formatRange($group),
                count($group),
                $group[0]->date,
                $group[count($group) - 1]->date,
                $dryRun ? ' [dry-run]' : ''
            ));
        }

        $total = array_sum(array_map('count', $groups));

        $this->info($dryRun
            ? "Dry-run: $total fila(s) en ".count($groups).' serie(s) se agruparian.'
            : "Se agruparon $total fila(s) en ".count($groups).' serie(s).');

        return 0;
    }

    /**
     * Agrupa filas del mismo area, titulo y horario cuyos dias son consecutivos.
     *
     * El titulo se deriva solo del nombre del area, asi que dos mantenimientos
     * distintos podrian coincidir en area+horario. Exigir que los dias sean
     * correlativos es lo que evita fusionarlos: cada serie creada por store()
     * tiene dias seguidos, y dos mantenimientos separados por dias libres
     * quedan en grupos distintos.
     *
     * @param  Collection<int,Maintenance>  $rows
     * @return array<int,array<int,Maintenance>>
     */
    private function groupConsecutiveDays($rows): array
    {
        $groups = [];
        $current = null;

        foreach ($rows as $row) {
            if ($current !== null && $this->isNextDayOf(end($current), $row)) {
                $current[] = $row;

                continue;
            }

            if ($current !== null) {
                $this->flush($groups, $current);
            }

            $current = [$row];
        }

        $this->flush($groups, $current);

        return $groups;
    }

    /**
     * Solo un grupo de 2+ filas se considera serie: una fila sola es un
     * mantenimiento de un dia y basta con dejarla sin series_id.
     *
     * @param  array<int,array<int,Maintenance>>  $groups
     * @param  array<int,Maintenance>|null  $current
     */
    private function flush(array &$groups, ?array $current): void
    {
        if ($current !== null && count($current) > 1) {
            $groups[] = $current;
        }
    }

    private function isNextDayOf(Maintenance $previous, Maintenance $row): bool
    {
        if ($previous->comun_area_id !== $row->comun_area_id) {
            return false;
        }

        if ($previous->title !== $row->title) {
            return false;
        }

        if ($previous->time_from !== $row->time_from || $previous->time_to !== $row->time_to) {
            return false;
        }

        return Carbon::parse($previous->date)->addDay()->isSameDay(Carbon::parse($row->date));
    }

    /**
     * @param  array<int,Maintenance>  $group
     */
    private function formatRange(array $group): string
    {
        $first = $group[0];
        $last = $group[count($group) - 1];

        if ($first->time_from && $first->time_to) {
            return substr((string) $first->time_from, 0, 5).' - '.substr((string) $first->time_to, 0, 5);
        }

        return $first->date === $last->date ? 'todo el dia' : 'todo el dia (multidia)';
    }
}
