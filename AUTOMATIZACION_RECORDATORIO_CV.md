# Configuración de Automatización: Recordatorio Anual de CV para Relatores

## Descripción

Este sistema envía recordatorios por correo a los relatores para que actualicen su CV cada año. El recordatorio incluye:

- Estado actual de su CV en el sistema
- Instrucciones para actualizar su CV
- Enlace al sistema Khironex

## Paso 1: Actualizar la Base de Datos

Ejecuta el siguiente SQL en tu gestor de base de datos (phpMyAdmin, HeidiSQL, etc.):

```sql
ALTER TABLE relatores ADD COLUMN last_cv_reminder_sent DATETIME DEFAULT NULL COMMENT 'Fecha del último envío de recordatorio de actualización de CV';
ALTER TABLE relatores ADD INDEX idx_last_cv_reminder (last_cv_reminder_sent);
```

Alternativamente, usa el archivo SQL preparado:

- Ruta: `sql/20260323_add_cv_reminder_tracking.sql`

## Paso 2: Verificar Configuración de Correos

El sistema usa la función PHP `mail()` nativa. Asegúrate de que:

### En Windows (XAMPP):

1. Abre `php.ini` (normalmente en `c:\xampp\php\php.ini`)
2. Busca la sección `[mail function]`
3. Configura:
   ```ini
   sendmail_path = "C:\xampp\mailhog\MailHogSendmail.exe"
   ; o si usas SMTP:
   SMTP = smtp.tuservidor.com
   smtp_port = 587
   ```
4. Reinicia Apache

### En Linux:

La mayoría de servidores Linux ya tiene `sendmail` o `postfix` configurado.

## Paso 3: Configurar la Automatización

### Opción A: Linux con Cron (Recomendado)

1. Abre la tabla cron:

   ```bash
   crontab -e
   ```

2. Agrega esta línea para ejecutar cada domingo a las 9:00 AM:

   ```bash
   0 9 * * 0 cd /var/www/html/khironex && php scripts/send_relator_cv_reminder.php >> logs/relator_reminders.log 2>&1
   ```

3. Otros ejemplos de horarios:
   - **Diariamente a las 9 AM**: `0 9 * * * cd /var/www/html/khironex && php scripts/send_relator_cv_reminder.php`
   - **Primer día de cada mes a las 8 AM**: `0 8 1 * * cd /var/www/html/khironex && php scripts/send_relator_cv_reminder.php`
   - **Cada lunes a las 10 AM**: `0 10 * * 1 cd /var/www/html/khironex && php scripts/send_relator_cv_reminder.php`

### Opción B: Windows con Task Scheduler

1. Abre **Task Scheduler** (Programador de tareas)
2. Haz clic en **Create Basic Task** (Crear tarea básica)
3. Nombre: `Khironex - Recordatorio de CV para Relatores`
4. Descripción: `Envía recordatorios anuales a relatores para actualizar CV`
5. En **Triggers**, selecciona:
   - **Frecuencia**: Weekly (Semanal) / Daily (Diario) / Monthly (Mensual)
   - **Día**: Domingo
   - **Hora**: 09:00 AM
6. En **Actions**, configura:
   - **Program/script**: `C:\xampp\php\php.exe`
   - **Add arguments**: `-f "C:\xampp\htdocs\khironex\scripts\send_relator_cv_reminder.php"`
   - **Start in**: `C:\xampp\htdocs\khironex`
7. Haz clic en **Finish**

### Opción C: Manual (Pruebas)

Para probar el script antes de automatizarlo:

```bash
# En Windows (Command Prompt o PowerShell)
cd C:\xampp\htdocs\khironex
C:\xampp\php\php.exe scripts/send_relator_cv_reminder.php

# En Linux
cd /var/www/html/khironex
php scripts/send_relator_cv_reminder.php
```

## Monitoreo y Logs

El script genera información en la consola/terminal. Para guardar los logs:

