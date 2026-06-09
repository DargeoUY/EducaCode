<?php
/**
 * docente/banco-preguntas.php — Banco de preguntas reutilizables
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $materia = sanitizar($_POST['materia']);
    $texto = sanitizar($_POST['texto']);
    $tipo = $_POST['tipo'];
    $respuesta = sanitizar($_POST['respuesta_correcta']);
    $puntaje = (int)$_POST['puntaje'];

    $opciones = [];
    if ($tipo === 'multiple') {
        for ($i = 1; $i <= 6; $i++) {
            $val = sanitizar($_POST['opcion_' . $i] ?? '');
            if ($val !== '') $opciones[] = $val;
        }
    } elseif ($tipo === 'verdadero_falso') {
        $opciones = ['Verdadero', 'Falso'];
    }

    if ($texto === '' || $respuesta === '') {
        $mensaje = ['error', 'Completa el texto y la respuesta correcta.'];
    } else {
        $pdo->prepare("INSERT INTO banco_preguntas (docente_id, materia, texto, tipo, opciones_json, respuesta_correcta, puntaje) VALUES (:d, :m, :t, :tp, :oj, :r, :p)")
            ->execute([
                ':d' => $uid, ':m' => $materia, ':t' => $texto, ':tp' => $tipo,
                ':oj' => json_encode($opciones, JSON_UNESCAPED_UNICODE),
                ':r' => $respuesta, ':p' => $puntaje
            ]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Pregunta agregada al banco.'];
        redirigir('docente/banco-preguntas.php');
    }
}

if (isset($_GET['eliminar'])) {
    $bid = (int)$_GET['eliminar'];
    $pdo->prepare("DELETE FROM banco_preguntas WHERE id = :id AND docente_id = :uid")->execute([':id' => $bid, ':uid' => $uid]);
    $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Pregunta eliminada.'];
    redirigir('docente/banco-preguntas.php');
}

$banco = $pdo->prepare("SELECT * FROM banco_preguntas WHERE docente_id = :uid ORDER BY materia, creado_en DESC");
$banco->execute([':uid' => $uid]);
$banco = $banco->fetchAll();

$materias = [];
foreach ($banco as $b) { $materias[$b['materia']] = true; }
$materias = array_keys($materias);

$titulo = 'Banco de Preguntas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🗂 Banco de Preguntas</h1>
    <p>Preguntas reutilizables para tus evaluaciones</p>
</div>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card">
    <h2>➕ Nueva pregunta</h2>
    <form method="POST">
        <div class="input-row-2">
            <div class="grupo-input">
                <label for="materia">Materia</label>
                <div style="display:flex;gap:8px;">
                    <select id="materia" name="materia" class="input-dato" style="flex:1;">
                        <option value="General">General</option>
                        <option value="HTML">HTML</option>
                        <option value="CSS">CSS</option>
                        <option value="JavaScript">JavaScript</option>
                        <option value="Python">Python</option>
                        <option value="Algoritmia">Algoritmia</option>
                        <option value="Pseudocódigo">Pseudocódigo</option>
                        <?php foreach ($materias as $m): if ($m !== 'General' && !in_array($m, ['HTML','CSS','JavaScript','Python','Algoritmia','Pseudocódigo'])): ?>
                        <option value="<?= sanitizar($m) ?>"><?= sanitizar($m) ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                    <input type="text" id="nueva-materia" class="input-dato" placeholder="O nueva materia..." style="flex:1;" onchange="if(this.value){var op=new Option(this.value,this.value);document.getElementById('materia').appendChild(op);document.getElementById('materia').value=this.value;}">
                </div>
            </div>
            <div class="grupo-input">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" class="input-dato" onchange="toggleTipoB(this.value)">
                    <option value="multiple">Multiple choice</option>
                    <option value="verdadero_falso">Verdadero / Falso</option>
                    <option value="completar">Completar espacio</option>
                </select>
            </div>
        </div>
        <div class="grupo-input">
            <label for="texto">Texto de la pregunta</label>
            <textarea id="texto" name="texto" class="input-dato" rows="2" required></textarea>
        </div>
        <div id="opciones-multiple">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="grupo-input" style="display:flex;align-items:center;gap:8px;">
                <input type="radio" name="correcta_idx" value="<?= $i ?>" title="Marcar como respuesta correcta" <?= $i === 1 ? 'checked' : '' ?>>
                <input type="text" name="opcion_<?= $i ?>" class="input-dato" placeholder="Opción <?= $i ?>" <?= $i <= 2 ? 'required' : '' ?>>
            </div>
            <?php endfor; ?>
        </div>
        <div id="respuesta-completar" style="display:none;">
            <div class="grupo-input">
                <input type="text" id="resp-texto" class="input-dato" placeholder="Respuesta correcta (y variantes separadas por |)">
                <span class="hint">Ej: print|imprimir (acepta cualquiera de las variantes)</span>
            </div>
        </div>
        <input type="hidden" name="respuesta_correcta" id="respuesta-correcta" value="">
        <div class="input-row-2">
            <div class="grupo-input">
                <label for="puntaje">Puntaje</label>
                <input type="number" id="puntaje" name="puntaje" class="input-dato" value="10" min="1">
            </div>
        </div>
        <button type="submit" class="btn-primario" onclick="return prepararRespuesta()">➕ Guardar pregunta</button>
    </form>
</div>

<div class="card">
    <h2>📋 Preguntas guardadas (<?= count($banco) ?>)</h2>
    <?php if (empty($banco)): ?>
        <p class="vacio">No tienes preguntas en tu banco.</p>
    <?php else: ?>
    <?php $matActual = ''; foreach ($banco as $bp): ?>
        <?php if ($bp['materia'] !== $matActual): $matActual = $bp['materia']; ?>
        <h3 style="color:var(--primario-claro);margin-top:16px;margin-bottom:8px;">📂 <?= sanitizar($matActual) ?></h3>
        <?php endif; ?>
        <?php $ops = json_decode($bp['opciones_json'], true) ?? []; ?>
        <div class="pregunta-mini">
            <div class="pregunta-mini-header">
                <span><strong><?= sanitizar($bp['tipo']) ?></strong> — <?= $bp['puntaje'] ?> pts</span>
                <a href="?eliminar=<?= $bp['id'] ?>" class="btn-sm btn-rechazar" onclick="return confirm('¿Eliminar?')">🗑</a>
            </div>
            <p><?= sanitizar($bp['texto']) ?></p>
            <?php if ($ops): ?>
                <small style="color:var(--texto-secundario);">Opciones: <?= implode(' | ', array_map('sanitizar', $ops)) ?></small><br>
            <?php endif; ?>
            <small style="color:var(--exito);">✅ <?= sanitizar($bp['respuesta_correcta']) ?></small>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleTipoB(tipo) {
    document.getElementById('opciones-multiple').style.display = tipo === 'multiple' ? 'block' : 'none';
    document.getElementById('respuesta-completar').style.display = tipo === 'completar' ? 'block' : 'none';
}

function prepararRespuesta() {
    var tipo = document.getElementById('tipo').value;
    if (tipo === 'multiple') {
        var radios = document.getElementsByName('correcta_idx');
        var idx = 0;
        for (var i = 0; i < radios.length; i++) { if (radios[i].checked) { idx = parseInt(radios[i].value); break; } }
        var val = document.getElementsByName('opcion_' + idx)[0].value;
        if (!val) { alert('La opción marcada como correcta no puede estar vacía.'); return false; }
        document.getElementById('respuesta-correcta').value = val;
    } else if (tipo === 'verdadero_falso') {
        document.getElementById('respuesta-correcta').value = 'Verdadero';
    } else if (tipo === 'completar') {
        var val = document.getElementById('resp-texto').value;
        if (!val) { alert('Ingresá la respuesta correcta.'); return false; }
        document.getElementById('respuesta-correcta').value = val;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
