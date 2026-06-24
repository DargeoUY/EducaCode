<?php
require_once __DIR__ . '/bloqueo.php';

if (!isset($usuario)) $usuario = usuario_actual($pdo);
$rol = $usuario['rol'] ?? '';
$nombre = $usuario['nombre'] ?? '';
$noLeidas = ($rol && $rol !== 'admin') ? notificacionesNoLeidas($pdo, $usuario['id']) : 0;

if ($rol === 'estudiante') {
    $bloqueo = verificar_bloqueo_actividad($pdo, $usuario['id']);
    if ($bloqueo) {
        mostrar_overlay_bloqueo('Has sido bloqueado por salir de la plataforma. Contacta a tu docente.');
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitizar($titulo ?? 'Plataforma Educativa') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilos.css">
    <link rel="icon" href="<?= BASE_URL ?>img/favicon.svg" type="image/svg+xml">
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="<?= BASE_URL ?>" class="nav-logo">🎓 EducaCode</a>
        <div class="nav-links">
            <a href="<?= BASE_URL ?>ide/">💻 IDE</a>
            <?php if ($rol === 'admin'): ?>
                <a href="<?= BASE_URL ?>admin/dashboard.php">📊 Dashboard</a>
                <a href="<?= BASE_URL ?>admin/usuarios.php">👥 Usuarios</a>
                <a href="<?= BASE_URL ?>admin/grupos.php">📁 Grupos</a>
                <a href="<?= BASE_URL ?>admin/solicitudes.php">📋 Solicitudes</a>
            <?php elseif ($rol === 'docente'): ?>
                <a href="<?= BASE_URL ?>docente/dashboard.php">📊 Dashboard</a>
                <a href="<?= BASE_URL ?>docente/grupos.php">📁 Mis Grupos</a>
                <a href="<?= BASE_URL ?>docente/banco-preguntas.php">🗂 Banco</a>
                <a href="<?= BASE_URL ?>docente/estadisticas.php">📈 Estadísticas</a>
            <?php elseif ($rol === 'estudiante'): ?>
                <a href="<?= BASE_URL ?>estudiante/dashboard.php">🏠 Inicio</a>
                <a href="<?= BASE_URL ?>estudiante/mis-grupos.php">📁 Mis Grupos</a>
            <?php endif; ?>
        </div>
        <div class="nav-user">
            <?php if ($rol && $rol !== 'admin' && $noLeidas > 0): ?>
                <span class="notif-badge">🔔 <?= $noLeidas ?></span>
            <?php endif; ?>
            <span class="nav-username"><?= sanitizar($nombre) ?></span>
            <span class="nav-rol rol-<?= $rol ?>"><?= strtoupper($rol) ?></span>
            <a href="<?= BASE_URL ?>logout.php" class="btn-logout">↪</a>
        </div>
    </div>
</nav>
<main class="main-content">
<?php if (isset($_SESSION['flash'])): ?>
    <div class="flash flash-<?= $_SESSION['flash']['tipo'] ?? 'info' ?>"><?= sanitizar($_SESSION['flash']['mensaje']) ?></div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
<script src="<?= BASE_URL ?>ide/ide-panel.js"></script>
