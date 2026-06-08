<?php
/**
 * docente/evaluacion-crear.php — Crear evaluacion con preguntas
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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitizar($_POST['titulo']);
    $descripcion = sanitizar($_POST['descripcion'] ?? '');
    $duracion = (int)$_POST['duracion_min'];
    $intentos_max = (int)$_POST['intentos_max'];
    $preguntas_mostrar = (int)($_POST['preguntas_mostrar'] ?? 0);
    $shuffle_preguntas = isset($_POST['shuffle_preguntas']) ? 1 : 0;
    $shuffle_opciones = isset($_POST['shuffle_opciones']) ? 1 : 0;
    $fecha_limite = $_POST['fecha_limite'] ? date('Y-m-d H:i:s', strtotime($_POST['fecha_limite'])) : null;

    $preguntasData = json_decode($_POST['preguntas_json'], true);
    if (!$preguntasData || empty($preguntasData)) {
        $error = 'Debes agregar al menos una pregunta.';
    } elseif ($titulo === '') {
        $error = 'El título es obligatorio.';
    } else {
        $puntajeMax = 0;
        foreach ($preguntasData as $p) {
            $puntajeMax += (int)($p['puntaje'] ?? 10);
        }

        $pdo->prepare(
            "INSERT INTO evaluaciones (grupo_id, titulo, descripcion, preguntas_json, puntaje_max, duracion_min, intentos_max, preguntas_mostrar, shuffle_preguntas, shuffle_opciones, fecha_limite)
             VALUES (:g, :t, :d, :pj, :pm, :du, :im, :pmos, :sp, :so, :fl)"
        )->execute([
            ':g' => $gid, ':t' => $titulo, ':d' => $descripcion,
            ':pj' => json_encode($preguntasData, JSON_UNESCAPED_UNICODE),
            ':pm' => $puntajeMax, ':du' => $duracion, ':im' => $intentos_max,
            ':pmos' => $preguntas_mostrar > 0 ? $preguntas_mostrar : null,
            ':sp' => $shuffle_preguntas, ':so' => $shuffle_opciones, ':fl' => $fecha_limite
        ]);

        redirigir("docente/evaluaciones.php?grupo_id=$gid&creada=1");
    }
}

$banco = $pdo->prepare("SELECT * FROM banco_preguntas WHERE docente_id = :uid ORDER BY materia, creado_en DESC");
$banco->execute([':uid' => $uid]);
$banco = $banco->fetchAll();

$titulo = 'Crear Evaluación';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>📝 Crear Evaluación — <?= sanitizar($grupo['nombre']) ?></h1>
    <a href="<?= BASE_URL ?>docente/evaluaciones.php?grupo_id=<?= $gid ?>" class="btn-secundario">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="flash flash-error"><?= $error ?></div>
<?php endif; ?>

<form method="POST" id="form-evaluacion">
    <div class="card">
        <h2>📋 Configuración general</h2>
        <div class="grupo-input">
            <label for="titulo">Título *</label>
            <input type="text" id="titulo" name="titulo" class="input-dato" required>
        </div>
        <div class="grupo-input">
            <label for="descripcion">Descripción / Instrucciones</label>
            <textarea id="descripcion" name="descripcion" class="input-dato" rows="2"></textarea>
        </div>
        <div class="input-row-3">
            <div class="grupo-input">
                <label for="duracion_min">Duración (minutos)</label>
                <input type="number" id="duracion_min" name="duracion_min" class="input-dato" value="30" min="1" required>
            </div>
            <div class="grupo-input">
                <label for="intentos_max">Intentos máximos</label>
                <input type="number" id="intentos_max" name="intentos_max" class="input-dato" value="1" min="1" required>
            </div>
            <div class="grupo-input">
                <label for="fecha_limite">Fecha límite</label>
                <input type="datetime-local" id="fecha_limite" name="fecha_limite" class="input-dato">
            </div>
        </div>
        <div class="input-row-2">
            <div class="grupo-input">
                <label for="preguntas_mostrar">Preguntas a mostrar (0 = todas)</label>
                <input type="number" id="preguntas_mostrar" name="preguntas_mostrar" class="input-dato" value="0" min="0">
            </div>
            <div class="grupo-input" style="display:flex;align-items:center;gap:20px;padding-top:6px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="shuffle_preguntas" value="1" checked> Mezclar preguntas
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="shuffle_opciones" value="1" checked> Mezclar opciones
                </label>
            </div>
        </div>
    </div>

    <div class="card" id="banco-card">
        <h2>🗂 Banco de preguntas</h2>
        <?php if (empty($banco)): ?>
            <p>No tienes preguntas en tu banco. Créalas abajo.</p>
        <?php else: ?>
            <p>Hacé clic en una pregunta para agregarla a la evaluación:</p>
            <div class="banco-grid">
            <?php foreach ($banco as $bp): ?>
                <?php
                $opciones = json_decode($bp['opciones_json'], true) ?? [];
                $preview = substr(strip_tags($bp['texto']), 0, 60);
                ?>
                <div class="banco-item" onclick="agregarDelBanco(<?= htmlspecialchars(json_encode($bp, JSON_UNESCAPED_UNICODE)) ?>)">
                    <strong><?= sanitizar($bp['materia']) ?></strong> — <?= sanitizar($preview) ?>...
                    <br><small><?= $bp['tipo'] ?> | <?= $bp['puntaje'] ?> pts</small>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" id="preguntas-container">
        <h2>✏️ Preguntas de la evaluación</h2>
        <div id="lista-preguntas"></div>
        <button type="button" class="btn-secundario" onclick="agregarPregunta()">➕ Agregar pregunta</button>
    </div>

    <input type="hidden" name="preguntas_json" id="preguntas-json" value="[]">

    <div style="text-align:center;margin:20px 0;">
        <button type="submit" class="btn-primario" style="font-size:1.1rem;padding:14px 48px;">✅ Guardar evaluación</button>
    </div>
</form>

<script>
var preguntas = [];
var contador = 0;

function agregarPregunta(tipo) {
    tipo = tipo || 'multiple';
    var p = { id: ++contador, texto: '', tipo: tipo, puntaje: 10, opciones: [], respuesta: '' };
    if (tipo === 'verdadero_falso') { p.opciones = ['Verdadero', 'Falso']; p.respuesta = 'Verdadero'; }
    preguntas.push(p);
    renderizar();
}

function agregarDelBanco(bp) {
    var opciones = JSON.parse(bp.opciones_json);
    var p = {
        id: ++contador,
        texto: bp.texto,
        tipo: bp.tipo,
        puntaje: parseInt(bp.puntaje),
        opciones: opciones.map(function(o){ return typeof o === 'string' ? o : o.texto || o; }),
        respuesta: bp.respuesta_correcta
    };
    preguntas.push(p);
    renderizar();
}

function eliminarPregunta(idx) {
    preguntas.splice(idx, 1);
    renderizar();
}

function renderizar() {
    var html = '';
    preguntas.forEach(function(p, idx) {
        html += '<div class="pregunta-card">';
        html += '<div class="pregunta-header">';
        html += '<strong>Pregunta ' + (idx + 1) + '</strong> — ';
        html += '<select onchange="cambiarTipo('+idx+',this.value)">';
        ['multiple','verdadero_falso','completar'].forEach(function(t){
            html += '<option value="'+t+'"'+(p.tipo===t?' selected':'')+'>'+t+'</option>';
        });
        html += '</select> | Puntaje: <input type="number" value="'+p.puntaje+'" onchange="preguntas['+idx+'].puntaje=parseInt(this.value)" style="width:60px;" min="1">';
        html += '<button type="button" class="btn-sm btn-rechazar" onclick="eliminarPregunta('+idx+')">🗑</button>';
        html += '</div>';

        html += '<textarea onchange="preguntas['+idx+'].texto=this.value" rows="2" class="input-dato" placeholder="Texto de la pregunta">' + (p.texto || '') + '</textarea>';

        if (p.tipo === 'multiple') {
            html += '<div class="opciones-grid" id="opciones-'+idx+'">';
            (p.opciones || ['', '']).forEach(function(o, oi){
                html += '<div class="opcion-row">';
                html += '<input type="radio" name="resp-'+idx+'"' + (p.respuesta === o ? ' checked' : '') + ' onchange="preguntas['+idx+'].respuesta=this.value" value="'+o+'" title="Marcar como correcta">';
                html += '<input type="text" class="input-dato" value="'+o+'" onchange="actualizarOpcion('+idx+','+oi+',this.value)" placeholder="Opción '+(oi+1)+'">';
                html += '<button type="button" class="btn-sm btn-rechazar" onclick="eliminarOpcion('+idx+','+oi+')">×</button>';
                html += '</div>';
            });
            html += '<button type="button" class="btn-sm btn-secundario" onclick="agregarOpcion('+idx+')">➕ Agregar opción</button>';
            html += '</div>';
        } else if (p.tipo === 'verdadero_falso') {
            html += '<p>Opciones: <strong>Verdadero</strong> / <strong>Falso</strong></p>';
            html += '<label>Respuesta correcta: <select onchange="preguntas['+idx+'].respuesta=this.value"><option value="Verdadero"'+(p.respuesta==='Verdadero'?' selected':'')+'>Verdadero</option><option value="Falso"'+(p.respuesta==='Falso'?' selected':'')+'>Falso</option></select></label>';
        } else if (p.tipo === 'completar') {
            html += '<label>Respuesta correcta: <input type="text" class="input-dato" value="'+p.respuesta+'" onchange="preguntas['+idx+'].respuesta=this.value"></label>';
        }

        html += '</div>';
    });

    document.getElementById('lista-preguntas').innerHTML = html || '<p class="vacio">Sin preguntas. Agregá preguntas o seleccionalas del banco.</p>';
    document.getElementById('preguntas-json').value = JSON.stringify(preguntas);
}

function cambiarTipo(idx, tipo) {
    preguntas[idx].tipo = tipo;
    if (tipo === 'verdadero_falso') { preguntas[idx].opciones = ['Verdadero', 'Falso']; preguntas[idx].respuesta = 'Verdadero'; }
    if (tipo === 'completar') { preguntas[idx].opciones = []; preguntas[idx].respuesta = ''; }
    renderizar();
}

function actualizarOpcion(idx, oi, valor) {
    preguntas[idx].opciones[oi] = valor;
    if (preguntas[idx].respuesta === preguntas[idx].opciones[oi]) {
        preguntas[idx].respuesta = valor;
    }
    actualizarJson();
}

function agregarOpcion(idx) {
    preguntas[idx].opciones.push('');
    renderizar();
}

function eliminarOpcion(idx, oi) {
    preguntas[idx].opciones.splice(oi, 1);
    renderizar();
}

function actualizarJson() {
    document.getElementById('preguntas-json').value = JSON.stringify(preguntas);
}

// Validar antes de enviar
document.getElementById('form-evaluacion').addEventListener('submit', function(e) {
    actualizarJson();
    if (preguntas.length === 0) {
        e.preventDefault();
        alert('Agrega al menos una pregunta.');
    }
});

renderizar();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
