<?php
/**
 * estudiante/mis-grupos.php — Grupos a los que pertenece
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_estudiante();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$grupos = $pdo->prepare(
    "SELECT g.*, gm.bloqueado AS estoy_bloqueado, u.nombre AS docente_nombre,
     (SELECT COUNT(*) FROM evaluaciones e WHERE e.grupo_id = g.id) AS total_evals
     FROM grupo_miembros gm
     JOIN grupos g ON gm.grupo_id = g.id
     JOIN usuarios u ON g.docente_id = u.id
     WHERE gm.usuario_id = :uid
     ORDER BY gm.unido_en DESC"
);
$grupos->execute([':uid' => $uid]);
$grupos = $grupos->fetchAll();

$titulo = 'Mis Grupos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📁 Mis Grupos</h1>
    <p>Grupos a los que te has unido</p>
</div>

<?php if (empty($grupos)): ?>
    <div class="card">
        <p class="vacio">No perteneces a ningún grupo todavía.</p>
        <a href="<?= BASE_URL ?>estudiante/dashboard.php" class="btn-primario">🔑 Ingresar código de grupo</a>
    </div>
<?php else: ?>
    <div class="grupos-grid">
    <?php foreach ($grupos as $g): ?>
        <div class="grupo-card <?= $g['estoy_bloqueado'] ? 'grupo-bloqueado' : '' ?>">
            <div class="grupo-card-header">
                <h3><?= sanitizar($g['nombre']) ?></h3>
                <?php if ($g['estoy_bloqueado']): ?>
                    <span class="estado-badge estado-rechazada">🔒 Bloqueado</span>
                <?php endif; ?>
            </div>
            <?php if ($g['descripcion']): ?>
                <p><?= sanitizar($g['descripcion']) ?></p>
            <?php endif; ?>
            <div class="grupo-stats">
                <span>👨‍🏫 <?= sanitizar($g['docente_nombre']) ?></span>
                <span>📝 <?= $g['total_evals'] ?> evaluaciones</span>
            </div>
            <?php if (!$g['estoy_bloqueado']): ?>
            <div class="grupo-card-actions">
                <a href="<?= BASE_URL ?>estudiante/grupo.php?id=<?= $g['id'] ?>" class="btn-primario">📂 Ver grupo</a>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
