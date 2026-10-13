<?php

namespace App\Services;

use App\Http\Controllers\Api\PayController;
use App\Models\Quota;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reglas de elegibilidad para el pago de cuotas de mantenimiento por parte de un
 * cliente (propietario o inquilino responsable).
 *
 * Los administradores quedan exentos: el registro de pagos de escritorio
 * (registerPay.vue) existe justamente para saldar morosidad, asi que el orden
 * de meses no debe aplicarse a esa via.
 *
 * @see PayController::storePay
 */
class QuotaPaymentEligibilityService
{
    /**
     * @param  array<int,int>  $quotaIds
     * @return array{code:int,error:string}|null null cuando el pago es valido
     */
    public function check(int $userId, array $quotaIds): ?array
    {
        $quotaIds = array_values(array_unique(array_map('intval', $quotaIds)));

        if ($quotaIds === []) {
            return ['code' => 422, 'error' => 'No se recibieron cuotas para pagar.'];
        }

        // Una sola consulta resuelve existencia y pertenencia: si el conteo no
        // cuadra, o falta la cuota o pertenece a otro usuario.
        $requested = Quota::visibleToUser($userId)
            ->whereIn('id', $quotaIds)
            ->get();

        if ($requested->count() !== count($quotaIds)) {
            return [
                'code' => 403,
                'error' => 'Una de las cuotas indicadas no existe o no te corresponde.',
            ];
        }

        if ($denied = $this->denyNonPending($requested)) {
            return $denied;
        }

        return $this->denyIfOlderDebtExists($userId, $requested);
    }

    /**
     * Solo se pueden enviar cuotas en status 1 (Pago pendiente). Sin esto, un
     * cliente podria reenviar el pago de una cuota ya pagada o ya en revision
     * falseando quota_ids y generar un Pay duplicado.
     *
     * @param  Collection<int,Quota>  $requested
     * @return array{code:int,error:string}|null
     */
    private function denyNonPending(Collection $requested): ?array
    {
        $notPending = $requested->filter(fn (Quota $quota) => (int) $quota->status !== 1);

        if ($notPending->isEmpty()) {
            return null;
        }

        $quota = $notPending->first();

        $reason = match ((int) $quota->status) {
            2 => 'ya tiene un pago en revisión.',
            3 => 'ya fue pagada.',
            default => 'no está disponible para pago.',
        };

        return [
            'code' => 422,
            'error' => 'La cuota de '.$quota->periodLabel().' '.$reason,
        ];
    }

    /**
     * Orden de pago: no se puede pagar un mes teniendo deuda de meses anteriores.
     *
     * @param  Collection<int,Quota>  $requested
     * @return array{code:int,error:string}|null
     */
    private function denyIfOlderDebtExists(int $userId, Collection $requested): ?array
    {
        $older = $this->findOlderDebt($userId, $requested);

        if ($older === null) {
            return null;
        }

        return [
            'code' => 409,
            'error' => $this->olderDebtMessage($older),
        ];
    }

    /**
     * Cuota pendiente/por devolucion mas antigua que bloquea el pago de las
     * cuotas indicadas. Devuelve null cuando no hay bloqueo.
     *
     * La comparacion usa due_date y no month porque las filas month = 0 (Cuota
     * Periodo 2025) se ordenarian como enero. Si el pago incluye una cuota
     * month = 0 la regla no aplica: ese cargo anual no participa del orden.
     *
     * @param  Collection<int,Quota>  $requested
     */
    private function findOlderDebt(int $userId, Collection $requested): ?Quota
    {
        $monthly = $requested->filter(fn (Quota $quota) => $quota->isMonthly());

        if ($monthly->isEmpty()) {
            return null;
        }

        $boundary = $monthly->min(fn (Quota $quota) => $quota->due_date);

        return Quota::visibleToUser($userId)
            ->monthly()
            ->outstanding()
            ->whereNotIn('id', $requested->modelKeys())
            ->where('due_date', '<', $boundary)
            ->orderBy('due_date')
            ->first();
    }

    private function olderDebtMessage(Quota $quota): string
    {
        return 'Primero debes pagar la cuota de '.$quota->periodLabel()
            .'. No puedes pagar meses posteriores mientras tengas cuotas anteriores pendientes.';
    }

    /**
     * Verificacion de elegibilidad para el formulario de pago (payForm.vue).
     *
     * check() devuelve un fallo HTTP para rechazar el registro; este metodo
     * devuelve un resultado estructurado para que el frontend pueda mostrar el
     * motivo y ofrecer ir a la cuota bloqueante antes de pedirle al usuario que
     * complete el formulario.
     *
     * @param  array<int,int|string>  $quotaIds
     * @return array{payable:bool,reason:?string,blocked_by:?array{label:string,due_date:string,quota_id:int},message:?string}
     */
    public function checkPaymentReadiness(int $userId, array $quotaIds): array
    {
        $quotaIds = array_values(array_unique(array_map('intval', $quotaIds)));
        $quotaIds = array_filter($quotaIds);

        if ($quotaIds === []) {
            return $this->readinessFail('no_quotas', 'No se recibieron cuotas para pagar.');
        }

        $requested = Quota::visibleToUser($userId)
            ->whereIn('id', $quotaIds)
            ->get();

        if ($requested->count() !== count($quotaIds)) {
            return $this->readinessFail('not_visible', 'Una de las cuotas indicadas no existe o no te corresponde.');
        }

        $notPending = $requested->filter(fn (Quota $quota) => (int) $quota->status !== 1);

        if ($notPending->isNotEmpty()) {
            $quota = $notPending->first();

            [$reason, $message] = match ((int) $quota->status) {
                2 => ['pending_review', 'La cuota de '.$quota->periodLabel().' ya tiene un pago en revisión.'],
                3 => ['already_paid', 'La cuota de '.$quota->periodLabel().' ya fue pagada.'],
                default => ['unavailable', 'La cuota de '.$quota->periodLabel().' no está disponible para pago.'],
            };

            return $this->readinessFail($reason, $message);
        }

        $older = $this->findOlderDebt($userId, $requested);

        if ($older === null) {
            return ['payable' => true, 'reason' => null, 'blocked_by' => null, 'message' => null];
        }

        return [
            'payable' => false,
            'reason' => 'older_debt',
            'blocked_by' => [
                'label' => $older->periodLabel(),
                'due_date' => $older->due_date instanceof CarbonInterface
                    ? $older->due_date->toDateString()
                    : (string) $older->due_date,
                'quota_id' => (int) $older->id,
            ],
            'message' => $this->olderDebtMessage($older),
        ];
    }

    /**
     * @return array{payable:bool,reason:string,blocked_by:null,message:string}
     */
    private function readinessFail(string $reason, string $message): array
    {
        return ['payable' => false, 'reason' => $reason, 'blocked_by' => null, 'message' => $message];
    }
}
