<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamo de Pago</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            color: #1f2937;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .header {
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }
        .header img {
            max-height: 60px;
            width: auto;
        }
        .content {
            padding: 32px 24px;
        }
        .ticket-badge {
            display: inline-block;
            background-color: #eff6ff;
            color: #2563eb;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .title {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 24px 0;
            color: #111827;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 15px;
        }
        .data-table th {
            color: #6b7280;
            font-weight: 500;
            width: 40%;
        }
        .data-table td {
            color: #111827;
            font-weight: 600;
        }
        .description-box {
            margin-top: 24px;
            background-color: #f9fafb;
            padding: 16px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .description-box p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: #4b5563;
        }
        .footer {
            padding: 24px;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Logo de la Junta de Condominio -->
        <div class="header">
            <!-- Reemplazar src con la URL absoluta del logo del condominio -->
            <img src="{{ asset('images/logo-condominio.png') }}" alt="Junta de Propietarios">
        </div>

        <div class="content">
            <div class="ticket-badge">
                Caso #{{ $claimData['ticket_id'] ?? strtoupper(Str::random(8)) }}
            </div>

            <h2 class="title">Nuevo Reclamo de Pago Registrado</h2>

            <table class="data-table">
                <tr>
                    <th>Residente / Propietario:</th>
                    <td>{{ $claimData['resident_name'] }}</td>
                </tr>
                <tr>
                    <th>Unidad / Departamento:</th>
                    <td>{{ $claimData['apartment_unit'] }}</td>
                </tr>
                <tr>
                    <th>Monto Reclamado:</th>
                    <!-- Interpolación del monto. Ajustado al contexto monetario. -->
                    <td>S/ {{ number_format($claimData['amount'], 2) }}</td>
                </tr>
                <tr>
                    <th>Fecha de Pago Indicada:</th>
                    <td>{{ $claimData['payment_date'] }}</td>
                </tr>
                <tr>
                    <th>Nº de Referencia / Operación:</th>
                    <td>{{ $claimData['reference_number'] }}</td>
                </tr>
                <tr>
                    <th>Banco Emisor:</th>
                    <td>{{ $claimData['bank_name'] }}</td>
                </tr>
            </table>    

            <div class="description-box">
                <strong>Observaciones del Residente:</strong><br><br>
                <p>{!! nl2br(e($claimData['description'])) !!}</p>
            </div>
        </div>

        <div class="footer">
            Este es un correo generado automáticamente por el Sistema de Gestión del Condominio.<br>
            Por favor, no responda a esta dirección.
        </div>
    </div>
</body>
</html>