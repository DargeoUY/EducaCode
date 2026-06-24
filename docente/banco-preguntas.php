<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$mensaje = '';

sembrarBancoDocente($pdo, $did);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'agregar') {
        $texto = trim($_POST['texto'] ?? ''); $tipo = $_POST['tipo'] ?? 'multiple'; $materia = trim($_POST['materia'] ?? 'General');
        $opciones = $_POST['opciones'] ?? '[]'; $correcta = trim($_POST['correcta'] ?? ''); $puntaje = (int)($_POST['puntaje'] ?? 10);
        if ($texto && $correcta) { $pdo->prepare("INSERT INTO banco_preguntas (docente_id, materia, texto, tipo, opciones_json, respuesta_correcta, puntaje) VALUES (:d,:m,:t,:tp,:o,:r,:p)")->execute([':d' => $did, ':m' => $materia, ':t' => $texto, ':tp' => $tipo, ':o' => $opciones, ':r' => $correcta, ':p' => $puntaje]); $mensaje = ['exito', 'Pregunta agregada.']; }
    } elseif ($_POST['accion'] === 'eliminar') {
        $pid = $_POST['id'] ?? 0; $pdo->prepare("DELETE FROM banco_preguntas WHERE id = :id AND docente_id = :d")->execute([':id' => $pid, ':d' => $did]);
    }
}

$materia = $_GET['materia'] ?? '';
$sql = "SELECT * FROM banco_preguntas WHERE docente_id = :d"; $params = [':d' => $did];
if ($materia) { $sql .= " AND materia = :m"; $params[':m'] = $materia; }
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $preguntas = $stmt->fetchAll();

$materias = $pdo->prepare("SELECT DISTINCT materia FROM banco_preguntas WHERE docente_id = :d"); $materias->execute([':d' => $did]);

$titulo = 'Banco de Preguntas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header"><h1>🗂 Banco de Preguntas</h1></div>
<?php if ($mensaje): ?><div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div><?php endif; ?>

<div class="card"><h2>➕ Nueva pregunta</h2>
<form method="POST"><input type="hidden" name="accion" value="agregar">
    <div class="grupo-input"><label>Texto</label><input type="text" name="texto" class="input-dato" required></div>
    <div style="display:flex;gap:10px"><div class="grupo-input" style="flex:1"><label>Materia</label><input type="text" name="materia" class="input-dato" value="General"></div><div class="grupo-input" style="flex:1"><label>Tipo</label><select name="tipo" class="input-dato"><option value="multiple">Multiple</option><option value="completar">Completar</option><option value="verdadero_falso">V/F</option></select></div><div class="grupo-input" style="flex:1"><label>Puntaje</label><input type="number" name="puntaje" class="input-dato" value="10"></div></div>
    <div class="grupo-input"><label>Opciones (JSON)</label><input type="text" name="opciones" class="input-dato" value='["A)","B)","C)","D)"]'></div>
    <div class="grupo-input"><label>Respuesta correcta</label><input type="text" name="correcta" class="input-dato" required></div>
    <button type="submit" class="btn-primario">Agregar</button>
</form></div>

<div class="card"><h2>Preguntas</h2>
<div style="margin-bottom:10px"><?php foreach($materias->fetchAll() as $m): ?><a href="?materia=<?= urlencode($m['materia']) ?>" class="btn-sm"><?= sanitizar($m['materia']) ?></a><?php endforeach; ?> <a href="?" class="btn-sm">Todas</a></div>
<?php if (empty($preguntas)): ?><p class="vacio">Sin preguntas.</p><?php else: ?>
<?php foreach($preguntas as $p): ?><div style="padding:8px 0;border-bottom:1px solid var(--superficie-claro)"><?= sanitizar($p['texto']) ?> <span class="tag"><?= $p['materia'] ?></span> <span class="tag"><?= $p['tipo'] ?></span>
<form method="POST" style="display:inline"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn-sm btn-peligro">Eliminar</button></form></div>
<?php endforeach; ?><?php endif; ?></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
