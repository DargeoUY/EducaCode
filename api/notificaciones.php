<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$uid = $_SESSION['usuario_id'];
$notifs = obtenerNotificaciones($pdo, $uid);
jsonRespuesta(true, ['notificaciones' => $notifs, 'total' => count($notifs)]);
