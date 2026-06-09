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

function sembrarBancoDocente($pdo) {
    $existe = $pdo->query("SELECT COUNT(*) FROM banco_preguntas WHERE docente_id = 0");
    if ($existe->fetchColumn() > 0) return;

    $preguntas = [
        ['Pseudocódigo', '¿Qué estructura se usa para repetir un bloque de código mientras una condición sea verdadera?', 'multiple', json_encode(['While','For','If','Switch']), 'While', 10],
        ['Pseudocódigo', '¿Qué símbolo se usa para asignar un valor a una variable?', 'multiple', json_encode(['=','←',':=','==']), '←', 10],
        ['Pseudocódigo', '¿Qué palabra clave se usa para declarar una función?', 'multiple', json_encode(['Funcion','Def','Function','Sub']), 'Funcion', 10],
        ['Pseudocódigo', '¿Cuál es el valor de verdad de: (5 > 3) Y (2 < 1)?', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Falso', 5],
        ['Pseudocódigo', 'Para leer un dato ingresado por el usuario se usa la instrucción...', 'completar', json_encode([]), 'Leer|leer|LEER', 10],
        ['Pseudocódigo', '¿Qué estructura se usa para evaluar múltiples condiciones excluyentes?', 'multiple', json_encode(['Si-Sino','Segun','Mientras','Para']), 'Segun', 10],
        ['Diseño Web', '¿Qué etiqueta HTML se usa para el encabezado principal?', 'multiple', json_encode(['h1','title','head','header']), 'h1', 10],
        ['Diseño Web', '¿Qué propiedad CSS cambia el color de fondo?', 'multiple', json_encode(['color','background-color','bgcolor','font-color']), 'background-color', 10],
        ['Diseño Web', '¿Qué etiqueta crea un enlace en HTML?', 'multiple', json_encode(['link','a','href','url']), 'a', 10],
        ['Diseño Web', 'CSS significa "Cascading Style Sheets"', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Verdadero', 5],
        ['Diseño Web', 'La etiqueta para insertar una imagen en HTML es...', 'completar', json_encode([]), 'img|IMG|Img', 10],
        ['HTML', '¿Qué etiqueta define un párrafo?', 'multiple', json_encode(['p','paragraph','text','div']), 'p', 10],
        ['HTML', '¿Cuál es la estructura básica de un documento HTML5?', 'multiple', json_encode(['html>head>body','header>main>footer','div>span>p','head>title>body']), 'html>head>body', 10],
        ['HTML', '¿Qué atributo se usa para abrir un enlace en nueva pestaña?', 'multiple', json_encode(['target="_blank"','href="_new"','open="tab"','rel="new"']), 'target="_blank"', 10],
        ['HTML', 'La etiqueta <br> sirve para crear un salto de línea', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Verdadero', 5],
        ['HTML', '¿Qué etiqueta se usa para crear una lista ordenada?', 'completar', json_encode([]), 'ol|OL', 10],
        ['CSS', '¿Qué propiedad se usa para cambiar el tamaño de fuente?', 'multiple', json_encode(['font-size','text-size','size','font-weight']), 'font-size', 10],
        ['CSS', '¿Qué valor de display convierte un elemento en bloque flexible?', 'multiple', json_encode(['flex','block','inline','grid']), 'flex', 10],
        ['CSS', '¿Qué propiedad controla el espacio interior de un elemento?', 'multiple', json_encode(['margin','padding','spacing','gap']), 'padding', 10],
        ['CSS', 'El selector de clase se escribe con un punto (.) antes del nombre', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Verdadero', 5],
        ['CSS', '¿Qué propiedad se usa para redondear bordes?', 'completar', json_encode([]), 'border-radius|border radius', 10],
        ['JavaScript', '¿Qué función muestra un mensaje en una ventana emergente?', 'multiple', json_encode(['alert()','console.log()','prompt()','confirm()']), 'alert()', 10],
        ['JavaScript', '¿Qué palabra clave se usa para declarar una variable?', 'multiple', json_encode(['let','var','const','Todas son correctas']), 'Todas son correctas', 10],
        ['JavaScript', '¿Qué método agrega un elemento al final de un array?', 'multiple', json_encode(['push()','pop()','shift()','append()']), 'push()', 10],
        ['JavaScript', 'document.getElementById() selecciona un elemento por su clase', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Falso', 5],
        ['Python', '¿Qué función se usa para mostrar texto en pantalla?', 'multiple', json_encode(['print','echo','console.log','display']), 'print', 10],
        ['Python', '¿Qué tipo de dato es True en Python?', 'multiple', json_encode(['string','int','bool','float']), 'bool', 10],
        ['Python', '¿Qué palabra clave define una función en Python?', 'multiple', json_encode(['def','function','func','define']), 'def', 10],
        ['Python', 'Python es un lenguaje compilado', 'verdadero_falso', json_encode(['Verdadero','Falso']), 'Falso', 5],
        ['Python', 'La función para obtener la longitud de una lista es...', 'completar', json_encode([]), 'len|len()|LEN', 10],
        ['Python', '¿Qué símbolo se usa para comentarios de una línea en Python?', 'completar', json_encode([]), '#|numeral|almohadilla', 10],
    ];

    $stmt = $pdo->prepare("INSERT INTO banco_preguntas (docente_id, materia, texto, tipo, opciones_json, respuesta_correcta, puntaje) VALUES (0, :m, :t, :tp, :oj, :r, :p)");
    foreach ($preguntas as $p) {
        $stmt->execute([':m' => $p[0], ':t' => $p[1], ':tp' => $p[2], ':oj' => $p[3], ':r' => $p[4], ':p' => $p[5]]);
    }
}

function esPreguntaSemilla($texto) {
    $semillas = [
        '¿Qué estructura se usa para repetir un bloque de código mientras una condición sea verdadera?',
        '¿Qué símbolo se usa para asignar un valor a una variable?',
        '¿Qué palabra clave se usa para declarar una función?',
        '¿Cuál es el valor de verdad de: (5 > 3) Y (2 < 1)?',
        'Para leer un dato ingresado por el usuario se usa la instrucción...',
        '¿Qué estructura se usa para evaluar múltiples condiciones excluyentes?',
        '¿Qué etiqueta HTML se usa para el encabezado principal?',
        '¿Qué propiedad CSS cambia el color de fondo?',
        '¿Qué etiqueta crea un enlace en HTML?',
        'CSS significa "Cascading Style Sheets"',
        'La etiqueta para insertar una imagen en HTML es...',
        '¿Qué etiqueta define un párrafo?',
        '¿Cuál es la estructura básica de un documento HTML5?',
        '¿Qué atributo se usa para abrir un enlace en nueva pestaña?',
        'La etiqueta <br> sirve para crear un salto de línea',
        '¿Qué etiqueta se usa para crear una lista ordenada?',
        '¿Qué propiedad se usa para cambiar el tamaño de fuente?',
        '¿Qué valor de display convierte un elemento en bloque flexible?',
        '¿Qué propiedad controla el espacio interior de un elemento?',
        'El selector de clase se escribe con un punto (.) antes del nombre',
        '¿Qué propiedad se usa para redondear bordes?',
        '¿Qué función muestra un mensaje en una ventana emergente?',
        '¿Qué palabra clave se usa para declarar una variable?',
        '¿Qué método agrega un elemento al final de un array?',
        'document.getElementById() selecciona un elemento por su clase',
        '¿Qué función se usa para mostrar texto en pantalla?',
        '¿Qué tipo de dato es True en Python?',
        '¿Qué palabra clave define una función en Python?',
        'Python es un lenguaje compilado',
        'La función para obtener la longitud de una lista es...',
        '¿Qué símbolo se usa para comentarios de una línea en Python?',
    ];
    return in_array($texto, $semillas);
}

function iconoMateria($materia) {
    $iconos = [
        'Pseudocódigo' => '📜',
        'Diseño Web'   => '🌐',
        'Python'       => '🐍',
        'HTML'         => '🏗️',
        'CSS'          => '🎨',
        'JavaScript'   => '⚡',
        'Algoritmia'   => '🧮',
        'General'      => '📂',
    ];
    return $iconos[$materia] ?? '📌';
}

/**
 * CSRF Protection
 */
function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . generarTokenCSRF() . '">';
}

function validarCSRF($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';
    return $token && $stored && hash_equals($stored, $token);
}
