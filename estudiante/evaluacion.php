<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_estudiante();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];
$eid = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT e.*, g.docente_id FROM evaluaciones e JOIN grupos g ON e.grupo_id = g.id WHERE e.id = :id");
$stmt->execute([':id' => $eid]); $eval = $stmt->fetch();
if (!$eval) { echo '<p>Evaluación no encontrada.</p>'; exit; }

$miembro = esMiembro($pdo, $eval['grupo_id'], $uid);
if (!$miembro) { echo '<p>No tenés acceso.</p>'; exit; }

$intentos = contarIntentos($pdo, $eid, $uid);
if ($intentos >= $eval['intentos_max']) {
    echo '<p>Ya agotaste los intentos.</p>'; exit;
}

$preguntas = json_decode($eval['preguntas_json'], true) ?: [];
if ($eval['shuffle_preguntas']) shuffle($preguntas);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuestas = $_POST['respuestas'] ?? [];
    $puntaje = 0;
    foreach ($preguntas as $i => $p) {
        $resp = $respuestas[$i] ?? '';
        if (strcasecmp(trim($resp), trim($p['correcta'])) === 0) $puntaje += 10;
    }
    $max = count($preguntas) * 10;
    $nota = $max > 0 ? round(($puntaje / $max) * 10, 1) : 0;
    $pdo->prepare("INSERT INTO evaluacion_intentos (evaluacion_id, usuario_id, intento_num, respuestas_json, puntaje, fecha_inicio, fecha_fin, finalizada) VALUES (:e,:u,:n,:r,:p,NOW() - INTERVAL 1 MINUTE, NOW(), 1)")
        ->execute([':e' => $eid, ':u' => $uid, ':n' => $intentos + 1, ':r' => json_encode($respuestas, JSON_UNESCAPED_UNICODE), ':p' => $nota]);
    echo '<div style="text-align:center;padding:60px"><h1>✅ Evaluación entregada</h1><p>Puntaje: ' . $puntaje . ' / ' . $max . '</p><p>Nota: ' . $nota . ' / 10</p><a href="grupo.php?id=' . $eval['grupo_id'] . '" class="btn-primario" style="margin-top:16px">Volver</a></div>';
    exit;
}

$titulo = 'Evaluación: ' . sanitizar($eval['titulo']);
?><!DOCTYPE html><html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= sanitizar($eval['titulo']) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilos.css">
<style>
    body.modo-evaluacion{overflow:hidden}
    .eval-header{position:sticky;top:0;z-index:100;background:var(--bg);padding:12px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
    .timer{font-size:1.3rem;font-weight:700;color:var(--accent1)}
    .pregunta-block{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px}
    .pregunta-block .enunciado{font-weight:600;margin-bottom:12px}
    .opcion{display:block;padding:8px 12px;margin:4px 0;border-radius:8px;cursor:pointer;transition:.2s;border:2px solid transparent}
    .opcion:hover{background:rgba(99,102,241,.05)}
    .opcion input{margin-right:8px}
    .watermark{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:999;opacity:.03;display:flex;align-items:center;justify-content:center;font-size:6rem;font-weight:900;transform:rotate(-20deg);white-space:nowrap;user-select:none}
</style>
</head>
<body class="modo-evaluacion">
<div class="watermark"><?= sanitizar($usuario['nombre']) ?></div>
<div class="eval-header">
    <h2><?= sanitizar($eval['titulo']) ?></h2>
    <div class="timer" id="timer"><?= $eval['duracion_min'] ?>:00</div>
</div>
<div style="max-width:800px;margin:0 auto;padding:20px">
<form method="POST" id="eval-form">
    <?php foreach($preguntas as $i => $p): ?>
    <div class="pregunta-block">
        <p class="enunciado"><?= ($i+1) ?>. <?= sanitizar($p['texto']) ?></p>
        <?php if($p['tipo']==='multiple' || $p['tipo']==='verdadero_falso'): foreach($p['opciones'] as $o): ?>
        <label class="opcion"><input type="radio" name="respuestas[<?= $i ?>]" value="<?= sanitizar($o) ?>" required> <?= sanitizar($o) ?></label>
        <?php endforeach; elseif($p['tipo']==='completar'): ?>
        <input type="text" name="respuestas[<?= $i ?>]" class="input-dato" required>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn-primario btn-full">Entregar Evaluación</button>
</form>
</div>

<script>
document.body.classList.add('modo-evaluacion');

try { document.documentElement.requestFullscreen().catch(function(){}); } catch(e) {}

var minutos = <?= $eval['duracion_min'] ?>, segundos = 0;
var timerEl = document.getElementById('timer');
var interval = setInterval(function(){
    if (segundos === 0) { if (minutos === 0) { clearInterval(interval); document.getElementById('eval-form').submit(); return; } minutos--; segundos = 59; }
    else segundos--;
    timerEl.textContent = minutos + ':' + (segundos < 10 ? '0' : '') + segundos;
    if (minutos < 2) timerEl.style.color = 'var(--peligro)';
}, 1000);

var tabCount = 0, intentoId = 0;
document.addEventListener('visibilitychange', function() {
    if (document.hidden) { tabCount++; fetch('../api/registrar-tab.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ intento_id: intentoId, tipo_evento: 'visibility' }) }).catch(function(){}); if (tabCount >= 3) document.getElementById('eval-form').submit(); }
});

document.addEventListener('keydown', function(e) {
    var k = e.key || e.keyCode;
    var ctrl = e.ctrlKey || e.metaKey;
    if (k === 'F12' || (ctrl && (k === 'u' || k === 's' || k === 'p' || k === 'w'))) { e.preventDefault(); e.stopPropagation(); return false; }
});

document.addEventListener('contextmenu', function(e) { e.preventDefault(); });
document.addEventListener('copy', function(e) { e.preventDefault(); });
document.addEventListener('paste', function(e) { e.preventDefault(); });
window.addEventListener('beforeunload', function(e) { e.preventDefault(); e.returnValue = ''; });
</script>
</body></html>
