<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];

$misGrupos = $pdo->prepare("SELECT g.*, (SELECT COUNT(*) FROM grupo_miembros gm WHERE gm.grupo_id = g.id) AS miembros, (SELECT COUNT(*) FROM evaluaciones ev WHERE ev.grupo_id = g.id) AS evals FROM grupos g WHERE g.docente_id = :d");
$misGrupos->execute([':d' => $did]);
$grupos = $misGrupos->fetchAll();

$totalEstudiantes = $pdo->prepare("SELECT COUNT(*) FROM grupo_miembros gm JOIN grupos g ON gm.grupo_id = g.id WHERE g.docente_id = :d");
$totalEstudiantes->execute([':d' => $did]);
$nEstudiantes = $totalEstudiantes->fetchColumn();

$bloqueos = $pdo->prepare("SELECT ba.*, u.username, u.nombre FROM bloqueos_actividad ba JOIN usuarios u ON ba.usuario_id = u.id WHERE ba.desbloqueado_en IS NULL ORDER BY ba.creado_en DESC LIMIT 10");
$bloqueos->execute();
$bloqueos = $bloqueos->fetchAll();

$titulo = 'Dashboard Docente';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📊 Panel de <?= sanitizar($usuario['nombre']) ?></h1></div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-num"><?= count($grupos) ?></div><div class="stat-label">Grupos</div></div>
    <div class="stat-card"><div class="stat-num"><?= $nEstudiantes ?></div><div class="stat-label">Estudiantes</div></div>
    <div class="stat-card"><a href="<?= BASE_URL ?>ide/" style="text-decoration:none"><div class="stat-num">💻</div><div class="stat-label">Ver proyectos</div></a></div>
</div>

<?php if (!empty($bloqueos)): ?>
<div class="card" style="border-color:rgba(239,68,68,.3)">
    <h2>🚫 Bloqueos por pérdida de foco</h2>
    <table>
        <tr><th>Estudiante</th><th>Fecha</th><th>Acción</th></tr>
        <?php foreach($bloqueos as $b): ?>
        <tr><td><?= sanitizar($b['nombre'] ?: $b['username']) ?></td><td><?= formatearFecha($b['creado_en']) ?></td>
        <td><form method="POST" action="../admin/usuarios.php" style="display:inline"><input type="hidden" name="usuario_id" value="<?= $b['usuario_id'] ?>"><input type="hidden" name="accion" value="desbloquear_foco"><button class="btn-sm btn-exito">Desbloquear</button></form></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
