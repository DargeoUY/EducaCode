<?php
/**
 * config.php — Configuracion global de la plataforma
 * Conexion PDO a MySQL, sesiones seguras, constantes
 * Para InfinityFree: ajustar DB_HOST, DB_NAME, DB_USER, DB_PASS
 * O mejor: usar setup.php para generar este archivo automaticamente
 * PROTEGIDO: acceso directo bloqueado por .htaccess
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.php') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die('Acceso denegado.');
}

define('APP_INSTALLED', true);

define('DB_HOST', getenv('DB_HOST') ?: 'mariadb');
define('DB_NAME', getenv('DB_NAME') ?: 'materiales1');
define('DB_USER', getenv('DB_USER') ?: 'app_user');
define('DB_PASS', getenv('DB_PASS') ?: 'app_pass_2026');
define('DB_CHARSET', 'utf8mb4');

$is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');
$proto = $is_https ? 'https' : 'http';
$subdir = str_replace('\\', '/', __DIR__);
$docroot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname($_SERVER['SCRIPT_FILENAME']), '/'));
if (stripos($subdir, $docroot) === 0) {
    $subdir = substr($subdir, strlen($docroot));
}
$subdir = ($subdir === '' || $subdir === '/') ? '/' : '/' . trim($subdir, '/') . '/';
define('BASE_URL', "{$proto}://{$_SERVER['HTTP_HOST']}{$subdir}");

define('MAX_INTENTOS_LOGIN', 5);
define('BLOQUEO_MINUTOS', 15);
define('LONGITUD_CODIGO', 6);
define('MAX_TAB_SALIDAS_PERMITIDAS', 3);

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 1800);
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// NO intentar conectar si las credenciales son placeholder (setup.php se encarga)
if (DB_HOST === 'sqlXXX.infinityfree.com' || DB_PASS === 'TU_CONTRASENA') {
    // Si no estamos en setup.php, redirigir allá
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($script !== 'setup.php') {
        header('Location: ' . BASE_URL . 'setup.php');
        exit;
    }
    // En setup.php: no crear $pdo, el setup maneja su propia conexión
    return;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
} catch (PDOException $e) {
    error_log('DB connection error: ' . $e->getMessage());
    die('<h1>Error de conexion a la base de datos</h1><p>No se pudo conectar. Verifica las credenciales o ejecuta <a href="setup.php">setup.php</a>.</p>');
}

if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
