<?php

namespace App\Services;

use App\Http\Controllers\Api\BookingController;
use App\Models\Booking;
use App\Models\User;

/**
 * Cancelación en cascada de extensiones (type 4) al cancelar su reserva original.
 *
 * Reglas por estado de la extensión:
 * - Pagada (monto > 0, pay status 1/2/4) y aún no completada → Pend. devolución
 *   (reserva status 6 + pay status 6): el admin registra el reembolso manualmente.
 * - Completada (status 4) o sin pagar → cancelación directa (status 0), sin reembolso.
 * - Ya cancelada (0) o ya en devolución (6) → se omite.
 */
final class BookingExtensionCancellationService
{
    /**
     * Cancela las extensiones activas de la reserva dada.
     * Debe ejecutarse dentro de la transacción del llamador.
     */
    public static function cancelExtensionsFor(Booking $parent, string $motive): void
    {
        if ((int) $parent->type === 4) {
            return;
        }

        $extensions = Booking::query()
            ->extensionOf($parent)
            ->with('pay')
            ->get();

        foreach ($extensions as $extension) {
            self::cancelExtension($extension, $motive);
        }
    }

    /**
     * Aplica las reglas de cancelación a una extensión individual.
     * Idempotente: omite extensiones ya canceladas o ya en devolución.
     */
    public static function cancelExtension(Booking $extension, string $motive): void
    {
        $extension->refresh();

        if (in_array((int) $extension->status, [
            Booking::STATUS_CANCELLED,
            Booking::STATUS_PENDING_DEVO,
        ])) {
            return;
        }

        $extension->loadMissing('pay');

        $isPaidNotCompleted = (float) $extension->amount > 0
            && (int) $extension->status !== Booking::STATUS_COMPLETED
            && $extension->pay !== null
            && in_array((int) $extension->pay->status, [1, 2, 4]);

        if ($isPaidNotCompleted) {
            $extension->update([
                'status' => Booking::STATUS_PENDING_DEVO,
                'motive' => $motive,
                'kind' => 'cancellation',
            ]);
            $extension->pay->update(['status' => 6]);
        } else {
            $extension->update([
                'status' => Booking::STATUS_CANCELLED,
                'motive' => $motive,
            ]);
        }

        self::notify($extension);
    }

    private static function notify(Booking $extension): void
    {
        BookingController::cancelReserveNotification([
            'admin' => User::find(1),
            'client' => User::find($extension->user_id),
        ], $extension);
    }
}