```bash
# Linux - agregar a crontab
0 9 * * 0 cd /var/www/html/khironex && php scripts/send_relator_cv_reminder.php >> logs/relator_reminders.log 2>&1

# Windows - redirigir salida en Task Scheduler
# Usa PowerShell con argumentos adicionales
```

## Verificación de Envío

1. **Verifica en la BD**:

   ```sql
   SELECT nombre_relator, email, last_cv_reminder_sent
   FROM relatores
   WHERE last_cv_reminder_sent IS NOT NULL
   ORDER BY last_cv_reminder_sent DESC;
   ```

2. **Revisa el correo** de un relator de prueba para confirmar que llega el correo

3. **Si no llega el correo**:
   - Verifica en phpMyAdmin que `last_cv_reminder_sent` se actualizó (significa que el script funcionó)
   - Revisa el servidor SMTP desde el que se envía
   - Comprueba si el correo llegó a spam/basura

## Campos de Búsqueda Dinámicos

El script detecta automáticamente:

- El nombre de la columna del ID del relator (id_relator, id, etc.)
- El nombre de la columna del nombre del relator (nombre_relator, full_name, name, etc.)

Esto lo hace compatible con diferentes esquemas de base de datos.

## Personalización

### Cambiar Remitente

En `scripts/send_relator_cv_reminder.php`, cambia:

```php
define('MAIL_FROM', 'noreply@khironex.local');
define('MAIL_FROM_NAME', 'Sistema Khironex');
define('SUBJECT', 'Recordatorio: Actualiza tu CV en el sistema');
```

### Cambiar Contenido del Correo

La estructura del HTML del correo está en la variable `$body` del script. Puedes editarlo para personalizarlo.

### Cambiar Período de Recordatorio

Actualmente es cada 1 año. Para cambiarlo, modifica esta línea:

```php
$oneYearAgo = date('Y-m-d H:i:s', strtotime('-1 year'));  // Cambia '-1 year' a lo que necesites
```

## Solución de Problemas

### "Columna 'last_cv_reminder_sent' no existe"

- Ejecuta el SQL de migración: [Paso 1](#paso-1-actualizar-la-base-de-datos)

### Los correos no se envían

- Verifica que `last_cv_reminder_sent` se actualiza en la BD (el script está funcionando)
- Configura un servidor SMTP válido en php.ini
- En desarrollo, prueba con [MailHog](https://github.com/mailhog/MailHog)

### Solo se envía a algunos relatores

- Verifica que tengan email en el sistema
- Comprueba que no tuvieron un recordatorio en el último año

### Script se ejecuta pero no ve relatores

```sql
-- Verifica que haya relatores con email
SELECT COUNT(*) FROM relatores WHERE email IS NOT NULL AND email != '';

-- Verifica últimos recordatorios
SELECT nombre_relator, email, last_cv_reminder_sent FROM relatores LIMIT 10;
```

## Registros de Auditoría

Para mantener un historial de recordatorios, considera crear una tabla adicional:

```sql
CREATE TABLE IF NOT EXISTS relator_cv_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    relator_id INT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    email VARCHAR(120),
    status ENUM('sent', 'failed', 'skipped') DEFAULT 'sent',
    notes TEXT,
    FOREIGN KEY (relator_id) REFERENCES relatores(id_relator) ON DELETE CASCADE,
    INDEX idx_relator (relator_id),
    INDEX idx_sent_at (sent_at)
);
```

Luego puedes registrar cada intento de envío:

```php
// Agregar después de cada intento de envío
$insertLog = $db->prepare("INSERT INTO relator_cv_reminders (relator_id, email, status, notes) VALUES (?, ?, ?, ?)");
```

## Soporte

Si encuentras problemas:

1. Verifica los logs del sistema
2. Prueba el script manualmente desde la línea de comandos
3. Revisa la configuración SMTP/mail en php.ini
4. Comprueba que los relatores tengan email registrado
