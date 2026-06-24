-- ============================================
-- db_setup.sql — Esquema completo de la BD
-- v2: FK constraints al final, compatible InfinityFree
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS solicitudes_docente;
DROP TABLE IF EXISTS sesiones_log;
DROP TABLE IF EXISTS banco_preguntas;
DROP TABLE IF EXISTS notificaciones;
DROP TABLE IF EXISTS tab_salidas;
DROP TABLE IF EXISTS evaluacion_intentos;
DROP TABLE IF EXISTS evaluaciones;
DROP TABLE IF EXISTS actividades;
DROP TABLE IF EXISTS materiales;
DROP TABLE IF EXISTS grupo_miembros;
DROP TABLE IF EXISTS grupos;
DROP TABLE IF EXISTS usuarios;

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
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    codigo_invitacion VARCHAR(10) NOT NULL UNIQUE,
    codigo_expiracion DATETIME DEFAULT NULL,
    docente_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grupo_miembros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    usuario_id INT NOT NULL,
    bloqueado TINYINT(1) NOT NULL DEFAULT 0,
    unido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_grupo_usuario (grupo_id, usuario_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('link','archivo','html') NOT NULL DEFAULT 'link',
    contenido_url VARCHAR(500) NOT NULL,
    archivo_nombre VARCHAR(255) DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    tipo ENUM('lectura','ejercicio','practico','video','discusion') NOT NULL DEFAULT 'lectura',
    contenido TEXT,
    fecha_limite DATETIME DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    preguntas_json LONGTEXT NOT NULL,
    puntaje_max INT NOT NULL DEFAULT 100,
    duracion_min INT NOT NULL DEFAULT 30,
    intentos_max INT NOT NULL DEFAULT 1,
    preguntas_mostrar INT DEFAULT NULL,
    shuffle_preguntas TINYINT(1) NOT NULL DEFAULT 0,
    shuffle_opciones TINYINT(1) NOT NULL DEFAULT 0,
    fecha_limite DATETIME DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluacion_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluacion_id INT NOT NULL,
    usuario_id INT NOT NULL,
    intento_num INT NOT NULL DEFAULT 1,
    respuestas_json LONGTEXT,
    puntaje DECIMAL(5,1) DEFAULT NULL,
    tab_salidas INT NOT NULL DEFAULT 0,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME DEFAULT NULL,
    finalizada TINYINT(1) NOT NULL DEFAULT 0,
    lti_outcome_url VARCHAR(500) DEFAULT NULL,
    lti_sourcedid VARCHAR(200) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tab_salidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intento_id INT NOT NULL,
    tipo_evento ENUM('visibility','blur','fullscreen_exit','devtools','copy_paste','other') NOT NULL DEFAULT 'other',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS banco_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    materia VARCHAR(100) NOT NULL DEFAULT 'General',
    texto TEXT NOT NULL,
    tipo ENUM('multiple','completar','verdadero_falso','ordenar') NOT NULL DEFAULT 'multiple',
    opciones_json LONGTEXT NOT NULL,
    respuesta_correcta VARCHAR(500) NOT NULL,
    puntaje INT NOT NULL DEFAULT 10,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sesiones_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(300) DEFAULT NULL,
    accion VARCHAR(50) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bloqueos_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    grupo_id INT DEFAULT NULL,
    motivo VARCHAR(100) DEFAULT 'perdida_foco',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    desbloqueado_en DATETIME NULL,
    desbloqueado_por INT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS proyectos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    lenguaje VARCHAR(30) NOT NULL DEFAULT 'python',
    contenido LONGTEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS solicitudes_docente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    comentario TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Agregar FK constraints
ALTER TABLE grupos ADD CONSTRAINT fk_grupos_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE grupo_miembros ADD CONSTRAINT fk_gm_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE;
ALTER TABLE grupo_miembros ADD CONSTRAINT fk_gm_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE materiales ADD CONSTRAINT fk_materiales_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE;
ALTER TABLE actividades ADD CONSTRAINT fk_actividades_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE;
ALTER TABLE evaluaciones ADD CONSTRAINT fk_evaluaciones_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE;
ALTER TABLE evaluacion_intentos ADD CONSTRAINT fk_ei_eval FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE CASCADE;
ALTER TABLE evaluacion_intentos ADD CONSTRAINT fk_ei_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE tab_salidas ADD CONSTRAINT fk_ts_intento FOREIGN KEY (intento_id) REFERENCES evaluacion_intentos(id) ON DELETE CASCADE;
ALTER TABLE notificaciones ADD CONSTRAINT fk_notif_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE;
ALTER TABLE banco_preguntas ADD CONSTRAINT fk_bp_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE sesiones_log ADD CONSTRAINT fk_sl_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE solicitudes_docente ADD CONSTRAINT fk_sd_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE usuarios ADD INDEX idx_rol (rol);
ALTER TABLE usuarios ADD INDEX idx_bloqueado (bloqueado);
ALTER TABLE grupos ADD INDEX idx_codigo (codigo_invitacion);
ALTER TABLE evaluacion_intentos ADD INDEX idx_eval_usuario (evaluacion_id, usuario_id);
ALTER TABLE banco_preguntas ADD INDEX idx_materia (materia);

-- LTI 1.1 migration: agrega columnas de outcome si faltan
SET @lti_col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluacion_intentos' AND COLUMN_NAME = 'lti_outcome_url');
SET @sql_lti = IF(@lti_col_exists = 0, 'ALTER TABLE evaluacion_intentos ADD COLUMN lti_outcome_url VARCHAR(500) DEFAULT NULL, ADD COLUMN lti_sourcedid VARCHAR(200) DEFAULT NULL', 'SELECT 1');
PREPARE stmt_lti FROM @sql_lti;
EXECUTE stmt_lti;
DEALLOCATE PREPARE stmt_lti;

SET FOREIGN_KEY_CHECKS = 1;

-- Admin default: usuario admin / password Admin123!
-- Cambiar en primer inicio desde admin/configuracion.php
INSERT INTO usuarios (username, password_hash, rol, nombre, email) VALUES
('admin', '$2y$12$zYhYkQKIlXj9FGyhSr4KJO3Vc6V1BFL7xR5kXpBqCzKgJs1dRSDCa', 'admin', 'Administrador', 'admin@plataforma.edu');
