<?php
/**
 * admin/configuracion.php — Configuracion global del sistema
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$usuario = usuario_actual($pdo);
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $uid = (int)$_POST['user_id'];
    $nueva = $_POST['nueva_password'];
    $nueva2 = $_POST['nueva_password2'];

    if ($nueva !== $nueva2) {
        $mensaje = ['error', 'Las contraseñas no coinciden.'];
    } elseif (strlen($nueva) < 6) {
        $mensaje = ['error', 'Mínimo 6 caracteres.'];
    } else {
        $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE usuarios SET password_hash = :p WHERE id = :id")->execute([':p' => $hash, ':id' => $uid]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Contraseña actualizada.'];
        redirigir('admin/configuracion.php');
    }
}

$titulo = 'Configuración';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>⚙️ Configuración del Sistema</h1>
</div>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card">
    <h2>🔑 Cambiar contraseña de usuario</h2>
    <form method="POST" class="form-inline">
        <input type="hidden" name="cambiar_password" value="1">
        <select name="user_id" class="input-dato" required>
            <option value="">Seleccionar usuario...</option>
            <?php
            $users = $pdo->query("SELECT id, username, nombre, rol FROM usuarios ORDER BY rol, username")->fetchAll();
            foreach ($users as $u):
            ?>
                <option value="<?= $u['id'] ?>"><?= sanitizar($u['nombre']) ?> (<?= sanitizar($u['username']) ?>) — <?= $u['rol'] ?></option>
            <?php endforeach; ?>
        </select>
        <input type="password" name="nueva_password" class="input-dato" placeholder="Nueva contraseña" required>
        <input type="password" name="nueva_password2" class="input-dato" placeholder="Repetir contraseña" required>
        <button type="submit" class="btn-primario">Actualizar</button>
    </form>
</div>

<div class="card">
    <h2>📊 Estadísticas del sistema</h2>
    <table class="tabla">
        <tr><td><strong>Usuarios totales</strong></td><td><?= $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn() ?></td></tr>
        <tr><td><strong>Admin</strong></td><td><?= $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='admin'")->fetchColumn() ?></td></tr>
        <tr><td><strong>Docentes</strong></td><td><?= $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='docente'")->fetchColumn() ?></td></tr>
        <tr><td><strong>Estudiantes</strong></td><td><?= $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='estudiante'")->fetchColumn() ?></td></tr>
        <tr><td><strong>Grupos creados</strong></td><td><?= $pdo->query("SELECT COUNT(*) FROM grupos")->fetchColumn() ?></td></tr>
        <tr><td><strong>Evaluaciones realizadas</strong></td><td><?= $pdo->query("SELECT COUNT(*) FROM evaluacion_intentos WHERE finalizada=1")->fetchColumn() ?></td></tr>
        <tr><td><strong>Sesiones registradas</strong></td><td><?= $pdo->query("SELECT COUNT(*) FROM sesiones_log")->fetchColumn() ?></td></tr>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
