<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$grupos = $pdo->query("SELECT g.*, u.nombre AS docente_nombre, (SELECT COUNT(*) FROM grupo_miembros gm WHERE gm.grupo_id = g.id) AS miembros FROM grupos g JOIN usuarios u ON g.docente_id = u.id ORDER BY g.creado_en DESC")->fetchAll();

$titulo = 'Todos los Grupos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📁 Todos los Grupos</h1></div>
<div class="card">
    <?php if (empty($grupos)): ?><p class="vacio">No hay grupos.</p><?php else: ?>
    <table>
        <tr><th>Grupo</th><th>Docente</th><th>Código</th><th>Miembros</th></tr>
        <?php foreach($grupos as $g): ?>
        <tr><td><?= sanitizar($g['nombre']) ?></td><td><?= sanitizar($g['docente_nombre']) ?></td><td><code><?= $g['codigo_invitacion'] ?></code></td><td><?= $g['miembros'] ?></td></tr>
        <?php endforeach; ?>
    </table><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
