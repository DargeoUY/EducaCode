<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$gid = $_GET['grupo_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = :id AND docente_id = :d");
$stmt->execute([':id' => $gid, ':d' => $did]); $grupo = $stmt->fetch();
if (!$grupo) { echo '<p>Grupo no encontrado.</p>'; exit; }

$evals = $pdo->prepare("SELECT e.*, (SELECT COUNT(*) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id) AS intentos FROM evaluaciones e WHERE e.grupo_id = :g ORDER BY e.creado_en DESC");
$evals->execute([':g' => $gid]);

$titulo = 'Evaluaciones: ' . sanitizar($grupo['nombre']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📝 Evaluaciones — <?= sanitizar($grupo['nombre']) ?></h1><a href="evaluacion-crear.php?grupo_id=<?= $gid ?>" class="btn-primario">+ Nueva</a></div>

<div class="card">
    <?php $evalsArr = $evals->fetchAll(); if (empty($evalsArr)): ?><p class="vacio">Sin evaluaciones.</p><?php else: ?>
    <table><tr><th>Título</th><th>Puntaje</th><th>Duración</th><th>Intentos</th></tr>
    <?php foreach($evalsArr as $e): ?>
    <tr><td><a href="evaluacion-resultados.php?id=<?= $e['id'] ?>"><?= sanitizar($e['titulo']) ?></a></td><td><?= $e['puntaje_max'] ?> pts</td><td><?= $e['duracion_min'] ?> min</td><td><?= $e['intentos'] ?></td></tr>
    <?php endforeach; ?></table><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
