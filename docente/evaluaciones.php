<?php
/**
 * docente/evaluaciones.php — Listar evaluaciones de un grupo
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$gid = (int)($_GET['grupo_id'] ?? 0);
$grupo = obtenerGrupo($pdo, $gid);
if (!$grupo || ($grupo['docente_id'] != $uid && $usuario['rol'] !== 'admin')) {
    redirigir('docente/grupos.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    if (!validarCSRF()) { $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Token de seguridad inválido.']; redirigir("docente/evaluaciones.php?grupo_id=$gid"); }
    $eid = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM evaluaciones WHERE id = :id AND grupo_id = :g")->execute([':id' => $eid, ':g' => $gid]);
    $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Evaluación eliminada.'];
    redirigir("docente/evaluaciones.php?grupo_id=$gid");
}

$evaluaciones = $pdo->prepare(
    "SELECT e.*, (SELECT COUNT(*) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id AND ei.finalizada = 1) AS intentos_total,
     (SELECT AVG(ei.puntaje) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id AND ei.finalizada = 1) AS promedio
     FROM evaluaciones e WHERE e.grupo_id = :g ORDER BY e.creado_en DESC"
);
$evaluaciones->execute([':g' => $gid]);
$evaluaciones = $evaluaciones->fetchAll();

$titulo = 'Evaluaciones: ' . $grupo['nombre'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📝 Evaluaciones — <?= sanitizar($grupo['nombre']) ?></h1>
    <div class="actions-row">
        <a href="<?= BASE_URL ?>docente/grupo-editar.php?id=<?= $gid ?>" class="btn-secundario">← Volver al grupo</a>
        <a href="<?= BASE_URL ?>docente/evaluacion-crear.php?grupo_id=<?= $gid ?>" class="btn-primario">➕ Crear evaluación</a>
    </div>
</div>

<div class="card">
    <?php if (empty($evaluaciones)): ?>
        <p class="vacio">No hay evaluaciones. Crea la primera.</p>
    <?php else: ?>
    <table class="tabla">
        <thead>
            <tr>
                <th>Título</th>
                <th>Puntaje máx</th>
                <th>Duración</th>
                <th>Intentos</th>
                <th>Promedio</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($evaluaciones as $e): ?>
            <tr>
                <td><strong><?= sanitizar($e['titulo']) ?></strong></td>
                <td><?= $e['puntaje_max'] ?> pts</td>
                <td><?= formatearTiempo($e['duracion_min']) ?></td>
                <td><?= $e['intentos_total'] ?></td>
                <td><?= $e['promedio'] !== null ? number_format($e['promedio'], 1) : '—' ?></td>
                <td>
                    <div class="btn-grupo-sm">
                        <a href="<?= BASE_URL ?>docente/evaluacion-resultados.php?id=<?= $e['id'] ?>" class="btn-sm btn-accion">📊 Resultados</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar?')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <button type="submit" class="btn-sm btn-rechazar">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
