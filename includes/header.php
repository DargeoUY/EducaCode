<?php
/**
 * header.php — Cabecera HTML comun con navegacion por rol
 * Espera las variables: $titulo, $usuario (array del usuario logueado)
 */
if (!isset($usuario)) $usuario = usuario_actual($pdo);
$rol = $usuario['rol'] ?? '';
$nombre = $usuario['nombre'] ?? '';
$noLeidas = ($rol && $rol !== 'admin') ? notificacionesNoLeidas($pdo, $usuario['id']) : 0;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; connect-src 'self' https:; upgrade-insecure-requests;">
    <title><?= sanitizar($titulo ?? 'Plataforma Educativa') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilos.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body>
<div class="fondo-particulas"></div>

<nav class="navbar">
    <div class="nav-container">
        <a href="<?= BASE_URL ?>" class="nav-logo">🎓 Plataforma</a>
        <div class="nav-links">
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
                <span class="notif-badge" title="Notificaciones nuevas">🔔 <?= $noLeidas ?></span>
            <?php endif; ?>
            <span class="nav-username"><?= sanitizar($nombre) ?></span>
            <span class="nav-rol rol-<?= $rol ?>"><?= strtoupper($rol) ?></span>
            <a href="<?= BASE_URL ?>logout.php" class="btn-logout" title="Cerrar sesión">↪</a>
        </div>
    </div>
</nav>

<main class="main-content">
<?php if (isset($_SESSION['flash'])): ?>
    <div class="flash flash-<?= $_SESSION['flash']['tipo'] ?? 'info' ?>">
        <?= sanitizar($_SESSION['flash']['mensaje']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
