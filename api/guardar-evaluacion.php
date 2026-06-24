<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$evaluacion_id = $data['evaluacion_id'] ?? 0;
$usuario_id = $data['usuario_id'] ?? ($_SESSION['usuario_id'] ?? 0);
$respuestas = $data['respuestas'] ?? [];
$tiempo = $data['tiempo'] ?? 0;
if (!$evaluacion_id || !$usuario_id) jsonRespuesta(false, ['error' => 'Faltan datos.']);

$evalStmt = $pdo->prepare("SELECT * FROM evaluaciones WHERE id = :id"); $evalStmt->execute([':id' => $evaluacion_id]); $eval = $evalStmt->fetch();
if (!$eval) jsonRespuesta(false, ['error' => 'Evaluación no encontrada.']);

$intentos = contarIntentos($pdo, $evaluacion_id, $usuario_id);
if ($intentos >= $eval['intentos_max']) jsonRespuesta(false, ['error' => 'Ya agotaste los intentos.']);

$preguntas = json_decode($eval['preguntas_json'], true) ?: [];
$puntaje = 0; $maxPuntaje = count($preguntas) * 10;
foreach ($preguntas as $i => $p) {
    $resp = $respuestas[$i] ?? '';
    if (strcasecmp(trim($resp), trim($p['correcta'])) === 0) $puntaje += 10;
}
$nota = $maxPuntaje > 0 ? round(($puntaje / $maxPuntaje) * 10, 1) : 0;

$pdo->prepare("INSERT INTO evaluacion_intentos (evaluacion_id, usuario_id, intento_num, respuestas_json, puntaje, fecha_inicio, fecha_fin, finalizada) VALUES (:e,:u,:n,:r,:p,NOW() - INTERVAL :t SECOND, NOW(), 1)")
    ->execute([':e' => $evaluacion_id, ':u' => $usuario_id, ':n' => $intentos + 1, ':r' => json_encode($respuestas, JSON_UNESCAPED_UNICODE), ':p' => $nota, ':t' => $tiempo]);

jsonRespuesta(true, ['puntaje' => $puntaje, 'nota' => $nota, 'max_puntaje' => $maxPuntaje]);
