<?php
/**
 * registrar-docente.php — Solicitud de cuenta docente
 * El admin debe aprobarla desde admin/solicitudes.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_rol'] === 'docente') redirigir('docente/dashboard.php');
    redirigir('estudiante/dashboard.php');
}

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || $nombre === '' || $password === '' || $password2 === '') {
        $error = 'Completa todos los campos obligatorios.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña docente debe tener al menos 8 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = :u OR email = :e LIMIT 1");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            $error = 'El usuario o email ya está registrado.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO usuarios (username, password_hash, rol, nombre, email) VALUES (:u, :p, 'estudiante', :n, :e)")
                ->execute([':u' => $username, ':p' => $hash, ':n' => $nombre, ':e' => $email]);
            $uid = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO solicitudes_docente (usuario_id) VALUES (:uid)")
                ->execute([':uid' => $uid]);

            $exito = 'Solicitud enviada. Un administrador revisará tu cuenta y te notificará por email.';
        }
    }
}

$titulo = 'Solicitar Cuenta Docente';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Docente — Plataforma Educativa</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilos.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body>
<div class="fondo-particulas"></div>

<div class="login-container">
    <div class="login-card" style="max-width:480px;">
        <div class="login-header">
            <div class="login-icono">👨‍🏫</div>
            <h1>Solicitar Cuenta Docente</h1>
            <p>Un administrador aprobará tu solicitud</p>
        </div>

        <?php if ($error): ?>
            <div class="flash flash-error"><?= sanitizar($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="flash flash-exito"><?= sanitizar($exito) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="login-form">
            <div class="grupo-input">
                <label for="username">Usuario *</label>
                <input type="text" id="username" name="username" class="input-dato" value="<?= sanitizar($_POST['username'] ?? '') ?>" required>
            </div>
            <div class="grupo-input">
                <label for="nombre">Nombre completo *</label>
                <input type="text" id="nombre" name="nombre" class="input-dato" value="<?= sanitizar($_POST['nombre'] ?? '') ?>" required>
            </div>
            <div class="grupo-input">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" class="input-dato" value="<?= sanitizar($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="input-row-2">
                <div class="grupo-input">
                    <label for="password">Contraseña *</label>
                    <input type="password" id="password" name="password" class="input-dato" required>
                </div>
                <div class="grupo-input">
                    <label for="password2">Repetir contraseña *</label>
                    <input type="password" id="password2" name="password2" class="input-dato" required>
                </div>
            </div>
            <button type="submit" class="btn-primario btn-full">📤 Enviar solicitud</button>
        </form>

        <div class="login-footer">
            <a href="<?= BASE_URL ?>index.php">Volver al inicio de sesión</a>
        </div>
    </div>
</div>
</body>
</html>