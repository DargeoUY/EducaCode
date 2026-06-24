CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin','docente') NOT NULL DEFAULT 'docente',
    activo BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    codigo_acceso VARCHAR(10) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estudiantes (
    cedula VARCHAR(20) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    grupo_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    slug VARCHAR(30) UNIQUE NOT NULL,
    icono VARCHAR(10) DEFAULT '📚',
    orden INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS niveles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    numero INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    tipo ENUM('leccion','ejercicios','evaluacion') DEFAULT 'leccion',
    archivo_html VARCHAR(200),
    orden INT DEFAULT 0,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS visibilidad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    curso_id INT NOT NULL,
    mostrar_lecciones BOOLEAN NOT NULL DEFAULT TRUE,
    mostrar_ejercicios BOOLEAN NOT NULL DEFAULT TRUE,
    mostrar_evaluaciones BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_desde DATE NULL,
    fecha_hasta DATE NULL,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vis (grupo_id, curso_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula_estudiante VARCHAR(20) NOT NULL,
    nivel_id INT NOT NULL,
    nota DECIMAL(5,2) NOT NULL,
    tiempo_seg INT DEFAULT 0,
    intento INT DEFAULT 1,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cedula_estudiante) REFERENCES estudiantes(cedula) ON DELETE CASCADE,
    FOREIGN KEY (nivel_id) REFERENCES niveles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS share_sessions (
    id VARCHAR(36) PRIMARY KEY,
    codigo_sala VARCHAR(10) UNIQUE NOT NULL,
    docente_id INT NOT NULL,
    grupo_id INT NULL,
    contenido TEXT,
    lenguaje VARCHAR(20) DEFAULT 'python',
    cursor_pos JSON,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS logs_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula_estudiante VARCHAR(20),
    evento VARCHAR(50),
    nivel_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO cursos (nombre, slug, icono, orden) VALUES
('Diseño Web', 'diseno-web', '🌐', 1),
('Algoritmia', 'algoritmia', '🧠', 2),
('Python', 'python', '🐍', 3),
('Pseudocódigo', 'pseudocodigo', '📋', 4);

INSERT INTO niveles (curso_id, numero, titulo, tipo, archivo_html, orden) VALUES
(1, 1, 'Mi Primera Página', 'leccion', 'Diseño_Web/DiseñoWeb-LVL 1.html', 1),
(1, 2, 'Conectando la Web', 'leccion', 'Diseño_Web/DiseñoWeb-LVL 2.html', 2),
(1, 3, 'Estructurando Datos', 'leccion', 'Diseño_Web/DiseñoWeb-LVL 3.html', 3),
(1, 4, 'Capturando Datos', 'leccion', 'Diseño_Web/DiseñoWeb-LVL 4.html', 4),
(1, 5, 'Evaluación HTML', 'evaluacion', 'Diseño_Web/DiseñoWeb-LVL 5.html', 5),
(1, 6, 'Dando Estilo', 'leccion', 'Diseño_Web/DiseñoWeb-CSS-LVL 1.html', 6),
(1, 7, 'Modelo de Cajas', 'leccion', 'Diseño_Web/DiseñoWeb-CSS-LVL 2.html', 7),
(1, 8, 'Flexbox', 'leccion', 'Diseño_Web/DiseñoWeb-CSS-LVL 3.html', 8),
(1, 9, 'Diseño Responsive', 'leccion', 'Diseño_Web/DiseñoWeb-CSS-LVL 4.html', 9),
(1, 10, 'Evaluación CSS', 'evaluacion', 'Diseño_Web/DiseñoWeb-CSS-LVL 5.html', 10),
(1, 11, 'Primeros Pasos JS', 'leccion', 'Diseño_Web/DiseñoWeb-JS-LVL 1.html', 11),
(1, 12, 'Interactuando', 'leccion', 'Diseño_Web/DiseñoWeb-JS-LVL 2.html', 12),
(1, 13, 'Decisiones', 'leccion', 'Diseño_Web/DiseñoWeb-JS-LVL 3.html', 13),
(1, 14, 'Bucles', 'leccion', 'Diseño_Web/DiseñoWeb-JS-LVL 4.html', 14),
(1, 15, 'Evaluación JS', 'evaluacion', 'Diseño_Web/DiseñoWeb-JS-LVL 5.html', 15),
(2, 1, 'El Arte de Dar Instrucciones', 'leccion', 'Algoritmia/Algoritmia - LVL 1.html', 1),
(2, 2, 'Secuencias y Orden Lógico', 'leccion', 'Algoritmia/Algoritmia - LVL 2.html', 2),
(2, 3, 'Tomando Decisiones', 'leccion', 'Algoritmia/Algoritmia - LVL 3.html', 3),
(2, 4, 'Bucles y Repetición', 'leccion', 'Algoritmia/Algoritmia - LVL 4.html', 4),
(2, 5, 'Evaluación Final', 'evaluacion', 'Algoritmia/Algoritmia - LVL 5.html', 5),
(3, 1, 'Primeros Pasos', 'leccion', 'Python/Python - LVL 1.html', 1),
(3, 2, 'Interactuando', 'leccion', 'Python/Python - LVL 2.html', 2),
(3, 3, 'Decisiones', 'leccion', 'Python/Python - LVL 3.html', 3),
(3, 4, 'Bucles', 'leccion', 'Python/Python - LVL 4.html', 4),
(3, 5, 'Evaluación Final', 'evaluacion', 'Python/Python - LVL 5.html', 5),
(4, 1, 'El Idioma de los Robots', 'leccion', 'pseudocodigo/Pseudocodigo - LVL 1.html', 1),
(4, 2, 'Tomando Decisiones', 'leccion', 'pseudocodigo/Pseudocodigo - LVL 2.html', 2),
(4, 3, 'El Bucle PARA', 'leccion', 'pseudocodigo/Pseudocodigo - LVL 3.html', 3),
(4, 4, 'El Bucle MIENTRAS', 'leccion', 'pseudocodigo/Pseudocodigo - LVL 4.html', 4),
(4, 5, 'Evaluación Final', 'evaluacion', 'pseudocodigo/Pseudocodigo - LVL 5.html', 5);

CREATE TABLE IF NOT EXISTS configuracion (
    id INT PRIMARY KEY DEFAULT 1,
    estado_prueba ENUM('abierta','cerrada') NOT NULL DEFAULT 'cerrada'
) ENGINE=InnoDB;

INSERT INTO configuracion (id, estado_prueba) VALUES (1, 'abierta');
