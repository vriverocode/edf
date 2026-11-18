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
            padding-left:8px!important;
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
            width: 15px;
            height: 15px;
            border: 1.5px solid #555;
            background-color: #414141ff;
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

        /* ── Salto de página para PDF y navegador ── */
        .page-break {
            page-break-before: always;
            break-before: page;
        }

        @media screen {
            .page-break {
                margin-top: 35px;
                padding-top: 25px;
                border-top: 2px dashed #226fb5;
            }
        }

        .page-header-secondary {
            width: 100%;
            border-bottom: 1.5px solid #226fb5;
            margin-bottom: 10px;
            padding-bottom: 4px;
        }
        .page-header-secondary td {
            vertical-align: middle;
        }

        /* ── Marca de agua (Pagada / No Pagada) ── */
        .watermark {
            position: fixed;
            top: 32%;
            left: 5%;
            width: 90%;
            text-align: center;
            font-size: 72px;
            font-weight: 900;
            border-radius: 12px;
            padding: 18px 24px;
            letter-spacing: 12px;
            transform: rotate(-28deg);
            -webkit-transform: rotate(-28deg);
            z-index: 9999;
            pointer-events: none;
            user-select: none;
        }
        .watermark-paid {
            color: rgba(22, 163, 74, 0.16);
            border: 7px solid rgba(22, 163, 74, 0.20);
        }
        .watermark-unpaid {
            color: rgba(220, 38, 38, 0.16);
            border: 7px solid rgba(220, 38, 38, 0.18);
        }
    </style>
