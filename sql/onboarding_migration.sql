-- Onboarding: procesos, fases, plantillas e historial.
-- Requiere la tabla compartida tareas, creada por el módulo de Tareas.
-- No duplica ni modifica datos personales de practicantes o usuarios.

CREATE TABLE IF NOT EXISTS onboarding_processes (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    practicante_id INT NOT NULL,
    start_date DATE NOT NULL,
    estimated_end_date DATE NOT NULL,
    actual_end_date DATE DEFAULT NULL,
    modality VARCHAR(20) NOT NULL DEFAULT 'presencial',
    responsible_user_id INT NOT NULL,
    supervisor_user_id INT NOT NULL,
    buddy_user_id INT DEFAULT NULL,
    backup_buddy_user_id INT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
    cancellation_reason TEXT DEFAULT NULL,
    cancelled_by INT DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active_practicante_id INT GENERATED ALWAYS AS (
        CASE WHEN status IN ('completed', 'cancelled') THEN NULL ELSE practicante_id END
    ) STORED,
    UNIQUE KEY uq_onboarding_active_practicante (active_practicante_id),
    INDEX idx_onboarding_status_dates (status, start_date, estimated_end_date),
    INDEX idx_onboarding_responsible (responsible_user_id, status),
    CONSTRAINT fk_onboarding_practicante FOREIGN KEY (practicante_id) REFERENCES practicantes(id),
    CONSTRAINT fk_onboarding_responsible FOREIGN KEY (responsible_user_id) REFERENCES usuarios(id),
    CONSTRAINT fk_onboarding_supervisor FOREIGN KEY (supervisor_user_id) REFERENCES usuarios(id),
    CONSTRAINT fk_onboarding_buddy FOREIGN KEY (buddy_user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_onboarding_backup_buddy FOREIGN KEY (backup_buddy_user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_onboarding_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_onboarding_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS onboarding_phase_templates (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    phase_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    phase_order TINYINT NOT NULL,
    start_offset_days SMALLINT NOT NULL DEFAULT 0,
    end_offset_days SMALLINT NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_onboarding_phase_template_key (phase_key),
    UNIQUE KEY uq_onboarding_phase_template_order (phase_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS onboarding_task_templates (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    phase_template_id INT NOT NULL,
    task_key VARCHAR(80) NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT DEFAULT NULL,
    due_offset_days SMALLINT NOT NULL DEFAULT 0,
    assignee_key VARCHAR(24) NOT NULL DEFAULT 'responsible',
    priority VARCHAR(20) NOT NULL DEFAULT 'media',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_onboarding_task_template_key (phase_template_id, task_key),
    CONSTRAINT fk_onboarding_task_template_phase FOREIGN KEY (phase_template_id) REFERENCES onboarding_phase_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS onboarding_phases (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    process_id BIGINT NOT NULL,
    template_id INT DEFAULT NULL,
    phase_order TINYINT NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_onboarding_process_phase_order (process_id, phase_order),
    INDEX idx_onboarding_phase_dates (start_date, end_date, status),
    CONSTRAINT fk_onboarding_phase_process FOREIGN KEY (process_id) REFERENCES onboarding_processes(id) ON DELETE CASCADE,
    CONSTRAINT fk_onboarding_phase_template FOREIGN KEY (template_id) REFERENCES onboarding_phase_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relaciona tareas del sistema existente con fases; su estado se lee/escribe en tareas.
CREATE TABLE IF NOT EXISTS onboarding_phase_tasks (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    phase_id BIGINT NOT NULL,
    task_id INT NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    template_task_id INT DEFAULT NULL,
    UNIQUE KEY uq_onboarding_shared_task (task_id),
    INDEX idx_onboarding_phase_task (phase_id, is_required),
    CONSTRAINT fk_onboarding_phase_task_phase FOREIGN KEY (phase_id) REFERENCES onboarding_phases(id) ON DELETE CASCADE,
    CONSTRAINT fk_onboarding_phase_task_task FOREIGN KEY (task_id) REFERENCES tareas(id) ON DELETE CASCADE,
    CONSTRAINT fk_onboarding_phase_task_template FOREIGN KEY (template_task_id) REFERENCES onboarding_task_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS onboarding_history (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    process_id BIGINT NOT NULL,
    user_id INT DEFAULT NULL,
    action VARCHAR(40) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    before_data LONGTEXT DEFAULT NULL,
    after_data LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_onboarding_history_process (process_id, created_at),
    CONSTRAINT fk_onboarding_history_process FOREIGN KEY (process_id) REFERENCES onboarding_processes(id) ON DELETE CASCADE,
    CONSTRAINT fk_onboarding_history_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO onboarding_phase_templates (phase_key, title, phase_order, start_offset_days, end_offset_days, description) VALUES
('pre_onboarding', 'Pre-onboarding', 1, -7, -1, 'Preparación previa al ingreso y coordinación de accesos.'),
('reception_connection', 'Recepción y conexión', 2, 0, 3, 'Bienvenida, presentación del equipo y primeros contactos.'),
('role_induction', 'Inducción al puesto', 3, 1, 10, 'Contexto del cargo, herramientas, procesos y expectativas.'),
('first_tasks', 'Primeras tareas', 4, 5, 20, 'Primeras actividades con acompañamiento del equipo.'),
('first_month', 'Primer mes', 5, 21, 30, 'Revisión del primer mes y acuerdos de continuidad.'),
('consolidation', 'Consolidación', 6, 31, 60, 'Seguimiento del aprendizaje y autonomía progresiva.'),
('evaluation_close', 'Evaluación y cierre', 7, 61, 90, 'Evaluación final, retroalimentación y próximos pasos.');

INSERT IGNORE INTO onboarding_task_templates (phase_template_id, task_key, title, description, due_offset_days, assignee_key, priority, is_required)
SELECT p.id, t.task_key, t.title, t.description, t.due_offset_days, t.assignee_key, t.priority, 1
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
