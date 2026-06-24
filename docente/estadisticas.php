<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];

$stats = $pdo->prepare("SELECT g.id, g.nombre, COUNT(DISTINCT gm.usuario_id) AS miembros, COUNT(DISTINCT ei.id) AS intentos, ROUND(AVG(ei.puntaje),1) AS promedio FROM grupos g LEFT JOIN grupo_miembros gm ON g.id = gm.grupo_id AND gm.bloqueado = 0 LEFT JOIN evaluaciones ev ON g.id = ev.grupo_id LEFT JOIN evaluacion_intentos ei ON ev.id = ei.evaluacion_id AND ei.finalizada = 1 WHERE g.docente_id = :d GROUP BY g.id, g.nombre");
$stats->execute([':d' => $did]);
$gruposStats = $stats->fetchAll();

$titulo = 'Estadísticas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📈 Estadísticas</h1></div>

<div class="card"><h2>Por grupo</h2>
<?php if (empty($gruposStats)): ?><p class="vacio">Sin datos.</p><?php else: ?>
<table><tr><th>Grupo</th><th>Estudiantes</th><th>Intentos</th><th>Promedio</th><th>Aprobación</th></tr>
<?php foreach($gruposStats as $g): ?>
<tr><td><?= sanitizar($g['nombre']) ?></td><td><?= $g['miembros'] ?></td><td><?= $g['intentos'] ?></td><td><?= $g['promedio'] ?? '—' ?></td><td><?= $g['promedio'] >= 60 ? '✅' : '⚠️' ?></td></tr>
<?php endforeach; ?></table><?php endif; ?></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
