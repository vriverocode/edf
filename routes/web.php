<?php

use App\Models\Quota;
use App\Services\BillInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Visualizar Blade de factura directamente en el navegador sin autenticación (admite /dev/invoice/1 o /dev/invoice?quota_ids=1,2)
// Route::get('/dev/invoice/{quotaId?}', function ($quotaId, Request $request, BillInvoiceService $billInvoiceService) {
//     $rawIds = $request->input('quota_ids', []);
//     if (is_string($rawIds)) {
//         $rawIds = explode(',', $rawIds);
//     }
//     if (empty($rawIds) && $quotaId) {
//         $rawIds = [$quotaId];
//     }
//     $quotaIds = array_values(array_unique(array_filter(array_map('intval', (array) $rawIds), fn ($id) => $id > 0)));

//     if (empty($quotaIds)) {
//         abort(404, 'Cuota no especificada');
//     }

//     $quotas = Quota::with([
//         'departament.owner',
//         'waterReading',
//         'pays' => function ($query) {
//             $query->where('status', 2)
//                 ->where('type', 1)
//                 ->orderByDesc('pay_date');
//         },
//     ])->whereIn('id', $quotaIds)->get();

//     if ($quotas->isEmpty()) {
//         abort(404, 'Cuota no encontrada');
//     }

//     $invoiceData = $billInvoiceService->buildInvoiceData($quotas->first(), $quotas);

//     return view('bills.invoice', $invoiceData);
// });

Route::fallback(function (Request $request) {
    if ($request->is('api/*')) {
        return response()->json(['message' => 'Not Found'], 404);
    }

    return view('app');
});
