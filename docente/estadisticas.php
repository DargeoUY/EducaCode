<?php
/**
 * docente/estadisticas.php — Panel de estadisticas
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$grupo_id = (int)($_GET['grupo_id'] ?? 0);

$grupos = $pdo->prepare("SELECT * FROM grupos WHERE docente_id = :uid ORDER BY nombre");
$grupos->execute([':uid' => $uid]);
$grupos = $grupos->fetchAll();

if ($grupo_id) {
    $gStmt = $pdo->prepare("SELECT * FROM grupos WHERE id = :id AND docente_id = :uid");
    $gStmt->execute([':id' => $grupo_id, ':uid' => $uid]);
    if (!$gStmt->fetch()) $grupo_id = 0;
}

$estadisticas = [];

if ($grupo_id) {
    $evaluaciones = $pdo->prepare(
        "SELECT e.id, e.titulo, e.puntaje_max,
         (SELECT COUNT(*) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id AND ei.finalizada = 1) AS total,
         (SELECT AVG(puntaje) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id AND ei.finalizada = 1) AS promedio,
         (SELECT COUNT(*) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id AND ei.finalizada = 1 AND ei.puntaje >= e.puntaje_max * 0.6) AS aprobados
         FROM evaluaciones e WHERE e.grupo_id = :gid ORDER BY e.creado_en DESC"
    );
    $evaluaciones->execute([':gid' => $grupo_id]);
    $estadisticas = $evaluaciones->fetchAll();
}

$titulo = 'Estadísticas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📈 Estadísticas</h1>
</div>

<div class="card">
    <h2>Seleccionar grupo</h2>
    <form method="GET" class="form-inline">
        <select name="grupo_id" class="input-dato" onchange="this.form.submit()">
            <option value="0">— Seleccionar grupo —</option>
            <?php foreach ($grupos as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $grupo_id == $g['id'] ? 'selected' : '' ?>><?= sanitizar($g['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($grupo_id && !empty($estadisticas)): ?>
<div class="card">
    <h2>📊 Rendimiento por evaluación</h2>
    <table class="tabla">
        <thead>
            <tr>
                <th>Evaluación</th>
                <th>Puntaje máx</th>
                <th>Intentos</th>
                <th>Promedio</th>
                <th>Aprobados (≥60%)</th>
                <th>% Aprobación</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($estadisticas as $e): ?>
            <tr>
                <td><strong><?= sanitizar($e['titulo']) ?></strong></td>
                <td><?= $e['puntaje_max'] ?></td>
                <td><?= $e['total'] ?></td>
                <td><?= $e['promedio'] !== null ? number_format($e['promedio'], 1) : '—' ?></td>
                <td><?= $e['aprobados'] ?></td>
                <td>
                    <?php if ($e['total'] > 0): ?>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;height:8px;background:var(--superficie-claro);border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:<?= round(($e['aprobados'] / $e['total']) * 100) ?>%;background:var(--exito);border-radius:4px;"></div>
                            </div>
                            <?= round(($e['aprobados'] / $e['total']) * 100) ?>%
                        </div>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($grupo_id): ?>
    <div class="card"><p class="vacio">No hay evaluaciones en este grupo todavía.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
