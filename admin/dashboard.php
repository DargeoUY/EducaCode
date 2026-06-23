<?php
/**
 * admin/dashboard.php — Panel principal del administrador
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$usuario = usuario_actual($pdo);

$total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$total_grupos = $pdo->query("SELECT COUNT(*) FROM grupos")->fetchColumn();
$total_evaluaciones = $pdo->query("SELECT COUNT(*) FROM evaluaciones")->fetchColumn();
$pendientes = $pdo->query("SELECT COUNT(*) FROM solicitudes_docente WHERE estado = 'pendiente'")->fetchColumn();

$titulo = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📊 Panel de Administración</h1>
    <p>Gestión global de la plataforma</p>
</div>

<div class="stats-grid">
    <div class="stat-card stat-blue">
        <div class="stat-icon">👥</div>
        <div class="stat-num"><?= $total_usuarios ?></div>
        <div class="stat-label">Usuarios</div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-icon">📁</div>
        <div class="stat-num"><?= $total_grupos ?></div>
        <div class="stat-label">Grupos</div>
    </div>
    <div class="stat-card stat-purple">
        <div class="stat-icon">📝</div>
        <div class="stat-num"><?= $total_evaluaciones ?></div>
        <div class="stat-label">Evaluaciones</div>
    </div>
    <div class="stat-card stat-orange">
        <div class="stat-icon">📋</div>
        <div class="stat-num"><?= $pendientes ?></div>
        <div class="stat-label">Solicitudes pendientes</div>
    </div>
</div>

<div class="card">
    <h2>Acciones rápidas</h2>
    <div class="actions-row">
        <a href="<?= BASE_URL ?>admin/usuarios.php" class="btn-primario">👥 Gestionar usuarios</a>
        <a href="<?= BASE_URL ?>admin/solicitudes.php" class="btn-secundario">
            📋 Solicitudes <?= $pendientes > 0 ? "({$pendientes})" : '' ?>
        </a>
        <a href="<?= BASE_URL ?>admin/grupos.php" class="btn-secundario">📁 Ver todos los grupos</a>
    </div>
</div>

<div class="card">
    <h2>Últimos registros de sesión</h2>
    <table class="tabla">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Acción</th>
                <th>IP</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $logs = $pdo->query(
                "SELECT sl.*, u.nombre, u.rol FROM sesiones_log sl
                 JOIN usuarios u ON sl.usuario_id = u.id
                 ORDER BY sl.timestamp DESC LIMIT 15"
            )->fetchAll();
            foreach ($logs as $l): ?>
            <tr>
                <td><?= sanitizar($l['nombre']) ?></td>
                <td><span class="rol-badge rol-<?= $l['rol'] ?>"><?= $l['rol'] ?></span></td>
                <td><?= sanitizar($l['accion']) ?></td>
                <td class="mono"><?= sanitizar($l['ip']) ?></td>
                <td><?= formatearFecha($l['timestamp']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="5" class="vacio">Sin registros</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