</head>
<body>
    @if((int) ($quota->status ?? 0) === 3)
    @else
    <div class="watermark watermark-unpaid">NO PAGADA</div>
    @endif
    <div class="invoice-container">

        {{-- ═══════════ HEADER ═══════════ --}}
        <table class="header-table">
            <tr>
                <td style="width: 26%;">
                    <div class="badge">CODIGO DEPOSITO</div>
                    <div class="badge-value">{{ $depositCode ?? '-' }}</div>
                    <div class="badge">UNIDAD INMOBILIARIA</div>
                    <div class="badge-value" style="font-size: {{ strlen($departamentNumberDisplay ?? $departament->number) > 10 ? '14px' : '20px' }};">
                        {{ strtoupper($departamentNumberDisplay ?? $departament->number) }}
                    </div>
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
        @if((int) ($quota->status ?? 0) === 3)
            <div class="" style="text-align: center; font-size: 20px; font-weight: bold; color: #51b522ff;">PAGADA</div>
        @endif

        {{-- ═══════════ POSEEDOR / PROPIETARIO / PERIODO ═══════════ --}}
        <table class="owner-table">
            <tr>
                <td class="label-cell" style="border: none; width: 0%;">&nbsp;</td>
                <th class="head-cell">NOMBRE O RAZON SOCIAL</th>
                <th class="head-cell" style="width: 14%;">DNI O RUC</th>
                <th class="head-cell" style="width: 11%;">PERIODO</th>
                <td class="period-value" style="border: 1px solid black">{{ strtoupper($monthLabel) }}</td>
            </tr>
            <tr>
                <td class="label-cell">POSEEDOR</td>
                <td class="value-cell">{{ strtoupper($owner->name ?? 'N/A') }}</td>
                <td class="value-cell center" style="text-align: center;">{{ $owner->document ?? '0' }}</td>
                <td class="label-cell">EMISION</td>
                <td class="date-value">{{ $emissionQuotaDate }}</td>
                {{-- <td class="date-value">{{ $emissionDate }}</td> --}}
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
                <td class="date-value">#{{ $quota->id ?? '' }}</td>
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
                    <td style="font-size:10.5px">CUOTA DE GASTOS COMUNES - {{ strtoupper($monthLabel) }} {{ $year }}</td>
                    <td class="right" style="font-size:10.5px">S/ {{ number_format($maintenanceAmount, 2) }}</td>
                </tr>
                @if($waterAmount > 0)
                <tr>
                    <td>CONSUMO INDIVIDUAL (AGUA) - {{ strtoupper($previousMonthLabel) }} {{ $waterYear }}</td>
                    <td class="right">{{ number_format($waterAmount, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <div class="amount-words" style="font-size:10.5px">
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
                @if((int) ($quota->status ?? 0) === 3)
                <td style="width: 4%;">
                    <table style="border-collapse: collapse;color:white">
                        <tr><td class="cbx-on" style="font-size:18px; padding:0px 6px">X</td></tr>
                    </table>
                </td>
                @else
                <td style="width: 4%;">
                    <table style="border-collapse: collapse;"><tr><td class="cbx">&nbsp;</td></tr></table>
                </td>
                @endif
                <td class="cbx-label" style="width: 10%;">MULTIBANCA</td>
                <td class="collect-label" style="width: 14%;">PRESUPUESTO</td>
                <td class="collect-value" style="width: 16%; padding-right: 8px; padding-left: 4px !important; font-size:12px">
                   S/ {{ number_format($totalMaintenanceBudget ?? ($monthlyBill->total_maintenance_budget ?? 0), 2) }}
                </td>
                <td class="collect-label" style="width: 12%;">CUOTA DEL MES</td>
                <td class="collect-value" style="width: 12%; padding-right: 8px; padding-left: 4px !important; font-size:12px">
                   S/ {{ number_format($totalAmount, 2) }}
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
                    <td>S/ {{ number_format($initialBalance, 2) }}</td>
                    <td>S/ {{ number_format($monthlyPayments, 2) }}</td>
                    <td>S/ {{ number_format($totalAmount, 2) }}</td>
                    <td>{{ $overdueQuotas }}</td>
                    <td>{{ $overdueDays }}</td>
                    <td>S/ {{ number_format($finalBalance, 2) }}</td>
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
                    <th class="right" style="width: 18%;">Monto a cancelar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($units as $unit)
                <tr>
                    <td>{{ strtoupper($unit->number) }}</td>
                    <td>{{ $unit->type_label }}</td>
                    <td class="right">{{ number_format($unit->area, 2) }}</td>
                    <td class="right">{{ number_format($unit->participation_percentage, 2) }}</td>
                    <td class="right">{{ number_format($unit->participation_percentage, 6) }}</td>
                    <td class="right">S/ {{ number_format(($unit->participation_percentage * ($totalMaintenanceBudget ?? ($monthlyBill->total_maintenance_budget ?? 0)))/100, 2) }}</td>


                </tr>
                @endforeach
                <tr class="total">
                    <td><strong>TOTAL</strong></td>
                    <td>&nbsp;</td>
                    <td class="right">{{ number_format($totalArea, 2) }} M2</td>
                    <td class="right">{{ number_format($totalParticipation, 2) }}%</td>
                    <td class="right">{{ number_format($totalParticipation, 6) }}%</td>
                    <td class="right">S/ {{ number_format(($totalParticipation * ($totalMaintenanceBudget ?? ($monthlyBill->total_maintenance_budget ?? 0)))/100, 2) }}</td>

                </tr>
            </tbody>
        </table>

        {{-- ═══════════ CONSUMO INDIVIDUAL ═══════════ --}}
        @php
            $hasWater = (isset($waterReadingsList) && count($waterReadingsList) > 0) || !empty($waterReading);
        @endphp
        @if($hasWater)
        <div class="section-title">CONSUMO INDIVIDUAL (AGUA Y DESAGÜE)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 24%;">CONCEPTO</th>
                    <th class="center" style="width: 10%;">U. MEDIDA</th>
                    <th class="right" style="width: 14%;">LECTURA ANTERIOR</th>
                    <th class="right" style="width: 14%;">LECTURA ACTUAL</th>
                    <th class="right" style="width: 12%;">CONSUMO</th>
                    <th class="right" style="width: 12%;">COSTO UNITARIO</th>
                    <th class="right" style="width: 14%;">MONTO TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @if(isset($waterReadingsList) && count($waterReadingsList) > 0)
                    @foreach($waterReadingsList as $wr)
                    <tr>
                        <td>Consumo de agua - {{ strtoupper($wr['departament_number']) }}</td>
                        <td class="center">M3</td>
                        <td class="right">{{ number_format($wr['previous_reading'], 2) }}</td>
                        <td class="right">{{ number_format($wr['current_reading'], 2) }}</td>
                        <td class="right">{{ number_format($wr['consumption'], 2) }}</td>
                        <td class="right">S/ {{ number_format($wr['price_per_m3'], 2) }}</td>
                        <td class="right">S/ {{ number_format($wr['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                    @if(count($waterReadingsList) > 1)
                    <tr class="total">
                        <td colspan="4"><strong>TOTAL AGUA Y DESAGÜE</strong></td>
                        <td class="right"><strong>{{ number_format($totalWaterConsumption ?? 0, 2) }} M3</strong></td>
                        <td>&nbsp;</td>
                        <td class="right"><strong>S/ {{ number_format($waterAmount, 2) }}</strong></td>
                    </tr>
                    @endif
                @elseif($waterReading)
                    <tr>
                        <td>Agua y Desagüe - Dpto {{ $departament->number }}</td>
                        <td class="center">M3</td>
                        <td class="right">{{ number_format($waterReading->previous_reading, 2) }}</td>
                        <td class="right">{{ number_format($waterReading->current_reading, 2) }}</td>
                        <td class="right">{{ number_format($waterConsumption, 2) }}</td>
                        <td class="right">S/ {{ number_format($waterPricePerM3, 2) }}</td>
                        <td class="right">S/ {{ number_format($waterAmount == 0 ? ($waterConsumption * $waterPricePerM3) : $waterAmount, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        {{-- ═══════════ FOTOS DE CONTÓMETROS DE AGUA ═══════════ --}}
        @php
            $waterPhotos = collect($waterReadingsList ?? [])
                ->filter(fn ($w) => !empty($w['photo_src']) || !empty($w['photo_url']))
                ->values();

            if ($waterPhotos->isEmpty() && (!empty($waterReadingPhotoSrc) || !empty($waterReadingPhotoUrl))) {
                $waterPhotos = collect([[
                    'departament_number' => $departament->number,
                    'current_reading' => $waterReading->current_reading ?? null,
                    'photo_src' => $waterReadingPhotoSrc ?? $waterReadingPhotoUrl,
                    'photo_url' => $waterReadingPhotoUrl,
                ]]);
            }
        @endphp

        @if($waterPhotos->isNotEmpty())
        <div style="margin-top: 10px; margin-bottom: 2px; page-break-inside: avoid;">
            <div class="section-title" style="margin-bottom: 0px;">EVIDENCIA DE LECTURA DE CONTÓMETRO</div>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    @foreach($waterPhotos as $pIndex => $pItem)
                        <td style="width: {{ $waterPhotos->count() === 1 ? '100%' : '50%' }}; text-align: start; vertical-align: top; padding: 2px;">
                            <div style="border: 1px solid #d6d6d6; background-color: #f9fbfd; border-radius: 4px; padding: 4px;">
                                <div style="font-size: 8.5px; font-weight: bold; color: #226fb5; text-transform: uppercase; margin-bottom: 2px; margin-left: 2px;">
                                    {{ $pItem['departament_number'] }}
                                    @if(!empty($pItem['current_reading']))
                                        &bull; {{ number_format($pItem['current_reading'], 2) }} m3
                                    @endif
                                </div>
                                <div style="text-align: start;">
                                    <img src="{{ $pItem['photo_src'] ?? $pItem['photo_url'] }}"
                                         style="max-width: 100%; max-height: 155px; width: auto; height: auto; border-radius: 3px;"
                                         alt="Foto contómetro {{ $pItem['departament_number'] }}" />
                                </div>
                            </div>
                        </td>
                        @if(($pIndex + 1) % 2 === 0 && ($pIndex + 1) < $waterPhotos->count())
                            </tr><tr>
                        @endif
                    @endforeach
                </tr>
            </table>
        </div>
        @endif
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

        {{-- ═══════════ DETALLE DE GASTOS COMUNES DEL MES (PÁGINA 2) ═══════════ --}}
        @if(isset($expenses) && $expenses->count() > 0)
        <div class="page-break">
            <table class="page-header-secondary">
                <tr>
                    <td style="font-size: 10px; font-weight: bold; color: #1a1a1a; text-transform: uppercase;">
                        JUNTA DE PROPIETARIOS DEL EDIFICIO PACIFIK OCEAN TOWER &bull; ANEXO DE GASTOS COMUNES
                    </td>
                    <td style="text-align: right; font-size: 9.5px; color: #444;">
                        {{ strtoupper($monthLabel) }} {{ $year }} &bull; UI: <strong>{{ $departamentNumberDisplay ?? $departament->number }}</strong>
                    </td>
                </tr>
            </table>

            <div class="section-title">DETALLE DE GASTOS COMUNES DEL MES - {{ strtoupper($monthLabel) }} {{ $year }}</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 14%;">CATEGORÍA</th>
                        <th style="width: 32%;">DESCRIPCIÓN</th>
                        <th style="width: 18%;">PROVEEDOR</th>
                        <th class="right" style="width: 14%;">PRESUPUESTO</th>
                        <th class="right" style="width: 10%;">% PART.</th>
                        <th class="right" style="width: 12%;">TU CUOTA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses as $expense)
                    <tr>
                        <td>{{ strtoupper($expense->serviceCategory->name ?? 'SERVICIO') }}</td>
                        <td>{{ $expense->description ?? $expense->serviceCategory->name ?? '' }}</td>
                        <td>{{ $expense->provider->name ?? '-' }}</td>
                        <td class="right">{{ number_format($expense->amount, 2) }}</td>
                        <td class="right">{{ number_format($totalParticipation, 2) }}%</td>
                        <td class="right">S/ {{ number_format($expense->amount * $totalParticipation / 100, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="3"><strong>TOTAL GASTOS COMUNES</strong></td>
                        <td class="right">{{ number_format($totalExpenses, 2) }}</td>
                        <td class="right">{{ number_format($totalParticipation, 2) }}%</td>
                        <td class="right">S/ {{ number_format($totalExpenses * $totalParticipation / 100, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif

        {{-- ═══════════ FOOTER ═══════════ --}}
        <div class="footer">
            Este documento es un comprobante de pago generado por el sistema de gestión del Condominio Pacifik.<br>
            Generado el {{ date('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
