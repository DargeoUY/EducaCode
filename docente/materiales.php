<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$gid = $_GET['grupo_id'] ?? 0;
$mensaje = '';

$stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = :id AND docente_id = :d");
$stmt->execute([':id' => $gid, ':d' => $did]); $grupo = $stmt->fetch();
if (!$grupo) { echo '<p>Grupo no encontrado.</p>'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo = $_POST['tipo'] ?? 'link';
    $url = trim($_POST['contenido_url'] ?? '');
    if ($titulo && $url) {
        $pdo->prepare("INSERT INTO materiales (grupo_id, titulo, tipo, contenido_url) VALUES (:g,:t,:tp,:u)")
            ->execute([':g' => $gid, ':t' => $titulo, ':tp' => $tipo, ':u' => $url]);
        $mensaje = ['exito', 'Material agregado.'];
    }
}

$materiales = $pdo->prepare("SELECT * FROM materiales WHERE grupo_id = :g ORDER BY creado_en DESC");
$materiales->execute([':g' => $gid]);

$titulo = 'Materiales: ' . sanitizar($grupo['nombre']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📚 Materiales — <?= sanitizar($grupo['nombre']) ?></h1><a href="grupos.php" class="btn-sm">← Volver</a></div>
<?php if ($mensaje): ?><div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div><?php endif; ?>

<div class="card"><h2>➕ Agregar material</h2>
<form method="POST">
    <div class="grupo-input"><label>Título</label><input type="text" name="titulo" class="input-dato" required></div>
    <div class="grupo-input"><label>Tipo</label><select name="tipo" class="input-dato"><option value="link">Link</option><option value="archivo">Archivo</option><option value="html">HTML</option></select></div>
    <div class="grupo-input"><label>URL o contenido</label><textarea name="contenido_url" class="input-dato" required></textarea></div>
    <button type="submit" class="btn-primario">Agregar</button>
</form></div>

<div class="card"><h2>Materiales existentes</h2>
<?php $mats = $materiales->fetchAll(); if (empty($mats)): ?><p class="vacio">Sin materiales.</p><?php else: ?>
<?php foreach($mats as $m): ?><div style="padding:8px 0;border-bottom:1px solid var(--superficie-claro)"><strong><?= sanitizar($m['titulo']) ?></strong> <span class="tag"><?= $m['tipo'] ?></span><br><a href="<?= sanitizar($m['contenido_url']) ?>" target="_blank" style="font-size:.8rem"><?= sanitizar($m['contenido_url']) ?></a></div><?php endforeach; ?>
<?php endif; ?></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
