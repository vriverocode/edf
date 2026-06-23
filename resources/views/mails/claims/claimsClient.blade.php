<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Reclamo - Pacifik</title>
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
        }
        .header img {
            max-width: 120px;
            height: auto;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #18181b;
            font-weight: 600;
            letter-spacing: 0.5px;
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
        .description-box {
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            padding: 15px;
            border-radius: 4px;
            font-size: 14px;
            color: #3f3f46;
            margin-top: 10px;
            white-space: pre-wrap;
        }
        .voucher-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #f4f4f5;
            color: #18181b;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            border: 1px solid #d4d4d8;
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
                <img src="https://website-a40e47dc.gtq.fvz.mybluehost.me/public/images/logo/logo.png" style="width: 50px;" alt="Logo Pacifik">
                <h1>Junta de Condominio Pacifik</h1>
            </div>

            <div class="content">
                <div class="badge">CASO #{{ $claimData['sequence'] }}</div>
                
                <div style="font-size: 16px; margin-bottom: 20px; color: #18181b;">
                    Se ha registrado un nuevo <strong>{{ $claimData['claim_type'] }}</strong> en el sistema con fecha <strong>{{ $claimData['createDate'] }}</strong>.
                </div>

                <div class="section-title">Datos del Residente</div>
                <table class="data-table">
                    <tr>
                        <td class="label">Nombre Completo:</td>
                        <td class="value">{{ $claimData['fullname'] }}</td>
                    </tr>
                    <tr>
                        <td class="label">Documento ({{ $claimData['doctype'] }}):</td>
                        <td class="value">{{ $claimData['document'] }}</td>
                    </tr>
                    <tr>
                        <td class="label">Ubicación:</td>
                        <td class="value">Piso {{ $claimData['floor'] }}, Dpto. {{ $claimData['departament'] }}</td>
                    </tr>
                    <tr>
                        <td class="label">Contacto:</td>
                        <td class="value">{{ $claimData['phone'] }}<br>{{ $claimData['email'] }}</td>
                    </tr>
                </table>

                <div class="section-title">Información del Servicio</div>
                <table class="data-table">
                    <tr>
                        <td class="label">Tipo de Servicio:</td>
                        <td class="value">{{ $claimData['service_type'] }}</td>
                    </tr>
                    <tr>
                        <td class="label">Nº de Servicio:</td>
                        <td class="value">{{ $claimData['service_number'] }}</td>
                    </tr>
                    <tr>
                        <td class="label">Fecha del Servicio:</td>
                        <td class="value">{{ $claimData['service_date'] }}</td>
                    </tr>
                </table>

                <div class="section-title">Detalles del Reclamo</div>
                <table class="data-table">
                    <tr>
                        <td class="label">Tipo de Solicitud:</td>
                        <td class="value" style="text-transform: capitalize;">{{ $claimData['claim_type'] }}</td>
                    </tr>
                    <tr>
                        <td class="label">Monto Involucrado:</td>
                        <td class="value">{{ number_format($claimData['amount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Fecha Indicada:</td>
                        <td class="value">{{ $claimData['claim_date'] }}</td>
                    </tr>
                </table>

                <div class="section-title">Descripción del Caso</div>
                <div class="description-box">{!! nl2br(e($claimData['claim_description'])) !!}</div>

                @if(!empty($claimData['claim_vaucher']))
                <div style="text-align: center;">
                    <a href="{{ asset($claimData['claim_vaucher']) }}" class="voucher-btn" target="_blank">
                        &#128206; Ver Comprobante Adjunto
                    </a>
                </div>
                @endif

            </div>

            <div class="footer">
                Este mensaje fue generado automáticamente por el sistema de gestión del Condominio Pacifik.<br>
                Por favor, no responda directamente a este correo.
            </div>

        </div>
    </div>
</body>
</html>