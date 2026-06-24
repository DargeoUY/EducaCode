<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_estudiante();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$stmt = $pdo->prepare("SELECT g.*, gm.unido_en, (SELECT COUNT(*) FROM grupo_miembros WHERE grupo_id = g.id) AS total FROM grupo_miembros gm JOIN grupos g ON gm.grupo_id = g.id WHERE gm.usuario_id = :u ORDER BY gm.unido_en DESC");
$stmt->execute([':u' => $uid]);
$grupos = $stmt->fetchAll();

$titulo = 'Mis Grupos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📁 Mis Grupos</h1></div>

<?php if (empty($grupos)): ?>
    <div class="card" style="text-align:center"><p>No estás en ningún grupo. Andá al inicio para unirte con un código.</p><a href="dashboard.php" class="btn-primario" style="margin-top:12px">Ir al inicio</a></div>
<?php else: ?>
<div class="grupos-grid">
    <?php foreach($grupos as $g): ?>
    <div class="card grupo-card">
        <h3><?= sanitizar($g['nombre']) ?></h3>
        <p style="font-size:.85rem;color:var(--text-sec)"><?= $g['total'] ?> miembros</p>
        <a href="grupo.php?id=<?= $g['id'] ?>" class="btn-primario btn-sm" style="margin-top:8px">Ver grupo</a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
