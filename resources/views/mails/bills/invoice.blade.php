<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Cuotas - {{ $departament->number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f5;
            margin: 0;
            padding: 0;
            color: #333333;
            line-height: 1.5;
        }
        .wrapper {
            width: 100%;
            padding: 40px 0;
            background-color: #f4f4f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            overflow: hidden;
        }
        .header {
            text-align: center;
            padding: 30px 20px 20px;
            border-bottom: 1px solid #e4e4e7;
            background-color: #18181b;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .header .subtitle {
            font-size: 12px;
            margin-top: 5px;
            color: #a1a1aa;
        }
        .content {
            padding: 30px;
        }
        .badge {
            display: inline-block;
            background-color: #18181b;
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }
        .section-title {
            font-size: 14px;
            text-transform: uppercase;
            color: #71717a;
            border-bottom: 1px solid #e4e4e7;
            padding-bottom: 5px;
            margin-top: 25px;
            margin-bottom: 15px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table td {
            padding: 8px 0;
            font-size: 14px;
            vertical-align: top;
        }
        .data-table td.label {
            width: 40%;
            color: #52525b;
            font-weight: 600;
        }
        .data-table td.value {
            width: 60%;
            color: #18181b;
        }
        .amount-box {
            background-color: #f4f4f5;
            border: 1px solid #e4e4e7;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
            margin-top: 20px;
        }
        .amount-box .amount {
            font-size: 24px;
            font-weight: bold;
            color: #18181b;
        }
        .amount-box .label {
            font-size: 12px;
            color: #71717a;
            text-transform: uppercase;
        }
        .download-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background-color: #18181b;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            text-align: center;
        }
        .footer {
            background-color: #fafafa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #a1a1aa;
            border-top: 1px solid #e4e4e7;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>Junta de Condominio Pacifik</h1>
                <div class="subtitle">Recibo de Cuotas de Gastos Comunes</div>
            </div>

            <div class="content">
                <div class="badge">UNIDAD {{ $departament->number }}</div>
                
                <div style="font-size: 16px; margin-bottom: 20px; color: #18181b;">
                    Estimado(a) <strong>{{ $owner->name ?? 'Propietario' }}</strong>,<br><br>
                    Se ha generado su recibo de cuotas correspondiente al periodo de <strong>{{ strtoupper($monthLabel) }} {{ $year }}</strong>.
                </div>

                <div class="section-title">Detalle del Recibo</div>
                <table class="data-table">
                    <tr>
                        <td class="label">Unidad:</td>
                        <td class="value">{{ $departament->number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Propietario:</td>
                        <td class="value">{{ $owner->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Periodo:</td>
                        <td class="value">{{ strtoupper($monthLabel) }} {{ $year }}</td>
                    </tr>
                    <tr>
                        <td class="label">Fecha de Emisión:</td>
                        <td class="value">{{ $emissionDate }}</td>
                    </tr>
                    <tr>
                        <td class="label">Fecha de Vencimiento:</td>
                        <td class="value">{{ $dueDate }}</td>
                    </tr>
                </table>

                <div class="section-title">Conceptos</div>
                <table class="data-table">
                    <tr>
                        <td class="label">Cuota de Gastos Comunes:</td>
                        <td class="value">S/ {{ number_format($maintenanceAmount, 2) }}</td>
                    </tr>
                    @if($waterAmount > 0)
                    <tr>
                        <td class="label">Consumo Individual (Agua):</td>
                        <td class="value">S/ {{ number_format($waterAmount, 2) }}</td>
                    </tr>
                    @endif
                </table>

                <div class="amount-box">
                    <div class="label">Total a Pagar</div>
                    <div class="amount">S/ {{ number_format($totalAmount, 2) }}</div>
                </div>

                <div style="text-align: center; margin-top: 25px;">
                    <a href="{{ $downloadUrl }}" class="download-btn" target="_blank">
                        &#128196; Descargar Recibo en PDF
                    </a>
                </div>

                <div style="font-size: 12px; color: #71717a; margin-top: 20px; text-align: center;">
                    <strong>Son:</strong> {{ $amountInWords }} SOLES
                </div>
            </div>

            <div class="footer">
                Este mensaje fue generado automáticamente por el sistema de gestión del Condominio Pacifik.<br>
                Por favor, no responda directamente a este correo.
            </div>
        </div>
    </div>
</body>
</html>
