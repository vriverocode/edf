<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Recibo de Mantenimiento - {{ $departament->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9px; color: #222; line-height: 1.3; }
        .page { width: 100%; padding: 12px 18px; }

        /* ── Header ── */
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
        .header .address { font-size: 8px; color: #555; letter-spacing: 0.5px; }
        .header .building { font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 3px 0; }
        .header .title { font-size: 13px; font-weight: bold; text-transform: uppercase; margin-top: 6px; }

        /* ── Owner / Receipt Info ── */
        .info-row { display: table; width: 100%; margin-bottom: 6px; border: 1px solid #ccc; }
        .info-cell { display: table-cell; padding: 4px 8px; vertical-align: top; }
        .info-cell .label { font-size: 7px; text-transform: uppercase; color: #666; font-weight: bold; }
        .info-cell .value { font-size: 10px; font-weight: bold; margin-top: 1px; }
        .info-cell .value.small { font-size: 9px; }
        .info-cell .value.large { font-size: 14px; }

        /* ── Tables ── */
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .detail-table th { background: #f0f0f0; font-size: 7px; text-transform: uppercase; font-weight: bold; padding: 4px 6px; text-align: left; border-bottom: 1px solid #ccc; }
        .detail-table td { font-size: 8.5px; padding: 3px 6px; border-bottom: 1px solid #eee; }
        .detail-table td.num { text-align: right; }
        .detail-table tr.total td { font-weight: bold; border-top: 2px solid #333; background: #f8f8f8; }

        /* ── Water Reading ── */
        .water-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .water-table th { background: #f0f0f0; font-size: 7px; text-transform: uppercase; font-weight: bold; padding: 3px 6px; text-align: left; border-bottom: 1px solid #ccc; }
        .water-table td { font-size: 8.5px; padding: 3px 6px; border-bottom: 1px solid #eee; }
        .water-table td.num { text-align: right; }

        /* ── Subtotals ── */
        .subtotals { width: 100%; margin-bottom: 8px; }
        .subtotals td { font-size: 8.5px; padding: 2px 6px; }
        .subtotals td.label { text-align: right; font-weight: bold; }
        .subtotals td.amount { text-align: right; width: 80px; }
        .subtotals tr.grand td { font-size: 11px; font-weight: bold; border-top: 2px solid #000; padding-top: 4px; }

        /* ── Bank Info ── */
        .bank-box { border: 1px solid #ccc; padding: 6px 10px; margin-top: 10px; margin-bottom: 8px; }
        .bank-box .bank-title { font-size: 8px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
        .bank-box .bank-line { font-size: 8.5px; margin-bottom: 1px; }
        .bank-box .bank-label { font-weight: bold; }

        /* ── Footer ── */
        .footer { text-align: center; font-size: 7.5px; color: #888; border-top: 1px solid #ddd; padding-top: 6px; margin-top: 10px; }

        /* ── Page break ── */
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
<div class="page">

    {{-- ═══════════ HEADER ═══════════ --}}
    <div class="header">
        <div class="address">{{ $buildingAddress ?? 'LIMA - SAN MIGUEL - AV. BERTOLOTO 850' }}</div>
        <div class="building">JUNTA DE PROPIETARIOS DEL EDIFICIO MULTIFAMILIAR<br>"PACIFIK OCEAN TOWER"</div>
        <div class="title">RECIBO DE MANTENIMIENTO</div>
    </div>

    {{-- ═══════════ OWNER INFO ═══════════ --}}
    <div class="info-row">
        <div class="info-cell" style="width: 30%;">
            <div class="label">PROPIETARIO</div>
            <div class="value small">{{ strtoupper($owner->name ?? 'N/A') }}</div>
        </div>
        <div class="info-cell" style="width: 20%;">
            <div class="label">DNI / RUC</div>
            <div class="value">{{ $owner->document ?? '0' }}</div>
        </div>
        <div class="info-cell" style="width: 25%;">
            <div class="label">CORREO ELECTRÓNICO</div>
            <div class="value small">{{ $owner->email ?? 'N/A' }}</div>
        </div>
        <div class="info-cell" style="width: 25%;">
            <div class="label">CÓDIGO SERVICIO</div>
            <div class="value">PAC{{ $departament->number }}</div>
        </div>
    </div>

    <div class="info-row">
        <div class="info-cell" style="width: 20%;">
            <div class="label">PREDIO</div>
            <div class="value">{{ $departament->number }}</div>
        </div>
        <div class="info-cell" style="width: 20%;">
            <div class="label">TOTAL CUOTA MES</div>
            <div class="value">S/ {{ number_format($totalAmount, 2) }}</div>
        </div>
        <div class="info-cell" style="width: 20%;">
            <div class="label">DEUDA ANTERIOR</div>
            <div class="value">S/ {{ number_format($initialBalance, 2) }}</div>
        </div>
        <div class="info-cell" style="width: 20%;">
            <div class="label">FECHA DE EMISIÓN</div>
            <div class="value">{{ $emissionDate }}</div>
        </div>
        <div class="info-cell" style="width: 20%;">
            <div class="label">FECHA DE VENCIMIENTO</div>
            <div class="value">{{ $dueDate }}</div>
        </div>
    </div>

    <div class="info-row">
        <div class="info-cell" style="width: 33%;">
            <div class="label">% COBRO APLICADO</div>
            <div class="value">{{ number_format($collectionRate, 4) }}%</div>
        </div>
        <div class="info-cell" style="width: 33%;">
            <div class="label">% DE PARTICIPACIÓN</div>
            <div class="value">{{ number_format($totalParticipation * 100, 2) }}%</div>
        </div>
        <div class="info-cell" style="width: 34%;">
            <div class="label">TIPO DE ÁREA</div>
            <div class="value">{{ $departament->type_label }}</div>
        </div>
    </div>

    {{-- ═══════════ ÁREAS DEL PROPIETARIO ═══════════ --}}
    <table class="detail-table">
        <thead>
            <tr>
                <th style="width: 40%;">CONCEPTO</th>
                <th style="width: 20%;">N° ÁREA</th>
                <th style="width: 20%; text-align: right;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            @foreach($units as $unit)
            <tr>
                <td>{{ strtoupper($unit->type_label) }}</td>
                <td>{{ $unit->number }}</td>
                <td class="num">S/ {{ number_format($unit->area, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ═══════════ DETALLE DE GASTOS (EXPENSES) ═══════════ --}}
    @if($expenses->count() > 0)
    <table class="detail-table">
        <thead>
            <tr>
                <th style="width: 12%;">TIPO</th>
                <th style="width: 28%;">PROVEEDOR / SERVICIO</th>
                <th style="width: 30%;">DESCRIPCIÓN</th>
                <th style="width: 15%;">N° FACTURA</th>
                <th style="width: 15%; text-align: right;">IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $expense)
            <tr>
                <td>{{ strtoupper($expense->serviceCategory->name ?? 'SERVICIO') }}</td>
                <td>{{ strtoupper($expense->provider->name ?? 'N/A') }}</td>
                <td>{{ strtoupper($expense->description ?? $expense->serviceCategory->name ?? '') }} {{ strtoupper($monthLabel) }} {{ $year }}</td>
                <td>{{ $expense->invoice_number ?? '' }}</td>
                <td class="num">S/ {{ number_format($expense->amount, 2) }}</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="4">CUOTA DE MANTENIMIENTO DE EQUIPOS Y PERSONAL</td>
                <td class="num">S/ {{ number_format($maintenanceAmount, 2) }}</td>
            </tr>
            <tr class="total">
                <td colspan="4">SUB-TOTAL OTROS CARGOS DEL MES</td>
                <td class="num">S/ {{ number_format($totalExpenses + $maintenanceAmount, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- ═══════════ LECTURA DE AGUA ═══════════ --}}
    @if($waterReading)
    <div style="margin-top: 6px; margin-bottom: 4px;">
        <strong style="font-size: 8px; text-transform: uppercase;">LECTURA CONTÓMETRO DE AGUA</strong>
    </div>
    <table class="water-table">
        <thead>
            <tr>
                <th style="width: 30%;">CONCEPTO</th>
                <th style="width: 14%;">LECTURA ANTERIOR (A)</th>
                <th style="width: 14%;">LECTURA ACTUAL (B)</th>
                <th style="width: 14%;">CONSUMO m³ (B-A)</th>
                <th style="width: 14%;">FACTOR DEL MES</th>
                <th style="width: 14%; text-align: right;">IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>CONSUMO DE AGUA (B-A)</td>
                <td class="num">{{ number_format($waterReading->previous_reading, 3) }}</td>
                <td class="num">{{ number_format($waterReading->current_reading, 3) }}</td>
                <td class="num">{{ number_format($waterConsumption, 3) }}</td>
                <td class="num">{{ number_format($waterPricePerM3, 2) }}</td>
                <td class="num">S/ {{ number_format($waterAmount, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- ═══════════ SUB-TOTALES ═══════════ --}}
    <table class="subtotals">
        <tr>
            <td class="label">SUB-TOTAL OTROS CARGOS DEL MES</td>
            <td class="amount">S/ {{ number_format($totalExpenses, 2) }}</td>
        </tr>
        <tr>
            <td class="label">SUB-TOTAL AGUA</td>
            <td class="amount">S/ {{ number_format($waterAmount, 2) }}</td>
        </tr>
        <tr>
            <td class="label">SUB-TOTAL MANTENIMIENTO DEL MES</td>
            <td class="amount">S/ {{ number_format($maintenanceAmount, 2) }}</td>
        </tr>
        <tr class="grand">
            <td class="label">TOTAL</td>
            <td class="amount">S/ {{ number_format($totalAmount, 2) }}</td>
        </tr>
    </table>

    {{-- ═══════════ MONTO EN LETRAS ═══════════ --}}
    <div style="background: #f8f8f8; padding: 5px 8px; border: 1px solid #ddd; font-style: italic; font-size: 8.5px; margin-bottom: 8px;">
        <strong>SON:</strong> {{ $amountInWords }} SOLES
    </div>

    {{-- ═══════════ CUENTAS RECAUDADORAS ═══════════ --}}
    <div class="bank-box">
        <div class="bank-title">CUENTAS RECAUDADORAS</div>
        <div class="bank-line"><span class="bank-label">JUNTA DE PROPIETARIOS DEL EDIFICIO MULTIFAMILIAR "PACIFIK OCEAN TOWER"</span></div>
        @if(isset($bankData) && is_array($bankData))
            @foreach($bankData as $bank)
                @if(($bank['type']['value'] ?? 0) !== 2)
                <div class="bank-line">{{ strtoupper($bank['title'] ?? '') }}: {{ $bank['data'] ?? '' }}</div>
                @endif
            @endforeach
        @endif
    </div>

    {{-- ═══════════ FOOTER ═══════════ --}}
    <div class="footer">
        CORREO: adm.pacifikot@gmail.com<br>
        ADMINISTRADOR: Jefry Vera Portillo &nbsp;&nbsp;|&nbsp;&nbsp; MÓVIL: 924 039 563
    </div>

</div>
</body>
</html>
