-- Aplicar en la base de datos local de desarrollo de Khironexx.
-- Crea una tabla independiente; no modifica solicitudes_cambio ni datos existentes.
CREATE TABLE IF NOT EXISTS tickets_soporte (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    asunto VARCHAR(160) NOT NULL,
    modulo VARCHAR(60) NOT NULL,
    prioridad VARCHAR(20) NOT NULL DEFAULT 'media',
    descripcion TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'abierto',
    respuesta TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    INDEX idx_tickets_status_created (status, created_at),
    INDEX idx_tickets_user_created (user_id, created_at),
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
