-- Ejecutar una sola vez en la base de datos de Khironexx.
-- Guarda evaluaciones por competencia con historial para cada practicante.
CREATE TABLE IF NOT EXISTS practicante_evaluaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    practicante_id INT NOT NULL,
    evaluador_id INT DEFAULT NULL,
    competencia VARCHAR(120) NOT NULL,
    nivel TINYINT UNSIGNED NOT NULL,
    comentario TEXT DEFAULT NULL,
    referencia_evidencia VARCHAR(255) DEFAULT NULL,
    fecha DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_practicante_evaluaciones_fecha (practicante_id, fecha, id),
    INDEX idx_practicante_evaluaciones_evaluador (evaluador_id),
    CONSTRAINT fk_pev_practicante FOREIGN KEY (practicante_id) REFERENCES practicantes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pev_evaluador FOREIGN KEY (evaluador_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;