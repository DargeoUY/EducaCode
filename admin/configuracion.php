<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$usuario = usuario_actual($pdo);
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cambiar_password'])) {
        $pw = $_POST['password'] ?? '';
        $pw2 = $_POST['password2'] ?? '';
        if (strlen($pw) < 6) $mensaje = ['error', 'Mínimo 6 caracteres.'];
        elseif ($pw !== $pw2) $mensaje = ['error', 'No coinciden.'];
        else {
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE usuarios SET password_hash = :h WHERE id = :id")->execute([':h' => $hash, ':id' => $usuario['id']]);
            $mensaje = ['exito', 'Contraseña actualizada.'];
        }
    }
}

$titulo = 'Configuración';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>⚙️ Configuración</h1></div>
<?php if ($mensaje): ?><div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div><?php endif; ?>

<div class="card" style="max-width:500px">
    <h2>Cambiar contraseña</h2>
    <form method="POST">
        <div class="grupo-input"><label>Nueva contraseña</label><input type="password" name="password" class="input-dato" required></div>
        <div class="grupo-input"><label>Repetir</label><input type="password" name="password2" class="input-dato" required></div>
        <button type="submit" name="cambiar_password" class="btn-primario">Guardar</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
