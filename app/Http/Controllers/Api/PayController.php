<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PayClaims;
use App\Models\Booking;
use App\Models\FinancialAccount;
use App\Models\Pay;
use App\Models\Quota;
use App\Models\Sequence;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\BillInvoiceService;
use App\Services\BookingPendingPayNotifier;
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
        if ($user->rol_id != 1 && $pay->user_id !== $user->id) {
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
        if ($request->user()->id != 1) {
            $pays->where('user_id', $request->user()->id);
        }

        // Aplicar filtros
        $this->applyPaysFilter($pays, $request);

        return $this->returnSuccess(200, $pays->get());
    }

    public function storePay(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
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

        $user = $request->user();

        $pay = Pay::create([
            'user_id' => $user->id,
            'booking_id' => $request->type == 2 ? $request->to_pay_id : null,
            'quota_id' => $quotaIdsForPay ? $quotaIdsForPay[0] : null,
            'consolidated_ids' => $quotaIdsForPay,
            'amount' => $request->amount,
            'reference' => $request->reference ?? '000000',
            'pay_id' => $prefixPayId[$request->pay_method].($request->booking_id ?? 'Q').'-'.rand(1000, 9999),
            'pay_date' => $request->pay_date ? date('Y-m-d', strtotime($request->pay_date)) : date('Y-m-d'),
            'type' => $request->type,
            'pay_method' => $request->pay_method,
            'status' => 1,
        ]);

        if ($request->type == 1) {
            $pay->quotas()->sync($quotaIdsForPay);
        }

        $this->afterPayAction($pay);
        $this->uploadVaucher($pay, $request);
        $this->sendNotification($pay);

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

                // Enviar recibos por correo después de aprobar el pago
                $this->billInvoiceService->sendBillInvoicesForPay($pay);
            } elseif ((int) $pay->type === 2 && $pay->booking_id) {
                $this->approveBooking($pay->booking_id);
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
            : $this->approveBooking($pay->booking_id);

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

    private function approveBooking($booking)
    {
        $APPROVE_VALUE = 3;
        $booking = Booking::find($booking);
        $booking->update([
            'status' => $APPROVE_VALUE,
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
            'vaucher.required' => 'Vaucher es requerido',
            'vaucher.file' => 'Vaucher no valido',

        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }

    private function uploadVaucher($pay, $vaucher, $type = 1)
    {
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

    private function updateBooking($id, $user)
    {
        $booking = Booking::find($id)->update([
            'status' => 2,
        ]);
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
        $users = [
            'admin' => User::find(1),
            'client' => User::find($pay->user_id),
        ];
        $dataNotificaction = $this->getDataToNotification($pay);

        try {
            $users['client']->notify(new RealtimeNotification(
                title: $dataNotificaction[0]['title'],
                message: $dataNotificaction[0]['message'],
                url: $dataNotificaction[0]['url'],
                meta: $dataNotificaction[0]['meta'],
            ));
            $users['admin']->notify(new RealtimeNotification(
                title: $dataNotificaction[1]['title'],
                message: $dataNotificaction[1]['message'],
                url: $dataNotificaction[1]['url'],
                meta: $dataNotificaction[1]['meta'],
            ));
        } catch (\Throwable $e) {
            // Silenciar errores de notificación para no romper el flujo
        }
    }

    private function getDataToNotification($pay)
    {
        return $pay->type == 1
            ? [
                [
                    'title' => 'Pago realizado',
                    'message' => 'Tu pago por las cuotas del mes de '.$pay->quotas[0]->month_label
                        .' fue realizado, el personal de administración lo validará en breve.',
                    'url' => '/client/quota/details/month/'.$pay->quotas[0]->month,
                    'meta' => ['quota_id' => $pay->id],
                ],
                [
                    'title' => 'Pago realizado',
                    'message' => 'Se ha realizado el pago por las cuotas del mes de '.$pay->quotas[0]->month_label
                        .', Por favor validar',
                    'url' => '/admin/pay/validate/'.$pay->id,
                    'meta' => ['quota_id' => $pay->id],
                ],
            ]
            : [
                [
                    'title' => 'Pago de reserva realizado',
                    'message' => 'Tu pago por la reserva #'.$pay->booking->booking_number
                        .' fue realizado, el personal de administración lo validará en breve.',
                    'url' => '/client/reserves/view/'.$pay->id,
                    'meta' => ['booking_id' => $pay->id],
                ],
                [
                    'title' => 'Pago de reserva realizado',
                    'message' => 'Se ha realizado el pago por la reserva #'.$pay->booking->booking_number
                        .' Por favor validar.',
                    'url' => '/admin/pay/validate/'.$pay->id,
                    'meta' => ['booking_id' => $pay->id],
                ],
            ];
    }

    private function sendReserveNotification($pay)
    {
        $users = [
            'admin' => User::find(1),
            'client' => User::find($pay->user_id),
        ];
        $this->reserveNotificationByStatus($users, $pay);
    }

    private function reserveNotificationByStatus($users, $pay)
    {

        $data = $pay->status == 0
            ? [
                'title' => 'Pago de reserva rechazado',
                'message' => 'Tu pago por la reserva #'.$pay->booking->booking_number.' fue rechazado.',
                'url' => '/client/reserves/view/'.$pay->id,
                'meta' => [
                    'booking_id' => $pay->id,
                    'icon' => $pay->status_icon,
                ],
            ]

            : [
                'title' => 'Pago de reserva aceptado',
                'message' => 'Tu pago por la reserva #'.$pay->booking->booking_number.' fue aprobada.',
                'url' => '/client/reserves/view/'.$pay->id,
                'meta' => [
                    'booking_id' => $pay->id,
                    'icon' => $pay->status_icon,
                ],
            ];

        try {
            $users['client']->notify(new RealtimeNotification(
                title: $data['title'],
                message: $data['message'],
                url: $data['url'],
                meta: $data['meta'],
            ));
        } catch (\Throwable $e) {
            // Silenciar errores de notificación para no romper el flujo
        }
    }

    private function successReserveNotification($user, $booking)
    {
        $users = [
            'admin' => User::find(1),
            'client' => User::find($user),
        ];
        try {
            $users['client']->notify(new RealtimeNotification(
                title: 'Reserva creada',
                message: 'Tu reserva #'.$booking->booking_number.' fue creada.',
                url: '/client/reserves/view/'.$booking->id,
                meta: [
                    'booking_id' => $booking->id,
                    'icon' => $booking->icon_status,
                ]
            ));

            if ($users['admin']) {
                $users['admin']->notify(new RealtimeNotification(
                    title: 'Nueva reserva',
                    message: 'Se creó la reserva #'.$booking->booking_number.'.',
                    url: '/admin/reserves',
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status,
                    ]
                ));
            }
        } catch (\Throwable $e) {
        }
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

        if ($request->filled('date_from')) {
            $query->whereDate('pay_date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('pay_date', '<=', $request->get('date_to'));
        }

        // Ordenamiento
        $validSortFields = ['created_at', 'pay_date', 'amount', 'status'];
        $sortBy = in_array($request->get('sort_by'), $validSortFields)
            ? $request->get('sort_by') : 'created_at';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);
    }
}
