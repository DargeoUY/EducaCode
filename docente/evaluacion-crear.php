<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$gid = $_GET['grupo_id'] ?? 0;
$error = '';

$stmt = $pdo->prepare("SELECT * FROM grupos WHERE id = :id AND docente_id = :d");
$stmt->execute([':id' => $gid, ':d' => $did]); $grupo = $stmt->fetch();
if (!$grupo) { echo '<p>Grupo no encontrado.</p>'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $puntaje = (int)($_POST['puntaje_max'] ?? 100);
    $duracion = (int)($_POST['duracion_min'] ?? 30);
    $intentos = (int)($_POST['intentos_max'] ?? 1);
    $shuffle = isset($_POST['shuffle_preguntas']);
    $preguntas = [];

    for ($i = 0; $i < 50; $i++) {
        if (empty($_POST["pregunta_$i"])) continue;
        $preguntas[] = [
            'texto' => $_POST["pregunta_$i"],
            'tipo' => $_POST["tipo_$i"] ?? 'multiple',
            'opciones' => explode("\n", trim($_POST["opciones_$i"] ?? '')),
            'correcta' => $_POST["correcta_$i"] ?? '',
        ];
    }

    if (!$titulo) $error = 'Ingresá un título.';
    elseif (empty($preguntas)) $error = 'Agregá al menos una pregunta.';
    else {
        $pdo->prepare("INSERT INTO evaluaciones (grupo_id, titulo, descripcion, preguntas_json, puntaje_max, duracion_min, intentos_max, shuffle_preguntas) VALUES (:g,:t,:d,:pj,:pm,:dm,:im,:s)")
            ->execute([':g' => $gid, ':t' => $titulo, ':d' => $descripcion, ':pj' => json_encode($preguntas, JSON_UNESCAPED_UNICODE), ':pm' => $puntaje, ':dm' => $duracion, ':im' => $intentos, ':s' => (int)$shuffle]);
        $nueva_eval_id = $pdo->lastInsertId();
        $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => 'Evaluación creada. ID: ' . $nueva_eval_id];
        $_SESSION['lti_eval_id'] = $nueva_eval_id;
        redirigir('docente/evaluacion-crear.php?grupo_id=' . $gid . '&creada=' . $nueva_eval_id);
    }
}

$titulo = 'Crear Evaluación';
$eval_creada_id = $_GET['creada'] ?? (($_SESSION['lti_eval_id'] ?? 0));
if ($eval_creada_id > 0 && isset($_SESSION['lti_eval_id'])) unset($_SESSION['lti_eval_id']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>📝 Crear Evaluación — <?= sanitizar($grupo['nombre']) ?></h1></div>
<?php if ($error): ?><div class="flash flash-error"><?= sanitizar($error) ?></div><?php endif; ?>

<?php if ($eval_creada_id > 0): ?>
<div class="card" style="border-color:var(--ok);border-width:2px">
    <h2>✅ Evaluación creada (ID: <?= $eval_creada_id ?>)</h2>
    <h3 style="margin-top:12px">🔗 Configuración LTI para CREA</h3>
    <p style="font-size:.85rem;color:var(--text-sec);margin-bottom:8px">Copiá estos datos en la configuración de la herramienta externa en CREA:</p>
    <div class="grupo-input"><label>URL de lanzamiento</label><input type="text" class="input-dato" readonly value="<?= BASE_URL ?>lti/launch" onclick="this.select()" style="font-family:monospace"></div>
    <div class="grupo-input"><label>Consumer Key</label><input type="text" class="input-dato" readonly value="educacode-crea-key" onclick="this.select()" style="font-family:monospace"></div>
    <div class="grupo-input"><label>Consumer Secret</label><input type="text" class="input-dato" readonly value="educacode-crea-secret-2026" onclick="this.select()" style="font-family:monospace"></div>
    <div class="grupo-input"><label>Parámetro personalizado</label><input type="text" class="input-dato" readonly value="custom_evaluacion_id=<?= $eval_creada_id ?>" onclick="this.select()" style="font-family:monospace"></div>
    <p style="font-size:.78rem;color:var(--text-sec);margin-top:8px">En CREA: Materiales → Agregar Material → Herramienta externa → Pegar URL de lanzamiento + Key + Secret. En "Parámetros personalizados" agregar el custom_evaluacion_id.</p>
</div>
<?php endif; ?>

<form method="POST" id="form-eval">
<div class="card"><h2>Configuración</h2>
    <div class="grupo-input"><label>Título</label><input type="text" name="titulo" class="input-dato" required></div>
    <div class="grupo-input"><label>Descripción</label><textarea name="descripcion" class="input-dato"></textarea></div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div class="grupo-input" style="flex:1"><label>Puntaje máximo</label><input type="number" name="puntaje_max" class="input-dato" value="100"></div>
        <div class="grupo-input" style="flex:1"><label>Duración (min)</label><input type="number" name="duracion_min" class="input-dato" value="30"></div>
        <div class="grupo-input" style="flex:1"><label>Intentos máx.</label><input type="number" name="intentos_max" class="input-dato" value="1"></div>
    </div>
    <label style="margin-top:8px"><input type="checkbox" name="shuffle_preguntas"> Mezclar preguntas</label>
</div>

<div class="card"><h2>Preguntas</h2><div id="preguntas-container"></div>
    <button type="button" class="btn-sm" onclick="agregarPregunta()" style="margin-top:10px">+ Agregar pregunta</button>
</div>

<button type="submit" class="btn-primario btn-full">Guardar Evaluación</button>
</form>

<script>
let pIdx=0;
function agregarPregunta(){
    let h=`<div class="pregunta-block" style="border:1px solid var(--superficie-claro);border-radius:8px;padding:12px;margin-bottom:10px">
        <div class="grupo-input"><label>Pregunta ${pIdx+1}</label><input type="text" name="pregunta_${pIdx}" class="input-dato" required></div>
        <div class="grupo-input"><label>Tipo</label><select name="tipo_${pIdx}" class="input-dato"><option value="multiple">Multiple choice</option><option value="completar">Completar</option><option value="verdadero_falso">Verdadero/Falso</option></select></div>
        <div class="grupo-input"><label>Opciones (una por línea)</label><textarea name="opciones_${pIdx}" class="input-dato" rows="3"></textarea></div>
        <div class="grupo-input"><label>Respuesta correcta</label><input type="text" name="correcta_${pIdx}" class="input-dato"></div>
        <button type="button" class="btn-sm btn-peligro" onclick="this.parentElement.remove()">Quitar</button>
    </div>`;
    document.getElementById('preguntas-container').insertAdjacentHTML('beforeend',h);
    pIdx++;
}
agregarPregunta();
agregarPregunta();
agregarPregunta();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
