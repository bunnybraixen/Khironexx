-- Actualización única para la base local Khironexx que ya tenía el esquema
-- antiguo del módulo Onboarding. Conserva y respalda las tablas existentes.
-- Ejecutar una vez en la base `khironex` antes de volver a crear un proceso.
-- No usar en cPanel ni en una instalación limpia; para esas bases usar
-- onboarding_migration.sql.

-- Copias de seguridad previas. Estas tablas no incluyen relaciones, pero sí
-- conservan las columnas y los registros originales para poder recuperarlos.
CREATE TABLE onboarding_processes_backup_20261119 LIKE onboarding_processes;
INSERT INTO onboarding_processes_backup_20261119 SELECT * FROM onboarding_processes;
CREATE TABLE onboarding_phases_backup_20261119 LIKE onboarding_phases;
INSERT INTO onboarding_phases_backup_20261119 SELECT * FROM onboarding_phases;
CREATE TABLE onboarding_phase_templates_backup_20261119 LIKE onboarding_phase_templates;
INSERT INTO onboarding_phase_templates_backup_20261119 SELECT * FROM onboarding_phase_templates;
CREATE TABLE onboarding_task_templates_backup_20261119 LIKE onboarding_task_templates;
INSERT INTO onboarding_task_templates_backup_20261119 SELECT * FROM onboarding_task_templates;
CREATE TABLE onboarding_phase_tasks_backup_20261119 LIKE onboarding_phase_tasks;
INSERT INTO onboarding_phase_tasks_backup_20261119 SELECT * FROM onboarding_phase_tasks;
CREATE TABLE onboarding_history_backup_20261119 LIKE onboarding_history;
INSERT INTO onboarding_history_backup_20261119 SELECT * FROM onboarding_history;

