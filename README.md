# Gestor de postulaciones académicas

Aplicación web en PHP y MySQL para administrar postulaciones de iniciativas universitarias. El esquema relacional modela postulaciones, equipos, empresas, sedes, etapas, cronogramas, estados y tipos de iniciativa.

## Funcionalidades

- Inicio de sesión con roles y vistas de postulaciones.
- Creación, consulta, edición y filtrado de postulaciones.
- Administración de empresas y evaluadores desde la vista principal.
- Consultas SQL, vista, función, procedimientos almacenados, trigger y restricciones referenciales.

## Requisitos y configuración

- PHP 8 o posterior con PDO MySQL habilitado.
- MySQL 8 o posterior.
- Servidor local como XAMPP o Apache con PHP.

1. Crea la base importando `BD/DDL.sql` en MySQL.
2. Carga los datos de ejemplo con `BD/DML.sql`.
3. Configura host, usuario y contraseña de MySQL en `src/libs/connection.php` para tu entorno local.
4. Copia el proyecto a la carpeta pública del servidor y abre la ruta del proyecto en el navegador. `index.php` dirige a la pantalla de inicio de sesión.

Los archivos DDL y DML son de demostración del curso. No contienen una guía de credenciales de acceso, por lo que deben crearse o confirmarse en la base de datos antes de probar el inicio de sesión.

## Estructura

- `BD/`: definición del esquema y datos de ejemplo.
- `public/`: pantallas y acciones web.
- `src/`: autenticación, conexión, validación y utilidades compartidas.
- `config/`: configuración del proyecto.

Trabajo académico. Revisa los créditos y las condiciones del curso antes de reutilizarlo fuera de ese contexto.
