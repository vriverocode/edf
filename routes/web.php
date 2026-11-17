<?php

use App\Models\Quota;
use App\Services\BillInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Visualizar Blade de factura directamente en el navegador sin autenticación
Route::get('/dev/invoice/{quotaId}', function ($quotaId, BillInvoiceService $billInvoiceService) {
    $quota = Quota::with([
        'departament.owner',
        'waterReading',
        'pays' => function ($query) {
            $query->where('status', 2)
                ->where('type', 1)
                ->orderByDesc('pay_date');
        },
    ])->findOrFail($quotaId);

    $invoiceData = $billInvoiceService->buildInvoiceData($quota);

    return view('bills.invoice', $invoiceData);
});

Route::fallback(function (Request $request) {
    if ($request->is('api/*')) {
        return response()->json(['message' => 'Not Found'], 404);
    }

    return view('app');
});

