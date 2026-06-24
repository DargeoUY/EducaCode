<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$uid = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $accion = $data['accion'] ?? 'guardar';
    $nombre = trim($data['nombre'] ?? 'Sin título');
    $lenguaje = $data['lenguaje'] ?? 'python';
    $contenido = $data['contenido'] ?? '';
    $id = $data['id'] ?? 0;

    if ($accion === 'guardar') {
        if ($id > 0) {
            $pdo->prepare("UPDATE proyectos SET nombre = :n, lenguaje = :l, contenido = :c WHERE id = :id AND usuario_id = :u")
                ->execute([':n' => $nombre, ':l' => $lenguaje, ':c' => $contenido, ':id' => $id, ':u' => $uid]);
            jsonRespuesta(true, ['id' => $id, 'mensaje' => 'Proyecto actualizado']);
        } else {
            $pdo->prepare("INSERT INTO proyectos (usuario_id, nombre, lenguaje, contenido) VALUES (:u, :n, :l, :c)")
                ->execute([':u' => $uid, ':n' => $nombre, ':l' => $lenguaje, ':c' => $contenido]);
            jsonRespuesta(true, ['id' => $pdo->lastInsertId(), 'mensaje' => 'Proyecto guardado']);
        }
    } elseif ($accion === 'eliminar' && $id > 0) {
        $pdo->prepare("DELETE FROM proyectos WHERE id = :id AND usuario_id = :u")->execute([':id' => $id, ':u' => $uid]);
        jsonRespuesta(true, ['mensaje' => 'Eliminado']);
    }
}

$lenguaje = $_GET['lenguaje'] ?? '';
$sql = "SELECT id, nombre, lenguaje, creado_en FROM proyectos WHERE usuario_id = :u";
$params = [':u' => $uid];
if ($lenguaje) { $sql .= " AND lenguaje = :l"; $params[':l'] = $lenguaje; }
$sql .= " ORDER BY creado_en DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$proyectos = $stmt->fetchAll();

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM proyectos WHERE id = :id AND usuario_id = :u");
    $stmt->execute([':id' => $_GET['id'], ':u' => $uid]);
    $p = $stmt->fetch();
    jsonRespuesta(true, $p ?: []);
    exit;
}

if (isset($_GET['docente']) && ($_SESSION['usuario_rol'] === 'docente' || $_SESSION['usuario_rol'] === 'admin')) {
    $gid = $_GET['grupo_id'] ?? 0;
    $pid = $_GET['proyecto_id'] ?? 0;
    if ($pid > 0) {
        $stmt = $pdo->prepare("SELECT p.*, u.username, u.nombre AS usuario_nombre FROM proyectos p JOIN usuarios u ON p.usuario_id = u.id WHERE p.id = :id");
        $stmt->execute([':id' => $pid]);
        jsonRespuesta(true, $stmt->fetch() ?: []);
        exit;
    }
    $sql = "SELECT p.id, p.nombre, p.lenguaje, p.creado_en, u.username, u.nombre AS usuario_nombre FROM proyectos p JOIN usuarios u ON p.usuario_id = u.id";
    $params = [];
    if ($gid) {
        $sql .= " JOIN grupo_miembros gm ON p.usuario_id = gm.usuario_id WHERE gm.grupo_id = :g";
        $params[':g'] = $gid;
    }
    $sql .= " ORDER BY p.creado_en DESC LIMIT 100";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    jsonRespuesta(true, $stmt->fetchAll());
    exit;
}

jsonRespuesta(true, $proyectos);
