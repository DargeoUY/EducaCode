<?php
require_once __DIR__ . '/../config.php';
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$intento_id = $data['intento_id'] ?? 0;
$tipo = $data['tipo_evento'] ?? 'other';
if ($intento_id) {
    $pdo->prepare("INSERT INTO tab_salidas (intento_id, tipo_evento) VALUES (:i, :t)")->execute([':i' => $intento_id, ':t' => $tipo]);
    $pdo->prepare("UPDATE evaluacion_intentos SET tab_salidas = tab_salidas + 1 WHERE id = :i")->execute([':i' => $intento_id]);
}
jsonRespuesta(true);
