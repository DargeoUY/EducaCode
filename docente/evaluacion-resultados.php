<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$eid = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT e.*, g.docente_id FROM evaluaciones e JOIN grupos g ON e.grupo_id = g.id WHERE e.id = :id");
$stmt->execute([':id' => $eid]); $eval = $stmt->fetch();
if (!$eval || ($eval['docente_id'] != $did && $usuario['rol'] !== 'admin')) { echo '<p>No autorizado.</p>'; exit; }

$intentos = $pdo->prepare("SELECT ei.*, u.username, u.nombre FROM evaluacion_intentos ei JOIN usuarios u ON ei.usuario_id = u.id WHERE ei.evaluacion_id = :e ORDER BY ei.fecha_inicio DESC");
$intentos->execute([':e' => $eid]);

$tab_salidas = $pdo->prepare("SELECT ts.*, u.username FROM tab_salidas ts JOIN evaluacion_intentos ei ON ts.intento_id = ei.id JOIN usuarios u ON ei.usuario_id = u.id WHERE ei.evaluacion_id = :e ORDER BY ts.timestamp DESC LIMIT 50");
$tab_salidas->execute([':e' => $eid]);

$titulo = 'Resultados: ' . sanitizar($eval['titulo']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📊 Resultados — <?= sanitizar($eval['titulo']) ?></h1></div>

<div class="card"><h2>Intentos</h2>
<?php $ints = $intentos->fetchAll(); if (empty($ints)): ?><p class="vacio">Sin intentos.</p><?php else: ?>
<table><tr><th>Estudiante</th><th>Puntaje</th><th>Salidas</th><th>Inicio</th><th>Fin</th></tr>
<?php foreach($ints as $i): ?>
<tr><td><?= sanitizar($i['nombre'] ?: $i['username']) ?></td><td><strong><?= $i['puntaje'] ?? '—' ?></strong></td><td><?= $i['tab_salidas'] ?></td><td><?= formatearFecha($i['fecha_inicio']) ?></td><td><?= formatearFecha($i['fecha_fin']) ?></td></tr>
<?php endforeach; ?></table><?php endif; ?></div>

<div class="card"><h2>Salidas de pestaña (anti-trampa)</h2>
<?php $tabs = $tab_salidas->fetchAll(); if (empty($tabs)): ?><p class="vacio">Sin salidas registradas.</p><?php else: ?>
<table><tr><th>Estudiante</th><th>Evento</th><th>Fecha</th></tr>
<?php foreach($tabs as $ts): ?>
<tr><td><?= sanitizar($ts['username']) ?></td><td><?= $ts['tipo_evento'] ?></td><td><?= formatearFecha($ts['timestamp']) ?></td></tr>
<?php endforeach; ?></table><?php endif; ?></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
