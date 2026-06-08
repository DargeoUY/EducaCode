<?php
/**
 * admin/grupos.php — Ver todos los grupos (admin)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$usuario = usuario_actual($pdo);

$grupos = $pdo->query(
    "SELECT g.*, u.nombre AS docente_nombre, u.username AS docente_user,
     (SELECT COUNT(*) FROM grupo_miembros gm WHERE gm.grupo_id = g.id) AS total_miembros
     FROM grupos g JOIN usuarios u ON g.docente_id = u.id
     ORDER BY g.creado_en DESC"
)->fetchAll();

$titulo = 'Todos los Grupos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📁 Todos los Grupos</h1>
    <p>Vista global de grupos y sus docentes</p>
</div>

<div class="card">
    <table class="tabla">
        <thead>
            <tr>
                <th>Grupo</th>
                <th>Docente</th>
                <th>Código</th>
                <th>Miembros</th>
                <th>Creado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($grupos as $g): ?>
            <tr>
                <td><strong><?= sanitizar($g['nombre']) ?></strong></td>
                <td><?= sanitizar($g['docente_nombre']) ?> (<?= sanitizar($g['docente_user']) ?>)</td>
                <td><code><?= sanitizar($g['codigo_invitacion']) ?></code></td>
                <td><?= $g['total_miembros'] ?></td>
                <td><?= formatearFecha($g['creado_en']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($grupos)): ?>
            <tr><td colspan="5" class="vacio">No hay grupos registrados</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
