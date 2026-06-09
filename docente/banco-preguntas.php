<?php
/**
 * docente/banco-preguntas.php — Banco de preguntas reutilizables
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];
$esAdmin = ($usuario['rol'] === 'admin');
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

    $destino = $esAdmin ? 0 : $uid;

    if ($texto === '' || $respuesta === '') {
        $mensaje = ['error', 'Completa el texto y la respuesta correcta.'];
    } else {
        $pdo->prepare("INSERT INTO banco_preguntas (docente_id, materia, texto, tipo, opciones_json, respuesta_correcta, puntaje) VALUES (:d, :m, :t, :tp, :oj, :r, :p)")
            ->execute([
                ':d' => $destino, ':m' => $materia, ':t' => $texto, ':tp' => $tipo,
                ':oj' => json_encode($opciones, JSON_UNESCAPED_UNICODE),
                ':r' => $respuesta, ':p' => $puntaje
            ]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Pregunta agregada al banco.'];
        redirigir('docente/banco-preguntas.php');
    }
}

if (isset($_GET['eliminar'])) {
    $bid = (int)$_GET['eliminar'];
    $check = $pdo->prepare("SELECT texto, docente_id FROM banco_preguntas WHERE id = :id");
    $check->execute([':id' => $bid]);
    $pregunta = $check->fetch();
    if (!$pregunta) {
        $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Pregunta no encontrada.'];
    } elseif ($pregunta['docente_id'] == 0 && !$esAdmin) {
        $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Las preguntas del banco de sugerencias no se pueden eliminar.'];
    } elseif ($pregunta['docente_id'] != $uid && !$esAdmin) {
        $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No puedes eliminar preguntas de otro docente.'];
    } elseif (esPreguntaSemilla($pregunta['texto'])) {
        $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Las preguntas recomendadas no se pueden eliminar.'];
    } else {
        $pdo->prepare("DELETE FROM banco_preguntas WHERE id = :id")->execute([':id' => $bid]);
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Pregunta eliminada.'];
    }
    redirigir('docente/banco-preguntas.php');
}

sembrarBancoDocente($pdo);

$tab = $_GET['tab'] ?? 'sugerencias';

$sugerencias = $pdo->query("SELECT * FROM banco_preguntas WHERE docente_id = 0 ORDER BY materia, creado_en DESC")->fetchAll();
$privadas = $pdo->prepare("SELECT * FROM banco_preguntas WHERE docente_id = :uid ORDER BY materia, creado_en DESC");
$privadas->execute([':uid' => $uid]);
$privadas = $privadas->fetchAll();

$banco = $tab === 'privadas' ? $privadas : $sugerencias;

$materias = [];
foreach (array_merge($sugerencias, $privadas) as $b) { $materias[$b['materia']] = true; }
$materias = array_keys($materias);

$titulo = 'Banco de Preguntas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🗂 Banco de Preguntas</h1>
    <p><?= $esAdmin ? 'Administrá el banco de sugerencias (visible para todos los docentes)' : 'Preguntas reutilizables para tus evaluaciones' ?></p>
</div>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card">
    <h2>➕ Nueva pregunta <?= $esAdmin ? '(Sugerencias — visible para todos)' : '(Privada)' ?></h2>
    <form method="POST">
        <div class="input-row-2">
            <div class="grupo-input">
                <label for="materia">Materia</label>
                <div style="display:flex;gap:8px;">
                    <select id="materia" name="materia" class="input-dato" style="flex:1;">
                        <option value="Pseudocódigo">Pseudocódigo</option>
                        <option value="HTML">HTML</option>
                        <option value="CSS">CSS</option>
                        <option value="JavaScript">JavaScript</option>
                        <option value="Diseño Web">Diseño Web</option>
                        <option value="Python">Python</option>
                        <option value="Algoritmia">Algoritmia</option>
                        <option value="General">General</option>
                        <?php foreach ($materias as $m): if (!in_array($m, ['Pseudocódigo','HTML','CSS','JavaScript','Diseño Web','Python','Algoritmia','General'])): ?>
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
    <div class="banco-tabs">
        <a href="?tab=sugerencias" class="banco-tab <?= $tab === 'sugerencias' ? 'active' : '' ?>">📌 Sugerencias (<?= count($sugerencias) ?>)</a>
        <a href="?tab=privadas" class="banco-tab <?= $tab === 'privadas' ? 'active' : '' ?>">🔒 Mis preguntas (<?= count($privadas) ?>)</a>
    </div>
    <?php if (empty($banco)): ?>
        <p class="vacio">No tienes preguntas en tu banco. ¡Crea la primera o espera a que se carguen las recomendadas!</p>
    <?php else: ?>
    <?php
    $agrupado = [];
    foreach ($banco as $bp) {
        $agrupado[$bp['materia']][] = $bp;
    }
    foreach ($agrupado as $mat => $preguntas):
        $icono = iconoMateria($mat);
        $total = count($preguntas);
    ?>
    <div class="materia-seccion colapsado">
        <div class="materia-header" onclick="this.parentElement.classList.toggle('colapsado')">
            <span class="materia-icono"><?= $icono ?></span>
            <span class="materia-nombre"><?= sanitizar($mat) ?></span>
            <span class="materia-contador"><?= $total ?> pregunta<?= $total !== 1 ? 's' : '' ?></span>
            <span class="materia-flecha">▼</span>
        </div>
        <div class="materia-body">
        <?php foreach ($preguntas as $bp):
            $ops = json_decode($bp['opciones_json'], true) ?? [];
            $esSemilla = esPreguntaSemilla($bp['texto']);
        ?>
            <div class="pregunta-mini <?= $esSemilla ? 'pregunta-semilla' : '' ?>">
                <div class="pregunta-mini-header">
                    <span>
                        <span class="tag tag-<?= $bp['tipo'] ?>"><?= $bp['tipo'] === 'multiple' ? 'Multiple' : ($bp['tipo'] === 'verdadero_falso' ? 'V/F' : 'Completar') ?></span>
                        <strong><?= $bp['puntaje'] ?> pts</strong>
                        <?php if ($esSemilla): ?><span class="tag tag-semilla">Recomendada</span><?php endif; ?>
                    </span>
                    <?php if (!$esSemilla): ?>
                    <a href="?eliminar=<?= $bp['id'] ?>" class="btn-sm btn-rechazar" onclick="return confirm('¿Eliminar esta pregunta?')" title="Eliminar">🗑</a>
                    <?php else: ?>
                    <span class="btn-sm" style="opacity:.3;cursor:not-allowed" title="Las preguntas recomendadas no se pueden eliminar">🔒</span>
                    <?php endif; ?>
                </div>
                <p><?= sanitizar($bp['texto']) ?></p>
                <?php if ($ops): ?>
                    <small style="color:var(--texto-secundario);"><?= implode(' | ', array_map('sanitizar', $ops)) ?></small>
                <?php endif; ?>
                <div style="margin-top:4px;">
                    <small style="color:var(--exito);">✅ <?= sanitizar($bp['respuesta_correcta']) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.banco-tabs {
    display: flex; gap: 0; margin-bottom: 16px;
    border-bottom: 2px solid var(--borde);
}
.banco-tab {
    padding: 10px 20px; text-decoration: none; font-weight: 600; font-size: .88rem;
    color: var(--texto-secundario); border-bottom: 2px solid transparent;
    margin-bottom: -2px; transition: all .2s;
}
.banco-tab:hover { color: var(--texto); }
.banco-tab.active {
    color: var(--primario-claro); border-bottom-color: var(--primario-claro);
}
.materia-seccion { margin-bottom: 12px; border: 1px solid var(--borde); border-radius: 12px; overflow: hidden; }
.materia-header {
    display: flex; align-items: center; gap: 12px; padding: 14px 18px;
    background: rgba(99,102,241,.06); cursor: pointer; user-select: none;
    transition: background .2s;
}
.materia-header:hover { background: rgba(99,102,241,.1); }
.materia-icono { font-size: 1.3rem; }
.materia-nombre { font-weight: 700; color: var(--texto); flex: 1; font-size: .95rem; }
.materia-contador { font-size: .8rem; color: var(--texto-secundario); background: rgba(99,102,241,.1); padding: 3px 10px; border-radius: 20px; }
.materia-flecha { font-size: .7rem; color: var(--texto-secundario); transition: transform .3s; }
.materia-seccion.colapsado .materia-body { display: none; }
.materia-seccion.colapsado .materia-flecha { transform: rotate(-90deg); }
.materia-body { padding: 8px 12px 12px; }
.pregunta-semilla { border-left: 3px solid rgba(99,102,241,.3); }
.tag-semilla { background: rgba(99,102,241,.15); color: #818cf8; padding: 2px 8px; border-radius: 10px; font-size: .7rem; font-weight: 600; }
.tag { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .7rem; font-weight: 600; margin-right: 6px; }
.tag-multiple { background: rgba(6,182,212,.15); color: #06b6d4; }
.tag-verdadero_falso { background: rgba(16,185,129,.15); color: #10b981; }
.tag-completar { background: rgba(245,158,11,.15); color: #f59e0b; }
</style>

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
