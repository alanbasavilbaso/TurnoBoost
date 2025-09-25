# Configuración del Servidor para TurnoBoost

## Procesos en Segundo Plano Requeridos

Para que TurnoBoost funcione completamente en el servidor, necesitas configurar los siguientes procesos:

### 1. Worker de Mensajes (Messenger)

Este proceso maneja la cola de mensajes para envío de emails y WhatsApp:

```bash
php bin/console messenger:consume async -vv
```

**¿Qué hace?**
- Procesa mensajes de la cola `async`
- Envía emails de confirmación, recordatorios y cancelaciones
- Envía mensajes de WhatsApp
- Maneja reintentos automáticos en caso de fallos

**Configuración recomendada:**
- Debe ejecutarse como servicio permanente
- Reiniciar automáticamente si falla
- Usar supervisor o systemd para gestión

### 2. Procesador de Notificaciones Programadas

Este comando procesa las notificaciones que están programadas para enviarse:

```bash
php bin/console app:process-notifications
```

**¿Qué hace?**
- Busca notificaciones pendientes cuya fecha de envío ya llegó
- Las envía a la cola de mensajes para procesamiento inmediato
- Marca como canceladas las notificaciones de turnos cancelados
- Maneja recordatorios programados

**Configuración recomendada:**
- Ejecutar cada 1-2 minutos como cron job
- Ejemplo de crontab: `*/2 * * * * cd /path/to/project && php bin/console app:process-notifications`

### 3. Endpoint de Notificaciones de Errores de WhatsApp

El nuevo endpoint que implementamos: