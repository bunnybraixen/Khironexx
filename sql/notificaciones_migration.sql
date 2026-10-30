-- Aplicar en la base de datos local de desarrollo de Khironexx.
-- Guarda avisos dentro del sistema y los asocia a cada usuario.
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    mensaje VARCHAR(500) NOT NULL DEFAULT '',
    ruta VARCHAR(40) NOT NULL DEFAULT 'dashboard',
    entity_id INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    UNIQUE KEY uq_notificaciones_evento (user_id, tipo, entity_id),
    INDEX idx_notificaciones_user_read_created (user_id, read_at, created_at),
    CONSTRAINT fk_notificaciones_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
