-- Migración: Agregar columna para rastrear envío de recordatorios de CV
-- Fecha: 2026-03-23
-- Descripción: Agrega la columna last_cv_reminder_sent a la tabla relatores
--              para automatizar el envío de recordatorios anuales de actualización de CV

ALTER TABLE relatores ADD COLUMN last_cv_reminder_sent DATETIME DEFAULT NULL COMMENT 'Fecha del último envío de recordatorio de actualización de CV';

-- Crear índice para mejorar performance de búsquedas
ALTER TABLE relatores ADD INDEX idx_last_cv_reminder (last_cv_reminder_sent);
