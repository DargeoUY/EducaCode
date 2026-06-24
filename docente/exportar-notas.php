<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_docente();

$usuario = usuario_actual($pdo);
$did = $usuario['id'];
$gid = $_GET['grupo_id'] ?? null;

$sql = "SELECT ei.puntaje, ei.fecha_fin, u.username, u.nombre, g.nombre AS grupo, e.titulo AS eval_titulo FROM evaluacion_intentos ei JOIN usuarios u ON ei.usuario_id = u.id JOIN evaluaciones e ON ei.evaluacion_id = e.id JOIN grupos g ON e.grupo_id = g.id WHERE g.docente_id = :d AND ei.finalizada = 1";
$params = [':d' => $did];
if ($gid) { $sql .= " AND g.id = :g"; $params[':g'] = $gid; }
$sql .= " ORDER BY ei.fecha_fin DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=notas.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['Usuario', 'Nombre', 'Grupo', 'Evaluación', 'Puntaje', 'Fecha']);
foreach ($rows as $r) {
    fputcsv($out, [$r['username'], $r['nombre'], $r['grupo'], $r['eval_titulo'], $r['puntaje'], $r['fecha_fin']]);
}
fclose($out);
