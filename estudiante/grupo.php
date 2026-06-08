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
    <h1>📂 <?= sanitizar($grupo['nombre']) ?></h1>
    <?php if ($grupo['descripcion']): ?>
        <p><?= sanitizar($grupo['descripcion']) ?></p>
    <?php endif; ?>
    <p><small>Docente: <?= sanitizar($grupo['docente_nombre']) ?></small></p>
    <a href="<?= BASE_URL ?>estudiante/mis-grupos.php" class="btn-secundario">← Mis grupos</a>
</div>

<?php if (!empty($notificaciones)): ?>
<div class="card">
    <h2>📢 Avisos del docente</h2>
    <?php foreach ($notificaciones as $notif): ?>
    <div class="notif-item">
        <strong><?= sanitizar($notif['titulo']) ?></strong>
        <p><?= sanitizar($notif['mensaje']) ?></p>
        <small><?= formatearFecha($notif['creado_en']) ?></small>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>📚 Materiales de ayuda</h2>
    <?php if (empty($materiales)): ?>
        <p class="vacio">No hay materiales disponibles.</p>
    <?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Título</th><th>Tipo</th><th>Acción</th></tr>
        </thead>
        <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr>
                <td><?= sanitizar($m['titulo']) ?></td>
                <td><span class="tag"><?= $m['tipo'] ?></span></td>
                <td><a href="<?= sanitizar($m['contenido_url']) ?>" target="_blank" rel="noopener" class="btn-sm btn-accion">Abrir ↗</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>📋 Actividades</h2>
    <?php if (empty($actividades)): ?>
        <p class="vacio">No hay actividades disponibles.</p>
    <?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Título</th><th>Tipo</th><th>Fecha límite</th></tr>
        </thead>
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

<div class="card">
    <h2>📝 Evaluaciones</h2>
    <?php if (empty($evaluaciones)): ?>
        <p class="vacio">No hay evaluaciones disponibles.</p>
    <?php else: ?>
    <table class="tabla">
        <thead>
            <tr>
                <th>Título</th>
                <th>Puntaje máx</th>
                <th>Duración</th>
                <th>Intentos</th>
                <th>Mejor nota</th>
                <th>Acción</th>
            </tr>
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
                        <span style="color:var(--error);">Sin intentos disponibles</span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
