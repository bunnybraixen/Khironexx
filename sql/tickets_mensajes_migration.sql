-- Aplicar en la base local de desarrollo de Khironexx después de tickets_soporte_migration.sql.
-- Guarda la conversación de cada ticket sin reemplazar mensajes anteriores.
CREATE TABLE IF NOT EXISTS tickets_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_messages_ticket_created (ticket_id, created_at),
    CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets_soporte(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_messages_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
