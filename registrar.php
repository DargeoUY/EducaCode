<?php
/**
 * registrar.php — Registro de estudiantes
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['usuario_id'])) redirigir('estudiante/dashboard.php');

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $pregunta = trim($_POST['pregunta_secreta'] ?? '');
    $respuesta = trim($_POST['respuesta_secreta'] ?? '');

    if ($username === '' || $nombre === '' || $password === '' || $password2 === '' || $pregunta === '' || $respuesta === '') {
        $error = 'Completa todos los campos obligatorios.';
    } elseif (strlen($username) < 3) {
        $error = 'El usuario debe tener al menos 3 caracteres.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = :u OR email = :e LIMIT 1");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            $error = 'El usuario o email ya está registrado.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $respuesta_hash = password_hash(strtolower(trim($respuesta)), PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, rol, nombre, email, pregunta_secreta, respuesta_secreta_hash) VALUES (:u, :p, 'estudiante', :n, :e, :ps, :rs)");
            $stmt->execute([
                ':u' => $username,
                ':p' => $hash,
                ':n' => $nombre,
                ':e' => $email ?: null,
                ':ps' => $pregunta,
                ':rs' => $respuesta_hash,
            ]);

            $exito = 'Cuenta creada exitosamente. Ya puedes iniciar sesión.';
        }
    }
}

$titulo = 'Registro de Estudiante';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro — Plataforma Educativa</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilos.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body>
<div class="fondo-particulas"></div>

<div class="login-container">
    <div class="login-card" style="max-width:480px;">
        <div class="login-header">
            <div class="login-icono">📝</div>
            <h1>Crear Cuenta</h1>
            <p>Registro de estudiante</p>
        </div>

        <?php if ($error): ?>
            <div class="flash flash-error"><?= sanitizar($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="flash flash-exito"><?= sanitizar($exito) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="login-form">
            <div class="input-row-2">
                <div class="grupo-input">
                    <label for="username">Usuario *</label>
                    <input type="text" id="username" name="username" class="input-dato" value="<?= sanitizar($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="grupo-input">
                    <label for="nombre">Nombre completo *</label>
                    <input type="text" id="nombre" name="nombre" class="input-dato" value="<?= sanitizar($_POST['nombre'] ?? '') ?>" required>
                </div>
            </div>
            <div class="grupo-input">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="input-dato" value="<?= sanitizar($_POST['email'] ?? '') ?>">
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
            <div class="grupo-input">
                <label for="pregunta_secreta">Pregunta secreta (recuperacion) *</label>
                <input type="text" id="pregunta_secreta" name="pregunta_secreta" class="input-dato" placeholder="Ej: ¿Nombre de tu primera mascota?" value="<?= sanitizar($_POST['pregunta_secreta'] ?? '') ?>" required>
            </div>
            <div class="grupo-input">
                <label for="respuesta_secreta">Respuesta secreta *</label>
                <input type="text" id="respuesta_secreta" name="respuesta_secreta" class="input-dato" placeholder="Tu respuesta" value="<?= sanitizar($_POST['respuesta_secreta'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn-primario btn-full">📝 Registrarse</button>
        </form>

        <div class="login-footer">
            <a href="<?= BASE_URL ?>index.php">Ya tengo cuenta — Iniciar sesión</a>
        </div>
    </div>
</div>
</body>
</html>