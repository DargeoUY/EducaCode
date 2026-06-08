<?php
/**
 * api/notificaciones.php — Obtener notificaciones del estudiante
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_estudiante();

$usuario = usuario_actual($pdo);
$notificaciones = obtenerNotificaciones($pdo, $usuario['id']);

jsonRespuesta(true, [
    'notificaciones' => $notificaciones,
    'total' => count($notificaciones)
]);
