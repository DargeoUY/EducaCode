<?php
/**
 * docente/grupo-crear.php — Crear un nuevo grupo
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitizar($_POST['nombre']);
    $descripcion = sanitizar($_POST['descripcion'] ?? '');
    $expiracion = $_POST['codigo_expiracion'] ?? '';

    if ($nombre === '') {
        $error = 'El nombre del grupo es obligatorio.';
    } else {
        $codigo = generarCodigoInvitacion();
        while ($pdo->prepare("SELECT id FROM grupos WHERE codigo_invitacion = :c")->execute([':c' => $codigo]) && $pdo->query("SELECT id FROM grupos WHERE codigo_invitacion = '$codigo'")->fetch()) {
            $codigo = generarCodigoInvitacion();
        }

        $exp = $expiracion ? date('Y-m-d H:i:s', strtotime($expiracion)) : null;

        $pdo->prepare("INSERT INTO grupos (nombre, descripcion, codigo_invitacion, codigo_expiracion, docente_id) VALUES (:n, :d, :c, :e, :did)")
            ->execute([':n' => $nombre, ':d' => $descripcion, ':c' => $codigo, ':e' => $exp, ':did' => $usuario['id']]);

        $gid = $pdo->lastInsertId();
        redirigir("docente/grupo-editar.php?id=$gid&creado=1");
    }
}

$titulo = 'Crear Grupo';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>➕ Crear Nuevo Grupo</h1>
</div>

<div class="card" style="max-width:600px;margin:0 auto;">
    <?php if ($error): ?>
        <div class="flash flash-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="grupo-input">
            <label for="nombre">Nombre del grupo *</label>
            <input type="text" id="nombre" name="nombre" class="input-dato" placeholder="Ej: 3ro A — Diseño Web" required>
        </div>
        <div class="grupo-input">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="input-dato" rows="3" placeholder="Descripción breve del grupo..."></textarea>
        </div>
        <div class="grupo-input">
            <label for="codigo_expiracion">Expiración del código (opcional)</label>
            <input type="datetime-local" id="codigo_expiracion" name="codigo_expiracion" class="input-dato">
            <span class="hint">Dejar vacío para que no expire. Los alumnos no podrán unirse después de esta fecha.</span>
        </div>
        <button type="submit" class="btn-primario btn-full">➕ Crear grupo</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
