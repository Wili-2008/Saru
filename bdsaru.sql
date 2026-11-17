-- SARU v1 | MySQL 8 / MariaDB 10.4+ (WampServer)
-- Ejecutar UNA SOLA VEZ sobre una base de datos nueva.
-- No crea usuarios, contraseñas, preguntas ni respuestas ficticias.
CREATE DATABASE IF NOT EXISTS dbsaru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dbsaru;

CREATE TABLE IF NOT EXISTS cursos (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL UNIQUE,
 activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 curso_id INT UNSIGNED NULL,
 nombres VARCHAR(100) NOT NULL,
 apellidos VARCHAR(100) NOT NULL,
 usuario VARCHAR(60) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 rol ENUM('administrador','estudiante') NOT NULL DEFAULT 'estudiante',
 activo TINYINT(1) NOT NULL DEFAULT 1,
 cambiar_password TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_usuarios_curso FOREIGN KEY (curso_id) REFERENCES cursos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS materias (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 curso_id INT UNSIGNED NOT NULL,
 nombre VARCHAR(120) NOT NULL,
 descripcion TEXT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_materia_curso (curso_id, nombre),
 CONSTRAINT fk_materias_curso FOREIGN KEY (curso_id) REFERENCES cursos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS temas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 materia_id INT UNSIGNED NOT NULL,
 titulo VARCHAR(200) NOT NULL,
 descripcion TEXT NULL,
 orden INT UNSIGNED NOT NULL DEFAULT 1,
 estado ENUM('borrador','publicado') NOT NULL DEFAULT 'borrador',
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_tema_materia (materia_id, titulo),
 KEY idx_temas_titulo (titulo),
 CONSTRAINT fk_temas_materia FOREIGN KEY (materia_id) REFERENCES materias(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS materiales (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tema_id INT UNSIGNED NOT NULL,
 titulo VARCHAR(200) NOT NULL,
 descripcion TEXT NULL,
 tipo ENUM('resumen','guia','libro','otro') NOT NULL DEFAULT 'resumen',
 nombre_original VARCHAR(255) NOT NULL,
 nombre_guardado VARCHAR(255) NOT NULL UNIQUE,
 tamano_bytes BIGINT UNSIGNED NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_materiales_tema (tema_id, activo),
 CONSTRAINT fk_materiales_tema FOREIGN KEY (tema_id) REFERENCES temas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recursos (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tema_id INT UNSIGNED NOT NULL,
 titulo VARCHAR(200) NOT NULL,
 tipo ENUM('video','libro','enlace') NOT NULL,
 url VARCHAR(2048) NOT NULL,
 descripcion TEXT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 KEY idx_recursos_tema (tema_id, activo),
 CONSTRAINT fk_recursos_tema FOREIGN KEY (tema_id) REFERENCES temas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cuestionarios (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tema_id INT UNSIGNED NOT NULL UNIQUE,
 titulo VARCHAR(200) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 0,
 CONSTRAINT fk_cuestionarios_tema FOREIGN KEY (tema_id) REFERENCES temas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS preguntas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cuestionario_id INT UNSIGNED NOT NULL,
 enunciado TEXT NOT NULL,
 explicacion TEXT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 KEY idx_preguntas_cuestionario (cuestionario_id, activo),
 CONSTRAINT fk_preguntas_cuestionario FOREIGN KEY (cuestionario_id) REFERENCES cuestionarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS opciones (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pregunta_id INT UNSIGNED NOT NULL,
 texto TEXT NOT NULL,
 es_correcta TINYINT(1) NOT NULL DEFAULT 0,
 orden TINYINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_opcion_orden (pregunta_id, orden),
 CONSTRAINT fk_opciones_pregunta FOREIGN KEY (pregunta_id) REFERENCES preguntas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS intentos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT UNSIGNED NOT NULL,
 cuestionario_id INT UNSIGNED NOT NULL,
 iniciado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 finalizado_en DATETIME NULL,
 estado ENUM('en_progreso','finalizado') NOT NULL DEFAULT 'en_progreso',
 aciertos TINYINT UNSIGNED NULL,
 calificacion DECIMAL(5,2) NULL,
 KEY idx_intentos_usuario (usuario_id, iniciado_en),
 KEY idx_intentos_cuestionario (cuestionario_id),
 CONSTRAINT fk_intentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
 CONSTRAINT fk_intentos_cuestionario FOREIGN KEY (cuestionario_id) REFERENCES cuestionarios(id)
) ENGINE=InnoDB;

-- Copia inmutable de las diez preguntas de un intento.
CREATE TABLE IF NOT EXISTS intento_preguntas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 intento_id BIGINT UNSIGNED NOT NULL,
 pregunta_origen_id INT UNSIGNED NULL,
 enunciado TEXT NOT NULL,
 explicacion TEXT NULL,
 orden TINYINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_intento_orden (intento_id, orden),
 KEY idx_ip_origen (pregunta_origen_id),
 CONSTRAINT fk_ip_intento FOREIGN KEY (intento_id) REFERENCES intentos(id),
 CONSTRAINT fk_ip_origen FOREIGN KEY (pregunta_origen_id) REFERENCES preguntas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Copia de alternativas y clave de corrección (NUNCA enviar es_correcta antes de finalizar).
CREATE TABLE IF NOT EXISTS intento_opciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 intento_pregunta_id BIGINT UNSIGNED NOT NULL,
 texto TEXT NOT NULL,
 es_correcta TINYINT(1) NOT NULL,
 orden TINYINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_io_orden (intento_pregunta_id, orden),
 UNIQUE KEY uq_io_id_pregunta (id, intento_pregunta_id),
 CONSTRAINT fk_io_pregunta FOREIGN KEY (intento_pregunta_id) REFERENCES intento_preguntas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS respuestas_estudiante (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 intento_pregunta_id BIGINT UNSIGNED NOT NULL UNIQUE,
 intento_opcion_id BIGINT UNSIGNED NOT NULL,
 respondido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_respuesta_pregunta FOREIGN KEY (intento_pregunta_id) REFERENCES intento_preguntas(id),
 CONSTRAINT fk_respuesta_opcion_misma_pregunta FOREIGN KEY (intento_opcion_id, intento_pregunta_id)
   REFERENCES intento_opciones(id, intento_pregunta_id)
) ENGINE=InnoDB;

-- Semillas idempotentes: solo curso, materias y tres temas. Sin usuarios ni cuestionarios vacíos.
INSERT INTO cursos (nombre) VALUES ('Sexto de secundaria')
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);
INSERT INTO materias (curso_id, nombre, descripcion)
SELECT c.id, 'Historia / Ciencias Sociales', 'Estudio de procesos históricos y sociales' FROM cursos c WHERE c.nombre='Sexto de secundaria'
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);
INSERT INTO materias (curso_id, nombre, descripcion)
SELECT c.id, 'Matemáticas', 'Razonamiento matemático y resolución de problemas' FROM cursos c WHERE c.nombre='Sexto de secundaria'
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);
INSERT INTO materias (curso_id, nombre, descripcion)
SELECT c.id, 'Física', 'Principios y leyes de los fenómenos físicos' FROM cursos c WHERE c.nombre='Sexto de secundaria'
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);
INSERT INTO temas (materia_id, titulo, descripcion, orden, estado)
SELECT m.id, 'Independencia de Bolivia', 'Materiales sobre el proceso de independencia de Bolivia', 1, 'borrador' FROM materias m JOIN cursos c ON c.id=m.curso_id WHERE c.nombre='Sexto de secundaria' AND m.nombre='Historia / Ciencias Sociales'
 ON DUPLICATE KEY UPDATE titulo=VALUES(titulo);
INSERT INTO temas (materia_id, titulo, descripcion, orden, estado)
SELECT m.id, 'Ecuaciones de segundo grado', 'Conceptos y resolución de ecuaciones cuadráticas', 1, 'borrador' FROM materias m JOIN cursos c ON c.id=m.curso_id WHERE c.nombre='Sexto de secundaria' AND m.nombre='Matemáticas'
 ON DUPLICATE KEY UPDATE titulo=VALUES(titulo);
INSERT INTO temas (materia_id, titulo, descripcion, orden, estado)
SELECT m.id, 'Leyes de Newton', 'Principios fundamentales de las leyes del movimiento', 1, 'borrador' FROM materias m JOIN cursos c ON c.id=m.curso_id WHERE c.nombre='Sexto de secundaria' AND m.nombre='Física'
 ON DUPLICATE KEY UPDATE titulo=VALUES(titulo);
