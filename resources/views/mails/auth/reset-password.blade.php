<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
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
        .content p {
            font-size: 15px;
            color: #3f3f46;
            margin: 0 0 15px;
        }
        .reset-btn {
            display: inline-block;
            margin: 20px 0;
            padding: 14px 32px;
            background-color: #c8a34b;
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            border-radius: 6px;
            text-align: center;
            letter-spacing: 0.3px;
        }
        .reset-btn:hover {
            background-color: #b8923d;
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
                <h1>Junta de Condominio Pacifik</h1>
                <div class="subtitle">Restablecimiento de contraseña</div>
            </div>

            <div class="content">
                <p>Estimado(a) <strong>{{ $userName }}</strong>,</p>

                <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en la plataforma de <strong>Pacifik</strong>.</p>

                <p>Haz clic en el siguiente botón para crear una nueva contraseña:</p>

                <div style="text-align: center;">
                    <a href="{{ $resetUrl }}" class="reset-btn">
                        Restablecer contraseña
                    </a>
                </div>

                <p style="font-size: 13px; color: #71717a; margin-top: 20px;">
                    Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:
                </p>
                <p style="font-size: 12px; color: #c8a34b; word-break: break-all;">
                    {{ $resetUrl }}
                </p>

                <div class="note">
                    <strong>Importante:</strong> Este enlace expirará en 60 minutos por razones de seguridad.<br>
                    Si no solicitaste este cambio, puedes ignorar este mensaje de forma segura.
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
