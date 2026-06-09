<?php
/**
 * estudiante/grupo.php — Ver contenido de un grupo (materiales, actividades, evaluaciones)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_estudiante();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$gid = (int)($_GET['id'] ?? 0);
$grupo = obtenerGrupo($pdo, $gid);
if (!$grupo) {
    redirigir('estudiante/mis-grupos.php');
}

$membresia = esMiembro($pdo, $gid, $uid);
if (!$membresia) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No eres miembro de este grupo.'];
    redirigir('estudiante/mis-grupos.php');
}

$materiales = $pdo->prepare("SELECT * FROM materiales WHERE grupo_id = :g ORDER BY creado_en DESC");
$materiales->execute([':g' => $gid]);
$materiales = $materiales->fetchAll();

$actividades = $pdo->prepare("SELECT * FROM actividades WHERE grupo_id = :g ORDER BY creado_en DESC");
$actividades->execute([':g' => $gid]);
$actividades = $actividades->fetchAll();

$evaluaciones = $pdo->prepare(
    "SELECT e.*,
     (SELECT COUNT(*) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id AND ei.usuario_id = :uid1 AND ei.finalizada = 1) AS mis_intentos,
     (SELECT MAX(ei.puntaje) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id AND ei.usuario_id = :uid2 AND ei.finalizada = 1) AS mi_mejor_nota
     FROM evaluaciones e WHERE e.grupo_id = :g ORDER BY e.creado_en DESC"
);
$evaluaciones->execute([':g' => $gid, ':uid1' => $uid, ':uid2' => $uid]);
$evaluaciones = $evaluaciones->fetchAll();

$notificaciones = $pdo->prepare(
    "SELECT * FROM notificaciones WHERE grupo_id = :g ORDER BY creado_en DESC LIMIT 10"
);
$notificaciones->execute([':g' => $gid]);
$notificaciones = $notificaciones->fetchAll();

$titulo = sanitizar($grupo['nombre']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><img src="<?= BASE_URL ?>img/grupo.png" alt="" width="28" height="28" style="vertical-align:middle;margin-right:8px;"><?= sanitizar($grupo['nombre']) ?></h1>
    <?php if ($grupo['descripcion']): ?>
        <p><?= sanitizar($grupo['descripcion']) ?></p>
    <?php endif; ?>
    <p><small>Docente: <?= sanitizar($grupo['docente_nombre']) ?></small></p>
    <a href="<?= BASE_URL ?>estudiante/mis-grupos.php" class="btn-secundario">← Mis grupos</a>
</div>

<?php if (!empty($notificaciones)): ?>
<div class="card collapsible collapsed">
    <div class="collapsible-header" onclick="this.parentElement.classList.toggle('collapsed')">
        <span>📢 Avisos del docente (<?= count($notificaciones) ?>)</span>
        <span class="collapsible-arrow">▼</span>
    </div>
    <div class="collapsible-body">
    <?php foreach ($notificaciones as $notif): ?>
    <div class="notif-item">
        <strong><?= sanitizar($notif['titulo']) ?></strong>
        <p><?= sanitizar($notif['mensaje']) ?></p>
        <small><?= formatearFecha($notif['creado_en']) ?></small>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card collapsible collapsed">
    <div class="collapsible-header" onclick="this.parentElement.classList.toggle('collapsed')">
        <span>📚 Materiales (<?= count($materiales) ?>)</span>
        <span class="collapsible-arrow">▼</span>
    </div>
    <div class="collapsible-body">
    <?php if (empty($materiales)): ?>
        <p class="vacio">No hay materiales disponibles.</p>
    <?php else: ?>
    <table class="tabla">
        <thead><tr><th>Título</th><th>Tipo</th><th>Acción</th></tr></thead>
        <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr>
                <td><?= sanitizar($m['titulo']) ?></td>
                <td><span class="tag tag-<?= $m['tipo'] ?>"><?= $m['tipo'] ?></span></td>
                <td><a href="<?= sanitizar($m['contenido_url']) ?>" target="_blank" rel="noopener" class="btn-sm btn-accion">Abrir ↗</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>

<div class="card collapsible collapsed">
    <div class="collapsible-header" onclick="this.parentElement.classList.toggle('collapsed')">
        <span>📋 Actividades (<?= count($actividades) ?>)</span>
        <span class="collapsible-arrow">▼</span>
    </div>
    <div class="collapsible-body">
    <?php if (empty($actividades)): ?>
        <p class="vacio">No hay actividades disponibles.</p>
    <?php else: ?>
    <table class="tabla">
        <thead><tr><th>Título</th><th>Tipo</th><th>Fecha límite</th></tr></thead>
        <tbody>
        <?php foreach ($actividades as $a): ?>
            <tr>
                <td><strong><?= sanitizar($a['titulo']) ?></strong>
                    <?php if ($a['descripcion']): ?><br><small><?= sanitizar($a['descripcion']) ?></small><?php endif; ?>
                </td>
                <td><span class="tag"><?= $a['tipo'] ?></span></td>
                <td><?= formatearFecha($a['fecha_limite']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>

<div class="card collapsible collapsed">
    <div class="collapsible-header" onclick="this.parentElement.classList.toggle('collapsed')">
        <span>📝 Evaluaciones (<?= count($evaluaciones) ?>)</span>
        <span class="collapsible-arrow">▼</span>
    </div>
    <div class="collapsible-body">
    <?php if (empty($evaluaciones)): ?>
        <p class="vacio">No hay evaluaciones disponibles.</p>
    <?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Título</th><th>Puntaje</th><th>Duración</th><th>Intentos</th><th>Mejor nota</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($evaluaciones as $e):
            $puedeRendir = $e['mis_intentos'] < $e['intentos_max'];
            $vencida = $e['fecha_limite'] && strtotime($e['fecha_limite']) < time();
        ?>
            <tr>
                <td><strong><?= sanitizar($e['titulo']) ?></strong></td>
                <td><?= $e['puntaje_max'] ?> pts</td>
                <td><?= formatearTiempo($e['duracion_min']) ?></td>
                <td><?= $e['mis_intentos'] ?>/<?= $e['intentos_max'] ?></td>
                <td><?= $e['mi_mejor_nota'] !== null ? number_format($e['mi_mejor_nota'], 1) : '—' ?></td>
                <td>
                    <?php if (!$puedeRendir): ?>
                        <span style="color:var(--error);">Sin intentos</span>
                    <?php elseif ($vencida): ?>
                        <span style="color:var(--texto-secundario);">Vencida</span>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>estudiante/evaluacion.php?id=<?= $e['id'] ?>" class="btn-primario">🎯 Rendir</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>

<style>
.collapsible { border: 1px solid var(--borde); border-radius: 12px; overflow: hidden; }
.collapsible-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; background: rgba(99,102,241,.05);
    cursor: pointer; user-select: none; font-weight: 700; font-size: .95rem;
    transition: background .2s;
}
.collapsible-header:hover { background: rgba(99,102,241,.1); }
.collapsible-arrow { font-size: .7rem; color: var(--texto-secundario); transition: transform .3s; }
.collapsible.collapsed .collapsible-body { display: none; }
.collapsible.collapsed .collapsible-arrow { transform: rotate(-90deg); }
.collapsible-body { padding: 12px 18px; }
.tag-link { background: rgba(99,102,241,.15); color: #818cf8; }
.tag-archivo { background: rgba(16,185,129,.15); color: #10b981; }
.tag-html { background: rgba(245,158,11,.15); color: #f59e0b; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
