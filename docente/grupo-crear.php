<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($nombre === '') $error = 'Ingresá un nombre.';
    else {
        $codigo = generarCodigoInvitacion();
        $pdo->prepare("INSERT INTO grupos (nombre, descripcion, codigo_invitacion, docente_id) VALUES (:n, :d, :c, :did)")
            ->execute([':n' => $nombre, ':d' => $descripcion, ':c' => $codigo, ':did' => $did]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => "Grupo creado. Código: $codigo"];
        redirigir('docente/grupos.php');
    }
}

$titulo = 'Crear Grupo';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>➕ Crear Grupo</h1></div>
<?php if ($error): ?><div class="flash flash-error"><?= sanitizar($error) ?></div><?php endif; ?>

<div class="card" style="max-width:500px">
    <form method="POST">
        <div class="grupo-input"><label>Nombre del grupo</label><input type="text" name="nombre" class="input-dato" required></div>
        <div class="grupo-input"><label>Descripción</label><textarea name="descripcion" class="input-dato"></textarea></div>
        <button type="submit" class="btn-primario btn-full">Crear Grupo</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
