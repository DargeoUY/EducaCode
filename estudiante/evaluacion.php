<?php
/**
 * estudiante/evaluacion.php — Rendir evaluacion con todas las medidas anti-trampa
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_estudiante();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$eid = (int)($_GET['id'] ?? 0);
$eval = $pdo->prepare("SELECT e.*, g.nombre AS grupo_nombre FROM evaluaciones e JOIN grupos g ON e.grupo_id = g.id WHERE e.id = :id LIMIT 1");
$eval->execute([':id' => $eid]);
$eval = $eval->fetch();

if (!$eval) { redirigir('estudiante/mis-grupos.php'); }

$membresia = esMiembro($pdo, $eval['grupo_id'], $uid);
if (!$membresia) { redirigir('estudiante/mis-grupos.php'); }

$intentosRealizados = contarIntentos($pdo, $eid, $uid);
if ($intentosRealizados >= $eval['intentos_max']) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No tienes más intentos disponibles.'];
    redirigir("estudiante/grupo.php?id={$eval['grupo_id']}");
}

if ($eval['fecha_limite'] && strtotime($eval['fecha_limite']) < time()) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'La evaluación ya venció.'];
    redirigir("estudiante/grupo.php?id={$eval['grupo_id']}");
}

// Crear nuevo intento
$intentoNum = $intentosRealizados + 1;
$pdo->prepare("INSERT INTO evaluacion_intentos (evaluacion_id, usuario_id, intento_num, fecha_inicio) VALUES (:eid, :uid, :num, NOW())")
    ->execute([':eid' => $eid, ':uid' => $uid, ':num' => $intentoNum]);
$intentoId = $pdo->lastInsertId();

$preguntas = json_decode($eval['preguntas_json'], true) ?? [];

// Etiquetar cada pregunta con su indice original para scoring server-side
foreach ($preguntas as $i => &$p) {
    $p['_idx'] = $i;
}
unset($p);

if ($eval['shuffle_preguntas']) {
    shuffle($preguntas);
}

if ($eval['preguntas_mostrar'] && $eval['preguntas_mostrar'] < count($preguntas)) {
    $preguntas = array_slice($preguntas, 0, $eval['preguntas_mostrar']);
}

foreach ($preguntas as &$p) {
    if (($p['tipo'] ?? 'multiple') !== 'completar' && $eval['shuffle_opciones']) {
        $opciones = $p['opciones'] ?? [];
        shuffle($opciones);
        $p['opciones'] = $opciones;
    }
}
unset($p);

// Guardar preguntas ordenadas (con respuestas) en el intento para scoring server-side
$pdo->prepare("UPDATE evaluacion_intentos SET respuestas_json = :rj WHERE id = :id")
    ->execute([':rj' => json_encode($preguntas, JSON_UNESCAPED_UNICODE), ':id' => $intentoId]);

// Enviar al cliente SIN las respuestas correctas
$preguntasClean = [];
foreach ($preguntas as $p) {
    $c = $p;
    unset($c['respuesta']);
    $preguntasClean[] = $c;
}
$preguntasJSON = json_encode($preguntasClean, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$duracionSeg = $eval['duracion_min'] * 60;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; connect-src 'self' https:; upgrade-insecure-requests;">
    <title>Evaluación — <?= sanitizar($eval['titulo']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/estilos.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    <style>
        * { user-select: none; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; }
        input, textarea { user-select: text; -webkit-user-select: text; }
        body { background: #0f172a; }
        .watermark {
            position:fixed; top:0; left:0; width:100%; height:100%;
            pointer-events:none; z-index:9999;
            display:flex; align-items:center; justify-content:center;
            font-size:3rem; color:rgba(255,255,255,0.04); font-weight:900;
            letter-spacing:20px; transform:rotate(-30deg); user-select:none;
        }
        .eval-container { max-width:800px; margin:0 auto; padding:20px; position:relative; z-index:1; }
        .eval-header { text-align:center; padding:20px; margin-bottom:20px; }
        .eval-header h1 { font-size:1.6rem; color:var(--primario-claro); }
        .timer-bar { height:6px; background:var(--superficie-claro); border-radius:3px; overflow:hidden; margin-bottom:8px; }
        .timer-fill { height:100%; background:var(--gradiente-primario); transition:width 1s linear; }
        .timer-text { text-align:center; font-size:1.3rem; font-weight:700; font-family:monospace; }
        .timer-warning { color:#f59e0b !important; }
        .timer-danger { color:#ef4444 !important; animation:pulsoTimer 1s ease-in-out infinite; }
        @keyframes pulsoTimer { 0%,100%{opacity:1}50%{opacity:.5} }
        .pregunta-block { background:var(--superficie); border:1px solid var(--superficie-claro); border-radius:12px; padding:24px; margin-bottom:20px; }
        .pregunta-block h3 { color:var(--texto); margin-bottom:8px; }
        .pregunta-block .texto-pregunta { font-size:1.05rem; margin-bottom:16px; line-height:1.6; color:var(--texto); }
        .opcion-eval { display:flex; align-items:center; gap:12px; padding:12px 16px; border:2px solid var(--superficie-claro); border-radius:10px; margin-bottom:8px; cursor:pointer; transition:all .2s; background:rgba(255,255,255,.02); }
        .opcion-eval:hover { border-color:var(--primario); background:rgba(99,102,241,.08); }
        .opcion-eval.seleccionada { border-color:var(--primario); background:rgba(99,102,241,.15); color:var(--primario-claro); }
        .opcion-eval input[type="radio"] { margin:0; width:18px; height:18px; accent-color:var(--primario); }
        .opcion-label { flex:1; cursor:pointer; font-size:0.95rem; color:var(--texto); }
        .input-respuesta { width:100%; padding:12px 16px; background:var(--fondo-codigo); color:var(--texto-codigo); border:2px solid var(--superficie-claro); border-radius:8px; font-family:Consolas,Monaco,monospace; font-size:1rem; outline:none; transition:border-color .3s; }
        .input-respuesta:focus { border-color:var(--primario); }
        .btn-entregar { display:block; width:100%; max-width:300px; margin:30px auto; background:var(--gradiente-primario); color:white; border:none; padding:16px; font-size:1.1rem; font-weight:700; border-radius:12px; cursor:pointer; transition:all .3s; font-family:inherit; }
        .btn-entregar:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(99,102,241,.4); }
        .btn-entregar:disabled { opacity:.5; cursor:not-allowed; transform:none; }
        .tab-counter { position:fixed; top:10px; right:10px; background:rgba(239,68,68,.9); color:white; padding:6px 12px; border-radius:8px; font-size:.75rem; font-weight:700; z-index:9999; display:none; }
        .fullscreen-aviso { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.95); color:white; display:flex; align-items:center; justify-content:center; z-index:99999; }
        .fullscreen-aviso button { background:var(--gradiente-primario); color:white; border:none; padding:16px 40px; font-size:1.2rem; border-radius:10px; cursor:pointer; }
        .tab-status { display:flex;align-items:center;gap:6px;font-size:.8rem;color:var(--exito);justify-content:center;margin-bottom:12px; }
        .tab-status.alerta { color:#f59e0b; }
        .tab-status.peligro { color:#ef4444; }
    </style>
</head>
<body>
<div class="watermark" id="watermark"><?= sanitizar($usuario['nombre']) ?></div>
<div class="tab-counter" id="tab-counter">⚠️ Salidas: 0</div>

<div class="fullscreen-aviso" id="fullscreen-aviso">
    <div style="text-align:center;">
        <h1 style="font-size:2rem;margin-bottom:10px;">🔒 Pantalla completa requerida</h1>
        <p style="margin-bottom:20px;">La evaluación requiere pantalla completa.<br>No podés cambiar de pestaña.</p>
        <button onclick="activarFullscreen()">🎯 Comenzar evaluación</button>
    </div>
</div>

<div class="eval-container" id="eval-container" style="display:none;">
    <div class="eval-header">
        <h1><?= sanitizar($eval['titulo']) ?></h1>
        <p><?= sanitizar($eval['descripcion'] ?? '') ?></p>
        <p style="color:var(--texto-secundario);">
            Grupo: <?= sanitizar($eval['grupo_nombre']) ?> |
            Intento <?= $intentoNum ?>/<?= $eval['intentos_max'] ?> |
            Puntaje máx: <?= $eval['puntaje_max'] ?> pts
        </p>
    </div>

    <div class="timer-bar"><div class="timer-fill" id="timer-fill"></div></div>
    <div class="timer-text" id="timer-text"><?= floor($duracionSeg / 60) ?>:00</div>
    <div class="tab-status" id="tab-status">🟢 Sin cambios de pestaña</div>

    <div id="preguntas-container"></div>

    <button class="btn-entregar" id="btn-entregar" onclick="entregarEvaluacion()">📤 Entregar evaluación</button>
</div>

<script>
var EVAL_ID = <?= $eid ?>;
var INTENTO_ID = <?= $intentoId ?>;
var DURACION_SEG = <?= $duracionSeg ?>;
var PUNT_MAX = <?= $eval['puntaje_max'] ?>;
var PREGUNTAS = <?= $preguntasJSON ?>;
var BASE_URL = '<?= BASE_URL ?>';
var tabSalidas = 0;
var tiempoRestante = DURACION_SEG;
var evaluacionEntregada = false;
var respuestas = {};

// Inicializar respuestas (key = _idx original)
PREGUNTAS.forEach(function(p, i) {
    respuestas[p._idx] = null;
});

// ============ ANTI-TRAMPA: FULLSCREEN ============
function activarFullscreen() {
    var el = document.documentElement;
    if (el.requestFullscreen) el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    else if (el.msRequestFullscreen) el.msRequestFullscreen();

    document.getElementById('fullscreen-aviso').style.display = 'none';
    document.getElementById('eval-container').style.display = 'block';
    iniciarEvaluacion();
}

// ============ ANTI-TRAMPA: VISIBILITY API ============
document.addEventListener('visibilitychange', function() {
    if (!document.getElementById('eval-container').style.display || document.getElementById('eval-container').style.display === 'none') return;
    if (evaluacionEntregada) return;

    if (document.hidden) {
        tabSalidas++;
        actualizarTabCounter();
        actualizarTabStatus();
        registrarSalida('visibility');

        if (tabSalidas >= <?= MAX_TAB_SALIDAS_PERMITIDAS ?>) {
            alert('Demasiados cambios de pestaña (' + tabSalidas + '). La evaluación se entrega automáticamente.');
            entregarEvaluacion();
        }
    }
});

// ============ ANTI-TRAMPA: FULLSCREEN EXIT ============
document.addEventListener('fullscreenchange', verificarFullscreen);
document.addEventListener('webkitfullscreenchange', verificarFullscreen);
document.addEventListener('msfullscreenchange', verificarFullscreen);

function verificarFullscreen() {
    if (evaluacionEntregada) return;
    var fs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
    if (!fs && document.getElementById('eval-container').style.display === 'block') {
        tabSalidas++;
        actualizarTabCounter();
        actualizarTabStatus();
        registrarSalida('fullscreen_exit');
        alert('Saliste de pantalla completa. Volvé a activarla o la evaluación se entregará.');
        setTimeout(function() { activarFullscreen(); }, 2000);
    }
}

// ============ ANTI-TRAMPA: BLOQUEAR ATAJOS ============
document.addEventListener('keydown', function(e) {
    // Bloquear: Ctrl+C, Ctrl+V, Ctrl+U, Ctrl+S, Ctrl+A, Ctrl+P, F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
    if (e.ctrlKey && (e.key === 'c' || e.key === 'v' || e.key === 'u' || e.key === 's' || e.key === 'a' || e.key === 'p')) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
    if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
        e.preventDefault();
        return false;
    }
    if (e.key === 'F12' || e.keyCode === 123) {
        e.preventDefault();
        return false;
    }
    if (e.key === 'PrintScreen' || e.keyCode === 44) {
        e.preventDefault();
        return false;
    }
});

// ============ ANTI-TRAMPA: BLOQUEAR CLICK DERECHO ============
document.addEventListener('contextmenu', function(e) { e.preventDefault(); return false; });

// ============ ANTI-TRAMPA: BLOQUEAR COPIAR/PEGAR ============
document.addEventListener('copy', function(e) { e.preventDefault(); return false; });
document.addEventListener('cut', function(e) { e.preventDefault(); return false; });
document.addEventListener('paste', function(e) { e.preventDefault(); return false; });

// ============ ANTI-TRAMPA: DETECTAR DEVTOOLS ============
var devtoolsDetectado = false;
setInterval(function() {
    if (evaluacionEntregada) return;
    var threshold = 160;
    var diff = window.outerWidth - window.innerWidth > threshold || window.outerHeight - window.innerHeight > threshold;
    if (diff && !devtoolsDetectado) {
        devtoolsDetectado = true;
        tabSalidas++;
        actualizarTabCounter();
        actualizarTabStatus();
        registrarSalida('devtools');
    }
    if (!diff) devtoolsDetectado = false;
}, 2000);

// ============ ANTI-TRAMPA: ANTES DE CERRAR ============
window.addEventListener('beforeunload', function(e) {
    if (!evaluacionEntregada) {
        e.preventDefault();
        e.returnValue = 'Si salís, la evaluación se entregará automáticamente.';
        return e.returnValue;
    }
});

function actualizarTabCounter() {
    var el = document.getElementById('tab-counter');
    el.style.display = 'block';
    el.textContent = '⚠️ Salidas: ' + tabSalidas;
}

function actualizarTabStatus() {
    var el = document.getElementById('tab-status');
    if (tabSalidas === 0) { el.className = 'tab-status'; el.textContent = '🟢 Sin cambios de pestaña'; }
    else if (tabSalidas < <?= MAX_TAB_SALIDAS_PERMITIDAS ?>) { el.className = 'tab-status alerta'; el.textContent = '⚠️ Salidas: ' + tabSalidas + '/' + <?= MAX_TAB_SALIDAS_PERMITIDAS ?>; }
    else { el.className = 'tab-status peligro'; el.textContent = '🔴 Límite excedido: ' + tabSalidas; }
}

function registrarSalida(tipo) {
    fetch(BASE_URL + 'api/registrar-tab.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ intento_id: INTENTO_ID, tipo_evento: tipo })
    }).catch(function() {});
}

// ============ TEMPORIZADOR ============
function iniciarEvaluacion() {
    renderizarPreguntas();
    var timerInterval = setInterval(function() {
        if (evaluacionEntregada) { clearInterval(timerInterval); return; }
        tiempoRestante--;
        actualizarTimer();

        if (tiempoRestante <= 0) {
            clearInterval(timerInterval);
            alert('Se acabó el tiempo. Tu evaluación se entrega automáticamente.');
            entregarEvaluacion();
        }
    }, 1000);
}

function actualizarTimer() {
    var min = Math.floor(tiempoRestante / 60);
    var seg = tiempoRestante % 60;
    var textEl = document.getElementById('timer-text');
    textEl.textContent = min + ':' + (seg < 10 ? '0' : '') + seg;

    var pct = (tiempoRestante / DURACION_SEG) * 100;
    var fillEl = document.getElementById('timer-fill');
    fillEl.style.width = pct + '%';

    if (tiempoRestante < 60) {
        textEl.className = 'timer-text timer-danger';
    } else if (tiempoRestante < 300) {
        textEl.className = 'timer-text timer-warning';
    }

    // Cambiar color de la barra
    if (tiempoRestante < 60) { fillEl.style.background = '#ef4444'; }
    else if (tiempoRestante < 300) { fillEl.style.background = '#f59e0b'; }
}

// ============ RENDERIZAR PREGUNTAS ============
function renderizarPreguntas() {
    var html = '';
    PREGUNTAS.forEach(function(p, idx) {
        html += '<div class="pregunta-block">';
        html += '<h3>Pregunta ' + (idx + 1) + ' de ' + PREGUNTAS.length + ' <span style="color:var(--primario-claro);font-size:.8rem;">(' + (p.puntaje || 10) + ' pts)</span></h3>';
        html += '<div class="texto-pregunta">' + escaparHTML(p.texto) + '</div>';

        var tipo = p.tipo || 'multiple';

        if (tipo === 'multiple' || tipo === 'verdadero_falso') {
            var opciones = p.opciones || [];
            opciones.forEach(function(op, oi) {
                html += '<div class="opcion-eval" onclick="seleccionarOpcion(' + idx + ',' + oi + ',this)" id="opcion-' + idx + '-' + oi + '">';
                html += '<input type="radio" name="pregunta-' + idx + '" id="radio-' + idx + '-' + oi + '" onclick="event.stopPropagation();seleccionarOpcion(' + idx + ',' + oi + ',document.getElementById(\'opcion-' + idx + '-' + oi + '\'))">';
                html += '<label class="opcion-label" for="radio-' + idx + '-' + oi + '">' + escaparHTML(op) + '</label>';
                html += '</div>';
            });
        } else if (tipo === 'completar') {
            html += '<input type="text" class="input-respuesta" id="respuesta-' + idx + '" placeholder="Escribí tu respuesta..." oninput="respuestas[' + p._idx + ']=this.value">';
        }

        html += '</div>';
    });
    document.getElementById('preguntas-container').innerHTML = html;
}

function escaparHTML(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function seleccionarOpcion(idx, oi, el) {
    var opciones = document.querySelectorAll('[id^="opcion-' + idx + '-"]');
    opciones.forEach(function(o) { o.classList.remove('seleccionada'); });
    el.classList.add('seleccionada');

    var radio = document.getElementById('radio-' + idx + '-' + oi);
    if (radio) radio.checked = true;

    var pregunta = PREGUNTAS[idx];
    respuestas[pregunta._idx] = pregunta.opciones[oi];
}

// ============ ENTREGAR ============
function entregarEvaluacion() {
    if (evaluacionEntregada) return;
    evaluacionEntregada = true;

    var btn = document.getElementById('btn-entregar');
    btn.disabled = true;
    btn.textContent = 'Entregando...';

    // Recolectar respuestas de completar
    PREGUNTAS.forEach(function(p, idx) {
        if ((p.tipo || 'multiple') === 'completar') {
            var input = document.getElementById('respuesta-' + idx);
            if (input) respuestas[p._idx] = input.value;
        }
    });

    fetch(BASE_URL + 'api/guardar-evaluacion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            intento_id: INTENTO_ID,
            evaluacion_id: EVAL_ID,
            respuestas: respuestas,
            tab_salidas: tabSalidas
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (document.fullscreenElement) {
            if (document.exitFullscreen) document.exitFullscreen();
        }
        document.body.innerHTML = '<div style="text-align:center;padding:60px 20px;max-width:600px;margin:0 auto;">' +
            '<div style="font-size:4rem;">🏆</div>' +
            '<h1 style="color:var(--primario-claro);margin:16px 0;">Evaluación entregada</h1>' +
            '<p style="font-size:1.2rem;">Tu puntaje: <strong style="color:var(--exito);">' + (data.puntaje || 0) + ' / ' + PUNT_MAX + '</strong> pts</p>' +
            (data.tab_salidas > 0 ? '<p style="color:#f59e0b;">⚠️ Se registraron ' + data.tab_salidas + ' salida(s) de pestaña.</p>' : '<p style="color:var(--exito);">✅ No se detectaron salidas de pestaña.</p>') +
            '<a href="' + BASE_URL + 'estudiante/mis-grupos.php" style="display:inline-block;margin-top:20px;background:var(--gradiente-primario);color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:700;">Volver a mis grupos</a>' +
            '</div>';
    })
    .catch(function(err) {
        alert('Error al entregar. Intenta de nuevo.');
        btn.disabled = false;
        btn.textContent = '📤 Entregar evaluación';
        evaluacionEntregada = false;
    });
}
</script>
</body>
</html>
