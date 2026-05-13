CREATE DATABASE ct_usm_postulaciones;
USE ct_usm_postulaciones;
CREATE TABLE region (
    id_region INT AUTO_INCREMENT PRIMARY KEY,
    nombre_region VARCHAR(40) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE estado_postulacion (
    id_estado_postulacion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_estado_postulacion VARCHAR(11) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE tipo_iniciativa (
    id_tipo_iniciativa INT AUTO_INCREMENT PRIMARY KEY,
    nombre_tipo_iniciativa VARCHAR(9) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE sede (
    id_sede INT AUTO_INCREMENT PRIMARY KEY,
    nombre_sede VARCHAR(30) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE tamanio_empresa (
    id_tamanio INT AUTO_INCREMENT PRIMARY KEY,
    tamanio VARCHAR(12) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE tipo_persona (
    id_tipo_persona INT AUTO_INCREMENT PRIMARY KEY,
    nombre_tipo_persona VARCHAR(10) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE convenio_usm (
    id_convenio_usm INT AUTO_INCREMENT PRIMARY KEY,
    convenio_usm VARCHAR(2) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE etapa (
    cod_etapa INT AUTO_INCREMENT PRIMARY KEY,
    nombre_etapa VARCHAR(25) NOT NULL,
    plazo INT NOT NULL,
    entregable VARCHAR(255) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE empresa (
    rut_empresa VARCHAR(12) PRIMARY KEY,
    id_tamanio INT,
    nombre_empresa VARCHAR(150) NOT NULL,
    nombre_representante VARCHAR(150) NOT NULL,
    telefono_representante INT NOT NULL,
    id_convenio_usm INT,
    FOREIGN KEY (id_tamanio) REFERENCES tamanio_empresa(id_tamanio) ON DELETE CASCADE,
    FOREIGN KEY (id_convenio_usm) REFERENCES convenio_usm(id_convenio_usm) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE integrante (
    rut_integrante VARCHAR(12) PRIMARY KEY,
    nombre_integrante VARCHAR(60) NOT NULL,
    depto_integrante VARCHAR(40) NOT NULL,
    email_integrante VARCHAR(40) NOT NULL,
    telefono_integrante INT NOT NULL,
    id_sede INT,
    id_tipo_persona INT,
    FOREIGN KEY (id_sede) REFERENCES sede(id_sede) ON DELETE CASCADE,
    FOREIGN KEY (id_tipo_persona) REFERENCES tipo_persona(id_tipo_persona) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE postulacion (
    codigo_postulacion INT PRIMARY KEY,
    fecha_postulacion DATE NOT NULL,
    responsable_1 VARCHAR(50) NOT NULL,
    responsable_2 VARCHAR(50),
    numero_postulacion INT NOT NULL UNIQUE,
    presupuesto INT NOT NULL,
    id_sede INT,
    id_region_ejecucion INT,
    id_region_impacto INT,
    rut_empresa VARCHAR(12),
    id_estado_postulacion INT,
    FOREIGN KEY (id_sede) REFERENCES sede(id_sede) ON DELETE CASCADE,
    FOREIGN KEY (id_region_ejecucion) REFERENCES region(id_region) ON DELETE CASCADE,
    FOREIGN KEY (id_region_impacto) REFERENCES region(id_region) ON DELETE CASCADE,
    FOREIGN KEY (rut_empresa) REFERENCES empresa(rut_empresa) ON DELETE CASCADE,
    FOREIGN KEY (id_estado_postulacion) REFERENCES estado_postulacion(id_estado_postulacion) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE cronograma (
    codigo_postulacion INT,
    cod_etapa INT,
    PRIMARY KEY (codigo_postulacion, cod_etapa),
    FOREIGN KEY (codigo_postulacion) REFERENCES postulacion(codigo_postulacion) ON DELETE CASCADE,
    FOREIGN KEY (cod_etapa) REFERENCES etapa(cod_etapa) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE iniciativa (
    cod_iniciativa INT AUTO_INCREMENT PRIMARY KEY,
    nombre_iniciativa VARCHAR(40) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    solucion VARCHAR(255) NOT NULL,
    resultados_esperados VARCHAR(255) NOT NULL,
    documentos VARCHAR(255) NOT NULL,
    codigo_postulacion INT,
    id_tipo_iniciativa INT,
    FOREIGN KEY (codigo_postulacion) REFERENCES postulacion(codigo_postulacion) ON DELETE CASCADE,
    FOREIGN KEY (id_tipo_iniciativa) REFERENCES tipo_iniciativa(id_tipo_iniciativa) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE equipo (
    codigo_postulacion INT,
    rut_integrante VARCHAR(12),
    rol_integrante VARCHAR(30) NOT NULL,
    PRIMARY KEY (codigo_postulacion, rut_integrante),
    FOREIGN KEY (rut_integrante) REFERENCES integrante(rut_integrante) ON DELETE CASCADE,
    FOREIGN KEY (codigo_postulacion) REFERENCES postulacion(codigo_postulacion) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(13) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    id_rol INT NOT NULL,
    FOREIGN KEY (id_rol) REFERENCES rol(id_rol) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE VIEW VistaMisPostulaciones AS
SELECT
    postulacion.*,
    iniciativa.nombre_iniciativa,
    iniciativa.descripcion,
    iniciativa.solucion,
    iniciativa.resultados_esperados,
    iniciativa.documentos,
    estado_postulacion.nombre_estado_postulacion,
    sede.nombre_sede,
    empresa.nombre_empresa,
    re.nombre_region AS nombre_region_ejecucion,
    ri.nombre_region AS nombre_region_impacto,
    tipo_iniciativa.nombre_tipo_iniciativa

FROM postulacion
LEFT JOIN iniciativa ON postulacion.codigo_postulacion = iniciativa.codigo_postulacion
LEFT JOIN estado_postulacion ON postulacion.id_estado_postulacion = estado_postulacion.id_estado_postulacion
LEFT JOIN sede ON postulacion.id_sede = sede.id_sede
LEFT JOIN empresa ON postulacion.rut_empresa = empresa.rut_empresa
LEFT JOIN region re ON postulacion.id_region_ejecucion = re.id_region
LEFT JOIN region ri ON postulacion.id_region_impacto = ri.id_region
LEFT JOIN tipo_iniciativa ON iniciativa.id_tipo_iniciativa = tipo_iniciativa.id_tipo_iniciativa;

DELIMITER //
CREATE TRIGGER trg_validar_presupuesto
BEFORE insert ON postulacion
FOR EACH ROW
BEGIN
    IF NEW.presupuesto < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: El presupuesto ingresado no puede ser un valor negativo.';
    END IF;
END 
// DELIMITER ;

DELIMITER //

CREATE PROCEDURE VerificarRequisitoEquipo(
    IN ejecucion_codigo_postulacion INT
)
BEGIN    
    DECLARE cantidad_profesores INT;
    DECLARE cantidad_estudiantes INT;

    SELECT 
        COUNT(*) INTO cantidad_profesores
    FROM equipo
    INNER JOIN integrante ON equipo.rut_integrante = integrante.rut_integrante
    INNER JOIN tipo_persona ON integrante.id_tipo_persona = tipo_persona.id_tipo_persona
    WHERE equipo.codigo_postulacion = ejecucion_codigo_postulacion 
    AND tipo_persona.nombre_tipo_persona = 'Profesor';  
 
    SELECT 
        COUNT(*) INTO cantidad_estudiantes
    FROM equipo
    INNER JOIN integrante ON equipo.rut_integrante = integrante.rut_integrante
    INNER JOIN tipo_persona ON integrante.id_tipo_persona = tipo_persona.id_tipo_persona
    WHERE equipo.codigo_postulacion = ejecucion_codigo_postulacion 
    AND tipo_persona.nombre_tipo_persona = 'Estudiante'; 

    IF cantidad_profesores < 3 THEN

        UPDATE postulacion
        SET id_estado_postulacion = 3
        WHERE codigo_postulacion = ejecucion_codigo_postulacion;
        
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: El equipo debe tener un mínimo de 3 profesores para enviar la postulación.';

    ELSEIF cantidad_estudiantes < 5 THEN  
        
        UPDATE postulacion
        SET id_estado_postulacion = 3
        WHERE codigo_postulacion = ejecucion_codigo_postulacion;
        
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: El equipo debe tener un mínimo de 5 alumnos para enviar la postulación.';

    ELSE

        UPDATE postulacion
        SET id_estado_postulacion = 2
        WHERE codigo_postulacion = ejecucion_codigo_postulacion;

    END IF;

END
// 
DELIMITER ;

DELIMITER //
CREATE PROCEDURE VerificarRequisitoCronograma(
    IN ejecucion_codigo_postulacion INT
)
BEGIN
    DECLARE suma_plazos INT;

    SELECT COALESCE(SUM(etapa.plazo), 0) INTO suma_plazos
    FROM cronograma
    INNER JOIN etapa ON cronograma.cod_etapa = etapa.cod_etapa
    WHERE cronograma.codigo_postulacion = ejecucion_codigo_postulacion;

    IF suma_plazos > 36 THEN  
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: La suma de las etapas del cronograma no puede superar las 36 semanas (9 meses).';
    END IF;
END
// 
DELIMITER ;