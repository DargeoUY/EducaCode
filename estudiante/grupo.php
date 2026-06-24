<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_estudiante();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];
$gid = $_GET['id'] ?? 0;

$m = esMiembro($pdo, $gid, $uid);
if (!$m) { echo '<p>No pertenecés a este grupo.</p>'; exit; }

$grupo = obtenerGrupo($pdo, $gid);
$materiales = $pdo->prepare("SELECT * FROM materiales WHERE grupo_id = :g ORDER BY creado_en DESC"); $materiales->execute([':g' => $gid]);
$actividades = $pdo->prepare("SELECT * FROM actividades WHERE grupo_id = :g ORDER BY fecha_limite ASC"); $actividades->execute([':g' => $gid]);
$evals = $pdo->prepare("SELECT e.*, (SELECT COUNT(*) FROM evaluacion_intentos ei WHERE ei.evaluacion_id = e.id AND ei.usuario_id = :u AND ei.finalizada = 1) AS rendidos FROM evaluaciones e WHERE e.grupo_id = :g ORDER BY e.creado_en DESC");
$evals->execute([':g' => $gid, ':u' => $uid]);

$titulo = 'Grupo: ' . sanitizar($grupo['nombre']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📂 <?= sanitizar($grupo['nombre']) ?></h1><p style="color:var(--text-sec);font-size:.85rem">Docente: <?= sanitizar($grupo['docente_nombre']) ?></p></div>

<div class="card"><h2>📚 Materiales</h2>
<?php $mats=$materiales->fetchAll(); if(empty($mats)): ?><p class="vacio">Sin materiales.</p><?php else: foreach($mats as $mat): ?>
<div style="padding:8px 0;border-bottom:1px solid var(--superficie-claro)"><strong><?= sanitizar($mat['titulo']) ?></strong> <span class="tag"><?= $mat['tipo'] ?></span><br><a href="<?= sanitizar($mat['contenido_url']) ?>" target="_blank" style="font-size:.82rem">Abrir</a></div>
<?php endforeach; endif; ?></div>

<div class="card"><h2>📋 Actividades</h2>
<?php $acts=$actividades->fetchAll(); if(empty($acts)): ?><p class="vacio">Sin actividades.</p><?php else: foreach($acts as $a): ?>
<div style="padding:8px 0;border-bottom:1px solid var(--superficie-claro)"><strong><?= sanitizar($a['titulo']) ?></strong> <span class="tag"><?= $a['tipo'] ?></span><?= $a['fecha_limite']?' <span style="font-size:.78rem;color:var(--text-sec)">Límite: '.formatearFecha($a['fecha_limite']).'</span>':'' ?><p style="font-size:.85rem;color:var(--text-sec)"><?= nl2br(sanitizar($a['contenido'])) ?></p></div>
<?php endforeach; endif; ?></div>

<div class="card"><h2>📝 Evaluaciones</h2>
<?php $evalsArr=$evals->fetchAll(); if(empty($evalsArr)): ?><p class="vacio">Sin evaluaciones.</p><?php else: foreach($evalsArr as $e): ?>
<div style="padding:10px 0;border-bottom:1px solid var(--superficie-claro);display:flex;justify-content:space-between;align-items:center">
    <div><strong><?= sanitizar($e['titulo']) ?></strong><br><span style="font-size:.78rem;color:var(--text-sec)"><?= $e['duracion_min'] ?> min · <?= $e['puntaje_max'] ?> pts · Intentos: <?= $e['rendidos'] ?>/<?= $e['intentos_max'] ?></span></div>
    <a href="evaluacion.php?id=<?= $e['id'] ?>" class="btn-primario btn-sm"><?= $e['rendidos']>=$e['intentos_max']?'Ver resultado':'Rendir' ?></a>
</div>
<?php endforeach; endif; ?></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
