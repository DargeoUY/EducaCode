# Historial de Versiones

## [1.0.0] — 2026-06-08 — Versión inicial completa

### Creado
- Sistema de autenticación: login, registro, logout, sesiones PHP con bcrypt
- 3 roles: admin, docente, estudiante con middleware de autorización
- Panel Admin: CRUD usuarios, solicitudes docente, grupos globales, stats
- Panel Docente: dashboard, grupos, materiales, actividades, banco de preguntas
- Panel Estudiante: ingreso por código, mis grupos, ver contenido, rendir evaluación
- Evaluaciones con creador visual de preguntas (multiple choice, V/F, completar)
- 11 medidas anti-trampa implementadas en el frontend de evaluación
- 12 tablas MySQL con claves foráneas e índices
- Banco de preguntas reutilizable por materia (6 materias predefinidas)
- Notificaciones de docente a grupo
- Dashboard de estadísticas con tasas de aprobación y promedios
- Exportación de notas a CSV
- Bloqueo de cuenta tras 5 intentos fallidos (15 min)
- Solo código de invitación compartido (6 caracteres) — original del proyecto: LTI para CREA

### Dependencias
- Ninguna. PHP vanilla + MySQL + HTML/CSS/JS vanilla.
- Hosting objetivo: InfinityFree

### Pendiente
- Probar en entorno real (InfinityFree)
- Verificar medidas anti-trampa en navegadores modernos
- Testear responsive en dispositivos móviles
