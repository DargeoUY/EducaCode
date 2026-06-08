<?php
/**
 * docente/evaluacion-resultados.php — Ver resultados de una evaluacion
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$eid = (int)($_GET['id'] ?? 0);
$eval = $pdo->prepare("SELECT e.*, g.docente_id, g.nombre AS grupo_nombre FROM evaluaciones e JOIN grupos g ON e.grupo_id = g.id WHERE e.id = :id LIMIT 1");
$eval->execute([':id' => $eid]);
$eval = $eval->fetch();
if (!$eval || ($eval['docente_id'] != $uid && $usuario['rol'] !== 'admin')) {
    redirigir('docente/grupos.php');
}

$resultados = $pdo->prepare(
    "SELECT ei.*, u.nombre, u.username FROM evaluacion_intentos ei
     JOIN usuarios u ON ei.usuario_id = u.id
     WHERE ei.evaluacion_id = :eid AND ei.finalizada = 1
     ORDER BY ei.puntaje DESC, ei.fecha_fin ASC"
);
$resultados->execute([':eid' => $eid]);
$resultados = $resultados->fetchAll();

$promedio = $pdo->prepare("SELECT AVG(puntaje) AS prom FROM evaluacion_intentos WHERE evaluacion_id = :eid AND finalizada = 1");
$promedio->execute([':eid' => $eid]);
$prom = $promedio->fetch()['prom'];

$titulo = 'Resultados: ' . $eval['titulo'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📊 <?= sanitizar($eval['titulo']) ?></h1>
    <p>Grupo: <?= sanitizar($eval['grupo_nombre']) ?> | Puntaje máx: <?= $eval['puntaje_max'] ?> | Promedio: <?= $prom !== null ? number_format($prom, 1) : '—' ?></p>
    <a href="<?= BASE_URL ?>docente/evaluaciones.php?grupo_id=<?= $eval['grupo_id'] ?>" class="btn-secundario">← Volver</a>
    <a href="<?= BASE_URL ?>docente/exportar-notas.php?eval_id=<?= $eid ?>" class="btn-secundario">📥 Exportar CSV</a>
</div>

<div class="card">
    <table class="tabla">
        <thead>
            <tr>
                <th>#</th>
                <th>Estudiante</th>
                <th>Usuario</th>
                <th>Puntaje</th>
                <th>Intentos</th>
                <th>Salidas tab</th>
                <th>Fecha inicio</th>
                <th>Fecha fin</th>
                <th>Duración</th>
            </tr>
        </thead>
        <tbody>
        <?php $pos = 0; foreach ($resultados as $r): $pos++; ?>
            <tr class="<?= $pos <= 3 ? 'fila-destacada' : '' ?>">
                <td><strong><?= $pos ?></strong></td>
                <td><?= sanitizar($r['nombre']) ?></td>
                <td><?= sanitizar($r['username']) ?></td>
                <td><strong><?= $r['puntaje'] ?></strong></td>
                <td><?= $r['intento_num'] ?>/<?= $eval['intentos_max'] ?></td>
                <td><?= $r['tab_salidas'] > 0 ? '<span style="color:#ef4444;">'.$r['tab_salidas'].' ⚠️</span>' : '✅ 0' ?></td>
                <td><?= formatearFecha($r['fecha_inicio']) ?></td>
                <td><?= formatearFecha($r['fecha_fin']) ?></td>
                <td><?php
                    if ($r['fecha_fin'] && $r['fecha_inicio']) {
                        $diff = strtotime($r['fecha_fin']) - strtotime($r['fecha_inicio']);
                        echo floor($diff / 60) . ':' . str_pad($diff % 60, 2, '0', STR_PAD_LEFT);
                    } else {
                        echo '—';
                    }
                ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($resultados)): ?>
            <tr><td colspan="9" class="vacio">Ningún estudiante ha completado la evaluación.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
