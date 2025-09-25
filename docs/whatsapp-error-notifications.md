# Sistema de Notificaciones de Errores de WhatsApp Service

## Configuración del Microservicio de WhatsApp

Para que el microservicio de WhatsApp envíe notificaciones de errores a TurnoBoost, necesitas configurar las siguientes variables de entorno en el microservicio:

### Variables de Entorno Requeridas

```bash
# URL completa del endpoint que recibirá las notificaciones
ERROR_NOTIFICATION_URL=https://tu-servidor.com/api/whatsapp/error-notifications

# Token de autenticación (debe coincidir con ERROR_NOTIFICATION_KEY en TurnoBoost)
ERROR_NOTIFICATION_KEY=your_secure_token_here_for_error_notifications
```

## Configuración en TurnoBoost

### Variables de Entorno

Agrega estas variables a tu archivo `.env` o `.env.local`:

```bash
# Token para autenticar las notificaciones del microservicio
ERROR_NOTIFICATION_KEY=your_secure_token_here_for_error_notifications

# Email donde se enviarán las notificaciones de errores
MAIL_WHEN_WHATSAPP_ERROR=tu-email@ejemplo.com
```

### Endpoint Implementado

- **URL**: `POST /api/whatsapp/error-notifications`
- **Autenticación**: Bearer token en header `Authorization`
- **Content-Type**: `application/json`

## Estructura de Datos

El microservicio enviará un POST con la siguiente estructura:

```json
{
  "timestamp": "2024-01-15T10:30:00.000Z",
  "service": "whatsapp-service",
  "phoneNumber": "542346505040",
  "type": "template_send_error",
  "appointmentId": "123",
  "phone": "5492346334077",
  "messageType": "confirmation",
  "error": {
    "message": "Cannot destructure property 'user' of 'jidDecode(...)'",
    "stack": "Error stack trace...",
    "code": "unknown"
  },
  "context": {
    "isBusinessAccount": false,
    "connectionState": "connected",
    "hasButtons": true
  }
}
```

## Tipos de Errores

- `template_send_error`: Error enviando template de cita médica
- `message_send_error`: Error enviando mensaje regular

## Comportamiento del Cliente

- Hace 3 intentos con backoff exponencial (2s, 4s, 8s)
- Timeout de 5 segundos por intento
- Si no están configuradas las variables de entorno, no envía notificaciones

## Respuestas Esperadas

- **Status 200-299**: Notificación recibida correctamente
- **Cualquier otro status**: Se considera error y se reintenta

## Pruebas

Puedes probar el endpoint usando el comando:

```bash
php bin/console app:test-whatsapp-error-notification
```

Este comando enviará una notificación de prueba al endpoint y verificará que funcione correctamente.

## Logs

Todos los errores se registran en los logs de Symfony para debugging adicional.