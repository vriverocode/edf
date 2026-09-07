<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Mail\PayClaims;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Pay;
use App\Models\Quota;
use App\Models\Refund;
use App\Models\Rol;
use App\Models\Sequence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillInvoiceService;
use App\Services\BookingPendingPayNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PayController extends Controller
{
    private BillInvoiceService $billInvoiceService;

    public function __construct(BillInvoiceService $billInvoiceService)
    {
        $this->billInvoiceService = $billInvoiceService;
    }

    public function getPayById($id)
    {
        $pay = Pay::with([
            'booking.comunArea',
            'user',
            'quotas.departament',
            'payMethod',
        ])->find($id);

        if (! $pay) {
            return $this->returnFail(404, ['messageType' => 'negative', 'message' => 'Pago no encontrado']);
        }

        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN]) && $pay->user_id !== $user->id) {
            return $this->returnFail(403, 'No autorizado');
        }

        if ((int) $pay->type === 1) {
            $consolidated = $pay->quotas;
            if ($consolidated->isEmpty()) {
                $quotaIds = $pay->consolidatedQuotaIds();
                $consolidated = $quotaIds !== []
                    ? Quota::query()->whereIn('id', $quotaIds)->with('departament')->get()
                    : collect([]);
            }
            $pay->setRelation('consolidated_quotas', $consolidated);
        } else {
            $pay->setRelation('consolidated_quotas', collect([]));
        }

        return $this->returnSuccess(200, $pay);
    }

    public function getPayQuotas($id)
    {
        $pay = Pay::with(['booking.comunArea', 'user', 'payMethod'])->find($id);

        return $this->returnSuccess(200, $pay);
    }

    public function getPaysByUser(Request $request)
    {
        $pays = Pay::with(['booking.comunArea', 'quotas.departament', 'user', 'payMethod']);

        // Filtrar por usuario si no es admin
        if (! in_array($request->user()->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            $pays->where('user_id', $request->user()->id);
        }

        // Aplicar filtros
        $this->applyPaysFilter($pays, $request);

        if ($request->filled('paginate') && intval($request->paginate) > 0) {
            return $this->returnSuccess(200, $pays->paginate(intval($request->paginate)));
        }

        return $this->returnSuccess(200, $pays->get());
    }

    public function downloadBookingReceipt(Request $request, int $payId)
    {
        $pay = Pay::with([
            'booking.comunArea',
            'booking.departament',
            'user',
            'payMethod',
        ])->find($payId);

        if (! $pay) {
            return $this->returnFail(404, 'Pago no encontrado');
        }

        $user = request()->user();
        $isAdmin = in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN]);
        $isOwner = $pay->user_id === $user->id;
        if (! $isAdmin && ! $isOwner) {
            return $this->returnFail(403, 'No autorizado');
        }

        if ((int) $pay->status !== 2) {
            return $this->returnFail(403, 'El recibo solo está disponible para pagos exitosos');
        }

        $booking = $pay->booking;
        $pdf = Pdf::loadView('bills.booking-receipt', [
            'pay' => $pay,
            'booking' => $booking,
            'user' => $pay->user,
            'payMethod' => $pay->payMethod,
            'date' => $pay->pay_date ? Carbon::parse($pay->pay_date)->format('d/m/Y') : '—',
        ])->setPaper('letter')->setOption('isRemoteEnabled', true);

        $filename = 'recibo-reserva-'.($booking->booking_number ?? $pay->id).'.pdf';

        return $pdf->download($filename);
    }

    public function storePay(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        if ((int) $request->type === 2) {
            $bookingExists = Booking::where('id', $request->to_pay_id)->exists();
            if (! $bookingExists) {
                return $this->returnFail(404, 'La reserva que intentas pagar no existe o ha sido eliminada.');
            }

            $hasActivePay = Pay::query()
                ->where('booking_id', $request->to_pay_id)
                ->whereIn('status', [1, 2, 6])
                ->exists();

            if ($hasActivePay) {
                return $this->returnFail(409, ['messageType' => 'negative', 'message' => 'La reserva ya tiene un pago registrado']);
            }
        }

        $prefixPayId = ['s', 't', 'y', 'd'];

        $quotaIdsForPay = null;
        if ((int) $request->type === 1) {
            $syncIds = $this->setQuotaIds($request);
            $consolidatedIdsForStore = $this->normalizedConsolidatedIdsFromPayRequest($request);
            if ($consolidatedIdsForStore === null || $consolidatedIdsForStore === []) {
                $consolidatedIdsForStore = $syncIds;
            }
            $quotaIdsForPay = $consolidatedIdsForStore;
        }

        if ((int) $request->type === 1 && ! empty($quotaIdsForPay)) {
            $tenantQuotas = Quota::whereIn('id', $quotaIdsForPay)
                ->whereNotNull('peoples_x_departments_id')
                ->with('responsiblePivot')
                ->get();

            foreach ($tenantQuotas as $tq) {
                if ($tq->responsiblePivot?->user_id !== $request->user()->id) {
                    return $this->returnFail(403, 'Esta cuota está asignada a un inquilino. El propietario no puede realizar el pago.');
                }
            }
        }

        $authUser = $request->user();
        $user = $authUser;
        if ($request->has('user_id') && in_array($authUser->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            $user = User::find((int) $request->user_id);
            if (! $user) {
                return $this->returnFail(404, 'Usuario no encontrado');
            }
        }

        try {
            DB::beginTransaction();

            $payIdPrefix = $prefixPayId[$request->pay_method] ?? 'x';
            $bookingIdStr = (int) $request->type === 2 ? $request->to_pay_id : 'Q';

            $pay = Pay::create([
                'user_id' => $user->id,
                'booking_id' => $request->type == 2 ? $request->to_pay_id : null,
                'quota_id' => ! empty($quotaIdsForPay) ? $quotaIdsForPay[0] : null,
                'consolidated_ids' => $quotaIdsForPay,
                'amount' => $request->amount,
                'reference' => $request->reference ?? '000000',
                'pay_id' => $payIdPrefix.$bookingIdStr.'-'.rand(1000, 9999),
                'pay_date' => $request->pay_date ? date('Y-m-d', strtotime($request->pay_date)) : date('Y-m-d'),
                'type' => $request->type,
                'pay_method' => $request->pay_method,
                'status' => 1,
            ]);

            if ($request->type == 1 && ! empty($quotaIdsForPay)) {
                $pay->quotas()->sync($quotaIdsForPay);
            }

            $this->afterPayAction($pay);
            $this->uploadVaucher($pay, $request);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en storePay: '.$e->getMessage(), ['exception' => $e]);

            return $this->returnFail(500, 'Ocurrió un error al procesar el pago. Intente nuevamente.');
        }

        try {
            $this->sendNotification($pay);
        } catch (\Throwable $e) {
            Log::error('Error en notificación storePay: '.$e->getMessage());
        }

        return $this->returnSuccess(200, ['idPay' => $pay->id]);
    }

    public function updateStatus(Request $request, $payId)
    {
        $pay = Pay::with(['booking', 'payMethod', 'quotas'])->find($payId);

        if (! $payId) {
            return $this->returnFail(404, ['messageType' => 'negative', 'message' => 'Pago no encontrado']);
        }

        try {
            $pay->update([
                'status' => $request->status,
            ]);
            $this->payStatusActionByType($pay);
        } catch (Exception $th) {
            return $this->returnFail(500, ['messageType' => 'negative', 'message' => 'Error al cambiar estado de pago']);
        }

        return $this->returnSuccess(200, [
            'status' => $pay->status,
        ]);
    }

    public function validatePayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'integer', 'in:2,3'],
            'financial_account_id' => ['required_if:status,2', 'nullable', 'exists:financial_accounts,id'],
            'transaction_category_id' => ['required_if:status,2', 'nullable', 'exists:transaction_categories,id'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, ['messageType' => 'negative', 'message' => $validator->errors()->first()]);
        }

        $statusAction = (int) $request->status;

        DB::beginTransaction();
        try {
            $pay = Pay::query()->with(['booking', 'quotas'])->lockForUpdate()->find($id);

            if (! $pay) {
                DB::rollBack();

                return $this->returnFail(404, ['messageType' => 'negative', 'message' => 'Pago no encontrado']);
            }

            if ((int) $pay->status !== 1) {
                DB::rollBack();

                return $this->returnFail(409, ['messageType' => 'negative', 'message' => 'Este pago ya fue validado.']);
            }

            if ($statusAction === 3) {
                $pay->update(['status' => 3]);

                if ((int) $pay->type === 2 && $pay->booking_id) {
                    $this->cancelBooking($pay->booking_id);
                }

                DB::commit();

                if ((int) $pay->type === 2) {
                    $pay->refresh();
                    $this->sendReserveNotification($pay);
                }

                return $this->returnSuccess(200, [
                    'pay' => $pay,
                    'transaction' => null,
                ]);
            }

            /** Aprobación (status 2) — contabilización */
            if (Transaction::query()->where('pay_id', $pay->id)->exists()) {
                DB::rollBack();

                return $this->returnFail(409, ['messageType' => 'negative', 'message' => 'Ya existe una transacción contable asociada a este pago.']);
            }

            $pay->update(['status' => 2]);

            if ((int) $pay->type === 1) {
                $quotaIds = $pay->consolidatedQuotaIds();
                if ($quotaIds === []) {
                    DB::rollBack();

                    return $this->returnFail(422, [
                        'messageType' => 'negative',
                        'message' => 'No hay cuotas asociadas a este pago.',
                    ]);
                }

                $lockedQuotas = Quota::query()
                    ->whereIn('id', $quotaIds)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                sort($quotaIds);
                sort($lockedQuotas);
                if ($lockedQuotas !== $quotaIds) {
                    DB::rollBack();

                    return $this->returnFail(422, ['messageType' => 'negative', 'message' => 'Una o más cuotas del pago consolidado no existen.']);
                }

                /** En este sistema el estado pagado efectivo es 3 ("Exitoso") */
                Quota::query()->whereIn('id', $quotaIds)->update(['status' => 3]);

                $shouldSendInvoice = true;
            } elseif ((int) $pay->type === 2 && $pay->booking_id) {
                $this->approveBooking($pay);
            }

            $financialAccountId = (int) $request->financial_account_id;
            $financialAccount = FinancialAccount::query()
                ->whereKey($financialAccountId)
                ->lockForUpdate()
                ->first();

            if (! $financialAccount || (int) $financialAccount->status !== 1) {
                DB::rollBack();

                return $this->returnFail(422, ['messageType' => 'negative', 'message' => 'Cuenta financiera inválida o inactiva.']);
            }

            $amount = round((float) $pay->amount, 2);
            $financialAccount->current_balance = round((float) $financialAccount->current_balance + $amount, 2);
            $financialAccount->save();

            $transaction = Transaction::create([
                'financial_account_id' => $financialAccount->id,
                'transaction_category_id' => (int) $request->transaction_category_id,
                'pay_id' => $pay->id,
                'amount' => $amount,
                'date' => now()->toDateString(),
                'reference' => (string) $pay->reference,
                'description' => sprintf('Ingreso validación de pago #%s', $pay->pay_id ?? $pay->id),
                'status' => 1,
                'type' => 1,
            ]);

            DB::commit();

            $pay->refresh()->loadMissing(['financialTransaction', 'payMethod']);

            if ((int) $pay->type === 1 && isset($shouldSendInvoice) && $shouldSendInvoice) {
                $this->billInvoiceService->sendBillInvoicesForPay($pay);
            }

            if ((int) $pay->type === 2) {
                $this->sendReserveNotification($pay);
            }

            return $this->returnSuccess(200, [
                'pay' => $pay,
                'transaction' => $transaction,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error($e);

            return $this->returnFail(500, ['messageType' => 'negative', 'message' => $e->getMessage()]);
        }
    }

    public function refund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'pay_id' => ['required', 'integer', 'exists:pays,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
            'vaucher' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'bank_account_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        DB::beginTransaction();
        try {
            $pay = Pay::with('booking.comunArea')->lockForUpdate()->find($request->pay_id);

            if (! $pay || ! in_array((int) $pay->status, [2, 4, 6])) {
                DB::rollBack();

                return $this->returnFail(409, 'El pago no está en estado aprobado, reembolsado parcialmente o pendiente por devolución.');
            }

            $booking = $pay->booking;

            if (! $booking) {
                DB::rollBack();

                return $this->returnFail(409, 'El pago no tiene una reserva asociada.');
            }

            $hasBankAccount = BankAccount::where('user_id', $booking->user_id)
                ->where('status', true)
                ->exists();

            if (! $hasBankAccount) {
                DB::rollBack();

                $this->notifyMissingBankAccount($booking);

                return $this->returnFail(409, 'El usuario no tiene cuenta bancaria registrada. Se le notificó para que registre una.');
            }

            $bankAccount = BankAccount::find($request->bank_account_id);
            if (! $bankAccount || (int) $bankAccount->user_id !== (int) $booking->user_id) {
                DB::rollBack();

                return $this->returnFail(403, 'La cuenta bancaria no pertenece al usuario de la reserva.');
            }

            $isWarranty = $booking->kind === 'warranty';
            $kind = $isWarranty ? 'warranty' : 'cancellation';

            $amount = (float) $request->amount;
            $reason = $request->reason ?? 'Devolución por cancelación de reserva';

            if ($isWarranty) {
                $warrantyPrice = (float) ($booking->comunArea?->warranty_price ?? 0);
                $amount = $warrantyPrice > 0 ? $warrantyPrice : $amount;
                $reason = 'Devolución de garantía por reserva completada';
            }

            $alreadyRefunded = $pay->refunds()->sum('amount');
            $newTotal = $alreadyRefunded + $amount;

            $vaucherPath = $this->uploadRefundVaucher($request);

            Refund::create([
                'booking_id' => $request->booking_id,
                'pay_id' => $request->pay_id,
                'amount' => $amount,
                'reason' => $reason,
                'type' => 'booking',
                'kind' => $kind,
                'vaucher' => $vaucherPath,
                'bank_account_id' => $bankAccount->id,
                'bank_account_snapshot' => [
                    'name' => $bankAccount->name,
                    'data' => $bankAccount->data,
                ],
                'status' => 'completed',
            ]);

            $pay->update(['status' => $newTotal >= (float) $pay->amount ? 5 : 4]);

            if ((int) $booking->status === Booking::STATUS_PENDING_DEVO) {
                $booking->update([
                    'status' => $isWarranty ? Booking::STATUS_COMPLETED : Booking::STATUS_CANCELLED,
                ]);
            }

            DB::commit();

            SendNotificationJob::dispatch($pay->user_id, 'Devolución procesada', 'Se procesó la devolución de S/ '.number_format($amount, 2).' por la reserva #'.($booking->booking_number ?? ''), '/client/reserves/view/'.$booking->id, ['booking_id' => $booking->id, 'icon' => 'eva-undo-outline']);

            return $this->returnSuccess(200, ['message' => 'Devolución registrada correctamente.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error($e);

            return $this->returnFail(500, 'Error al procesar la devolución: '.$e->getMessage());
        }
    }

    public function getClaimSequence(Request $request)
    {
        $sequence = Sequence::where('name', 'claims')->first();

        return $this->returnSuccess(200, $sequence->value);
    }

    public function notifyMissingBankAccountRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $booking = Booking::find($request->booking_id);
        $this->notifyMissingBankAccount($booking);

        return $this->returnSuccess(200, 'Notificación enviada al usuario.');
    }

    private function normalizedConsolidatedIdsFromPayRequest(Request $request): ?array
    {
        $raw = $request->input('consolidated_ids');

        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map('intval', $raw))));
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded)
                ? array_values(array_unique(array_filter(array_map('intval', $decoded))))
                : null;
        }

        return null;
    }

    public function processCulqiPayment(Request $request)
    {
        $rules = [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'amount' => ['required', 'numeric'],
            // Campos de la reserva (según tu BookingController)
            'comun_area_id' => ['required', 'exists:comun_areas,id'],
            'date' => ['required', 'date'],
            'time_from' => ['required'],
            'time_to' => ['required'],
            'type' => ['required', 'numeric'], // 1: Cuota, 2: Reserva
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            // 2. Intentar el cobro en Culqi primero
            $response = Http::withToken(env('CULQI_SECRET_KEY'), 'Bearer')
                ->post('https://api.culqi.com/v2/charges', [
                    'amount' => (int) ($request->amount * 100),
                    'currency_code' => 'PEN',
                    'email' => $request->email,
                    'source_id' => $request->token,
                    'description' => 'Pago y creación de reserva automática',
                ]);

            $culqiData = $response->json();

            if ($response->successful()) {
                // 3. El pago es válido, ahora creamos los registros en la BD
                return DB::transaction(function () use ($request, $culqiData) {

                    // Creamos el registro del Pago (Pay)
                    $pay = Pay::create([
                        'user_id' => $request->user()->id,
                        'amount' => $request->amount,
                        'reference' => $culqiData['id'], // ID de transacción de Culqi
                        'pay_id' => 'CULQI-'.$request->comun_area_id.'-'.rand(100, 999),
                        'pay_date' => date('Y-m-d'),
                        'type' => 2, // Tipo Pago de Reserva
                        'pay_method' => 3, // Online
                        'status' => 1,  // Aprobado
                    ]);

                    // Ejecutamos tus acciones post-pago y notificaciones
                    $this->afterPayAction($pay);
                    $this->uploadVaucher($pay, $request);
                    $this->sendNotification($pay);

                    return $this->returnSuccess(200, [
                        'idPay' => $pay->id,
                    ]);
                });
            }

            return $this->returnFail(400, $culqiData['user_message'] ?? 'Pago rechazado');
        } catch (Exception $e) {
            return $this->returnFail(500, 'Error procesando la operación');
        }
    }

    public function claimsByPay(Request $request)
    {
        // $vaucherPath = $this->uploadVaucher($request, $request, 2);
        try {
            $rawSequence = intval($request->sequence);
            $formattedSequence = '0'.str_pad($rawSequence, 5, '0', STR_PAD_LEFT);
            $claimData = [
                'sequence' => $formattedSequence,
                'fullname' => $request->fullname,
                'doctype' => $request->doctype,
                'document' => $request->document,
                'floor' => $request->floor,
                'departament' => $request->department,
                'phone' => $request->phone,
                'email' => $request->email,
                'service_type' => $request->service_type,
                'service_number' => $request->service_number,
                'service_date' => $request->claim_date,
                'amount' => $request->amount,
                'claim_type' => $request->claim_type,
                'claim_date' => Carbon::parse($request->claim_date)->format('d/m/Y'),
                'claim_description' => $request->claim_description,
                'claim_vaucher' => $vaucherPath ?? '',
                'createDate' => Carbon::parse($request->create_date)->format('d/m/Y'),
            ];
            Mail::to($request->user()->email)->send(new PayClaims($claimData));
            Mail::to('test@edificiopacifik.com')->send(new PayClaims($claimData));

            Sequence::where('name', 'claims')->update([
                'value' => $rawSequence + 1,
            ]);
        } catch (Exception $th) {
            return $this->returnFail(500, $th->getMessage());
        }

        return $this->returnSuccess(200, $claimData);
    }

    private function afterPayAction($pay)
    {
        if ($pay->type == 2) {
            $this->updateBooking($pay->booking_id, $pay->user_id);
        }

        $this->updateQuota($pay->quotas);
        /** Cuotas de mantenimiento: el estado efectivo ("Exitoso") se aplica solo al aprobar vía validatePayment */
    }

    private function payStatusActionByType(Pay $pay)
    {
        if ($pay->type == 2) {
            $this->bookingActionByStatus($pay);
        } elseif ($pay->type == 1) {
            $this->quotaActionByStatus($pay);
        }
    }

    private function quotaActionByStatus($pay)
    {
        $newStatus = $pay->status == 0 ? 1 : 3;
        foreach ($pay->quotas as $quota) {
            $quota->update(['status' => $newStatus]);
        }

        // Enviar recibos por correo cuando se aprueba el pago (status 3)
        if ($newStatus === 3) {
            $this->billInvoiceService->sendBillInvoicesForPay($pay);
        }
    }

    private function bookingActionByStatus($pay)
    {
        $returnMessage = $pay->status == 0
            ? ['messageType' => 'negative', 'message' => 'Pago cancelado con exito']
            : ['messageType' => 'positive', 'message' => 'Pago aprobado con exito'];

        $pay->status == 0
            ? $this->cancelBooking($pay->booking_id)
            : $this->approveBooking($pay);

        $this->sendReserveNotification($pay);

        return $returnMessage;
    }

    private function cancelBooking($booking)
    {
        $CANCEL_VALUE = 0;
        $booking = Booking::find($booking);
        $booking->update([
            'status' => $CANCEL_VALUE,
        ]);
    }

    private function approveBooking($pay)
    {
        $APPROVE_VALUE = 3;
        $booking = Booking::find($pay->booking_id);
        $booking->update([
            'status' => $APPROVE_VALUE,
            'pay_id' => $pay->id,
        ]);
    }

    private function validateFieldsFromInput($inputs)
    {

        $rules = $inputs['pay_method'] != 3
            ? [
                'amount' => ['required', 'numeric'],
                'pay_date' => ['required', 'date'],
                'reference' => ['required', 'regex:/^[0-9 &]+$/i'],
                'pay_method' => ['required', 'numeric'],
                'vaucher' => ['required', 'file'],

            ]
            : [
                'amount' => ['required', 'numeric'],
                'pay_method' => ['required', 'numeric'],
            ];

        $messages = [
            'amount.required' => 'El monto es requerido',
            'amount.numeric' => 'El monto no es valido',
            'pay_date.required' => 'La fecha es requerida',
            'pay_date.date' => 'La fecha no es valida',
            'reference.required' => 'la referencia es requerida',
            'reference.regex' => 'la referencia no es valida',
            'pay_method.required' => 'Metodo de pago es requerido',
            'pay_method.numeric' => 'Metodo de pago no es valido',
            'vaucher.required' => 'Voucher es requerido',
            'vaucher.file' => 'Voucher no valido',

        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }

    private function uploadVaucher($pay, $vaucher, $type = 1)
    {
        if ($type == 1 && $pay->vaucher) {
            return $pay->vaucher;
        }

        $path = '';
        $id = $type == 1 ? $pay->id : $pay->sequence;
        $folder = $type == 1 ? 'vaucher' : 'claims';

        if ($vaucher->file('vaucher')) {
            $rand = rand(1000000, 9999999);
            $fileName = trim(str_replace(' ', '_', $id));
            $extension = $vaucher->file('vaucher')->extension();
            $path = "/public/images/{$folder}/{$rand}_{$fileName}.{$extension}";
            $vaucherPath = public_path()."/images/{$folder}/";
            $vaucher->file('vaucher')->move($vaucherPath, $path);
        }
        if ($type == 1) {
            $pay->vaucher = $path;
            $pay->save();
        }

        return $path;
    }

    private function uploadRefundVaucher(Request $request): string
    {
        $rand = rand(1000000, 9999999);
        $fileName = trim(str_replace(' ', '_', $request->booking_id));
        $extension = $request->file('vaucher')->extension();
        $path = "/public/images/refunds/{$rand}_{$fileName}.{$extension}";
        $folder = public_path().'/images/refunds/';

        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $request->file('vaucher')->move($folder, basename($path));

        return $path;
    }

    private function notifyMissingBankAccount($booking): void
    {
        SendNotificationJob::dispatch($booking->user_id, 'Registra tu cuenta bancaria', 'Para recibir la devolución de tu reserva #'.$booking->booking_number.', registra tu cuenta bancaria o Yape.', '/client/account-bank', ['booking_id' => $booking->id, 'icon' => 'eva-credit-card-outline']);
    }

    private function updateBooking($id, $user)
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return;
        }
        $booking->update(['status' => 2]);
        $this->successReserveNotification($id, $booking);
    }

    private function updateQuota($quotas)
    {
        $VALIDATION_PENDING_STATUS = 2;
        foreach ($quotas as $quota) {
            Quota::find($quota->id)->update([
                'status' => $VALIDATION_PENDING_STATUS,
            ]);
        }
    }

    private function setQuotaIds(Request $request): array
    {
        $raw = $request->has('quota_ids') ? $request->quota_ids : [$request->to_pay_id];

        return array_values(array_unique(array_filter(array_map('intval', (array) $raw))));
    }

    private function sendNotification($pay)
    {
        $dataNotificaction = $this->getDataToNotification($pay);

        SendNotificationJob::dispatch($pay->user_id, $dataNotificaction[0]['title'], $dataNotificaction[0]['message'], $dataNotificaction[0]['url'], $dataNotificaction[0]['meta']);
        SendNotificationJob::dispatch(1, $dataNotificaction[1]['title'], $dataNotificaction[1]['message'], $dataNotificaction[1]['url'], $dataNotificaction[1]['meta']);
    }

    private function getDataToNotification($pay)
    {
        $bookingNumber = $pay->booking?->booking_number ?? '#'.$pay->id;
        $monthLabel = $pay->quotas->first()?->month_label ?? '';
        $month = $pay->quotas->first()?->month ?? '';

        return $pay->type == 1
            ? [
                [
                    'title' => 'Pago realizado',
                    'message' => 'Tu pago por las cuotas del mes de '.$monthLabel
                    .' fue realizado, el personal de administración lo validará en breve.',
                    'url' => '/client/quota/details/month/'.$month,
                    'meta' => ['quota_id' => $pay->id],
                ],
                [
                    'title' => 'Pago realizado',
                    'message' => 'Se ha realizado el pago por las cuotas del mes de '.$monthLabel
                    .', Por favor validar',
                    'url' => '/admin/pay/validate/'.$pay->id,
                    'meta' => ['quota_id' => $pay->id],
                ],
            ]
            : [
                [
                    'title' => 'Pago de reserva realizado',
                    'message' => 'Tu pago por la reserva '.$bookingNumber
                        .' fue realizado, el personal de administración lo validará en breve.',
                    'url' => '/client/reserves/view/'.$pay->id,
                    'meta' => ['booking_id' => $pay->id],
                ],
                [
                    'title' => 'Pago de reserva realizado',
                    'message' => 'Se ha realizado el pago por la reserva '.$bookingNumber
                    .' Por favor validar.',
                    'url' => '/admin/pay/validate/'.$pay->id,
                    'meta' => ['booking_id' => $pay->id],
                ],
            ];
    }

    private function sendReserveNotification($pay)
    {
        $this->reserveNotificationByStatus($pay);
    }

    private function reserveNotificationByStatus($pay)
    {
        $bookingNumber = $pay->booking?->booking_number ?? '#'.$pay->id;

        $data = $pay->status == 0
            ? [
                'title' => 'Pago de reserva rechazado',
                'message' => 'Tu pago por la reserva '.$bookingNumber.' fue rechazado.',
                'url' => '/client/reserves/view/'.$pay->id,
                'meta' => [
                    'booking_id' => $pay->id,
                    'icon' => $pay->status_icon,
                ],
            ]

            : [
                'title' => 'Pago de reserva aceptado',
                'message' => 'Tu pago por la reserva '.$bookingNumber.' fue aprobada.',
                'url' => '/client/reserves/view/'.$pay->id,
                'meta' => [
                    'booking_id' => $pay->id,
                    'icon' => $pay->status_icon,
                ],
            ];

        SendNotificationJob::dispatch($pay->user_id, $data['title'], $data['message'], $data['url'], $data['meta']);
    }

    private function successReserveNotification($user, $booking)
    {
        SendNotificationJob::dispatch($user, 'Reserva creada', 'Tu reserva #'.$booking->booking_number.' fue creada.', '/client/reserves/view/'.$booking->id, [
            'booking_id' => $booking->id,
            'icon' => $booking->icon_status,
        ]);
        SendNotificationJob::dispatch(1, 'Nueva reserva', 'Se creó la reserva #'.$booking->booking_number.'.', '/admin/reserves/view/'.$booking->id, [
            'booking_id' => $booking->id,
            'icon' => $booking->icon_status,
        ]);
    }

    private function pedingToPayReserveNotification($users, $booking)
    {
        BookingPendingPayNotifier::notify($booking);
    }

    private function applyPaysFilter($query, Request $request)
    {
        $VIEW_ALL_STATUS = 4;

        // Filtro por estado
        if ($request->filled('status') && intval($request->status) !== $VIEW_ALL_STATUS) {
            $query->where('status', intval($request->status));
        }

        if ($request->filled('pay_method')) {
            $query->where('pay_method', intval($request->pay_method));
        }

        if ($request->filled('type')) {
            $query->where('type', intval($request->type));
        }

        // Filtro por búsqueda (nombre de usuario o número de departamento)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('quotas.departament', fn ($dq) => $dq->where('number', 'like', '%'.$search.'%'));
            });
        }

        if ($request->filled('date_from')) {
            $dateFrom = Carbon::createFromFormat('d/m/Y', $request->get('date_from'))->format('Y-m-d');
            $query->whereDate('pay_date', '>=', $dateFrom);
        }
        if ($request->filled('date_to')) {
            $dateTo = Carbon::createFromFormat('d/m/Y', $request->get('date_to'))->format('Y-m-d');
            $query->whereDate('pay_date', '<=', $dateTo);
        }

        // Ordenamiento
        $validSortFields = ['created_at', 'pay_date', 'amount', 'status'];
        $sortBy = in_array($request->get('sort_by'), $validSortFields)
            ? $request->get('sort_by') : 'created_at';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);
    }

    public function storeExpensePay(Request $request, int $expenseId)
    {
        $expense = Expense::find($expenseId);

        if (! $expense) {
            return $this->returnFail(404, ['messageType' => 'negative', 'message' => 'Gasto no encontrado']);
        }

        if ($expense->pay_id) {
            $existingPay = Pay::find($expense->pay_id);
            if ($existingPay && in_array($existingPay->status, [1, 2])) {
                return $this->returnFail(409, ['messageType' => 'negative', 'message' => 'Este gasto ya tiene un pago registrado']);
            }
        }

        $validated = Validator::make($request->all(), [
            'amount' => ['required', 'numeric'],
            'financial_account_id' => ['required', 'exists:financial_accounts,id'],
            'transaction_category_id' => ['required', 'exists:transaction_categories,id'],
            'reference' => ['nullable', 'string'],
            'pay_date' => ['required', 'date'],
            'vaucher' => ['nullable', 'file', 'max:10240'],
        ], [
            'amount.required' => 'El monto es requerido',
            'amount.numeric' => 'El monto no es válido',
            'financial_account_id.required' => 'La cuenta financiera es requerida',
            'financial_account_id.exists' => 'La cuenta financiera no es válida',
            'transaction_category_id.required' => 'La categoría de transacción es requerida',
            'transaction_category_id.exists' => 'La categoría de transacción no es válida',
            'pay_date.required' => 'La fecha de pago es requerida',
            'pay_date.date' => 'La fecha de pago no es válida',
        ]);

        if ($validated->fails()) {
            return $this->returnFail(422, ['messageType' => 'negative', 'message' => $validated->errors()->first()]);
        }

        DB::beginTransaction();
        try {
            $pay = Pay::create([
                'user_id' => $request->user()->id,
                'expense_id' => $expenseId,
                'amount' => $request->amount,
                'reference' => $request->reference ?? null,
                'pay_id' => 'E-'.rand(1000, 9999),
                'pay_date' => date('Y-m-d', strtotime($request->pay_date)),
                'type' => 3,
                'pay_method' => null,
                'status' => 2,
            ]);

            if ($request->hasFile('vaucher')) {
                $this->uploadExpenseVaucher($pay, $request);
            }

            $expense->update(['pay_id' => $pay->id, 'status' => 3]);

            $financialAccountId = (int) $request->financial_account_id;
            $financialAccount = FinancialAccount::query()
                ->whereKey($financialAccountId)
                ->lockForUpdate()
                ->first();

            if (! $financialAccount || (int) $financialAccount->status !== 1) {
                DB::rollBack();

                return $this->returnFail(422, ['messageType' => 'negative', 'message' => 'Cuenta financiera inválida o inactiva']);
            }

            $amount = round((float) $pay->amount, 2);
            $financialAccount->current_balance = round((float) $financialAccount->current_balance - $amount, 2);
            $financialAccount->save();

            $transaction = Transaction::create([
                'financial_account_id' => $financialAccount->id,
                'transaction_category_id' => (int) $request->transaction_category_id,
                'pay_id' => $pay->id,
                'expense_id' => $expenseId,
                'amount' => $amount,
                'date' => now()->toDateString(),
                'reference' => (string) $pay->reference,
                'description' => sprintf('Egreso pago de gasto #%s', $pay->pay_id),
                'status' => 1,
                'type' => 2,
            ]);

            DB::commit();

            $pay->refresh()->load(['expense', 'user']);

            return $this->returnSuccess(200, [
                'pay' => $pay,
                'transaction' => $transaction,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error($e);

            return $this->returnFail(500, ['messageType' => 'negative', 'message' => 'Error al registrar el pago del gasto']);
        }
    }

    private function uploadExpenseVaucher(Pay $pay, Request $request)
    {
        if (! $request->hasFile('vaucher')) {
            return null;
        }

        $rand = rand(1000000, 9999999);
        $fileName = trim(str_replace(' ', '_', $pay->id));
        $extension = $request->file('vaucher')->extension();
        $path = "/public/images/vaucher/{$rand}_{$fileName}.{$extension}";
        $folder = public_path().'/images/vaucher/';

        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $request->file('vaucher')->move($folder, basename($path));

        $pay->vaucher = $path;
        $pay->save();

        return $path;
    }
}
