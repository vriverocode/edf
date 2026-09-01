# Endpoint: Notificación de actualización de app

## Descripción
Endpoint público para enviar notificaciones push a todos los usuarios alertando sobre una nueva versión de la app PACIFIK.

## Endpoint
```
POST /api/notify-update-app
```
**Sin autenticación** — público.

## Respuesta exitosa
```json
{
  "code": 200,
  "data": {
    "message": "Notificaciones enviadas correctamente.",
    "users_notified": 45
  }
}
```

## Respuesta sin usuarios
```json
{
  "code": 404,
  "error": "No hay usuarios con token de notificación registrado."
}
```

## Uso
```bash
curl -X POST https://tu-dominio/api/notify-update-app
```

## Flujo
1. Admin llama `POST /api/notify-update-app`
2. Backend busca usuarios activos (`status=1`) con `device_token` no nulo
3. Envía push vía FCM usando `RealtimeNotification` con:
   - **Título:** "Nueva versión de PACIFIK disponible"
   - **Mensaje:** "Actualiza para seguir usando la app"
   - **URL:** `https://github.com/vriverocode/edf/releases/download/apk/pacifik.apk`
4. Usuario toca la notificación → se abre el link en el navegador
5. Usuario descarga e instala el APK

## Archivos
| Archivo | Descripción |
|---------|-------------|
| `app/Http/Controllers/Api/AppUpdateController.php` | Controller con método `notifyUpdateApp()` |
| `routes/api.php` | Ruta pública `POST /notify-update-app` |

## Notas
- No requiere autenticación (público)
- Solo notifica a usuarios con `device_token` registrado
- El frontend existente (`pushNotifications.js`) ya maneja la apertura de URLs al tocar notificaciones
- No se requieren cambios en el frontend
