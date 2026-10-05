<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\Quota;
use App\Services\MonthlyQuotaService;
use Illuminate\Console\Command;

class BackfillQuotasTenantPivot extends Command
{
    protected $signature = 'backfill:quotas-tenant-pivot
                            {--dry-run : Solo muestra lo que se actualizaria, sin escribir}';

    protected $description = 'Asigna peoples_x_departments_id a las cuotas pendientes de departamentos con tenant_pays_quota activo';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $departaments = Departament::where('tenant_pays_quota', true)->get();

        if ($departaments->isEmpty()) {
            $this->info('No hay departamentos con tenant_pays_quota activo.');

            return 0;
        }

        $updated = 0;
        $skipped = [];

        foreach ($departaments as $departament) {
            $quotaIds = Quota::where('departament_id', $departament->id)
                ->where('status', 1)
                ->whereNull('peoples_x_departments_id')
                ->pluck('id');

            if ($quotaIds->isEmpty()) {
                continue;
            }

            $pivotId = MonthlyQuotaService::findActiveTenantPivotId($departament->id);

            if ($pivotId === null) {
                $skipped[] = $departament->number;

                continue;
            }

            $count = $quotaIds->count();

            if (! $dryRun) {
                Quota::whereIn('id', $quotaIds)
                    ->update(['peoples_x_departments_id' => $pivotId]);
            }

            $updated += $count;

            $this->line(sprintf(
                '  %s -> pivot %d (%d cuota%s)%s',
                $departament->number,
                $pivotId,
                $count,
                $count === 1 ? '' : 's',
                $dryRun ? ' [dry-run]' : ''
            ));
        }

        if ($skipped !== []) {
            $this->warn('Departamentos sin inquilino activo, se omiten: '.implode(', ', $skipped));
        }

        $this->info($dryRun
            ? "Dry-run: $updated cuota(s) se actualizarian."
            : "Se actualizaron $updated cuota(s).");

        return 0;
    }
}
