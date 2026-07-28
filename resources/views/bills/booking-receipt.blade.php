<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Reserva</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        .receipt-container { width: 100%; max-width: 800px; margin: 0 auto; padding: 15px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header .building-name { font-size: 14px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
        .header .document-title { font-size: 16px; font-weight: bold; text-transform: uppercase; margin-top: 10px; }
        .header .location { font-size: 10px; color: #666; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 15px; border: 1px solid #ccc; padding: 10px; }
        .info-box { flex: 1; padding: 5px; }
        .info-box .label { font-size: 8px; text-transform: uppercase; color: #666; margin-bottom: 3px; }
        .info-box .value { font-size: 11px; font-weight: bold; }
        .section-title { font-size: 9px; text-transform: uppercase; font-weight: bold; background-color: #f0f0f0; padding: 5px 10px; margin: 10px 0 5px 0; border-left: 3px solid #333; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .data-table th, .data-table td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #eee; font-size: 10px; }
        .data-table th { background-color: #f8f8f8; font-weight: bold; text-transform: uppercase; font-size: 8px; color: #555; }
        .data-table td.number { text-align: right; }
        .data-table tr.total { font-weight: bold; border-top: 2px solid #333; background-color: #f8f8f8; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <div class="location">LIMA - SAN MIGUEL</div>
            <div class="building-name">JUNTA DE PROPIETARIOS DEL EDIFICIO PACIFIK OCEAN TOWER</div>
            <div class="document-title">RECIBO DE PAGO - RESERVA</div>
        </div>

        <div class="info-row">
            <div class="info-box">
                <div class="label">RESERVA N°</div>
                <div class="value">{{ $booking->booking_number ?? 'N/A' }}</div>
            </div>
            <div class="info-box">
                <div class="label">FECHA DE PAGO</div>
                <div class="value">{{ $date }}</div>
            </div>
            <div class="info-box">
                <div class="label">ESTADO</div>
                <div class="value">{{ $pay->status_label }}</div>
            </div>
        </div>

        <div class="section-title">DATOS DEL CLIENTE</div>
        <table class="data-table">
            <tr><th>NOMBRE</th><td>{{ $user->name ?? 'N/A' }}</td></tr>
            <tr><th>UNIDAD</th><td>#{{ $booking->departament->number ?? 'N/A' }}</td></tr>
            <tr><th>EMAIL</th><td>{{ $user->email ?? 'N/A' }}</td></tr>
        </table>

        <div class="section-title">DETALLE DE LA RESERVA</div>
        <table class="data-table">
            <tr><th>ÁREA COMÚN</th><td>{{ $booking->comunArea->name ?? 'N/A' }}</td></tr>
            <tr><th>FECHA</th><td>{{ $booking->date ? \Carbon\Carbon::parse($booking->date)->format('d/m/Y') : 'N/A' }}</td></tr>
            <tr><th>HORARIO</th><td>{{ $booking->time_from }} - {{ $booking->time_to }}</td></tr>
        </table>

        <div class="section-title">DETALLE DEL PAGO</div>
        <table class="data-table">
            <tr><th>MONTO</th><td class="number">S/ {{ number_format($pay->amount, 2) }}</td></tr>
            <tr><th>MÉTODO DE PAGO</th><td>{{ $payMethod->name ?? 'N/A' }}</td></tr>
            <tr><th>REFERENCIA</th><td>{{ $pay->reference ?? '—' }}</td></tr>
            <tr class="total"><th>TOTAL PAGADO</th><td class="number">S/ {{ number_format($pay->amount, 2) }}</td></tr>
        </table>

        <div class="footer">
            Este documento es un comprobante de pago generado por el sistema de gestión del Condominio Pacifik.
        </div>
    </div>
</body>
</html>
