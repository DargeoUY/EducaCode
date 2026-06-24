<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$gid = $_GET['id'] ?? 0;
$mensaje = '';

$stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = :id AND docente_id = :d");
$stmt->execute([':id' => $gid, ':d' => $did]);
$grupo = $stmt->fetch();
if (!$grupo) { echo '<p>Grupo no encontrado.</p>'; require_once __DIR__ . '/../includes/footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'agregar') {
        $username = trim($_POST['username'] ?? '');
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = :u AND rol = 'estudiante' LIMIT 1");
        $stmt->execute([':u' => $username]); $est = $stmt->fetch();
        if (!$est) $mensaje = ['error', 'Estudiante no encontrado.'];
        else {
            $ex = $pdo->prepare("SELECT id FROM grupo_miembros WHERE grupo_id = :g AND usuario_id = :u");
            $ex->execute([':g' => $gid, ':u' => $est['id']]);
            if ($ex->fetch()) $mensaje = ['info', 'Ya es miembro.'];
            else { $pdo->prepare("INSERT INTO grupo_miembros (grupo_id, usuario_id) VALUES (:g,:u)")->execute([':g' => $gid, ':u' => $est['id']]); $mensaje = ['exito', 'Estudiante agregado.']; }
        }
    } elseif ($accion === 'bloquear') {
        $uid = $_POST['usuario_id'] ?? 0; $bloq = ($_POST['bloqueado'] ?? '0') === '1';
        $pdo->prepare("UPDATE grupo_miembros SET bloqueado = :b WHERE grupo_id = :g AND usuario_id = :u")->execute([':b' => $bloq ? 1 : 0, ':g' => $gid, ':u' => $uid]);
        $mensaje = ['exito', $bloq ? 'Bloqueado.' : 'Desbloqueado.'];
    } elseif ($accion === 'notificar') {
        $tituloN = trim($_POST['titulo_notif'] ?? ''); $msg = trim($_POST['mensaje_notif'] ?? '');
        if ($tituloN && $msg) { $pdo->prepare("INSERT INTO notificaciones (grupo_id, titulo, mensaje) VALUES (:g,:t,:m)")->execute([':g' => $gid, ':t' => $tituloN, ':m' => $msg]); $mensaje = ['exito', 'Notificación enviada.']; }
    }
}

$miembros = $pdo->prepare("SELECT u.*, gm.bloqueado AS bloq_grupo FROM grupo_miembros gm JOIN usuarios u ON gm.usuario_id = u.id WHERE gm.grupo_id = :g"); $miembros->execute([':g' => $gid]);
$miembros = $miembros->fetchAll();

$titulo = 'Gestionar: ' . sanitizar($grupo['nombre']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>👥 <?= sanitizar($grupo['nombre']) ?></h1><a href="grupos.php" class="btn-sm">← Volver</a></div>
<?php if ($mensaje): ?><div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div><?php endif; ?>

<div class="card"><h2>➕ Agregar estudiante</h2><form method="POST"><input type="hidden" name="accion" value="agregar"><div class="grupo-input"><label>Username del estudiante</label><input type="text" name="username" class="input-dato" required></div><button type="submit" class="btn-primario">Agregar</button></form></div>

<div class="card"><h2>📢 Enviar notificación</h2><form method="POST"><input type="hidden" name="accion" value="notificar"><div class="grupo-input"><label>Título</label><input type="text" name="titulo_notif" class="input-dato" required></div><div class="grupo-input"><label>Mensaje</label><textarea name="mensaje_notif" class="input-dato" required></textarea></div><button type="submit" class="btn-primario">Enviar</button></form></div>

<div class="card"><h2>Miembros (<?= count($miembros) ?>)</h2>
<?php if (empty($miembros)): ?><p class="vacio">Sin miembros.</p><?php else: ?>
<table><tr><th>Usuario</th><th>Nombre</th><th>Estado</th><th>Acción</th></tr>
<?php foreach($miembros as $m): ?>
<tr><td><?= sanitizar($m['username']) ?></td><td><?= sanitizar($m['nombre']) ?></td><td><?= $m['bloq_grupo'] ? '🔒 Bloqueado' : '✅ Activo' ?></td>
<td><form method="POST"><input type="hidden" name="accion" value="bloquear"><input type="hidden" name="usuario_id" value="<?= $m['id'] ?>"><input type="hidden" name="bloqueado" value="<?= $m['bloq_grupo'] ? '0' : '1' ?>"><button class="btn-sm <?= $m['bloq_grupo'] ? 'btn-exito' : 'btn-peligro' ?>"><?= $m['bloq_grupo'] ? 'Desbloquear' : 'Bloquear' ?></button></form></td></tr>
<?php endforeach; ?>
</table><?php endif; ?></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
