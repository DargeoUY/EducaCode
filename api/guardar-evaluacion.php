<?php
/**
 * api/guardar-evaluacion.php — Guardar respuestas y calcular puntaje (server-side)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonRespuesta(false, ['error' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonRespuesta(false, ['error' => 'Datos inválidos'], 400);
}

$intentoId = (int)($input['intento_id'] ?? 0);
$evalId = (int)($input['evaluacion_id'] ?? 0);
$respuestasAlumno = $input['respuestas'] ?? [];
$tabSalidas = (int)($input['tab_salidas'] ?? 0);

$stmt = $pdo->prepare("SELECT ei.*, e.preguntas_json FROM evaluacion_intentos ei JOIN evaluaciones e ON ei.evaluacion_id = e.id WHERE ei.id = :id AND ei.finalizada = 0 LIMIT 1");
$stmt->execute([':id' => $intentoId]);
$intento = $stmt->fetch();

if (!$intento) {
    jsonRespuesta(false, ['error' => 'Intento no encontrado o ya finalizado.'], 404);
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] != $intento['usuario_id']) {
    jsonRespuesta(false, ['error' => 'No autorizado.'], 403);
}

// Las preguntas ordenadas (con respuestas) se guardaron en respuestas_json al crear el intento
$preguntasServidor = json_decode($intento['respuestas_json'], true) ?? [];

// Si no estan ahi (compatibilidad), usar las originales de la evaluacion
if (empty($preguntasServidor)) {
    $preguntasServidor = json_decode($intento['preguntas_json'], true) ?? [];
    foreach ($preguntasServidor as $i => &$p) { $p['_idx'] = $i; }
    unset($p);
}

// Calcular puntaje usando solo datos del servidor
$puntaje = 0;
$detalleRespuestas = [];

foreach ($preguntasServidor as $pregunta) {
    $idxOrig = $pregunta['_idx'];
    $respuestaAlumno = $respuestasAlumno[$idxOrig] ?? null;
    $tipo = $pregunta['tipo'] ?? 'multiple';
    $correcta = $pregunta['respuesta'] ?? '';
    $puntajePregunta = (int)($pregunta['puntaje'] ?? 10);
    $esCorrecta = false;

    if ($tipo === 'completar') {
        if ($respuestaAlumno !== null && $respuestaAlumno !== '') {
            $variantes = explode('|', strtolower(trim($correcta)));
            $respAlumnoLimpia = strtolower(trim($respuestaAlumno));
            foreach ($variantes as $v) {
                if (trim($v) === $respAlumnoLimpia) {
                    $puntaje += $puntajePregunta;
                    $esCorrecta = true;
                    break;
                }
            }
        }
    } else {
        if ($respuestaAlumno === $correcta) {
            $puntaje += $puntajePregunta;
            $esCorrecta = true;
        }
    }

    $detalleRespuestas[$idxOrig] = [
        'respuesta' => $respuestaAlumno,
        'correcta' => $esCorrecta,
        'puntaje' => $esCorrecta ? $puntajePregunta : 0
    ];
}

// Guardar respuestas del alumno (no las del servidor)
$pdo->prepare("UPDATE evaluacion_intentos SET respuestas_json = :rj, puntaje = :p, tab_salidas = :ts, fecha_fin = NOW(), finalizada = 1 WHERE id = :id")
    ->execute([
        ':rj' => json_encode($detalleRespuestas, JSON_UNESCAPED_UNICODE),
        ':p' => $puntaje,
        ':ts' => $tabSalidas,
        ':id' => $intentoId
    ]);

$evalOriginal = $pdo->prepare("SELECT puntaje_max FROM evaluaciones WHERE id = :id");
$evalOriginal->execute([':id' => $evalId]);
$puntajeMax = $evalOriginal->fetchColumn();

jsonRespuesta(true, [
    'puntaje' => $puntaje,
    'puntaje_max' => (int)$puntajeMax,
    'tab_salidas' => $tabSalidas,
    'intento_id' => $intentoId
]);
