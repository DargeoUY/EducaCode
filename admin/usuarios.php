<?php
/**
 * admin/usuarios.php — Gestion de usuarios (CRUD por admin)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$usuario = usuario_actual($pdo);
$mensaje = '';

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!validarCSRF()) { $mensaje = ['error', 'Token de seguridad inválido. Recargá la página.']; }
    else {
    if ($_POST['accion'] === 'crear') {
        $u = sanitizar($_POST['username']);
        $n = sanitizar($_POST['nombre']);
        $r = $_POST['rol'];
        $p = $_POST['password'];
        $e = sanitizar($_POST['email'] ?? '');

        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $u]);
        if ($stmt->fetch()) {
            $mensaje = ['error', 'El usuario ya existe.'];
        } elseif (strlen($p) < 6) {
            $mensaje = ['error', 'Contraseña muy corta.'];
        } else {
            $hash = password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO usuarios (username, password_hash, rol, nombre, email) VALUES (:u, :p, :r, :n, :e)")
                ->execute([':u' => $u, ':p' => $hash, ':r' => $r, ':n' => $n, ':e' => $e]);
            $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Usuario creado correctamente.'];
            redirigir('admin/usuarios.php');
        }
    } elseif ($_POST['accion'] === 'bloquear') {
        $uid = (int)$_POST['id'];
        $bloq = (int)$_POST['bloqueado'];
        $pdo->prepare("UPDATE usuarios SET bloqueado = :b, intentos_login = 0, bloqueo_hasta = NULL WHERE id = :id")
            ->execute([':b' => $bloq ? 0 : 1, ':id' => $uid]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => $bloq ? 'Usuario desbloqueado.' : 'Usuario bloqueado.'];
        redirigir('admin/usuarios.php');
    } elseif ($_POST['accion'] === 'cambiar_rol') {
        $uid = (int)$_POST['id'];
        $nuevo_rol = $_POST['nuevo_rol'];
        if ($uid != $usuario['id']) {
            $pdo->prepare("UPDATE usuarios SET rol = :r WHERE id = :id")->execute([':r' => $nuevo_rol, ':id' => $uid]);
            $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Rol actualizado.'];
        } else {
            $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No puedes cambiarte tu propio rol.'];
        }
        redirigir('admin/usuarios.php');
    }
    }
}

$busqueda = $_GET['q'] ?? '';
$sql = "SELECT * FROM usuarios WHERE 1=1";
$params = [];
if ($busqueda) {
    $sql .= " AND (username LIKE :q OR nombre LIKE :q2 OR email LIKE :q3)";
    $params[':q'] = "%$busqueda%";
    $params[':q2'] = "%$busqueda%";
    $params[':q3'] = "%$busqueda%";
}
$sql .= " ORDER BY creado_en DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$titulo = 'Gestión de Usuarios';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>👥 Gestión de Usuarios</h1>
    <p>Crear, editar roles, bloquear/desbloquear</p>
</div>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card">
    <h2>➕ Crear usuario</h2>
    <form method="POST" class="form-inline">
        <?= csrfInput() ?>
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="username" placeholder="Usuario" class="input-dato" required>
        <input type="text" name="nombre" placeholder="Nombre completo" class="input-dato" required>
        <select name="rol" class="input-dato">
            <option value="estudiante">Estudiante</option>
            <option value="docente">Docente</option>
            <option value="admin">Admin</option>
        </select>
        <input type="password" name="password" placeholder="Contraseña" class="input-dato" required>
        <input type="email" name="email" placeholder="Email" class="input-dato">
        <button type="submit" class="btn-primario">Crear</button>
    </form>
</div>

<div class="card">
    <form method="GET" class="form-inline" style="margin-bottom: 16px;">
        <input type="text" name="q" value="<?= sanitizar($busqueda) ?>" placeholder="Buscar por nombre, usuario o email..." class="input-dato" style="flex:1;">
        <button type="submit" class="btn-secundario">🔍 Buscar</button>
    </form>

    <table class="tabla">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Email</th>
                <th>Estado</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr class="<?= $u['bloqueado'] ? 'fila-bloqueada' : '' ?>">
                <td><strong><?= sanitizar($u['username']) ?></strong></td>
                <td><?= sanitizar($u['nombre']) ?></td>
                <td><span class="rol-badge rol-<?= $u['rol'] ?>"><?= $u['rol'] ?></span></td>
                <td><?= sanitizar($u['email'] ?? '—') ?></td>
                <td><?= $u['bloqueado'] ? '🔒 Bloqueado' : '✅ Activo' ?></td>
                <td><?= formatearFecha($u['creado_en']) ?></td>
                <td>
                    <div class="btn-grupo-sm">
                        <form method="POST" style="display:inline;">
                            <?= csrfInput() ?>
                            <input type="hidden" name="accion" value="bloquear">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="bloqueado" value="<?= $u['bloqueado'] ?>">
                            <button type="submit" class="btn-sm <?= $u['bloqueado'] ? 'btn-desbloquear' : 'btn-bloquear' ?>">
                                <?= $u['bloqueado'] ? '🔓 Desbloquear' : '🔒 Bloquear' ?>
                            </button>
                        </form>
                        <?php if ($u['id'] != $usuario['id']): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrfInput() ?>
                            <input type="hidden" name="accion" value="cambiar_rol">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <select name="nuevo_rol" onchange="this.form.submit()" class="input-dato input-sm">
                                <option value="estudiante" <?= $u['rol'] === 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
                                <option value="docente" <?= $u['rol'] === 'docente' ? 'selected' : '' ?>>Docente</option>
                                <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
