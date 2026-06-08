-- ============================================
-- db_setup.sql — Esquema completo de la BD
-- Ejecutar en phpMyAdmin de InfinityFree
-- ============================================

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin','docente','estudiante') NOT NULL DEFAULT 'estudiante',
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    pregunta_secreta VARCHAR(200) DEFAULT NULL,
    respuesta_secreta_hash VARCHAR(255) DEFAULT NULL,
    bloqueado TINYINT(1) NOT NULL DEFAULT 0,
    intentos_login INT NOT NULL DEFAULT 0,
    bloqueo_hasta DATETIME DEFAULT NULL,
    ultimo_login DATETIME DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rol (rol),
    INDEX idx_bloqueado (bloqueado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    codigo_invitacion VARCHAR(10) NOT NULL UNIQUE,
    codigo_expiracion DATETIME DEFAULT NULL,
    docente_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_codigo (codigo_invitacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grupo_miembros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    usuario_id INT NOT NULL,
    bloqueado TINYINT(1) NOT NULL DEFAULT 0,
    unido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uk_grupo_usuario (grupo_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('link','archivo','html') NOT NULL DEFAULT 'link',
    contenido_url VARCHAR(500) NOT NULL,
    archivo_nombre VARCHAR(255) DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    tipo ENUM('lectura','ejercicio','practico','video','discusion') NOT NULL DEFAULT 'lectura',
    contenido TEXT DEFAULT NULL,
    fecha_limite DATETIME DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    preguntas_json LONGTEXT NOT NULL,
    puntaje_max INT NOT NULL DEFAULT 100,
    duracion_min INT NOT NULL DEFAULT 30,
    intentos_max INT NOT NULL DEFAULT 1,
    preguntas_mostrar INT DEFAULT NULL,
    shuffle_preguntas TINYINT(1) NOT NULL DEFAULT 0,
    shuffle_opciones TINYINT(1) NOT NULL DEFAULT 0,
    fecha_limite DATETIME DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluacion_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluacion_id INT NOT NULL,
    usuario_id INT NOT NULL,
    intento_num INT NOT NULL DEFAULT 1,
    respuestas_json LONGTEXT DEFAULT NULL,
    puntaje DECIMAL(5,1) DEFAULT NULL,
    tab_salidas INT NOT NULL DEFAULT 0,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME DEFAULT NULL,
    finalizada TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_eval_usuario (evaluacion_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tab_salidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intento_id INT NOT NULL,
    tipo_evento ENUM('visibility','blur','fullscreen_exit','devtools','copy_paste','other') NOT NULL DEFAULT 'other',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intento_id) REFERENCES evaluacion_intentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banco_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    materia VARCHAR(100) NOT NULL DEFAULT 'General',
    texto TEXT NOT NULL,
    tipo ENUM('multiple','completar','verdadero_falso','ordenar') NOT NULL DEFAULT 'multiple',
    opciones_json LONGTEXT NOT NULL,
    respuesta_correcta VARCHAR(500) NOT NULL,
    puntaje INT NOT NULL DEFAULT 10,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_materia (materia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sesiones_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(300) DEFAULT NULL,
    accion VARCHAR(50) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitudes_docente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    comentario TEXT DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Datos iniciales: Admin por defecto
-- Usuario: admin  |  Contrasena: Admin123!
-- (Debe cambiarse en el primer inicio)
-- ============================================
-- Usuario admin por defecto
-- LA PRIMERA VEZ: ejecutar crear-admin.php desde el navegador
-- para establecer la contrasena del admin.
-- Contrasena temporal: Admin123!
-- Debes cambiarla desde admin/configuracion.php
INSERT INTO usuarios (username, password_hash, rol, nombre, email) VALUES
('admin', '$2y$12$zYhYkQKIlXj9FGyhSr4KJO3Vc6V1BFL7xR5kXpBqCzKgJs1dRSDCa', 'admin', 'Administrador', 'admin@plataforma.edu');

