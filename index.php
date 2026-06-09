<?php
/**
 * index.php — Pagina de Login
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_rol'] === 'admin') redirigir('admin/dashboard.php');
    elseif ($_SESSION['usuario_rol'] === 'docente') redirigir('docente/dashboard.php');
    else redirigir('estudiante/dashboard.php');
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Completa todos los campos.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = :u OR email = :e LIMIT 1");
            $stmt->execute([':u' => $username, ':e' => $username]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                if (estaBloqueado($usuario)) {
                    $tiempo = strtotime($usuario['bloqueo_hasta']) - time();
                    $min = ceil($tiempo / 60);
                    $error = "Cuenta bloqueada. Intenta de nuevo en $min minuto(s).";
                } elseif (password_verify($password, $usuario['password_hash'])) {
                    $pdo->prepare("UPDATE usuarios SET intentos_login = 0, bloqueo_hasta = NULL, ultimo_login = NOW() WHERE id = :id")
                        ->execute([':id' => $usuario['id']]);

                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_rol'] = $usuario['rol'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];

                    registrarSesion($pdo, $usuario['id'], 'login');

                    if ($redirect && strpos($redirect, 'logout') === false) {
                        header('Location: ' . $redirect);
                    } elseif ($usuario['rol'] === 'admin') {
                        redirigir('admin/dashboard.php');
                    } elseif ($usuario['rol'] === 'docente') {
                        redirigir('docente/dashboard.php');
                    } else {
                        redirigir('estudiante/dashboard.php');
                    }
                    exit;
                } else {
                    $intentos = $usuario['intentos_login'] + 1;
                    if ($intentos >= MAX_INTENTOS_LOGIN) {
                        $bloqueo = date('Y-m-d H:i:s', strtotime('+' . BLOQUEO_MINUTOS . ' minutes'));
                        $pdo->prepare("UPDATE usuarios SET intentos_login = :i, bloqueado = 1, bloqueo_hasta = :b WHERE id = :id")
                            ->execute([':i' => $intentos, ':b' => $bloqueo, ':id' => $usuario['id']]);
                        $error = "Demasiados intentos. Cuenta bloqueada por " . BLOQUEO_MINUTOS . " minutos.";
                    } else {
                        $pdo->prepare("UPDATE usuarios SET intentos_login = :i WHERE id = :id")
                            ->execute([':i' => $intentos, ':id' => $usuario['id']]);
                        $error = "Contraseña incorrecta. Te quedan " . (MAX_INTENTOS_LOGIN - $intentos) . " intento(s).";
                    }
                }
            } else {
                $error = 'Usuario no encontrado.';
            }
        }
    } catch (Exception $e) {
        $error = 'Error del servidor: ' . $e->getMessage();
        error_log('Login error: ' . $e->getMessage());
    }
}

$titulo = 'Iniciar Sesión';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; connect-src 'self' https:; upgrade-insecure-requests;">
    <title>EducaCode — Iniciar Sesión</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilos.css">
    <link rel="icon" href="<?= BASE_URL ?>img/favicon.svg" type="image/svg+xml">
</head>
<body>
<div class="fondo-particulas"></div>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <img src="<?= BASE_URL ?>img/logo-icon.svg" alt="EducaCode" class="login-logo" width="100" height="100">
            <h1>EducaCode</h1>
            <p>Plataforma Educativa</p>
        </div>

        <?php if (isset($_SESSION['flash'])): ?>
            <div class="flash flash-<?= $_SESSION['flash']['tipo'] ?? 'info' ?>"><?= sanitizar($_SESSION['flash']['mensaje']) ?></div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash flash-error"><?= sanitizar($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="login-form">
            <div class="grupo-input">
                <label for="username">Usuario o Email</label>
                <input type="text" id="username" name="username" class="input-dato" placeholder="Tu usuario" value="<?= sanitizar($_POST['username'] ?? '') ?>" required autocomplete="username">
            </div>
            <div class="grupo-input">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="input-dato" placeholder="Tu contraseña" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-primario btn-full">🔐 Ingresar</button>
        </form>

        <div class="login-footer">
            <a href="<?= BASE_URL ?>registrar.php">Crear cuenta de estudiante</a>
            <a href="<?= BASE_URL ?>registrar-docente.php">Solicitar cuenta docente</a>
        </div>
    </div>
</div>
</body>
</html>