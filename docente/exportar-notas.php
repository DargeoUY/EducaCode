<?php
/**
 * docente/exportar-notas.php — Exportar resultados a CSV
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];

$eid = (int)($_GET['eval_id'] ?? 0);
$eval = $pdo->prepare("SELECT e.*, g.docente_id FROM evaluaciones e JOIN grupos g ON e.grupo_id = g.id WHERE e.id = :id LIMIT 1");
$eval->execute([':id' => $eid]);
$eval = $eval->fetch();
if (!$eval || ($eval['docente_id'] != $uid && $usuario['rol'] !== 'admin')) {
    die('No autorizado.');
}

$resultados = $pdo->prepare(
    "SELECT ei.*, u.nombre, u.username FROM evaluacion_intentos ei
     JOIN usuarios u ON ei.usuario_id = u.id
     WHERE ei.evaluacion_id = :eid AND ei.finalizada = 1
     ORDER BY ei.puntaje DESC"
);
$resultados->execute([':eid' => $eid]);
$resultados = $resultados->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="notas_' . preg_replace('/[^a-zA-Z0-9]/', '_', $eval['titulo']) . '.csv"');
header('Pragma: no-cache');

$salida = fopen('php://output', 'w');
fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

fputcsv($salida, ['Evaluación', $eval['titulo']]);
fputcsv($salida, ['Puntaje Máximo', $eval['puntaje_max']]);
fputcsv($salida, ['']);
fputcsv($salida, ['#', 'Estudiante', 'Usuario', 'Puntaje', 'Intentos', 'Salidas de pestaña', 'Fecha inicio', 'Fecha fin']);

$pos = 0;
foreach ($resultados as $r) {
    $pos++;
    $diff = '';
    if ($r['fecha_fin'] && $r['fecha_inicio']) {
        $d = strtotime($r['fecha_fin']) - strtotime($r['fecha_inicio']);
        $diff = floor($d / 60) . 'min';
    }
    fputcsv($salida, [
        $pos,
        $r['nombre'],
        $r['username'],
        $r['puntaje'],
        $r['intento_num'] . '/' . $eval['intentos_max'],
        $r['tab_salidas'],
        $r['fecha_inicio'] ? date('d/m/Y H:i', strtotime($r['fecha_inicio'])) : '',
        $r['fecha_fin'] ? date('d/m/Y H:i', strtotime($r['fecha_fin'])) : '',
        $diff
    ]);
}

fclose($salida);
exit;
