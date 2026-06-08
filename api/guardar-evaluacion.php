<?php
/**
 * api/guardar-evaluacion.php — Guardar respuestas y calcular puntaje
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
$respuestas = $input['respuestas'] ?? [];
$preguntasData = $input['preguntas'] ?? [];
$tabSalidas = (int)($input['tab_salidas'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM evaluacion_intentos WHERE id = :id AND finalizada = 0 LIMIT 1");
$stmt->execute([':id' => $intentoId]);
$intento = $stmt->fetch();

if (!$intento) {
    jsonRespuesta(false, ['error' => 'Intento no encontrado o ya finalizado.'], 404);
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] != $intento['usuario_id']) {
    jsonRespuesta(false, ['error' => 'No autorizado.'], 403);
}

// Calcular puntaje
$puntaje = 0;
foreach ($preguntasData as $idx => $pregunta) {
    $respuestaAlumno = $respuestas[$idx] ?? null;
    $tipo = $pregunta['tipo'] ?? 'multiple';
    $correcta = $pregunta['respuesta'] ?? '';
    $puntajePregunta = (int)($pregunta['puntaje'] ?? 10);

    if ($tipo === 'completar') {
        if ($respuestaAlumno) {
            $variantes = explode('|', strtolower(trim($correcta)));
            $respAlumnoLimpia = strtolower(trim($respuestaAlumno));
            foreach ($variantes as $v) {
                if (trim($v) === $respAlumnoLimpia) {
                    $puntaje += $puntajePregunta;
                    break;
                }
            }
        }
    } else {
        if ($respuestaAlumno === $correcta) {
            $puntaje += $puntajePregunta;
        }
    }
}

// Guardar
$pdo->prepare("UPDATE evaluacion_intentos SET respuestas_json = :rj, puntaje = :p, tab_salidas = :ts, fecha_fin = NOW(), finalizada = 1 WHERE id = :id")
    ->execute([
        ':rj' => json_encode($respuestas, JSON_UNESCAPED_UNICODE),
        ':p' => $puntaje,
        ':ts' => $tabSalidas,
        ':id' => $intentoId
    ]);

jsonRespuesta(true, [
    'puntaje' => $puntaje,
    'puntaje_max' => array_sum(array_column($preguntasData, 'puntaje')),
    'tab_salidas' => $tabSalidas,
    'intento_id' => $intentoId
]);
