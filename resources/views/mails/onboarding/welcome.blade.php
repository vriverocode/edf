<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido</title>
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
        .credentials {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #F7F1E4;
            border-radius: 8px;
            overflow: hidden;
        }
        .credentials td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #ECE3D2;
        }
        .credentials tr:last-child td {
            border-bottom: 0;
        }
        .credentials .label {
            color: #7C8A80;
            width: 38%;
        }
        .credentials .value {
            color: #1E3A2A;
            font-weight: bold;
            word-break: break-all;
        }
        .login-btn {
            display: inline-block;
            margin: 10px 0 20px;
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
                <div class="subtitle">Bienvenido a tu comunidad</div>
            </div>

            <div class="content">
                <p>Estimado(a) <strong>{{ $userName }}</strong>,</p>

                <p>Se creó tu cuenta en la plataforma de gestión de <strong>{{ $urbanismoName }}</strong>.</p>

                <p>Estas son tus credenciales de acceso:</p>

                <table class="credentials">
                    <tr>
                        <td class="label">Usuario</td>
                        <td class="value">{{ $username }}</td>
                    </tr>
                    <tr>
                        <td class="label">Contraseña temporal</td>
                        <td class="value">{{ $password }}</td>
                    </tr>
                    @if ($unidad)
                        <tr>
                            <td class="label">Unidad</td>
                            <td class="value">{{ $unidad }}</td>
                        </tr>
                    @endif
                </table>

                <div style="text-align: center;">
                    <a href="{{ $loginUrl }}" class="login-btn">
                        Ingresar a la plataforma
                    </a>
                </div>

                <div class="note">
                    <strong>Importante:</strong> al ingresar por primera vez deberás cambiar tu contraseña y confirmar tu número de teléfono.<br>
                    Si no esperabas este mensaje, puedes ignorarlo de forma segura.
                </div>
            </div>

            <div class="footer">
                Este correo fue enviado automáticamente por {{ $urbanismoName }}.
            </div>
        </div>
    </div>
</body>
</html>
