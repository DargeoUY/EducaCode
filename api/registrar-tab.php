<?php
/**
 * api/registrar-tab.php — Registrar salida de pestaña
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonRespuesta(false, ['error' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$intentoId = (int)($input['intento_id'] ?? 0);
$tipo = $input['tipo_evento'] ?? 'other';

$tiposValidos = ['visibility', 'blur', 'fullscreen_exit', 'devtools', 'copy_paste', 'other'];
if (!in_array($tipo, $tiposValidos)) {
    $tipo = 'other';
}

$pdo->prepare("INSERT INTO tab_salidas (intento_id, tipo_evento) VALUES (:iid, :tipo)")
    ->execute([':iid' => $intentoId, ':tipo' => $tipo]);

$total = $pdo->prepare("SELECT COUNT(*) FROM tab_salidas WHERE intento_id = :iid");
$total->execute([':iid' => $intentoId]);
$count = (int)$total->fetchColumn();

$pdo->prepare("UPDATE evaluacion_intentos SET tab_salidas = :ts WHERE id = :id")
    ->execute([':ts' => $count, ':id' => $intentoId]);

jsonRespuesta(true, ['total' => $count, 'tipo' => $tipo]);
