<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$gid = $_GET['grupo_id'] ?? 0;
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? 'lectura';
    $contenido = trim($_POST['contenido'] ?? '');
    $fecha = trim($_POST['fecha_limite'] ?? '');
    if ($titulo && $contenido) {
        $pdo->prepare("INSERT INTO actividades (grupo_id, titulo, descripcion, tipo, contenido, fecha_limite) VALUES (:g,:t,:d,:tp,:c,:f)")
            ->execute([':g' => $gid, ':t' => $titulo, ':d' => $descripcion, ':tp' => $tipo, ':c' => $contenido, ':f' => $fecha ?: null]);
        $mensaje = ['exito', 'Actividad creada.'];
    }
}

$actividades = $pdo->prepare("SELECT * FROM actividades WHERE grupo_id = :g ORDER BY fecha_limite ASC");
$actividades->execute([':g' => $gid]);

$titulo = 'Actividades';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📋 Actividades</h1></div>
<?php if ($mensaje): ?><div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div><?php endif; ?>

<div class="card"><h2>➕ Nueva actividad</h2>
<form method="POST">
    <div class="grupo-input"><label>Título</label><input type="text" name="titulo" class="input-dato" required></div>
    <div class="grupo-input"><label>Descripción</label><textarea name="descripcion" class="input-dato"></textarea></div>
    <div style="display:flex;gap:10px"><div class="grupo-input" style="flex:1"><label>Tipo</label><select name="tipo" class="input-dato"><option value="lectura">Lectura</option><option value="ejercicio">Ejercicio</option><option value="practico">Práctico</option><option value="video">Video</option></select></div><div class="grupo-input" style="flex:1"><label>Fecha límite</label><input type="datetime-local" name="fecha_limite" class="input-dato"></div></div>
    <div class="grupo-input"><label>Contenido</label><textarea name="contenido" class="input-dato" rows="4" required></textarea></div>
    <button type="submit" class="btn-primario">Crear</button>
</form></div>

<div class="card"><h2>Actividades existentes</h2>
<?php $acts = $actividades->fetchAll(); if (empty($acts)): ?><p class="vacio">Sin actividades.</p><?php else: foreach($acts as $a): ?>
<div style="padding:10px 0;border-bottom:1px solid var(--superficie-claro)"><strong><?= sanitizar($a['titulo']) ?></strong> <span class="tag"><?= $a['tipo'] ?></span> <?= $a['fecha_limite'] ? '<span style="font-size:.78rem">Límite: '.formatearFecha($a['fecha_limite']).'</span>' : '' ?><p style="font-size:.85rem;color:var(--texto-secundario)"><?= sanitizar($a['descripcion']) ?></p></div>
<?php endforeach; endif; ?></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