-- Alinear nombres y campos de procesos con el código actual.
ALTER TABLE onboarding_processes
    DROP FOREIGN KEY fk_onb_practitioner,
    DROP FOREIGN KEY fk_onb_responsible,
    DROP FOREIGN KEY fk_onb_manager,
    DROP FOREIGN KEY fk_onb_buddy,
    DROP FOREIGN KEY fk_onb_backup_buddy,
    CHANGE COLUMN practitioner_id practicante_id INT(11) NOT NULL,
    CHANGE COLUMN manager_user_id supervisor_user_id INT(11) NOT NULL,
    MODIFY COLUMN responsible_user_id INT(11) NOT NULL,
    MODIFY COLUMN estimated_end_date DATE NOT NULL,
    MODIFY COLUMN modality VARCHAR(20) NOT NULL DEFAULT 'presencial',
    MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    ADD COLUMN cancellation_reason TEXT DEFAULT NULL,
    ADD COLUMN cancelled_by INT(11) DEFAULT NULL,
    ADD COLUMN cancelled_at DATETIME DEFAULT NULL,
    ADD COLUMN created_by INT(11) NOT NULL,
    ADD COLUMN active_practicante_id INT(11) GENERATED ALWAYS AS (
        CASE WHEN status IN ('completed', 'cancelled') THEN NULL ELSE practicante_id END
    ) STORED,
    ADD UNIQUE KEY uq_onboarding_active_practicante (active_practicante_id),
    ADD CONSTRAINT fk_onboarding_practicante FOREIGN KEY (practicante_id) REFERENCES practicantes(id),
    ADD CONSTRAINT fk_onboarding_responsible FOREIGN KEY (responsible_user_id) REFERENCES usuarios(id),
    ADD CONSTRAINT fk_onboarding_supervisor FOREIGN KEY (supervisor_user_id) REFERENCES usuarios(id),
    ADD CONSTRAINT fk_onboarding_buddy FOREIGN KEY (buddy_user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_onboarding_backup_buddy FOREIGN KEY (backup_buddy_user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_onboarding_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_onboarding_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id);

-- Actualizar las siete plantillas existentes sin reemplazar sus nombres.
ALTER TABLE onboarding_phase_templates
    CHANGE COLUMN phase_number phase_order TINYINT(3) UNSIGNED NOT NULL,
    CHANGE COLUMN name title VARCHAR(150) NOT NULL,
    ADD COLUMN phase_key VARCHAR(60) DEFAULT NULL,
    ADD COLUMN start_offset_days SMALLINT NOT NULL DEFAULT 0,
    ADD COLUMN end_offset_days SMALLINT NOT NULL DEFAULT 0;

-- Algunas versiones guardaban el orden como 0..6. Mover primero los valores
-- para evitar colisiones con el índice único al convertirlos a 1..7.
UPDATE onboarding_phase_templates SET phase_order = phase_order + 10
WHERE phase_order BETWEEN 0 AND 6;
UPDATE onboarding_phase_templates SET phase_order = phase_order - 9
WHERE phase_order BETWEEN 10 AND 16;

-- Usar claves temporales evita colisiones con el índice único si una ejecución
-- anterior ya había asignado parcialmente claves a fases con orden 0..6.
UPDATE onboarding_phase_templates SET phase_key = CONCAT('legacy_phase_', id);

UPDATE onboarding_phase_templates SET
    phase_key = CASE phase_order
        WHEN 1 THEN 'pre_onboarding'
        WHEN 2 THEN 'reception_connection'
        WHEN 3 THEN 'role_induction'
        WHEN 4 THEN 'first_tasks'
        WHEN 5 THEN 'first_month'
        WHEN 6 THEN 'consolidation'
        WHEN 7 THEN 'evaluation_close'
        ELSE CONCAT('legacy_phase_', id)
    END,
    start_offset_days = CASE phase_order
        WHEN 1 THEN -7 WHEN 2 THEN 0 WHEN 3 THEN 1 WHEN 4 THEN 5
        WHEN 5 THEN 21 WHEN 6 THEN 31 WHEN 7 THEN 61 ELSE 0
    END,
    end_offset_days = CASE phase_order
        WHEN 1 THEN -1 WHEN 2 THEN 3 WHEN 3 THEN 10 WHEN 4 THEN 20
        WHEN 5 THEN 30 WHEN 6 THEN 60 WHEN 7 THEN 90 ELSE 0
    END;

ALTER TABLE onboarding_phase_templates
    MODIFY COLUMN phase_key VARCHAR(60) NOT NULL,
    ADD UNIQUE KEY uq_onboarding_phase_template_key (phase_key),
    ADD UNIQUE KEY uq_onboarding_phase_template_order (phase_order);

-- Adaptar las plantillas de tareas. Se conservan registros preexistentes y sus
-- prioridades se traducen al vocabulario usado por el módulo actual.
ALTER TABLE onboarding_task_templates
    CHANGE COLUMN days_after_start due_offset_days SMALLINT DEFAULT 0,
    ADD COLUMN task_key VARCHAR(80) DEFAULT NULL,
    ADD COLUMN assignee_key VARCHAR(24) NOT NULL DEFAULT 'responsible',
    MODIFY COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'media';

UPDATE onboarding_task_templates SET
    task_key = CONCAT('legacy_task_', id),
    priority = CASE priority
        WHEN 'low' THEN 'baja' WHEN 'medium' THEN 'media'
        WHEN 'high' THEN 'alta' WHEN 'urgent' THEN 'urgente'
        ELSE priority
    END
WHERE task_key IS NULL;

ALTER TABLE onboarding_task_templates
    MODIFY COLUMN task_key VARCHAR(80) NOT NULL,
    ADD UNIQUE KEY uq_onboarding_task_template_key (phase_template_id, task_key);

-- Si ya había tareas de plantilla, asociarlas con su fase lógica después de
-- normalizar los identificadores de fase.
UPDATE onboarding_task_templates t
JOIN onboarding_phase_templates p ON p.phase_key = CASE t.task_key
    WHEN 'confirm_start' THEN 'pre_onboarding'
    WHEN 'prepare_access' THEN 'pre_onboarding'
    WHEN 'welcome_meeting' THEN 'reception_connection'
    WHEN 'share_contacts' THEN 'reception_connection'
    WHEN 'role_goals' THEN 'role_induction'
    WHEN 'tools_training' THEN 'role_induction'
    WHEN 'assign_first_task' THEN 'first_tasks'
    WHEN 'review_first_task' THEN 'first_tasks'
    WHEN 'checkin_30' THEN 'first_month'
    WHEN 'set_next_goals' THEN 'first_month'
    WHEN 'checkin_60' THEN 'consolidation'
    WHEN 'feedback_60' THEN 'consolidation'
    WHEN 'evaluation_90' THEN 'evaluation_close'
    WHEN 'close_conversation' THEN 'evaluation_close'
    ELSE NULL
END
SET t.phase_template_id = p.id;

-- Renombrar las columnas antiguas de las fases; mantener IDs enteros para que
-- las claves foráneas existentes sigan siendo compatibles.
ALTER TABLE onboarding_phases
    DROP FOREIGN KEY fk_onb_phase_process,
    DROP FOREIGN KEY fk_onb_phase_template,
    CHANGE COLUMN phase_template_id template_id INT(11) DEFAULT NULL,
    CHANGE COLUMN phase_number phase_order TINYINT(3) UNSIGNED NOT NULL,
    CHANGE COLUMN name title VARCHAR(150) NOT NULL,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD CONSTRAINT fk_onboarding_phase_process FOREIGN KEY (process_id) REFERENCES onboarding_processes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT fk_onboarding_phase_template FOREIGN KEY (template_id) REFERENCES onboarding_phase_templates(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- La tabla antigua de historial guardaba la descripción en lugar del resumen
-- y no conservaba los estados antes/después.
ALTER TABLE onboarding_history
    ADD COLUMN summary VARCHAR(500) DEFAULT NULL,
    ADD COLUMN before_data LONGTEXT DEFAULT NULL,
    ADD COLUMN after_data LONGTEXT DEFAULT NULL;

UPDATE onboarding_history
SET summary = COALESCE(NULLIF(description, ''), action)
WHERE summary IS NULL;

ALTER TABLE onboarding_history
    MODIFY COLUMN summary VARCHAR(500) NOT NULL;

-- Tareas iniciales, dos obligatorias para cada fase, usando usuarios y la tabla
-- `tareas` que ya existen. INSERT IGNORE permite conservar claves ya creadas.
INSERT IGNORE INTO onboarding_task_templates
    (phase_template_id, task_key, title, description, due_offset_days, assignee_key, priority, is_required, is_active)
SELECT p.id, t.task_key, t.title, t.description, t.due_offset_days, t.assignee_key, t.priority, 1, 1
FROM onboarding_phase_templates p
JOIN (
    SELECT 'pre_onboarding' AS phase_key, 'confirm_start' AS task_key, 'Confirmar fecha de ingreso y condiciones' AS title, 'Validar la fecha de inicio y coordinar los detalles previos.' AS description, -3 AS due_offset_days, 'responsible' AS assignee_key, 'alta' AS priority
    UNION ALL SELECT 'pre_onboarding', 'prepare_access', 'Coordinar accesos y espacio de trabajo', 'Preparar accesos, equipo y espacio antes del primer día.', -1, 'supervisor', 'alta'
    UNION ALL SELECT 'reception_connection', 'welcome_meeting', 'Realizar bienvenida y presentación del equipo', 'Presentar al equipo, canales de comunicación y agenda inicial.', 0, 'buddy', 'alta'
    UNION ALL SELECT 'reception_connection', 'share_contacts', 'Compartir contactos y canales de apoyo', 'Acordar con quién resolver dudas operativas y de acompañamiento.', 2, 'responsible', 'media'
    UNION ALL SELECT 'role_induction', 'role_goals', 'Revisar objetivos y responsabilidades del puesto', 'Conversar sobre expectativas, funciones y prioridades del área.', 5, 'supervisor', 'alta'
    UNION ALL SELECT 'role_induction', 'tools_training', 'Completar inducción a herramientas y procesos', 'Revisar las herramientas y procedimientos necesarios para el rol.', 9, 'buddy', 'media'
    UNION ALL SELECT 'first_tasks', 'assign_first_task', 'Acordar primera tarea acompañada', 'Definir una tarea inicial con alcance y resultado esperado.', 12, 'supervisor', 'alta'
    UNION ALL SELECT 'first_tasks', 'review_first_task', 'Revisar resultado y resolver dudas', 'Entregar retroalimentación sobre la primera tarea.', 19, 'buddy', 'media'
    UNION ALL SELECT 'first_month', 'checkin_30', 'Realizar conversación de seguimiento de 30 días', 'Revisar adaptación, avances y necesidades de apoyo.', 30, 'responsible', 'alta'
    UNION ALL SELECT 'first_month', 'set_next_goals', 'Acordar objetivos del siguiente periodo', 'Registrar objetivos para el periodo de consolidación.', 30, 'supervisor', 'media'
    UNION ALL SELECT 'consolidation', 'checkin_60', 'Realizar conversación de seguimiento de 60 días', 'Revisar autonomía, desempeño y barreras pendientes.', 60, 'responsible', 'alta'
    UNION ALL SELECT 'consolidation', 'feedback_60', 'Registrar retroalimentación del área', 'Compartir observaciones y definir apoyos de cierre.', 60, 'supervisor', 'media'
    UNION ALL SELECT 'evaluation_close', 'evaluation_90', 'Completar evaluación de 90 días', 'Evaluar objetivos acordados y registrar aprendizajes.', 90, 'supervisor', 'alta'
    UNION ALL SELECT 'evaluation_close', 'close_conversation', 'Realizar conversación de cierre', 'Compartir retroalimentación final y acordar próximos pasos.', 90, 'responsible', 'alta'
) t ON t.phase_key = p.phase_key;
