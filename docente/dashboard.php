<?php
/**
 * docente/dashboard.php — Panel principal del docente
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$total_grupos = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE docente_id = :uid")->execute([':uid' => $uid]) &&
    $pdo->query("SELECT COUNT(*) FROM grupos WHERE docente_id = $uid")->fetchColumn();

$total_alumnos = $pdo->prepare(
    "SELECT COUNT(DISTINCT gm.usuario_id) FROM grupo_miembros gm
     JOIN grupos g ON gm.grupo_id = g.id
     WHERE g.docente_id = :uid AND gm.bloqueado = 0"
);
$total_alumnos->execute([':uid' => $uid]);
$total_alumnos = (int)$total_alumnos->fetchColumn();

$total_evaluaciones = $pdo->prepare("SELECT COUNT(*) FROM evaluaciones WHERE grupo_id IN (SELECT id FROM grupos WHERE docente_id = :uid)");
$total_evaluaciones->execute([':uid' => $uid]);
$total_evaluaciones = (int)$total_evaluaciones->fetchColumn();

$miGrupos = $pdo->prepare(
    "SELECT g.*, (SELECT COUNT(*) FROM grupo_miembros gm WHERE gm.grupo_id = g.id AND gm.bloqueado = 0) AS miembros
     FROM grupos g WHERE g.docente_id = :uid ORDER BY g.creado_en DESC LIMIT 10"
);
$miGrupos->execute([':uid' => $uid]);
$miGrupos = $miGrupos->fetchAll();

$titulo = 'Dashboard Docente';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>👨‍🏫 Panel Docente</h1>
    <p>Bienvenido, <?= sanitizar($usuario['nombre']) ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card stat-blue">
        <div class="stat-icon">📁</div>
        <div class="stat-num"><?= $total_grupos ?></div>
        <div class="stat-label">Mis Grupos</div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-icon">👥</div>
        <div class="stat-num"><?= $total_alumnos ?></div>
        <div class="stat-label">Alumnos activos</div>
    </div>
    <div class="stat-card stat-purple">
        <div class="stat-icon">📝</div>
        <div class="stat-num"><?= $total_evaluaciones ?></div>
        <div class="stat-label">Evaluaciones</div>
    </div>
</div>

<div class="card">
    <div class="card-header-row">
        <h2>📁 Mis Grupos</h2>
        <a href="<?= BASE_URL ?>docente/grupo-crear.php" class="btn-primario">➕ Crear grupo</a>
    </div>
    <?php if (empty($miGrupos)): ?>
        <p class="vacio">No tienes grupos todavía. ¡Crea tu primer grupo!</p>
    <?php else: ?>
    <table class="tabla">
        <thead>
            <tr>
                <th>Grupo</th>
                <th>Código</th>
                <th>Miembros</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($miGrupos as $g): ?>
            <tr>
                <td><strong><?= sanitizar($g['nombre']) ?></strong></td>
                <td><code><?= sanitizar($g['codigo_invitacion']) ?></code></td>
                <td><?= $g['miembros'] ?></td>
                <td><?= formatearFecha($g['creado_en']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>docente/grupo-editar.php?id=<?= $g['id'] ?>" class="btn-sm btn-accion">⚙️ Gestionar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
