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
            line-height: 1.4;
        }
        .invoice-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header .building-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .header .document-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .header .location {
            font-size: 10px;
            color: #666;
        }
        .unit-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            padding: 10px;
        }
        .unit-info-box {
            flex: 1;
            padding: 5px;
        }
        .unit-info-box .label {
            font-size: 8px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 3px;
        }
        .unit-info-box .value {
            font-size: 11px;
            font-weight: bold;
        }
        .unit-info-box .value.large {
            font-size: 16px;
        }
        .section-title {
            font-size: 9px;
            text-transform: uppercase;
            font-weight: bold;
            background-color: #f0f0f0;
            padding: 5px 10px;
            margin: 10px 0 5px 0;
            border-left: 3px solid #333;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th,
        .data-table td {
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 10px;
        }
        .data-table th {
            background-color: #f8f8f8;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            color: #555;
        }
        .data-table td.number {
            text-align: right;
        }
        .data-table tr.total {
            font-weight: bold;
            border-top: 2px solid #333;
            background-color: #f8f8f8;
        }
        .amount-words {
            background-color: #f8f8f8;
            padding: 8px 10px;
            border: 1px solid #ddd;
            margin: 10px 0;
            font-style: italic;
            font-size: 10px;
        }
        .payment-info {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        .payment-info-box {
            flex: 1;
            border: 1px solid #ccc;
            padding: 8px;
        }
        .payment-info-box .box-title {
            font-size: 8px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .payment-info-box .box-value {
            font-size: 11px;
            font-weight: bold;
        }
        .unit-detail-section {
            margin: 15px 0;
            border: 1px solid #ccc;
            padding: 10px;
        }
        .unit-detail-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .unit-detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .unit-detail-table th,
        .unit-detail-table td {
            padding: 5px 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 9px;
        }
        .unit-detail-table th {
            background-color: #f8f8f8;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
        }
        .consumption-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .consumption-table th,
        .consumption-table td {
            padding: 5px 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 9px;
        }
        .consumption-table th {
            background-color: #f8f8f8;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
        }
        .consumption-table td.number {
            text-align: right;
        }
        .payment-history {
            margin-top: 15px;
        }
        .payment-history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-history-table th,
        .payment-history-table td {
            padding: 5px 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 9px;
        }
        .payment-history-table th {
            background-color: #f8f8f8;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
        }
        .payment-history-table td.number {
            text-align: right;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .highlight {
            background-color: #fffde7;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        {{-- Header --}}
        <div class="header">
            <div class="location">{{ $buildingAddress ?? 'LIMA - SAN MIGUEL - AV. BERTOLOTO 850' }}</div>
            <div class="building-name">JUNTA DE PROPIETARIOS DEL EDIFICIO PACIFIK OCEAN TOWER</div>
            <div class="document-title">RECIBO DE CUOTAS<br>DE GASTOS COMUNES</div>
        </div>

        {{-- Unit Info Row --}}
        <div class="unit-info-row">
            <div class="unit-info-box">
                <div class="label">UNIDAD INMOBILIARIA</div>
                <div class="value">N° {{ $departament->number }}</div>
            </div>
            <div class="unit-info-box">
                <div class="label">NOMBRE O RAZON SOCIAL</div>
                <div class="value">{{ $owner->name ?? 'N/A' }}</div>
            </div>
            <div class="unit-info-box">
                <div class="label">DNI O RUC</div>
                <div class="value">{{ $owner->document ?? '0' }}</div>
            </div>
        </div>

        {{-- Period & Dates --}}
        <div class="unit-info-row">
            <div class="unit-info-box">
                <div class="label">PERIODO</div>
                <div class="value">{{ strtoupper($monthLabel) }} {{ $year }}</div>
            </div>
            <div class="unit-info-box">
                <div class="label">EMISION</div>
                <div class="value">{{ $emissionDate }}</div>
            </div>
            <div class="unit-info-box">
                <div class="label">VENCIMIENTO</div>
                <div class="value">{{ $dueDate }}</div>
            </div>
            <div class="unit-info-box">
                <div class="label">REF. INTERNA</div>
                <div class="value">{{ $quota->id ?? '' }}</div>
            </div>
        </div>

        {{-- Charges Description --}}
        <div class="section-title">DESCRIPCION</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>DESCRIPCION</th>
                    <th style="text-align: right;">MONTO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>CUOTA DE GASTOS COMUNES - {{ strtoupper($monthLabel) }} {{ $year }}</td>
                    <td class="number">{{ number_format($maintenanceAmount, 2) }}</td>
                </tr>
                @if($waterAmount > 0)
                <tr>
                    <td>CONSUMO INDIVIDUAL (AGUA) - {{ strtoupper($previousMonthLabel) }} {{ $waterYear }}</td>
                    <td class="number">{{ number_format($waterAmount, 2) }}</td>
                </tr>
                @endif
                <tr class="total">
                    <td>TOTAL A PAGAR</td>
                    <td class="number">S/ {{ number_format($totalAmount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Amount in Words --}}
        <div class="amount-words">
            <strong>SON:</strong> {{ $amountInWords }} SOLES
        </div>

        {{-- Payment System Info --}}
        <div class="section-title">SISTEMA DE RECAUDACION</div>
        <div class="payment-info">
            <div class="payment-info-box">
                <div class="box-title">TRADICIONAL</div>
                <div class="box-value">PRESUPUESTO</div>
            </div>
            <div class="payment-info-box">
                <div class="box-title">SALDO INICIAL</div>
                <div class="box-value">{{ number_format($initialBalance, 2) }}</div>
            </div>
            <div class="payment-info-box">
                <div class="box-title">ABONOS DEL MES</div>
                <div class="box-value">{{ number_format($monthlyPayments, 2) }}</div>
            </div>
            <div class="payment-info-box">
                <div class="box-title">CUOTAS VENCIDAS</div>
                <div class="box-value">{{ $overdueQuotas }}</div>
            </div>
            <div class="payment-info-box">
                <div class="box-title">CUOTA DEL MES</div>
                <div class="box-value highlight">{{ number_format($totalAmount, 2) }}</div>
            </div>
            <div class="payment-info-box">
                <div class="box-title">DIAS VENCIDOS</div>
                <div class="box-value">{{ $overdueDays }}</div>
            </div>
            <div class="payment-info-box">
                <div class="box-title">SALDO FINAL</div>
                <div class="box-value">{{ number_format($finalBalance, 2) }}</div>
            </div>
        </div>

        {{-- Unit Detail --}}
        <div class="section-title">INFORMACION DE UNIDAD INMOBILIARIA</div>
        <div class="unit-detail-section">
            <div class="unit-detail-header">
                <div>
                    <span style="font-weight: bold;">UI</span>
                    <span style="font-size: 14px; font-weight: bold; margin-left: 10px;">{{ $departament->inter_number }}</span>
                </div>
            </div>
            <table class="unit-detail-table">
                <thead>
                    <tr>
                        <th>UI</th>
                        <th>DESCRIPCION</th>
                        <th>AREA OCUPADA</th>
                        <th>PARTICIPACION</th>
                        <th>% CUOTA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($units as $unit)
                    <tr>
                        <td>{{ $unit->inter_number }}</td>
                        <td>{{ $unit->type_label }}</td>
                        <td>{{ number_format($unit->area, 2) }} M2</td>
                        <td>{{ number_format($unit->participation_percentage * 100, 6) }}%</td>
                        <td>{{ number_format($unit->participation_percentage * 100, 6) }}%</td>
                    </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="2">TOTAL</td>
                        <td>{{ number_format($totalArea, 2) }} M2</td>
                        <td colspan="2">{{ number_format($totalParticipation * 100, 6) }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Individual Consumption --}}
        @if($waterReading)
        <div class="section-title">CONSUMO INDIVIDUAL</div>
        <table class="consumption-table">
            <thead>
                <tr>
                    <th>CONCEPTO</th>
                    <th>U. MEDIDA</th>
                    <th>LECTURA ANTERIOR</th>
                    <th>LECTURA ACTUAL</th>
                    <th>CONSUMO</th>
                    <th>COSTO UNITARIO</th>
                    <th style="text-align: right;">MONTO TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Agua y Desague</td>
                    <td>M3</td>
                    <td>{{ number_format($waterReading->previous_reading, 2) }}</td>
                    <td>{{ number_format($waterReading->current_reading, 2) }}</td>
                    <td>{{ number_format($waterConsumption, 2) }}</td>
                    <td>{{ number_format($waterPricePerM3, 2) }}</td>
                    <td class="number">{{ number_format($waterAmount, 2) }}</td>
                </tr>
                <tr>
                    <td>Electricidad</td>
                    <td>KWH</td>
                    <td>0.00</td>
                    <td>0.00</td>
                    <td>0.00</td>
                    <td>1.800</td>
                    <td class="number">0.00</td>
                </tr>
                <tr class="total">
                    <td colspan="6">MONTO TOTAL</td>
                    <td class="number">{{ number_format($waterAmount, 2) }}</td>
                </tr>
            </tbody>
        </table>
        @endif

        {{-- Payment History --}}
        @if(count($paymentHistory) > 0)
        <div class="section-title">ULTIMOS {{ count($paymentHistory) }} ABONOS</div>
        <table class="payment-history-table">
            <thead>
                <tr>
                    <th>FECHA PAGO</th>
                    <th>DESCRIPCION</th>
                    <th style="text-align: right;">MONTO</th>
                    <th style="text-align: right;">INTERESES</th>
                    <th style="text-align: right;">TOTAL PAGADO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paymentHistory as $payment)
                <tr>
                    <td>{{ $payment['date'] }}</td>
                    <td>{{ $payment['description'] }}</td>
                    <td class="number">{{ number_format($payment['amount'], 2) }}</td>
                    <td class="number">{{ number_format($payment['interest'], 2) }}</td>
                    <td class="number">{{ number_format($payment['total_paid'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        {{-- Footer --}}
        <div class="footer">
            Este documento es un comprobante de pago generado por el sistema de gestión del Condominio Pacifik.<br>
            Powered by TCPDF (www.tcpdf.org)
        </div>
    </div>
</body>
</html>