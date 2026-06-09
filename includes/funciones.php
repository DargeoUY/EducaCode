<?php
/**
 * funciones.php — Utilidades de la plataforma
 */

function generarCodigoInvitacion($longitud = LONGITUD_CODIGO) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $codigo = '';
    for ($i = 0; $i < $longitud; $i++) {
        $codigo .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $codigo;
}

function registrarSesion($pdo, $usuario_id, $accion) {
    $stmt = $pdo->prepare("INSERT INTO sesiones_log (usuario_id, ip, user_agent, accion) VALUES (:uid, :ip, :ua, :accion)");
    $stmt->execute([
        ':uid' => $usuario_id,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 0, 300),
        ':accion' => $accion
    ]);
}

function redirigir($url) {
    header('Location: ' . BASE_URL . $url);
    exit;
}

function estaBloqueado($usuario) {
    if (!$usuario['bloqueado']) return false;
    if ($usuario['bloqueo_hasta'] && strtotime($usuario['bloqueo_hasta']) < time()) {
        return false;
    }
    return true;
}

function sanitizar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

function jsonRespuesta($ok, $datos = [], $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    $respuesta = ['ok' => $ok];
    if ($ok) {
        $respuesta = array_merge($respuesta, $datos);
    } else {
        $respuesta['error'] = $datos['error'] ?? 'Error desconocido';
    }
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

function obtenerUsuario($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function obtenerGrupo($pdo, $id) {
    $stmt = $pdo->prepare("SELECT g.*, u.nombre AS docente_nombre FROM grupos g JOIN usuarios u ON g.docente_id = u.id WHERE g.id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function esMiembro($pdo, $grupo_id, $usuario_id) {
    $stmt = $pdo->prepare("SELECT * FROM grupo_miembros WHERE grupo_id = :gid AND usuario_id = :uid AND bloqueado = 0 LIMIT 1");
    $stmt->execute([':gid' => $grupo_id, ':uid' => $usuario_id]);
    return $stmt->fetch();
}

function contarIntentos($pdo, $evaluacion_id, $usuario_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM evaluacion_intentos WHERE evaluacion_id = :eid AND usuario_id = :uid AND finalizada = 1");
    $stmt->execute([':eid' => $evaluacion_id, ':uid' => $usuario_id]);
    return (int)$stmt->fetch()['total'];
}

function obtenerNotificaciones($pdo, $usuario_id) {
    $stmt = $pdo->prepare(
        "SELECT n.*, g.nombre AS grupo_nombre FROM notificaciones n
         JOIN grupos g ON n.grupo_id = g.id
         JOIN grupo_miembros gm ON gm.grupo_id = n.grupo_id
         WHERE gm.usuario_id = :uid AND gm.bloqueado = 0
         ORDER BY n.creado_en DESC LIMIT 20"
    );
    $stmt->execute([':uid' => $usuario_id]);
    return $stmt->fetchAll();
}

function notificacionesNoLeidas($pdo, $usuario_id) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(n.id) AS total FROM notificaciones n
         JOIN grupos g ON n.grupo_id = g.id
         JOIN grupo_miembros gm ON gm.grupo_id = n.grupo_id
         WHERE gm.usuario_id = :uid AND gm.bloqueado = 0
         AND n.creado_en > COALESCE((SELECT ultimo_login FROM usuarios WHERE id = :uid2), '2000-01-01')"
    );
    $stmt->execute([':uid' => $usuario_id, ':uid2' => $usuario_id]);
    return (int)$stmt->fetch()['total'];
}

function esDocenteDelGrupo($pdo, $grupo_id, $docente_id) {
    $stmt = $pdo->prepare("SELECT id FROM grupos WHERE id = :gid AND docente_id = :did LIMIT 1");
    $stmt->execute([':gid' => $grupo_id, ':did' => $docente_id]);
    return (bool)$stmt->fetch();
}

function formatearFecha($fecha, $formato = 'd/m/Y H:i') {
    if (!$fecha) return '—';
    $dt = new DateTime($fecha);
    return $dt->format($formato);
}

function formatearTiempo($minutos) {
    if ($minutos >= 60) {
        $h = floor($minutos / 60);
        $m = $minutos % 60;
        return $h . 'h ' . ($m > 0 ? $m . 'min' : '');
    }
    return $minutos . ' min';
}

function sembrarBancoDocente($pdo, $docente_id) {
    $existe = $pdo->prepare("SELECT COUNT(*) FROM banco_preguntas WHERE docente_id = :d");
    $existe->execute([':d' => $docente_id]);
    if ($existe->fetchColumn() > 0) return;

    $preguntas = [
        ['Pseudocódigo', '¿Qué estructura se usa para repetir un bloque de código mientras una condición sea verdadera?', 'multiple', json_encode(['While','For','If','Switch']), 'While', 10],
        ['Pseudocódigo', '¿Qué símbolo se usa para asignar un valor a una variable?', 'multiple', json_encode(['=','←',':=','==']), '←', 10],
        ['Pseudocódigo', '¿Qué palabra clave se usa para declarar una función?', 'multiple', json_encode(['Funcion','Def','Function','Sub']), 'Funcion', 10],
        ['Pseudocódigo', '¿Cuál es el valor de verdad de: (5 > 3) Y (2 < 1)?', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Falso', 5],
        ['Pseudocódigo', 'Para leer un dato ingresado por el usuario se usa la instrucción...', 'completar', json_encode([]), 'Leer|leer|LEER', 10],
        ['Diseño Web', '¿Qué etiqueta HTML se usa para el encabezado principal?', 'multiple', json_encode(['h1','title','head','header']), 'h1', 10],
        ['Diseño Web', '¿Qué propiedad CSS cambia el color de fondo?', 'multiple', json_encode(['color','background-color','bgcolor','font-color']), 'background-color', 10],
        ['Diseño Web', '¿Qué etiqueta crea un enlace en HTML?', 'multiple', json_encode(['link','a','href','url']), 'a', 10],
        ['Diseño Web', 'CSS significa "Cascading Style Sheets"', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Verdadero', 5],
        ['Diseño Web', 'La etiqueta para insertar una imagen en HTML es...', 'completar', json_encode([]), 'img|IMG|Img', 10],
        ['Python', '¿Qué función se usa para mostrar texto en pantalla?', 'multiple', json_encode(['print','echo','console.log','display']), 'print', 10],
        ['Python', '¿Qué tipo de dato es True en Python?', 'multiple', json_encode(['string','int','bool','float']), 'bool', 10],
        ['Python', '¿Qué palabra clave define una función en Python?', 'multiple', json_encode(['def','function','func','define']), 'def', 10],
        ['Python', 'Python es un lenguaje compilado', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Falso', 5],
        ['Python', 'La función para obtener la longitud de una lista es...', 'completar', json_encode([]), 'len|len()|LEN', 10],
    ];

    $stmt = $pdo->prepare("INSERT INTO banco_preguntas (docente_id, materia, texto, tipo, opciones_json, respuesta_correcta, puntaje) VALUES (:d, :m, :t, :tp, :oj, :r, :p)");
    foreach ($preguntas as $p) {
        $stmt->execute([':d' => $docente_id, ':m' => $p[0], ':t' => $p[1], ':tp' => $p[2], ':oj' => $p[3], ':r' => $p[4], ':p' => $p[5]]);
    }
}
