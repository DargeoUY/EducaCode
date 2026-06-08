# Plataforma Educativa — Pensamiento Computacional

LMS educativo con roles (admin, docente, estudiante), grupos con código de invitación, evaluaciones con medidas anti-trampa y banco de preguntas.

## Stack

PHP 7.4+ (vanilla) + MySQL 5.7+ + HTML/CSS/JS vanilla. Cero dependencias externas.

## Instalación rápida

1. Crear base de datos MySQL
2. Ejecutar `includes/db_setup.sql` en phpMyAdmin
3. Editar `config.php` con credenciales de InfinityFree
4. Subir todo por FTP a `htdocs/plataforma/`
5. Entrar a `setup.php` para crear contraseña admin
6. **Eliminar `setup.php`** por seguridad
7. Login: `admin` / tu contraseña

## Roles

| Rol | Capacidades |
|---|---|
| Admin | CRUD usuarios, cambiar roles, ver todos los grupos, stats globales |
| Docente | Crear grupos, agregar/bloquear alumnos, subir materiales, crear evaluaciones, ver resultados y estadísticas |
| Estudiante | Registrarse, unirse a grupos con código, ver materiales, rendir evaluaciones |

## Características principales

- Códigos de invitación de 6 caracteres con expiración opcional
- Evaluaciones con shuffle de preguntas y opciones, límite de intentos, temporizador
- 11 medidas anti-trampa (fullscreen obligatorio, bloqueo de atajos, detección de salidas de pestaña, etc.)
- Banco de preguntas reutilizable por materia
- Notificaciones del docente al grupo
- Dashboard de estadísticas con tasas de aprobación
- Exportación de notas a CSV
- Bloqueo de cuenta tras 5 intentos fallidos de login
- Registro de sesiones con IP y user agent

## Estructura

```
plataforma/
├── config.php
├── index.php (login)
├── registrar.php / registrar-docente.php
├── setup.php (1er uso, eliminar después)
├── admin/ (5 archivos)
├── docente/ (10 archivos)
├── estudiante/ (4 archivos)
├── api/ (3 archivos)
├── includes/ (5 archivos)
└── assets/ (css + js)
```

Ver `AGENTS.md` para documentación técnica detallada.
