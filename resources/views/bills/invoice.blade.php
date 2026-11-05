<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Cuotas - {{ $departament->number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.35;
        }
        .invoice-container {
            width: 96%;
            margin: 0 auto;
            padding: 0;
        }

        /* Colores */
        .primary-bg { background-color: #226fb5; }
        .primary-text { color: #226fb5; }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
        }
        .badge {
            background-color: #226fb5;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            padding: 3px 6px;
            letter-spacing: 0.3px;
        }
        .badge-value {
            font-size: 22px;
            font-weight: bold;
            color: #1a1a1a;
            padding: 2px 8px 8px 8px;
        }
        .header-center {
            text-align: center;
            padding-top: 18px !important;
        }
        .header-center .building-name {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1a1a1a;
        }
        .header-center .location {
            font-size: 10px;
            color: #444;
            margin-top: 4px;
        }
        .header-right {
            text-align: right;
            padding-top: 4px !important;
        }
        .header-right .document-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1a1a1a;
            line-height: 1.25;
        }
        .header-right .receipt-number {
            font-size: 12px;
            color: #1a1a1a;
            margin-top: 6px;
        }

        /* ── Owner / period ── */
        .owner-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .owner-table th,
        .owner-table td {
            padding: 5px 8px;
            font-size: 9.5px;
            border: 1px solid #d6d6d6;
            vertical-align: middle;
        }
        .owner-table .label-cell {
            background-color: #226fb5;
            color: #fff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5px;
            text-align: center;
            width: 11%;
        }
        .owner-table .head-cell {
            background-color: #226fb5;
            color: #fff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5px;
            text-align: center;
        }
        .owner-table .value-cell {
            font-size: 10.5px;
            color: #1a1a1a;
        }
        .owner-table .period-value {
            font-size: 16px;
            font-weight: bold;
            color: #1a1a1a;
            text-align: right;
            width: 20%;
        }
        .owner-table .date-value {
            text-align: right;
            font-size: 10px;
            color: #1a1a1a;
            width: 20%;
        }

        /* ── Section title bar ── */
        .section-title {
            background-color: #226fb5;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            padding: 4px 8px;
            letter-spacing: 0.4px;
        }

        /* ── Description table ── */
        .desc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .desc-table th {
            background-color: #226fb5;
            color: #fff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 8px;
            text-align: left;
        }
        .desc-table th.right,
        .desc-table td.right {
            text-align: right;
        }
        .desc-table td {
            padding: 4px 8px;
            font-size: 9.5px;
            border-bottom: 1px solid #e4e4e4;
            color: #1a1a1a;
        }

        /* ── Amount in words ── */
        .amount-words {
            padding: 4px 2px 10px 2px;
            font-size: 9.5px;
            color: #1a1a1a;
        }

        /* ── Collection system row ── */
        .collect-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .collect-table td {
            padding: 3px 6px;
            font-size: 9.5px;
            vertical-align: middle;
        }
        .collect-label {
            background-color: #226fb5;
            color: #fff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5px;
            text-align: center;
            padding: 5px 8px !important;
            white-space: nowrap;
        }
        .collect-value {
            font-size: 13px;
            font-weight: bold;
            color: #1a1a1a;
            padding-left: 2px !important;
        }
        .cbx {
            width: 13px;
            height: 13px;
            border: 1.5px solid #555;
            background-color: #fff;
        }
        .cbx-on {
            border: 1.5px solid #555;
            background-color: #226fb5;
        }
        .cbx-label {
            font-size: 9.5px;
            font-weight: bold;
            color: #1a1a1a;
            text-transform: uppercase;
            padding-left: 3px !important;
        }

        /* ── Balances band ── */
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .balance-table th {
            background-color: #226fb5;
            color: #fff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            padding: 5px 4px;
            border: 1px solid #1c5f9e;
        }
        .balance-table td {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            color: #1a1a1a;
            padding: 6px 4px;
            border: 1px solid #d6d6d6;
        }

        /* ── Data tables (unidad / consumo / abonos) ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .data-table th {
            background-color: #fff;
            color: #226fb5;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
            padding: 4px 8px;
            border-bottom: 1.5px solid #226fb5;
        }
        .data-table th.right,
        .data-table td.right {
            text-align: right;
        }
        .data-table th.center,
        .data-table td.center {
            text-align: center;
        }
        .data-table td {
            padding: 4px 8px;
            font-size: 9.5px;
            border-bottom: 1px solid #e4e4e4;
            color: #1a1a1a;
        }
        .data-table tr.total td {
            font-weight: bold;
            border-top: 1.5px solid #226fb5;
            border-bottom: none;
            background-color: #f3f7fb;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 14px;
            text-align: center;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">

        {{-- ═══════════ HEADER ═══════════ --}}
        <table class="header-table">
            <tr>
                <td style="width: 26%;">
                    <div class="badge">CODIGO DEPOSITO</div>
                    <div class="badge-value">{{ $depositCode ?? '-' }}</div>
                    <div class="badge">UNIDAD INMOBILIARIA</div>
                    <div class="badge-value">{{ $departament->number }}</div>
                </td>
                <td class="header-center" style="width: 48%;">
                    <div class="building-name">JUNTA DE PROPIETARIOS DEL EDIFICIO PACIFIK OCEAN TOWER</div>
                    <div class="location">{{ $buildingAddress ?? 'LIMA - SAN MIGUEL - AV. BERTOLOTO 850' }}</div>
                </td>
                <td class="header-right" style="width: 26%;">
                    <div class="document-title">RECIBO DE CUOTAS<br>DE GASTOS COMUNES</div>
                    <div class="receipt-number">N° {{ $receiptNumber ?? '001 - '.str_pad((string) ($quota->id ?? 0), 6, '0', STR_PAD_LEFT) }}</div>
                </td>
            </tr>
        </table>

        {{-- ═══════════ POSEEDOR / PROPIETARIO / PERIODO ═══════════ --}}
        <table class="owner-table">
            <tr>
                <td class="label-cell" style="border: none; width: 0%;">&nbsp;</td>
                <th class="head-cell">NOMBRE O RAZON SOCIAL</th>
                <th class="head-cell" style="width: 14%;">DNI O RUC</th>
                <th class="head-cell" style="width: 11%;">PERIODO</th>
                <td class="period-value" style="border: none;">{{ strtoupper($monthLabel) }}</td>
            </tr>
            <tr>
                <td class="label-cell">POSEEDOR</td>
                <td class="value-cell">{{ strtoupper($owner->name ?? 'N/A') }}</td>
                <td class="value-cell center" style="text-align: center;">{{ $owner->document ?? '0' }}</td>
                <td class="label-cell">EMISION</td>
                <td class="date-value">{{ $emissionDate }}</td>
            </tr>
            <tr>
                <td class="label-cell">PROPIETARIO</td>
                <td class="value-cell">{{ strtoupper($owner->name ?? 'N/A') }}</td>
                <td class="value-cell center" style="text-align: center;">{{ $owner->document ?? '0' }}</td>
                <td class="label-cell">VENCIMIENTO</td>
                <td class="date-value">{{ $dueDate }}</td>
            </tr>
            <tr>
                <td class="label-cell" style="border: none;">&nbsp;</td>
                <td style="border: none;">&nbsp;</td>
                <td style="border: none;">&nbsp;</td>
                <td class="label-cell">REF. INTERNA</td>
                <td class="date-value">{{ $quota->id ?? '' }}</td>
            </tr>
        </table>

        {{-- ═══════════ DESCRIPCION / MONTO ═══════════ --}}
        <table class="desc-table">
            <thead>
                <tr>
                    <th>DESCRIPCION</th>
                    <th class="right" style="width: 18%;">MONTO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>CUOTA DE GASTOS COMUNES - {{ strtoupper($monthLabel) }} {{ $year }}</td>
                    <td class="right">{{ number_format($maintenanceAmount, 2) }}</td>
                </tr>
                @if($waterAmount > 0)
                <tr>
                    <td>CONSUMO INDIVIDUAL (AGUA) - {{ strtoupper($previousMonthLabel) }} {{ $waterYear }}</td>
                    <td class="right">{{ number_format($waterAmount, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <div class="amount-words">
            <strong>SON:</strong> {{ $amountInWords }} SOLES
        </div>

        {{-- ═══════════ SISTEMA DE RECAUDACION / PRESUPUESTO / CUOTA DEL MES ═══════════ --}}
        <table class="collect-table">
            <tr>
                <td class="collect-label" style="width: 22%;">SISTEMA DE RECAUDACION</td>
                <td style="width: 4%;">
                    <table style="border-collapse: collapse;"><tr><td class="cbx">&nbsp;</td></tr></table>
                </td>
                <td class="cbx-label" style="width: 10%;">TRADICIONAL</td>
                <td style="width: 4%;">
                    <table style="border-collapse: collapse;"><tr><td class="cbx-on">&nbsp;</td></tr></table>
                </td>
                <td class="cbx-label" style="width: 10%;">MULTIBANCA</td>
                <td class="collect-label" style="width: 14%;">PRESUPUESTO</td>
                <td class="collect-value" style="width: 16%; padding-right: 8px; padding-left: 4px !important;">
                    {{ number_format($totalMaintenanceBudget ?? ($monthlyBill->total_maintenance_budget ?? 0), 2) }}
                </td>
                <td class="collect-label" style="width: 12%;">CUOTA DEL MES</td>
                <td class="collect-value" style="width: 12%; padding-right: 8px; padding-left: 4px !important;">
                    {{ number_format($totalAmount, 2) }}
                </td>
            </tr>
        </table>

        {{-- ═══════════ BALANCES ═══════════ --}}
        <table class="balance-table">
            <thead>
                <tr>
                    <th style="width: 17%;">SALDO INICIAL</th>
                    <th style="width: 17%;">ABONOS DEL MES</th>
                    <th style="width: 16%;">CARGOS DEL MES</th>
                    <th style="width: 16%;">CUOTAS VENCIDAS</th>
                    <th style="width: 16%;">DIAS VENCIDOS</th>
                    <th style="width: 18%;">SALDO FINAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ number_format($initialBalance, 2) }}</td>
                    <td>{{ number_format($monthlyPayments, 2) }}</td>
                    <td>{{ number_format($totalAmount, 2) }}</td>
                    <td>{{ $overdueQuotas }}</td>
                    <td>{{ $overdueDays }}</td>
                    <td>{{ number_format($finalBalance, 2) }}</td>
                </tr>
            </tbody>
        </table>

        {{-- ═══════════ INFORMACION DE UNIDAD INMOBILIARIA ═══════════ --}}
        <div class="section-title">INFORMACION DE UNIDAD INMOBILIARIA</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 12%;">UI</th>
                    <th style="width: 34%;">DESCRIPCION</th>
                    <th class="right" style="width: 18%;">AREA OCUPADA</th>
                    <th class="right" style="width: 18%;">PARTICIPACION</th>
                    <th class="right" style="width: 18%;">% CUOTA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($units as $unit)
                <tr>
                    <td>{{ $unit->inter_number }}</td>
                    <td>{{ $unit->type_label }}</td>
                    <td class="right">{{ number_format($unit->area, 2) }}</td>
                    <td class="right">{{ number_format($unit->participation_percentage, 2) }}</td>
                    <td class="right">{{ number_format($unit->participation_percentage, 6) }}</td>
                </tr>
                @endforeach
                <tr class="total">
                    <td><strong>TOTAL</strong></td>
                    <td>&nbsp;</td>
                    <td class="right">{{ number_format($totalArea, 2) }} M2</td>
                    <td class="right">{{ number_format($totalParticipation, 2) }}%</td>
                    <td class="right">{{ number_format($totalParticipation, 6) }}%</td>
                </tr>
            </tbody>
        </table>

        {{-- ═══════════ CONSUMO INDIVIDUAL ═══════════ --}}
        @if($waterReading)
        <div class="section-title">CONSUMO INDIVIDUAL</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20%;">CONCEPTO</th>
                    <th class="center" style="width: 10%;">U. MEDIDA</th>
                    <th class="right" style="width: 15%;">LECTURA ANTERIOR</th>
                    <th class="right" style="width: 14%;">LECTURA ACTUAL</th>
                    <th class="right" style="width: 12%;">CONSUMO</th>
                    <th class="right" style="width: 13%;">COSTO UNITARIO</th>
                    <th class="right" style="width: 16%;">MONTO TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Agua y Desague</td>
                    <td class="center">M3</td>
                    <td class="right">{{ number_format($waterReading->previous_reading, 2) }}</td>
                    <td class="right">{{ number_format($waterReading->current_reading, 2) }}</td>
                    <td class="right">{{ number_format($waterConsumption, 2) }}</td>
                    <td class="right">{{ number_format($waterPricePerM3, 2) }}</td>
                    <td class="right">{{ number_format($waterAmount, 2) }}</td>
                </tr>
                <tr>
                    <td>Electricidad</td>
                    <td class="center">KWH</td>
                    <td class="right">0.00</td>
                    <td class="right">0.00</td>
                    <td class="right">0.00</td>
                    <td class="right">1.800</td>
                    <td class="right">&nbsp;</td>
                </tr>
            </tbody>
        </table>
        @endif

        {{-- ═══════════ ULTIMOS ABONOS ═══════════ --}}
        @if(count($paymentHistory) > 0)
        <div class="section-title">ULTIMOS {{ count($paymentHistory) }} ABONOS</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 14%;">FECHA PAGO</th>
                    <th style="width: 44%;">DESCRIPCION</th>
                    <th class="right" style="width: 14%;">MONTO</th>
                    <th class="right" style="width: 14%;">INTERESES</th>
                    <th class="right" style="width: 14%;">TOTAL PAGADO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paymentHistory as $payment)
                <tr>
                    <td>{{ $payment['date'] }}</td>
                    <td>{{ $payment['description'] }}</td>
                    <td class="right">{{ number_format($payment['amount'], 2) }}</td>
                    <td class="right">{{ number_format($payment['interest'], 2) }}</td>
                    <td class="right">{{ number_format($payment['total_paid'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        {{-- ═══════════ FOOTER ═══════════ --}}
        <div class="footer">
            Este documento es un comprobante de pago generado por el sistema de gestión del Condominio Pacifik.<br>
            Generado el {{ date('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
