<?php
/**
 * docente/grupos.php — Listar y crear grupos del docente
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$miGrupos = $pdo->prepare(
    "SELECT g.*, (SELECT COUNT(*) FROM grupo_miembros gm WHERE gm.grupo_id = g.id AND gm.bloqueado = 0) AS miembros,
     (SELECT COUNT(*) FROM grupo_miembros gm WHERE gm.grupo_id = g.id AND gm.bloqueado = 1) AS bloqueados
     FROM grupos g WHERE g.docente_id = :uid ORDER BY g.creado_en DESC"
);
$miGrupos->execute([':uid' => $uid]);
$miGrupos = $miGrupos->fetchAll();

$titulo = 'Mis Grupos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📁 Mis Grupos</h1>
    <a href="<?= BASE_URL ?>docente/grupo-crear.php" class="btn-primario">➕ Crear nuevo grupo</a>
</div>

<?php if (empty($miGrupos)): ?>
    <div class="card">
        <p class="vacio">No tienes grupos todavía.</p>
        <a href="<?= BASE_URL ?>docente/grupo-crear.php" class="btn-primario">Crear mi primer grupo</a>
    </div>
<?php else: ?>
    <div class="grupos-grid">
    <?php foreach ($miGrupos as $g): ?>
        <div class="grupo-card">
            <div class="grupo-card-header">
                <h3><?= sanitizar($g['nombre']) ?></h3>
                <code class="codigo-grande"><?= sanitizar($g['codigo_invitacion']) ?></code>
            </div>
            <?php if ($g['descripcion']): ?>
                <p><?= sanitizar($g['descripcion']) ?></p>
            <?php endif; ?>
            <div class="grupo-stats">
                <span>👥 <?= $g['miembros'] ?> activos</span>
                <?php if ($g['bloqueados'] > 0): ?>
                    <span>🔒 <?= $g['bloqueados'] ?> bloqueados</span>
                <?php endif; ?>
            </div>
            <div class="grupo-card-actions">
                <a href="<?= BASE_URL ?>docente/grupo-editar.php?id=<?= $g['id'] ?>" class="btn-primario">⚙️ Gestionar</a>
                <a href="<?= BASE_URL ?>docente/materiales.php?grupo_id=<?= $g['id'] ?>" class="btn-secundario">📚 Materiales</a>
                <a href="<?= BASE_URL ?>docente/actividades.php?grupo_id=<?= $g['id'] ?>" class="btn-secundario">📋 Actividades</a>
                <a href="<?= BASE_URL ?>docente/evaluaciones.php?grupo_id=<?= $g['id'] ?>" class="btn-secundario">📝 Evaluaciones</a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
