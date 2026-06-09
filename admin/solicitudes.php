<?php
/**
 * admin/solicitudes.php — Aprobar o rechazar solicitudes de cuenta docente
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$usuario = usuario_actual($pdo);
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $sid = (int)$_POST['id'];
    $stmt = $pdo->prepare("SELECT * FROM solicitudes_docente WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $sid]);
    $sol = $stmt->fetch();

    if ($sol && $sol['estado'] === 'pendiente') {
        $nuevo_estado = $_POST['accion'] === 'aprobar' ? 'aprobada' : 'rechazada';
        $pdo->prepare("UPDATE solicitudes_docente SET estado = :e WHERE id = :id")
            ->execute([':e' => $nuevo_estado, ':id' => $sid]);

        if ($nuevo_estado === 'aprobada') {
            $pdo->prepare("UPDATE usuarios SET rol = 'docente' WHERE id = :uid")
                ->execute([':uid' => $sol['usuario_id']]);
        }

        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Solicitud ' . $nuevo_estado . '.'];
        redirigir('admin/solicitudes.php');
    }
}

$solicitudes = $pdo->query(
    "SELECT s.*, u.nombre, u.username, u.email FROM solicitudes_docente s
     JOIN usuarios u ON s.usuario_id = u.id
     ORDER BY s.estado ASC, s.creado_en DESC"
)->fetchAll();

$titulo = 'Solicitudes Docentes';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📋 Solicitudes de Cuenta Docente</h1>
    <p>Aprobar o rechazar solicitudes</p>
</div>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card">
    <table class="tabla">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($solicitudes as $s): ?>
            <tr>
                <td><strong><?= sanitizar($s['username']) ?></strong></td>
                <td><?= sanitizar($s['nombre']) ?></td>
                <td><?= sanitizar($s['email'] ?? '—') ?></td>
                <td>
                    <span class="estado-badge estado-<?= $s['estado'] ?>"><?= $s['estado'] ?></span>
                </td>
                <td><?= formatearFecha($s['creado_en']) ?></td>
                <td>
                    <?php if ($s['estado'] === 'pendiente'): ?>
                    <div class="btn-grupo-sm">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="accion" value="aprobar">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-sm btn-aprobar">✅ Aprobar</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="accion" value="rechazar">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-sm btn-rechazar">❌ Rechazar</button>
                        </form>
                    </div>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($solicitudes)): ?>
            <tr><td colspan="6" class="vacio">No hay solicitudes</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
