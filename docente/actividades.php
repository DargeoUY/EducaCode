<?php
/**
 * docente/actividades.php — Listar y crear actividades
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

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitizar($_POST['titulo']);
    $tipo = $_POST['tipo'];
    $descripcion = sanitizar($_POST['descripcion'] ?? '');
    $contenido = sanitizar($_POST['contenido'] ?? '');
    $limite = $_POST['fecha_limite'] ? date('Y-m-d H:i:s', strtotime($_POST['fecha_limite'])) : null;

    if ($titulo === '') {
        $mensaje = ['error', 'El título es obligatorio.'];
    } else {
        $pdo->prepare("INSERT INTO actividades (grupo_id, titulo, descripcion, tipo, contenido, fecha_limite) VALUES (:g, :t, :d, :tp, :c, :f)")
            ->execute([':g' => $gid, ':t' => $titulo, ':d' => $descripcion, ':tp' => $tipo, ':c' => $contenido, ':f' => $limite]);
        $mensaje = ['exito', 'Actividad creada.'];
    }
}

if (isset($_GET['eliminar'])) {
    $aid = (int)$_GET['eliminar'];
    $pdo->prepare("DELETE FROM actividades WHERE id = :id AND grupo_id = :g")->execute([':id' => $aid, ':g' => $gid]);
    $mensaje = ['exito', 'Actividad eliminada.'];
}

$actividades = $pdo->prepare("SELECT * FROM actividades WHERE grupo_id = :g ORDER BY creado_en DESC");
$actividades->execute([':g' => $gid]);
$actividades = $actividades->fetchAll();

$titulo = 'Actividades: ' . $grupo['nombre'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📋 Actividades — <?= sanitizar($grupo['nombre']) ?></h1>
    <a href="<?= BASE_URL ?>docente/grupo-editar.php?id=<?= $gid ?>" class="btn-secundario">← Volver al grupo</a>
</div>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card">
    <h2>➕ Crear actividad</h2>
    <form method="POST">
        <div class="grupo-input">
            <label for="titulo">Título *</label>
            <input type="text" id="titulo" name="titulo" class="input-dato" required>
        </div>
        <div class="input-row-2">
            <div class="grupo-input">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" class="input-dato">
                    <option value="lectura">📖 Lectura</option>
                    <option value="ejercicio">✏️ Ejercicio</option>
                    <option value="practico">🛠 Práctico</option>
                    <option value="video">🎬 Video</option>
                    <option value="discusion">💬 Discusión</option>
                </select>
            </div>
            <div class="grupo-input">
                <label for="fecha_limite">Fecha límite</label>
                <input type="datetime-local" id="fecha_limite" name="fecha_limite" class="input-dato">
            </div>
        </div>
        <div class="grupo-input">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="input-dato" rows="3"></textarea>
        </div>
        <div class="grupo-input">
            <label for="contenido">Contenido / Instrucciones</label>
            <textarea id="contenido" name="contenido" class="input-dato" rows="5"></textarea>
        </div>
        <button type="submit" class="btn-primario">➕ Crear actividad</button>
    </form>
</div>

<div class="card">
    <h2>📋 Actividades del grupo</h2>
    <?php if (empty($actividades)): ?>
        <p class="vacio">Sin actividades.</p>
    <?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Título</th><th>Tipo</th><th>Fecha límite</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($actividades as $a): ?>
            <tr>
                <td><strong><?= sanitizar($a['titulo']) ?></strong></td>
                <td><span class="tag"><?= $a['tipo'] ?></span></td>
                <td><?= formatearFecha($a['fecha_limite']) ?></td>
                <td>
                    <a href="?grupo_id=<?= $gid ?>&eliminar=<?= $a['id'] ?>" class="btn-sm btn-rechazar" onclick="return confirm('¿Eliminar?')">🗑</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
