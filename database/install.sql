
-- ============================================================
-- CARGA ACADÉMICA
-- Script de instalación limpia
-- MariaDB 10.11+
-- ============================================================

-- ============================================================
-- Tabla: usuarios
-- Almacena las cuentas de acceso a la aplicación.
-- ============================================================

CREATE TABLE usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tabla: asignaturas
-- Cada asignatura pertenece a un usuario.
-- ============================================================

CREATE TABLE asignaturas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    codigo VARCHAR(30) DEFAULT NULL,
    horas_semanales DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY usuario_id (usuario_id),

    CONSTRAINT asignaturas_ibfk_1
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tabla: actividades
-- Cada actividad pertenece a una asignatura.
-- ============================================================

CREATE TABLE actividades (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asignatura_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    tipo ENUM(
        'Trabajo',
        'Examen',
        'Lectura',
        'Otro',
        'Proyecto',
        'Tarea'
    ) DEFAULT NULL,
    fecha_entrega DATE DEFAULT NULL,
    horas_estimadas DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    dificultad ENUM('Baja', 'Media', 'Alta') DEFAULT NULL,
    prioridad ENUM('Baja', 'Media', 'Alta') DEFAULT NULL,
    estado ENUM(
        'Pendiente',
        'En progreso',
        'Completada'
    ) NOT NULL DEFAULT 'Pendiente',
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY asignatura_id (asignatura_id),

    CONSTRAINT actividades_ibfk_1
        FOREIGN KEY (asignatura_id)
        REFERENCES asignaturas (id)
        ON DELETE CASCADE,

    CONSTRAINT actividades_tipo_no_nulo_chk
        CHECK (tipo IS NOT NULL)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tabla: disponibilidad_semanal
-- Una fila por usuario y día de la semana.
-- NULL significa que el día todavía no está configurado.
-- ============================================================

CREATE TABLE disponibilidad_semanal (
    usuario_id INT UNSIGNED NOT NULL,
    dia_semana TINYINT UNSIGNED NOT NULL,
    horas_disponibles DECIMAL(5,2) DEFAULT NULL,

    PRIMARY KEY (usuario_id, dia_semana),

    CONSTRAINT disponibilidad_semanal_usuario_fk
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id)
        ON DELETE CASCADE,

    CONSTRAINT disponibilidad_semanal_dia_chk
        CHECK (dia_semana BETWEEN 1 AND 7),

    CONSTRAINT disponibilidad_semanal_horas_chk
        CHECK (
            horas_disponibles IS NULL
            OR horas_disponibles >= 0
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
