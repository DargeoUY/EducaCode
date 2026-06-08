# CONTEXTO DEL PROYECTO — Plataforma Educativa

## Para IAs: este archivo es el punto de entrada. Leelo completo antes de trabajar.

## Estado actual del proyecto (junio 2026)

El proyecto acaba de ser creado. Está **completo en su versión 1.0.0** pero **NO fue probado ni deployado**. Todas las funcionalidades están codificadas pero pendientes de verificación en un entorno real (InfinityFree con MySQL).

## Qué hace la plataforma

Plataforma educativa web tipo LMS (Learning Management System) con:
- **3 roles**: Administrador, Docente, Estudiante
- **Grupos**: los docentes crean grupos, generan código de invitación (6 chars)
- **Estudiantes**: se registran, ingresan código para unirse a grupos
- **Evaluaciones**: con 11 medidas anti-trampa (fullscreen, bloqueo atajos, detección salidas de pestaña, etc.)
- **Banco de preguntas**: reutilizable por materia
- **Notificaciones**: docente puede enviar avisos al grupo
- **Estadísticas y exportación CSV**

## Stack tecnológico

| Componente | Tecnología | Versión |
|---|---|---|
| Backend | PHP vanilla (sin framework) | 7.4+ |
| Base de datos | MySQL | 5.7+ (InfinityFree) |
| Frontend | HTML + CSS + JS vanilla | — |
| Hosting | InfinityFree (gratuito) | — |
| Sesiones | PHP Sessions | — |
| Hasheo contraseñas | bcrypt (cost 12) | — |
| Dependencias externas | Ninguna (0 dependencias) | — |

NO usa Composer, npm, frameworks, ni librerías externas. Es PHP puro para ser compatible con InfinityFree.

## Estructura de archivos (37 archivos)

```
plataforma/
├── config.php                 # Conexion PDO + config + sesiones (EDITAR credenciales)
├── index.php                  # Login
├── registrar.php              # Registro estudiante (con pregunta secreta)
├── registrar-docente.php      # Solicitud cuenta docente (admin aprueba)
├── logout.php                 # Cerrar sesion
├── setup.php                  # Configurar contrasena admin (1er uso, luego ELIMINAR)
├── .gitignore                 # Excluye config.php, setup.php, uploads/
│
├── admin/
│   ├── dashboard.php          # Stats globales + logs de sesion
│   ├── usuarios.php           # CRUD usuarios, bloquear, cambiar rol
│   ├── solicitudes.php        # Aprobar/rechazar solicitudes docente
│   ├── grupos.php             # Ver todos los grupos
│   └── configuracion.php      # Cambiar passwords, stats sistema
│
├── docente/
│   ├── dashboard.php          # Stats de mis grupos
│   ├── grupos.php             # Listar mis grupos (tarjetas)
│   ├── grupo-crear.php        # Crear grupo + codigo automatico
│   ├── grupo-editar.php       # Agregar alumnos, bloquear, notificar
│   ├── materiales.php         # Subir links/archivos/multimedia
│   ├── actividades.php        # Crear actividades con fecha limite
│   ├── evaluaciones.php       # Listar evaluaciones del grupo
│   ├── evaluacion-crear.php   # Creador visual de preguntas + banco
│   ├── evaluacion-resultados.php # Ver resultados, tab_salidas
│   ├── banco-preguntas.php    # Preguntas reutilizables por materia
│   ├── estadisticas.php       # Promedios, tasas aprobacion
│   └── exportar-notas.php     # CSV descargable
│
├── estudiante/
│   ├── dashboard.php          # Ingresar codigo + notificaciones
│   ├── mis-grupos.php         # Grupos a los que pertenece
│   ├── grupo.php              # Materiales, actividades, evaluaciones
│   └── evaluacion.php         # Rendir con 11 medidas anti-trampa
│
├── api/
│   ├── guardar-evaluacion.php # Calcular puntaje y guardar
│   ├── registrar-tab.php      # Registrar salida de pestana
│   └── notificaciones.php     # Obtener notificaciones
│
├── includes/
│   ├── auth.php               # Middleware require_login/require_admin/etc
│   ├── db_setup.sql           # 12 tablas MySQL
│   ├── funciones.php          # Utilidades (codigo, sanitizar, etc.)
│   ├── header.php             # Cabecera HTML + navbar por rol
│   └── footer.php             # Pie HTML
│
└── assets/
    ├── css/estilos.css        # Estilos completos (tema oscuro por defecto)
    └── js/app.js              # JS general (auto-ocultar flash)
```

