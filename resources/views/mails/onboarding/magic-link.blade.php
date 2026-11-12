<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activa tu cuenta</title>
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
            background-color: #2E4433;
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
            color: #CBD8C8;
        }
        .content {
            padding: 30px;
        }
        .content p {
            font-size: 15px;
            color: #3f3f46;
            margin: 0 0 15px;
        }
        .activate-btn {
            display: inline-block;
            margin: 20px 0;
            padding: 14px 32px;
            background-color: #E3A33C;
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            border-radius: 6px;
            text-align: center;
            letter-spacing: 0.3px;
        }
        .activate-btn:hover {
            background-color: #D9972E;
        }
        .note {
            font-size: 12px;
            color: #71717a;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e4e4e7;
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
                <h1>{{ $urbanismoName }}</h1>
                <div class="subtitle">Activación de cuenta</div>
            </div>

            <div class="content">
                <p>Estimado(a) <strong>{{ $userName }}</strong>,</p>

                <p>Se creó una cuenta para ti en la plataforma de gestión de <strong>{{ $urbanismoName }}</strong>.</p>

                <p>Para ingresar, crea tu contraseña definitiva haciendo clic en el siguiente botón:</p>

                <div style="text-align: center;">
                    <a href="{{ $activationUrl }}" class="activate-btn">
                        Crear mi contraseña
                    </a>
                </div>

                <p style="font-size: 13px; color: #71717a; margin-top: 20px;">
                    Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:
                </p>
                <p style="font-size: 12px; color: #A8573F; word-break: break-all;">
                    {{ $activationUrl }}
                </p>

                <div class="note">
                    <strong>Importante:</strong> Este enlace expirará en {{ $expiresInDays }} días por razones de seguridad.<br>
                    Si no esperabas este mensaje, puedes ignorarlo de forma segura.
                </div>
            </div>

            <div class="footer">
                Este mensaje fue generado automáticamente por el sistema de gestión de {{ $urbanismoName }}.<br>
                Por favor, no responda directamente a este correo.
            </div>
        </div>
    </div>
</body>
</html>
