<?php

namespace App\Console\Commands;

use App\Models\Quota;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckUserMorosos extends Command
{
    protected $signature = 'app:check-user-morosos';

    protected $description = 'Marca como morosos (status=2) a usuarios con cuotas vencidas en 2 o mas meses distintos';

    private array $stats = [
        'overdue_quotas_found' => 0,
        'users_marked_moroso' => 0,
        'users_restored_al_dia' => 0,
        'already_moroso' => 0,
        'inactive_skipped' => 0,
    ];

    public function handle(): int
    {
        $overdueQuotas = $this->getOverduePendingQuotas();
        $this->stats['overdue_quotas_found'] = $overdueQuotas->count();

        // Users qualifying as moroso: unpaid overdue quotas spanning >= 2 distinct months
        $qualifyingUserIds = $this->getQualifyingUserIds($overdueQuotas);

        $this->markMorosos($qualifyingUserIds);
        $this->restoreAlDia($qualifyingUserIds);
        $this->printSummary();

        return 0;
    }

    private function getOverduePendingQuotas()
    {
        return Quota::pastDue()
            ->with('departament.owner', 'responsiblePivot.user')
            ->get();
    }

    /**
     * Distinct responsible user ids that qualify as moroso
     * (their overdue quotas span >= 2 distinct months).
     *
     * @return array<int>
     */
    private function getQualifyingUserIds($overdueQuotas): array
    {
        return $overdueQuotas
            ->groupBy(fn ($quota) => $this->getResponsibleUserId($quota))
            ->filter(fn ($quotas, $userId) => $userId && Quota::hasMorosoDebt($quotas))
            ->keys()
            ->values()
            ->all();
    }

    private function getResponsibleUserId(Quota $quota): ?int
    {
        return $quota->responsiblePivot?->user?->id ?? $quota->departament?->owner?->id;
    }

    private function markMorosos(array $qualifyingUserIds): void
    {
        if (empty($qualifyingUserIds)) {
            return;
        }

        $users = User::whereIn('id', $qualifyingUserIds)->get(['id', 'status']);

        $markedIds = [];
        foreach ($users as $user) {
            if ((int) $user->status === 3) {
                $this->stats['inactive_skipped']++;

                continue;
            }

            if ((int) $user->status === 2) {
                $this->stats['already_moroso']++;

                continue;
            }

            $markedIds[] = $user->id;
        }

        if (! empty($markedIds)) {
            $updated = User::whereIn('id', $markedIds)->update(['status' => 2, 'parentesco' => 'moroso']);
            $this->stats['users_marked_moroso'] = $updated;

            Log::info('[CheckUserMorosos] Marcados como morosos (>=2 meses distintos)', [
                'user_ids' => $markedIds,
            ]);
        }
    }

    private function restoreAlDia(array $qualifyingUserIds): void
    {
        $allMorosos = User::where('status', 2)->pluck('id')->toArray();

        if (empty($allMorosos)) {
            return;
        }

        // A moroso is restored when their overdue quotas no longer span >= 2 distinct months
        $toRestore = array_values(array_diff($allMorosos, $qualifyingUserIds));

        if (empty($toRestore)) {
            return;
        }

        $restored = User::whereIn('id', $toRestore)->update(['status' => 1]);
        $this->stats['users_restored_al_dia'] = $restored;

        if ($restored > 0) {
            Log::info('[CheckUserMorosos] Restaurados a Pagos al día', [
                'user_ids' => $toRestore,
            ]);
        }
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Cuotas vencidas encontradas (status 1/4, due_date < hoy)', $this->stats['overdue_quotas_found']],
                ['Usuarios marcados como morosos (>=2 meses distintos)', $this->stats['users_marked_moroso']],
                ['Usuarios restaurados a Pagos al día', $this->stats['users_restored_al_dia']],
                ['Omitidos (ya estaban morosos)', $this->stats['already_moroso']],
                ['Omitidos (inactivos, status=3)', $this->stats['inactive_skipped']],
            ]
        );
    }
}