## Base de datos: 12 tablas

1. **usuarios** — username, password_hash (bcrypt), rol (admin/docente/estudiante), bloqueo
2. **grupos** — nombre, codigo_invitacion, codigo_expiracion, docente_id (FK)
3. **grupo_miembros** — grupo_id, usuario_id, bloqueado (FKs)
4. **materiales** — grupo_id, titulo, tipo (link/archivo/html), contenido_url
5. **actividades** — grupo_id, titulo, tipo, contenido, fecha_limite
6. **evaluaciones** — grupo_id, preguntas_json, puntaje_max, duracion_min, intentos_max, shuffle, etc.
7. **evaluacion_intentos** — evaluacion_id, usuario_id, respuestas_json, puntaje, tab_salidas
8. **tab_salidas** — intento_id, tipo_evento (visibility/blur/fullscreen_exit/devtools)
9. **notificaciones** — grupo_id, titulo, mensaje
10. **banco_preguntas** — docente_id, materia, tipo, opciones_json, respuesta_correcta
11. **sesiones_log** — usuario_id, ip, user_agent, accion
12. **solicitudes_docente** — usuario_id, estado (pendiente/aprobada/rechazada)

## Medidas anti-trampa implementadas

La evaluacion (`estudiante/evaluacion.php`) tiene 11 capas:

1. **Visibility API** — detecta cambio de pestaña, registra en BD
2. **Fullscreen forzado** — no permite empezar sin pantalla completa
3. **Bloqueo click derecho** — `contextmenu` prevent
4. **Bloqueo atajos** — Ctrl+C/V/U/S/A/P, F12, Ctrl+Shift+I/J/C
5. **Bloqueo selección texto** — CSS `user-select: none`
6. **Bloqueo copiar/pegar** — eventos copy/cut/paste
7. **Auto-envío a los 3 tabs** — entrega automática si detecta 3+ salidas
8. **Watermark nombre** — overlay semitransparente con nombre del alumno
9. **Temporizador** — cuenta regresiva con envío automático al expirar
10. **Detección DevTools** — monitorea diff outerWidth - innerWidth
11. **beforeunload** — advierte antes de cerrar/recargar

## Flujos principales

### Registro estudiante
1. `registrar.php` → crea cuenta (estudiante)
2. Login → `estudiante/dashboard.php` → ingresa código de grupo
3. `estudiante/mis-grupos.php` → ve grupos
4. `estudiante/grupo.php` → materiales, actividades, evaluaciones
5. `estudiante/evaluacion.php` → rinde evaluación con anti-trampa

### Registro docente
1. `registrar-docente.php` → solicita cuenta (queda como estudiante)
2. Admin en `admin/solicitudes.php` → aprueba → cambia rol a docente
3. Login → `docente/dashboard.php`
4. `docente/grupo-crear.php` → crea grupo
5. `docente/grupo-editar.php` → gestiona miembros, notifica
6. `docente/evaluacion-crear.php` → crea evaluación
7. `docente/evaluacion-resultados.php` → ve resultados

## Pendiente (TO-DO)

- [ ] Editar `config.php` con credenciales reales de InfinityFree
- [ ] Ejecutar `includes/db_setup.sql` en phpMyAdmin
- [ ] Ejecutar `setup.php` desde el navegador para crear contraseña admin
- [ ] **Eliminar `setup.php`** por seguridad
- [ ] Probar el flujo completo: registro → login → unirse a grupo → rendir evaluación
- [ ] Verificar que las medidas anti-trampa funcionan en navegadores reales
- [ ] Testear en dispositivos móviles
- [ ] Subir a InfinityFree via FTP

## Notas importantes para IAs

- **NO uses frameworks, Composer, ni npm.** Todo es vanilla PHP/JS/CSS.
- **Las sesiones son nativas de PHP.** No hay JWT ni tokens complejos.
- **Las contraseñas usan bcrypt cost 12.** `password_hash()` / `password_verify()`.
- **Las preguntas se almacenan como JSON** en `preguntas_json` (LONGTEXT).
- **Los endpoints API esperan JSON** con `Content-Type: application/json`.
- **El CSS es tema oscuro** basado en el diseño del proyecto `lti-test/`.
- **Los roles se validan con middleware** en `includes/auth.php` (funciones require_admin, etc.).
- **El código de grupo es 6 caracteres alfanuméricos** sin vocales ni símbolos confusos.
- **La protección anti-trampa es client-side** (JS). Las salidas se registran server-side via API.
- **InfinityFree no permite WebSocket ni procesos largos.** Todo es request/response estándar.
