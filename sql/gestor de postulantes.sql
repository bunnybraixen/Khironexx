-- =====================================
-- BASE DE DATOS
-- =====================================
CREATE DATABASE IF NOT EXISTS gestor_postulaciones;
USE gestor_postulaciones;

-- =====================================
-- TABLAS DE CONFIGURACIÓN
-- =====================================

CREATE TABLE areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
);

-- =====================================
-- TABLA PRINCIPAL
-- =====================================

CREATE TABLE practicantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    correo VARCHAR(200),

    area_id INT,
    rol_id INT,

    fecha_entrada DATE,
    fecha_salida DATE,
    dias_restantes INT,
    horario_laboral TEXT,

    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (area_id) REFERENCES areas(id),
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- =====================================
-- DATOS DE CONFIGURACIÓN (DESDE EXCEL)
-- =====================================

INSERT INTO areas (nombre) VALUES
('Administración'),
('Analista Programador'),
('Comercial'),
('Control de Gestión'),
('Informática'),
('Marketing'),
('Recursos Humanos'),
('Psicología');

INSERT INTO roles (nombre) VALUES
('Encargado'),
('Practicante');

-- =====================================
-- DATOS REALES DE PRACTICANTES (EXCEL)
-- =====================================

INSERT INTO practicantes
(nombre, apellido, correo, area_id, rol_id, fecha_entrada, fecha_salida, dias_restantes, horario_laboral)
VALUES
('Jesus Antonio', 'Cheuque Curaqueo', 'jesus.cheuque@consultorescyc.cl', 1, 1, '2025-12-09', '2026-02-13', 16, 'Lunes a Viernes 09:30 - 17:00 hrs'),
('Román', 'Sommariva', 'roman.sommariva@consultorescyc.cl', 1, 2, '2026-01-21', '2026-03-06', 37, 'Lunes a Viernes 09:00 - 17:30 hrs'),
('Rosemery', 'Rios', 'rosemery.rios@consultorescyc.cl', 1, 2, '2026-01-26', '2026-03-27', 58, 'Lunes a Viernes 09:00 - 18:00 hrs'),
('Khrisn', 'Muñoz Calderón', 'khrisn04@gmail.com', 2, 2, '2025-12-01', '2026-02-16', 360, 'Híbrido'),
('Diego Maximiliano', 'Barra', 'diegobarrabarrera@gmail.com', 2, 2, '2025-12-01', '2026-03-19', 360, 'Híbrido'),
('Milagros', 'Silva Mendoza', 'milieyias@gmail.com', 3, 2, '2025-11-17', '2026-03-12', 540, 'Híbrido'),
('Juan', 'Contreras', 'juan.contreras.v@mail.pucv.cl', 3, 2, '2026-01-05', '2026-02-16', 200, 'Híbrido'),
('Francisco', 'Mellado', 'nacho.melladogodoy@gmail.com', 4, 2, '2025-12-09', '2026-03-18', 480, 'Híbrido'),
('Nicolas', 'Suazo', 'suazo.dni@gmail.com', 4, 2, '2025-01-05', '2026-01-17', 364, 'Híbrido'),
('Rubén', 'Herrera Aravena', 'herrera.aravena@gmail.com', 5, 2, '2025-11-17', '2026-02-06', 360, 'Híbrido'),
('Renato', 'Ramirez Vásquez', 'renatoramirezvasquez@gmail.com', 5, 2, '2025-12-09', '2026-02-13', 320, 'Híbrido'),
('Cristóbal Alonso', 'Ramos Araya', 'ramoscristobal762@gmail.com', 5, 2, '2025-01-05', '2026-02-14', 360, 'Híbrido'),
('Diego', 'Rojas Campos', 'diego.rojas792@gmail.com', 6, 2, '2025-11-17', '2026-02-06', 360, 'Híbrido');
