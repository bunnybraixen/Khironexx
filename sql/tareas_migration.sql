-- Aplicar en la base de datos local de desarrollo de Khironexx.
-- Crea la tabla de tareas del módulo nuevo; no modifica tablas existentes.
CREATE TABLE IF NOT EXISTS tareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(160) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    prioridad VARCHAR(20) NOT NULL DEFAULT 'media',
    fecha_limite DATE DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    creado_por INT NOT NULL,
    asignado_a INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    INDEX idx_tareas_status_created (status, created_at),
    INDEX idx_tareas_asignado_status (asignado_a, status),
    CONSTRAINT fk_tareas_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_tareas_asignado FOREIGN KEY (asignado_a) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
