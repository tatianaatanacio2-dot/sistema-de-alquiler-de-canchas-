USE canchas_alquiler;

CREATE TABLE IF NOT EXISTS administrador (
    id TINYINT UNSIGNED PRIMARY KEY CHECK (id = 1),
    nombre VARCHAR(80) NOT NULL,
    correo VARCHAR(254) NOT NULL,
    clave VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canchas (
    id INT UNSIGNED PRIMARY KEY CHECK (id = 1),
    nombre VARCHAR(100) NOT NULL,
    tarifa INT UNSIGNED DEFAULT 30 CHECK (tarifa > 0),
    activa TINYINT UNSIGNED NOT NULL CHECK (activa IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO canchas (id, nombre, activa) VALUES (1, 'Cancha de césped sintético', 1)
ON DUPLICATE KEY UPDATE id = 1;

CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(20) NOT NULL DEFAULT '',
    nombre VARCHAR(120) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    correo VARCHAR(254) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cancha_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NULL,
    inicio CHAR(16) NOT NULL,
    fin CHAR(16) NOT NULL,
    tarifa INT UNSIGNED NOT NULL,
    total DECIMAL(22, 2) UNSIGNED NOT NULL,
    estado ENUM('confirmada', 'completada', 'cancelada') NOT NULL DEFAULT 'confirmada',
    CHECK (inicio < fin),
    FOREIGN KEY (cancha_id) REFERENCES canchas(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    INDEX reservas_horario (cancha_id, inicio, fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
