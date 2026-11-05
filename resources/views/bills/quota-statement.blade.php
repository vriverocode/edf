<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de cuenta de cuotas</title>
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

        .statement {
            width: 96%;
            margin: 0 auto;
            padding: 8px 0 18px 0;
        }

        .page-break {
            page-break-after: always;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #226fb5;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: top;
            padding-bottom: 8px;
        }

        .building-name {
            font-size: 15px;
            font-weight: bold;
            color: #226fb5;
            letter-spacing: 0.4px;
        }

        .document-title {
            font-size: 11px;
            font-weight: bold;
            color: #1a1a1a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .right {
            text-align: right;
        }

        .period {
            font-size: 13px;
            font-weight: bold;
            color: #1a1a1a;
        }

        .small {
            font-size: 8.5px;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            color: #fff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info-table td {
            padding: 3px 6px;
            vertical-align: top;
            border: 1px solid #e5e7eb;
        }

        .info-label {
            width: 18%;
            background-color: #f3f6fa;
            color: #555;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            width: 32%;
            font-size: 9.5px;
            color: #1a1a1a;
        }

        .amounts-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .amounts-table th {
            background-color: #226fb5;
            color: #fff;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 5px 6px;
            text-align: left;
        }

        .amounts-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9.5px;
        }

        .amounts-table .right {
            text-align: right;
        }

        .amounts-table .total-row td {
            border-top: 2px solid #226fb5;
            border-bottom: none;
            font-size: 11px;
            font-weight: bold;
            color: #1a1a1a;
        }

        .credit-box {
            border: 1px solid #86efac;
            background-color: #f0fdf4;
            padding: 6px 8px;
            margin-bottom: 10px;
        }

        .credit-box .row {
            width: 100%;
            border-collapse: collapse;
        }

        .credit-box .row td {
            padding: 2px 0;
            font-size: 9.5px;
        }

        .credit-box .highlight {
            font-size: 11px;
            font-weight: bold;
            color: #166534;
        }

        .payment-box {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            padding: 6px 8px;
            margin-bottom: 10px;
        }

        .payment-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #226fb5;
            margin-bottom: 4px;
        }

        .payment-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-grid td {
            padding: 2px 0;
            font-size: 9px;
            vertical-align: top;
        }

        .payment-grid .label {
            color: #666;
        }

        .payment-grid .value {
            color: #1a1a1a;
            font-weight: bold;
        }

        .note {
            font-size: 8px;
            color: #666;
            margin-top: 3px;
        }

        .pending-box {
            border: 1px solid #fcd34d;
            background-color: #fffbeb;
            padding: 6px 8px;
            margin-bottom: 10px;
        }

        .pending-box .label {
            font-size: 9px;
            color: #92400e;
        }

        .pending-box .amount {
            font-size: 12px;
            font-weight: bold;
            color: #92400e;
        }

        .footer {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            font-size: 7.5px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    @foreach ($quotas as $item)
        @php
            $statusColors = [
                0 => '#6b7280',
                1 => '#d97706',
                2 => '#d97706',
                3 => '#16a34a',
                4 => '#dc2626',
            ];
            $statusColor = $statusColors[$item['status']] ?? '#6b7280';
            $unitNumber = $item['departament']?->number ?? '-';
            $unitType = $item['departament']?->type_label ?? '-';
            $ownerName = $item['owner']?->name ?? '-';
            $responsibleName = $item['responsible']?->name ?? $ownerName;
            $extraAmount = (float) ($item['extra_amount'] ?? 0);
        @endphp

        <div class="statement {{ ! $loop->last ? 'page-break' : '' }}">
            <table class="header-table">
                <tr>
                    <td>
                        <div class="building-name">CONDOMINIO PACIFIK</div>
                        <div class="document-title">Estado de cuenta de cuotas</div>
                    </td>
                    <td class="right">
                        <div class="period">{{ $item['period_label'] }}</div>
                        <div class="small">Emitido: {{ $emissionDate }}</div>
                    </td>
                </tr>
            </table>

            <table class="info-table">
                <tr>
                    <td class="info-label">Unidad</td>
                    <td class="info-value">{{ $unitNumber }} ({{ $unitType }})</td>
                    <td class="info-label">Vencimiento</td>
                    <td class="info-value">{{ $item['due_date'] }}</td>
                </tr>
                <tr>
                    <td class="info-label">Propietario</td>
                    <td class="info-value">{{ $ownerName }}</td>
                    <td class="info-label">Estado</td>
                    <td class="info-value">
                        <span class="status-badge" style="background-color: {{ $statusColor }};">
                            {{ $item['status_label'] }}
                        </span>
                    </td>
                </tr>
                @if ($item['is_tenant'])
                    <tr>
                        <td class="info-label">Inquilino</td>
                        <td class="info-value">{{ $responsibleName }}</td>
                        <td class="info-label">Periodo</td>
                        <td class="info-value">{{ $item['period_label'] }}</td>
                    </tr>
                @endif
            </table>

            <table class="amounts-table">
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th class="right">Detalle</th>
                        <th class="right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mantenimiento</td>
                        <td class="right">Cuota mensual</td>
                        <td class="right">S/ {{ number_format($item['maintenance_amount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Agua</td>
                        <td class="right">
                            @if ($item['water_consumption'] > 0)
                                {{ number_format($item['water_consumption'], 2) }} m³ × S/ {{ number_format($item['water_price_per_m3'], 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="right">S/ {{ number_format($item['water_amount'], 2) }}</td>
                    </tr>
                    @if ($extraAmount > 0)
                        <tr>
                            <td>Cargos y multas</td>
                            <td class="right">Cuotas adicionales</td>
                            <td class="right">S/ {{ number_format($extraAmount, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td colspan="2">TOTAL DE LA CUOTA</td>
                        <td class="right">S/ {{ number_format($item['total_amount'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

            @if ($item['is_paid'] && $item['payment'])
                <div class="payment-box">
                    <div class="payment-title">Pago registrado</div>
                    <table class="payment-grid">
                        <tr>
                            <td class="label">Fecha de pago</td>
                            <td class="value">{{ $item['payment']['date'] }}</td>
                            <td class="label">Método</td>
                            <td class="value">{{ $item['payment']['method'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Referencia</td>
                            <td class="value">{{ $item['payment']['reference'] }}</td>
                            <td class="label">Monto del pago</td>
                            <td class="value">S/ {{ number_format($item['payment']['amount'], 2) }}</td>
                        </tr>
                        @if ($item['payment']['credit_applied'] > 0)
                            <tr>
                                <td class="label">Saldo a favor aplicado</td>
                                <td class="value">S/ {{ number_format($item['payment']['credit_applied'], 2) }}</td>
                                <td class="label"></td>
                                <td class="value"></td>
                            </tr>
                        @endif
                    </table>
                    @if ($item['payment']['consolidated_count'] > 1)
                        <div class="note">
                            El monto corresponde a un pago consolidado de
                            {{ $item['payment']['consolidated_count'] }} cuotas.
                        </div>
                    @endif
                </div>
            @elseif (! $item['is_paid'])
                @if ($item['credit_available'] > 0)
                    <div class="credit-box">
                        <table class="row">
                            <tr>
                                <td>Saldo a favor disponible para esta cuota</td>
                                <td class="right">- S/ {{ number_format($item['credit_available'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="highlight">Monto a pagar</td>
                                <td class="right highlight">S/ {{ number_format($item['amount_after_credit'], 2) }}</td>
                            </tr>
                        </table>
                    </div>
                @else
                    <div class="pending-box">
                        <table class="row" style="width: 100%;">
                            <tr>
                                <td class="label">Monto pendiente de pago</td>
                                <td class="right amount">S/ {{ number_format($item['amount_after_credit'], 2) }}</td>
                            </tr>
                        </table>
                    </div>
                @endif
            @endif

            <div class="footer">
                Documento informativo generado por el sistema de gestión del Condominio Pacifik.
            </div>
        </div>
    @endforeach
</body>
</html>
