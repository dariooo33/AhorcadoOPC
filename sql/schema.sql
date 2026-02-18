-- =====================================================
-- Ahorcado OPC - Esquema MySQL
-- =====================================================
-- 1) Crea la base de datos
CREATE DATABASE IF NOT EXISTS ahorcadoopc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ahorcadoopc;

-- 2) Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL UNIQUE,
    username VARCHAR(40) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    trofeos INT NOT NULL DEFAULT 30,
    partidas_jugadas INT NOT NULL DEFAULT 0,
    partidas_ganadas INT NOT NULL DEFAULT 0,
    partidas_perdidas INT NOT NULL DEFAULT 0,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3) Palabras disponibles para el juego
CREATE TABLE IF NOT EXISTS palabras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    palabra VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 4) Partidas del sistema (multijugador y amistoso)
CREATE TABLE IF NOT EXISTS partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('multijugador', 'amistoso') NOT NULL,
    estado ENUM('esperando', 'en_curso', 'finalizada', 'cancelada') NOT NULL DEFAULT 'esperando',
    palabra VARCHAR(80) NOT NULL,
    turno_actual INT NULL,
    errores_jugador1 TINYINT UNSIGNED NOT NULL DEFAULT 0,
    errores_jugador2 TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ganador_id INT NULL,
    jugador1_id INT NOT NULL,
    jugador2_id INT NULL,
    letras_correctas VARCHAR(120) NOT NULL DEFAULT '',
    letras_incorrectas VARCHAR(120) NOT NULL DEFAULT '',
    creada_por_id INT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_partida_turno FOREIGN KEY (turno_actual) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_partida_ganador FOREIGN KEY (ganador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_partida_jugador1 FOREIGN KEY (jugador1_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_partida_jugador2 FOREIGN KEY (jugador2_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_partida_creador FOREIGN KEY (creada_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5) Salas para el modo amistoso
CREATE TABLE IF NOT EXISTS salas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(12) NOT NULL UNIQUE,
    creador_id INT NOT NULL,
    partida_id INT NOT NULL,
    estado ENUM('esperando', 'en_juego', 'cerrada') NOT NULL DEFAULT 'esperando',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sala_creador FOREIGN KEY (creador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_sala_partida FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6) Relacion jugadores <-> salas amistosas
CREATE TABLE IF NOT EXISTS sala_jugadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_union DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sala_usuario (sala_id, usuario_id),
    CONSTRAINT fk_sala_jugador_sala FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE CASCADE,
    CONSTRAINT fk_sala_jugador_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7) Relacion de amistades
CREATE TABLE IF NOT EXISTS amistades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario1_id INT NOT NULL,
    usuario2_id INT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_amistad_par (usuario1_id, usuario2_id),
    CONSTRAINT fk_amistad_usuario1 FOREIGN KEY (usuario1_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_amistad_usuario2 FOREIGN KEY (usuario2_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8) Solicitudes de amistad pendientes
CREATE TABLE IF NOT EXISTS solicitudes_amistad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emisor_id INT NOT NULL,
    receptor_id INT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_solicitud_direccion (emisor_id, receptor_id),
    CONSTRAINT fk_solicitud_emisor FOREIGN KEY (emisor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitud_receptor FOREIGN KEY (receptor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9) Semilla de palabras
INSERT IGNORE INTO palabras (palabra) VALUES
('ALGORITMO'),
('DESARROLLO'),
('PROGRAMACION'),
('FRONTEND'),
('BACKEND'),
('SERVIDOR'),
('CONTROLADOR'),
('BASEDEDATOS'),
('VARIABLE'),
('FUNCION'),
('OBJETO'),
('ARQUITECTURA'),
('CODIGO'),
('SISTEMA'),
('COMPILADOR'),
('DEPURACION'),
('INTERFAZ'),
('RESPONSIVE'),
('SEGURIDAD'),
('SESSION'),
('CONCURRENCIA'),
('CONSULTA'),
('MIGRACION'),
('API'),
('SOFTWARE');
