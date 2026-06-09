<?php
/**
 * docente/grupo-editar.php — Gestionar miembros de un grupo, bloquear, agregar manualmente
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$gid = (int)($_GET['id'] ?? 0);
$grupo = obtenerGrupo($pdo, $gid);
if (!$grupo || ($grupo['docente_id'] != $uid && $usuario['rol'] !== 'admin')) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Grupo no encontrado.'];
    redirigir('docente/grupos.php');
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] === 'agregar_usuario') {
        $username = sanitizar($_POST['username']);
        $stmt = $pdo->prepare("SELECT id,rol FROM usuarios WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $u = $stmt->fetch();
        if (!$u) {
            $mensaje = ['error', 'Usuario no encontrado.'];
        } elseif ($u['rol'] !== 'estudiante') {
            $mensaje = ['error', 'Solo se pueden agregar estudiantes.'];
        } else {
            $existe = $pdo->prepare("SELECT id FROM grupo_miembros WHERE grupo_id = :g AND usuario_id = :u");
            $existe->execute([':g' => $gid, ':u' => $u['id']]);
            if ($existe->fetch()) {
                $mensaje = ['error', 'El usuario ya está en el grupo.'];
            } else {
                $pdo->prepare("INSERT INTO grupo_miembros (grupo_id, usuario_id) VALUES (:g, :u)")
                    ->execute([':g' => $gid, ':u' => $u['id']]);
                $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Estudiante agregado al grupo.'];
                redirigir("docente/grupo-editar.php?id=$gid");
            }
        }
    } elseif (isset($_POST['accion']) && $_POST['accion'] === 'bloquear') {
        $mid = (int)$_POST['miembro_id'];
        $bloq = (int)$_POST['bloqueado'];
        $pdo->prepare("UPDATE grupo_miembros SET bloqueado = :b WHERE id = :id AND grupo_id = :g")
            ->execute([':b' => $bloq ? 0 : 1, ':id' => $mid, ':g' => $gid]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => $bloq ? 'Estudiante desbloqueado.' : 'Estudiante bloqueado del grupo.'];
        redirigir("docente/grupo-editar.php?id=$gid");
    } elseif (isset($_POST['accion']) && $_POST['accion'] === 'remover') {
        $mid = (int)$_POST['miembro_id'];
        $pdo->prepare("DELETE FROM grupo_miembros WHERE id = :id AND grupo_id = :g")
            ->execute([':id' => $mid, ':g' => $gid]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Estudiante removido del grupo.'];
        redirigir("docente/grupo-editar.php?id=$gid");
    } elseif (isset($_POST['accion']) && $_POST['accion'] === 'enviar_notificacion') {
        $titulo = sanitizar($_POST['notif_titulo']);
        $msg = sanitizar($_POST['notif_mensaje']);
        if ($titulo && $msg) {
            $pdo->prepare("INSERT INTO notificaciones (grupo_id, titulo, mensaje) VALUES (:g, :t, :m)")
                ->execute([':g' => $gid, ':t' => $titulo, ':m' => $msg]);
            $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Notificación enviada al grupo.'];
            redirigir("docente/grupo-editar.php?id=$gid");
        }
    }
}

$miembros = $pdo->prepare(
    "SELECT gm.*, u.username, u.nombre, u.email, u.rol FROM grupo_miembros gm
     JOIN usuarios u ON gm.usuario_id = u.id
     WHERE gm.grupo_id = :gid ORDER BY gm.bloqueado ASC, gm.unido_en DESC"
);
$miembros->execute([':gid' => $gid]);
$miembros = $miembros->fetchAll();

$creado = isset($_GET['creado']);

$titulo = 'Gestionar: ' . $grupo['nombre'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>⚙️ <?= sanitizar($grupo['nombre']) ?></h1>
    <p>Código de invitación: <strong class="codigo-grande"><?= sanitizar($grupo['codigo_invitacion']) ?></strong>
    <?php if ($grupo['codigo_expiracion']): ?>
        — Expira: <?= formatearFecha($grupo['codigo_expiracion']) ?>
    <?php endif; ?>
    </p>
    <div class="actions-row">
        <a href="<?= BASE_URL ?>docente/grupos.php" class="btn-secundario">← Volver</a>
        <a href="<?= BASE_URL ?>docente/materiales.php?grupo_id=<?= $gid ?>" class="btn-secundario">📚 Materiales</a>
        <a href="<?= BASE_URL ?>docente/actividades.php?grupo_id=<?= $gid ?>" class="btn-secundario">📋 Actividades</a>
        <a href="<?= BASE_URL ?>docente/evaluaciones.php?grupo_id=<?= $gid ?>" class="btn-primario">📝 Evaluaciones</a>
    </div>
</div>

<?php if ($creado): ?>
    <div class="flash flash-exito">Grupo creado. Comparte el código <strong><?= sanitizar($grupo['codigo_invitacion']) ?></strong> con tus estudiantes.</div>
<?php endif; ?>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card">
    <h2>👥 Agregar estudiante manualmente</h2>
    <form method="POST" class="form-inline">
        <input type="hidden" name="accion" value="agregar_usuario">
        <input type="text" name="username" class="input-dato" placeholder="Nombre de usuario del estudiante" required>
        <button type="submit" class="btn-primario">➕ Agregar</button>
    </form>
    <p class="hint">También puedes compartir el código <strong><?= sanitizar($grupo['codigo_invitacion']) ?></strong> y los estudiantes se unirán solos.</p>
</div>

<div class="card">
    <h2>📢 Enviar notificación al grupo</h2>
    <form method="POST">
        <input type="hidden" name="accion" value="enviar_notificacion">
        <div class="grupo-input">
            <input type="text" name="notif_titulo" class="input-dato" placeholder="Título de la notificación" required>
        </div>
        <div class="grupo-input">
            <textarea name="notif_mensaje" class="input-dato" rows="2" placeholder="Mensaje..." required></textarea>
        </div>
        <button type="submit" class="btn-secundario">📢 Enviar a todo el grupo</button>
    </form>
</div>

<div class="card">
    <h2>👥 Miembros del grupo (<?= count($miembros) ?>)</h2>
    <table class="tabla">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Unido</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($miembros as $m): ?>
            <tr class="<?= $m['bloqueado'] ? 'fila-bloqueada' : '' ?>">
                <td><strong><?= sanitizar($m['username']) ?></strong></td>
                <td><?= sanitizar($m['nombre']) ?></td>
                <td><?= $m['bloqueado'] ? '🔒 Bloqueado' : '✅ Activo' ?></td>
                <td><?= formatearFecha($m['unido_en']) ?></td>
                <td>
                    <div class="btn-grupo-sm">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="accion" value="bloquear">
                            <input type="hidden" name="miembro_id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="bloqueado" value="<?= $m['bloqueado'] ?>">
                            <button type="submit" class="btn-sm <?= $m['bloqueado'] ? 'btn-desbloquear' : 'btn-bloquear' ?>">
                                <?= $m['bloqueado'] ? '🔓 Desbloquear' : '🔒 Bloquear' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Remover permanentemente a este estudiante?')">
                            <input type="hidden" name="accion" value="remover">
                            <input type="hidden" name="miembro_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn-sm btn-rechazar">🗑 Remover</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($miembros)): ?>
            <tr><td colspan="5" class="vacio">No hay miembros todavía</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
