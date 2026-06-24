<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_POST['usuario_id'] ?? 0;
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_rol' && ($_POST['nuevo_rol'] ?? '')) {
        $pdo->prepare("UPDATE usuarios SET rol = :r WHERE id = :id")->execute([':r' => $_POST['nuevo_rol'], ':id' => $uid]);
        $mensaje = ['exito', 'Rol actualizado.'];
    } elseif ($accion === 'bloquear') {
        $bloq = ($_POST['bloqueado'] ?? '0') === '1' ? 1 : 0;
        $hasta = $bloq ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null;
        $pdo->prepare("UPDATE usuarios SET bloqueado = :b, bloqueo_hasta = :h WHERE id = :id")->execute([':b' => $bloq, ':h' => $hasta, ':id' => $uid]);
        $mensaje = ['exito', $bloq ? 'Usuario bloqueado.' : 'Usuario desbloqueado.'];
    } elseif ($accion === 'desbloquear_foco') {
        $pdo->prepare("DELETE FROM bloqueos_actividad WHERE usuario_id = :uid")->execute([':uid' => $uid]);
        $pdo->prepare("UPDATE grupo_miembros SET bloqueado = 0 WHERE usuario_id = :uid")->execute([':uid' => $uid]);
        $mensaje = ['exito', 'Bloqueo por foco removido.'];
    }
}

$busqueda = $_GET['q'] ?? '';
$sql = "SELECT * FROM usuarios";
$params = [];
if ($busqueda) { $sql .= " WHERE username LIKE :q OR nombre LIKE :q2 OR email LIKE :q3"; $params = [':q' => "%$busqueda%", ':q2' => "%$busqueda%", ':q3' => "%$busqueda%"]; }
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$usuarios = $stmt->fetchAll();

$titulo = 'Gestión de Usuarios';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>👥 Usuarios</h1></div>

<?php if ($mensaje): ?><div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div><?php endif; ?>

<div class="card">
    <form method="GET" style="margin-bottom:16px"><input type="text" name="q" class="input-dato" placeholder="Buscar..." value="<?= sanitizar($busqueda) ?>"><button type="submit" class="btn-primario" style="margin-left:6px">Buscar</button></form>
    <table>
        <tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Bloqueado</th><th>Acciones</th></tr>
        <?php foreach($usuarios as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td><td><?= sanitizar($u['username']) ?></td><td><?= sanitizar($u['nombre']) ?></td><td><?= sanitizar($u['email']) ?></td>
            <td><span class="rol-badge rol-<?= $u['rol'] ?>"><?= $u['rol'] ?></span></td>
            <td><?= $u['bloqueado'] ? '🔒 Sí' : '✅ No' ?></td>
            <td style="display:flex;gap:4px;flex-wrap:wrap">
                <form method="POST" style="display:inline"><input type="hidden" name="usuario_id" value="<?= $u['id'] ?>"><input type="hidden" name="accion" value="cambiar_rol"><select name="nuevo_rol" onchange="this.form.submit()"><option value="estudiante" <?= $u['rol']==='estudiante'?'selected':'' ?>>Estudiante</option><option value="docente" <?= $u['rol']==='docente'?'selected':'' ?>>Docente</option><option value="admin" <?= $u['rol']==='admin'?'selected':'' ?>>Admin</option></select></form>
                <form method="POST" style="display:inline"><input type="hidden" name="usuario_id" value="<?= $u['id'] ?>"><input type="hidden" name="accion" value="bloquear"><input type="hidden" name="bloqueado" value="<?= $u['bloqueado'] ? '0' : '1' ?>"><button type="submit" class="btn-sm <?= $u['bloqueado']?'btn-exito':'btn-peligro' ?>"><?= $u['bloqueado']?'Desbloquear':'Bloquear' ?></button></form>
                <form method="POST" style="display:inline"><input type="hidden" name="usuario_id" value="<?= $u['id'] ?>"><input type="hidden" name="accion" value="desbloquear_foco"><button type="submit" class="btn-sm" style="background:#f59e0b;color:#000">Desbloquear foco</button></form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
