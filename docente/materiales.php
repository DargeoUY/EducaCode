<?php
/**
 * docente/materiales.php — Listar materiales de un grupo
 * docente/material-crear.php — Agregar material
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
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Grupo no encontrado.'];
    redirigir('docente/grupos.php');
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCSRF()) { $mensaje = ['error', 'Token de seguridad inválido. Recargá la página.']; }
    else {
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $mid = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM materiales WHERE id = :id AND grupo_id = :g")->execute([':id' => $mid, ':g' => $gid]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Material eliminado.'];
        redirigir("docente/materiales.php?grupo_id=$gid");
    } else {
    $titulo = sanitizar($_POST['titulo']);
    $tipo = $_POST['tipo'];
    $url = sanitizar($_POST['contenido_url']);

    if ($titulo === '' || $url === '') {
        $mensaje = ['error', 'Completa todos los campos.'];
    } else {
        $archivo_nombre = null;
        if ($tipo === 'archivo' && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo'];
            $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $extPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'mp3', 'mp4', 'webm'];
            $mimeTypesPermitidos = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
                'application/zip',
                'application/x-rar-compressed',
                'application/x-rar',
                'image/png',
                'image/jpeg',
                'image/gif',
                'image/svg+xml',
                'audio/mpeg',
                'audio/mp3',
                'video/mp4',
                'video/webm'
            ];

            if (!in_array($ext, $extPermitidas)) {
                $mensaje = ['error', 'Tipo de archivo no permitido. Extensiones válidas: ' . implode(', ', $extPermitidas)];
            } elseif ($archivo['size'] > 50 * 1024 * 1024) {
                $mensaje = ['error', 'El archivo excede el límite de 50MB.'];
            } elseif (!in_array($archivo['type'], $mimeTypesPermitidos)) {
                $mensaje = ['error', 'Tipo MIME no permitido.'];
            } else {
                $archivo_nombre = 'material_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($archivo['tmp_name'], UPLOAD_DIR . $archivo_nombre);
                $url = BASE_URL . 'uploads/' . $archivo_nombre;
            }
        }

        $pdo->prepare("INSERT INTO materiales (grupo_id, titulo, tipo, contenido_url, archivo_nombre) VALUES (:g, :t, :tp, :u, :an)")
            ->execute([':g' => $gid, ':t' => $titulo, ':tp' => $tipo, ':u' => $url, ':an' => $archivo_nombre]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Material agregado.'];
        redirigir("docente/materiales.php?grupo_id=$gid");
    }
    }
    }
}

$materiales = $pdo->prepare("SELECT * FROM materiales WHERE grupo_id = :g ORDER BY creado_en DESC");
$materiales->execute([':g' => $gid]);
$materiales = $materiales->fetchAll();

$titulo = 'Materiales: ' . $grupo['nombre'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📚 Materiales — <?= sanitizar($grupo['nombre']) ?></h1>
    <div class="actions-row">
        <a href="<?= BASE_URL ?>docente/grupo-editar.php?id=<?= $gid ?>" class="btn-secundario">← Volver al grupo</a>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card">
    <h2>➕ Agregar material</h2>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfInput() ?>
        <div class="grupo-input">
            <label for="titulo">Título</label>
            <input type="text" id="titulo" name="titulo" class="input-dato" placeholder="Ej: Guía HTML Nivel 1" required>
        </div>
        <div class="grupo-input">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo" class="input-dato" onchange="document.getElementById('campo-archivo').style.display=this.value==='archivo'?'block':'none';document.getElementById('campo-url').style.display=this.value==='archivo'?'none':'block'">
                <option value="link">Enlace (URL)</option>
                <option value="archivo">Archivo (PDF, DOC, etc.)</option>
                <option value="html">Página HTML</option>
            </select>
        </div>
        <div class="grupo-input" id="campo-url">
            <label for="contenido_url">URL</label>
            <input type="url" id="contenido_url" name="contenido_url" class="input-dato" placeholder="https://...">
        </div>
        <div class="grupo-input" id="campo-archivo" style="display:none;">
            <label for="archivo">Archivo (máx 50MB)</label>
            <input type="file" id="archivo" name="archivo" class="input-dato">
        </div>
        <button type="submit" class="btn-primario">➕ Agregar</button>
    </form>
</div>

<div class="card">
    <h2>📋 Materiales del grupo</h2>
    <?php if (empty($materiales)): ?>
        <p class="vacio">Sin materiales. Agrega el primero.</p>
    <?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Título</th><th>Tipo</th><th>Enlace</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr>
                <td><strong><?= sanitizar($m['titulo']) ?></strong></td>
                <td><span class="tag tag-<?= $m['tipo'] ?>"><?= $m['tipo'] ?></span></td>
                <td><a href="<?= sanitizar($m['contenido_url']) ?>" target="_blank" rel="noopener">Abrir ↗</a></td>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar material?')">
                        <?= csrfInput() ?>
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn-sm btn-rechazar">🗑 Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
