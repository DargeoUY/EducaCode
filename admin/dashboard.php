<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$usuario = usuario_actual($pdo);

$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$totalDocentes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='docente'")->fetchColumn();
$totalEstudiantes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='estudiante'")->fetchColumn();
$totalGrupos = $pdo->query("SELECT COUNT(*) FROM grupos")->fetchColumn();
$totalEvaluaciones = $pdo->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
$pendientes = $pdo->query("SELECT COUNT(*) FROM solicitudes_docente WHERE estado='pendiente'")->fetchColumn();

$logs = $pdo->query("SELECT sl.*, u.username, u.nombre FROM sesiones_log sl JOIN usuarios u ON sl.usuario_id = u.id ORDER BY sl.timestamp DESC LIMIT 20")->fetchAll();

$titulo = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📊 Panel de Administración</h1></div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-num"><?= $totalUsuarios ?></div><div class="stat-label">Usuarios</div></div>
    <div class="stat-card"><div class="stat-num"><?= $totalDocentes ?></div><div class="stat-label">Docentes</div></div>
    <div class="stat-card"><div class="stat-num"><?= $totalEstudiantes ?></div><div class="stat-label">Estudiantes</div></div>
    <div class="stat-card"><div class="stat-num"><?= $totalGrupos ?></div><div class="stat-label">Grupos</div></div>
    <div class="stat-card"><div class="stat-num"><?= $totalEvaluaciones ?></div><div class="stat-label">Evaluaciones</div></div>
    <div class="stat-card"><div class="stat-num" style="color:<?= $pendientes>0?'#f59e0b':'var(--exito)' ?>"><?= $pendientes ?></div><div class="stat-label">Solicitudes pendientes</div></div>
</div>

<div class="card">
    <h2>📋 Últimos accesos</h2>
    <table>
        <tr><th>Usuario</th><th>Acción</th><th>IP</th><th>Fecha</th></tr>
        <?php foreach($logs as $l): ?>
        <tr><td><?= sanitizar($l['nombre'] ?: $l['username']) ?></td><td><?= $l['accion'] ?></td><td><?= $l['ip'] ?></td><td><?= formatearFecha($l['timestamp']) ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
