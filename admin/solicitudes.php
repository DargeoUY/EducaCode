<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = $_POST['solicitud_id'] ?? 0;
    $accion = $_POST['accion'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM solicitudes_docente WHERE id = :id"); $stmt->execute([':id' => $sid]); $sol = $stmt->fetch();
    if ($sol && $accion === 'aprobar') {
        $pdo->prepare("UPDATE solicitudes_docente SET estado = 'aprobada' WHERE id = :id")->execute([':id' => $sid]);
        $pdo->prepare("UPDATE usuarios SET rol = 'docente', activo = 1 WHERE id = :id")->execute([':id' => $sol['usuario_id']]);
        $mensaje = ['exito', 'Solicitud aprobada. Rol cambiado a docente.'];
    } elseif ($sol && $accion === 'rechazar') {
        $pdo->prepare("UPDATE solicitudes_docente SET estado = 'rechazada' WHERE id = :id")->execute([':id' => $sid]);
        $mensaje = ['info', 'Solicitud rechazada.'];
    }
}

$estado = $_GET['estado'] ?? 'pendiente';
$stmt = $pdo->prepare("SELECT sd.*, u.username, u.nombre, u.email FROM solicitudes_docente sd JOIN usuarios u ON sd.usuario_id = u.id WHERE sd.estado = :e ORDER BY sd.creado_en DESC");
$stmt->execute([':e' => $estado]);
$solicitudes = $stmt->fetchAll();

$titulo = 'Solicitudes Docentes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📋 Solicitudes Docentes</h1></div>
<?php if ($mensaje): ?><div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div><?php endif; ?>

<div class="card">
    <div style="margin-bottom:12px">
        <a href="?estado=pendiente" class="btn-sm <?= $estado==='pendiente'?'btn-primario':'' ?>">Pendientes</a>
        <a href="?estado=aprobada" class="btn-sm <?= $estado==='aprobada'?'btn-primario':'' ?>">Aprobadas</a>
        <a href="?estado=rechazada" class="btn-sm <?= $estado==='rechazada'?'btn-primario':'' ?>">Rechazadas</a>
    </div>
    <?php if (empty($solicitudes)): ?><p class="vacio">No hay solicitudes.</p><?php else: ?>
    <table>
        <tr><th>Usuario</th><th>Nombre</th><th>Email</th><th>Acciones</th></tr>
        <?php foreach($solicitudes as $s): ?>
        <tr><td><?= sanitizar($s['username']) ?></td><td><?= sanitizar($s['nombre']) ?></td><td><?= sanitizar($s['email']) ?></td>
        <td><?php if($s['estado']==='pendiente'): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>"><input type="hidden" name="accion" value="aprobar"><button class="btn-sm btn-exito">Aprobar</button></form>
            <form method="POST" style="display:inline"><input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>"><input type="hidden" name="accion" value="rechazar"><button class="btn-sm btn-peligro">Rechazar</button></form>
        <?php else: echo $s['estado']; endif; ?></td></tr>
        <?php endforeach; ?>
    </table><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
