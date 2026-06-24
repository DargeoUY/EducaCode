<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $gid = $_POST['grupo_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT id FROM grupos WHERE id = :id AND docente_id = :d");
    $stmt->execute([':id' => $gid, ':d' => $did]);
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM grupos WHERE id = :id")->execute([':id' => $gid]);
        $mensaje = ['exito', 'Grupo eliminado.'];
    }
}

$stmt = $pdo->prepare("SELECT g.*, (SELECT COUNT(*) FROM grupo_miembros gm WHERE gm.grupo_id = g.id) AS miembros FROM grupos g WHERE g.docente_id = :d ORDER BY g.creado_en DESC");
$stmt->execute([':d' => $did]);
$grupos = $stmt->fetchAll();

$titulo = 'Mis Grupos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📁 Mis Grupos</h1><a href="grupo-crear.php" class="btn-primario">+ Nuevo Grupo</a></div>
<?php if ($mensaje): ?><div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div><?php endif; ?>

<div class="grupos-grid">
    <?php foreach($grupos as $g): ?>
    <div class="card grupo-card">
        <h3><?= sanitizar($g['nombre']) ?></h3>
        <p><strong>Código:</strong> <code><?= $g['codigo_invitacion'] ?></code></p>
        <p><?= $g['miembros'] ?> estudiantes</p>
        <div class="grupo-actions">
            <a href="grupo-editar.php?id=<?= $g['id'] ?>" class="btn-sm">Gestionar</a>
            <a href="materiales.php?grupo_id=<?= $g['id'] ?>" class="btn-sm">Materiales</a>
            <a href="evaluaciones.php?grupo_id=<?= $g['id'] ?>" class="btn-sm">Evaluaciones</a>
            <form method="POST" style="display:inline"><input type="hidden" name="grupo_id" value="<?= $g['id'] ?>"><input type="hidden" name="accion" value="eliminar"><button class="btn-sm btn-peligro" onclick="return confirm('¿Eliminar grupo?')">Eliminar</button></form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($grupos)): ?><p class="vacio">No tenés grupos. Creá el primero.</p><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
