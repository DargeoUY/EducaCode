<?php
/**
 * setup.php — Instalador completo de la plataforma
 * Paso 1: Conexión a la base de datos y URL base
 * Paso 2: Crear tablas, admin y escribir config.php
 * Eliminar este archivo después de instalar.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$paso = 1;
$mensaje = '';
$error = false;
$exito = false;

// Verificar si ya está instalado (sin incluir config.php)
if (file_exists(__DIR__ . '/config.php')) {
    $cfg = file_get_contents(__DIR__ . '/config.php');
    if (strpos($cfg, "define('DB_HOST', 'sqlXXX") === false && strpos($cfg, "TU_CONTRASENA") === false) {
        // config.php tiene credenciales reales, intentar verificar
        if (preg_match("/define\('DB_HOST',\s*'([^']+)'\)/", $cfg, $m) && $m[1] !== 'sqlXXX.infinityfree.com') {
            try {
                preg_match("/define\('DB_NAME',\s*'([^']+)'\)/", $cfg, $mn);
                preg_match("/define\('DB_USER',\s*'([^']+)'\)/", $cfg, $mu);
                preg_match("/define\('DB_PASS',\s*'([^']+)'\)/", $cfg, $mp);
                if ($mn && $mu) {
                    $dsn = "mysql:host={$m[1]};dbname={$mn[1]};charset=utf8mb4";
                    $tmpPdo = new PDO($dsn, $mu[1], $mp[1] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $check = $tmpPdo->query("SELECT id FROM usuarios WHERE username = 'admin' LIMIT 1");
                    if ($check->fetch()) {
                        $mensaje = 'La plataforma ya está instalada. <a href="index.php" style="color:#818cf8;">Ir al login →</a>';
                        $exito = true;
                    }
                }
            } catch (Exception $e) {
                // BD no accesible, permitir reinstalar
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$exito) {
    $paso = (int)($_POST['paso'] ?? 1);

    if ($paso === 1) {
        $host = trim($_POST['db_host'] ?? '');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        $url  = trim($_POST['base_url'] ?? '');

        if (empty($host) || empty($name) || empty($user) || empty($url)) {
            $mensaje = 'Todos los campos son obligatorios.';
            $error = true;
        } else {
            $url = rtrim($url, '/') . '/';
            try {
                $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
                new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $_SESSION['setup_db'] = compact('host', 'name', 'user', 'pass', 'url');
                $paso = 2;
                $mensaje = 'Conexión exitosa. Definí la contraseña del administrador.';
            } catch (PDOException $e) {
                $mensaje = 'Error de conexión: ' . htmlspecialchars($e->getMessage());
                $error = true;
            }
        }
    } elseif ($paso === 2) {
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $db = $_SESSION['setup_db'] ?? null;

        if (!$db) {
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        if (strlen($password) < 8) {
            $mensaje = 'La contraseña debe tener al menos 8 caracteres.';
            $error = true;
        } elseif ($password !== $password2) {
            $mensaje = 'Las contraseñas no coinciden.';
            $error = true;
        } else {
            try {
                $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // Ejecutar SQL de creación de tablas
                $sql = file_get_contents(__DIR__ . '/includes/db_setup.sql');
                // Separar por punto y coma seguido de newline
                $statements = preg_split('/;\s*\r?\n/', $sql);
                foreach ($statements as $stmts) {
                    $stmts = trim($stmts);
                    if (empty($stmts) || strpos($stmts, '--') === 0) continue;
                    // Saltar el INSERT del admin (lo creamos después con la contraseña del usuario)
                    if (stripos($stmts, 'INSERT INTO usuarios') !== false) continue;
                    $pdo->exec($stmts);
                }

                // Crear admin con la contraseña elegida
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("INSERT INTO usuarios (username, password_hash, rol, nombre, email) VALUES ('admin', :p, 'admin', 'Administrador', 'admin@plataforma.edu')")
                    ->execute([':p' => $hash]);

                // Auto-detectar protocolo y subdirectorio para BASE_URL
                $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                $proto = $is_https ? 'https' : 'http';
                $subdir = dirname($_SERVER['SCRIPT_NAME']);
                $subdir = ($subdir === '/' || $subdir === '\\') ? '/' : $subdir . '/';

                // Escribir config.php con las credenciales reales
                $configContenido = '<?php
/**
 * config.php — Configuracion global de la plataforma
 * Generado automaticamente por setup.php
 */

define(\'DB_HOST\', \'' . addcslashes($db['host'], "\\'") . '\');
define(\'DB_NAME\', \'' . addcslashes($db['name'], "\\'") . '\');
define(\'DB_USER\', \'' . addcslashes($db['user'], "\\'") . '\');
define(\'DB_PASS\', \'' . addcslashes($db['pass'], "\\'") . '\');
define(\'DB_CHARSET\', \'utf8mb4\');

$subdir = dirname($_SERVER[\'SCRIPT_NAME\']);
$subdir = ($subdir === \'/\' || $subdir === \'\\\\\') ? \'/\' : $subdir . \'/\';
$is_https = (!empty($_SERVER[\'HTTPS\']) && $_SERVER[\'HTTPS\'] !== \'off\')
    || (isset($_SERVER[\'SERVER_PORT\']) && $_SERVER[\'SERVER_PORT\'] == 443)
    || (isset($_SERVER[\'HTTP_X_FORWARDED_PROTO\']) && $_SERVER[\'HTTP_X_FORWARDED_PROTO\'] === \'https\');
$proto = $is_https ? \'https\' : \'http\';
define(\'BASE_URL\', "{$proto}://{$_SERVER[\'HTTP_HOST\']}{$subdir}");

define(\'MAX_INTENTOS_LOGIN\', 5);
define(\'BLOQUEO_MINUTOS\', 15);
define(\'LONGITUD_CODIGO\', 6);
define(\'MAX_TAB_SALIDAS_PERMITIDAS\', 3);

define(\'UPLOAD_DIR\', __DIR__ . \'/uploads/\');
define(\'MAX_FILE_SIZE\', 50 * 1024 * 1024);

if (session_status() === PHP_SESSION_NONE) {
    ini_set(\'session.cookie_httponly\', 1);
    ini_set(\'session.cookie_samesite\', \'Lax\');
    ini_set(\'session.use_strict_mode\', 1);
    if (isset($_SERVER[\'HTTPS\']) && $_SERVER[\'HTTPS\'] === \'on\') {
        ini_set(\'session.cookie_secure\', 1);
    }
    session_start();
}

header(\'X-Frame-Options: DENY\');
header(\'X-Content-Type-Options: nosniff\');
header(\'Referrer-Policy: strict-origin-when-cross-origin\');
header("Content-Security-Policy: default-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net; connect-src \'self\' https:; font-src \'self\' data:; img-src \'self\' data: blob: https:; upgrade-insecure-requests;");

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
} catch (PDOException $e) {
    die(\'<h1>Error de conexion a la base de datos</h1><p>\' . htmlspecialchars($e->getMessage()) . \'</p>\');
}

if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
';

                if (file_put_contents(__DIR__ . '/config.php', $configContenido) === false) {
                    throw new RuntimeException('No se pudo escribir config.php. Verificá permisos del directorio.');
                }

                unset($_SESSION['setup_db']);
                $mensaje = 'Plataforma instalada correctamente. Ya podés iniciar sesión como admin.';
                $exito = true;
            } catch (Exception $e) {
                $mensaje = 'Error: ' . htmlspecialchars($e->getMessage());
                $error = true;
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; upgrade-insecure-requests;">
    <title>Instalación — Plataforma</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Segoe UI,Tahoma,sans-serif;background:#0f172a;color:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
        .setup-card{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:36px;max-width:480px;width:100%;box-shadow:0 8px 40px rgba(0,0,0,.4);position:relative;z-index:1}
        h1{text-align:center;background:linear-gradient(135deg,#6366f1,#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:4px}
        .sub{text-align:center;color:#94a3b8;font-size:.88rem;margin-bottom:24px}
        .steps{display:flex;justify-content:center;gap:8px;margin-bottom:20px}
        .step{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;background:#334155;color:#94a3b8;transition:all .3s}
        .step.active{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
        .step.done{background:#10b981;color:#fff}
        .form-group{margin-bottom:14px}
        label{display:block;font-size:.85rem;font-weight:600;margin-bottom:4px;color:#e2e8f0}
        input{width:100%;padding:10px 14px;background:#0f172a;color:#e2e8f0;border:2px solid #334155;border-radius:8px;font-size:.95rem;outline:none;transition:border-color .3s}
        input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
        button{width:100%;padding:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:all .3s}
        button:hover{transform:translateY(-1px);box-shadow:0 4px 20px rgba(99,102,241,.4)}
        .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:.88rem;text-align:center;word-break:break-word}
        .msg-error{background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3)}
        .msg-exito{background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.3)}
        .alerta{background:rgba(245,158,11,.08);border-left:3px solid #f59e0b;padding:10px 14px;border-radius:0 6px 6px 0;font-size:.8rem;color:#f59e0b;margin-top:16px}
        .help{font-size:.75rem;color:#64748b;margin-top:2px}
        a{color:#818cf8;text-decoration:none}
        a:hover{text-decoration:underline}
    </style>
</head>
<body>
<div class="setup-card">
    <h1>Instalación</h1>
    <p class="sub">Configurá la base de datos y creá el administrador</p>

    <div class="steps">
        <div class="step <?= $paso >= 1 ? 'active' : '' ?>">1</div>
        <div class="step <?= $paso >= 2 ? ($exito ? 'done' : 'active') : '' ?>">2</div>
    </div>

    <?php if ($mensaje): ?>
        <div class="msg <?= $exito ? 'msg-exito' : 'msg-error' ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if (!$exito): ?>
        <?php if ($paso === 1): ?>
        <form method="POST">
            <input type="hidden" name="paso" value="1">
            <div class="form-group">
                <label for="db_host">Servidor MySQL</label>
                <input type="text" id="db_host" name="db_host" required placeholder="sqlXXX.infinityfree.com" value="<?= htmlspecialchars($_POST['db_host'] ?? '') ?>">
                <div class="help">En InfinityFree: MySQL → Host Name</div>
            </div>
            <div class="form-group">
                <label for="db_name">Nombre de la base de datos</label>
                <input type="text" id="db_name" name="db_name" required placeholder="if0_XXXXXX_plataforma" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="db_user">Usuario MySQL</label>
                <input type="text" id="db_user" name="db_user" required placeholder="if0_XXXXXX" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="db_pass">Contraseña MySQL</label>
                <input type="password" id="db_pass" name="db_pass" placeholder="Contraseña de la BD">
            </div>
            <div class="form-group">
                <label for="base_url">URL de la plataforma</label>
                <input type="text" id="base_url" name="base_url" required placeholder="https://test.geodk.sbs/plataforma" value="<?= htmlspecialchars($_POST['base_url'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/plataforma') ?>">
                <div class="help">Con https:// y sin barra final</div>
            </div>
            <button type="submit">Probar conexión y continuar</button>
        </form>
        <?php elseif ($paso === 2): ?>
        <form method="POST">
            <input type="hidden" name="paso" value="2">
            <div class="form-group">
                <label for="password">Contraseña del administrador</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
            </div>
            <div class="form-group">
                <label for="password2">Repetir contraseña</label>
                <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password" placeholder="Repetí la contraseña">
            </div>
            <button type="submit">Instalar plataforma</button>
        </form>
        <?php endif; ?>
    <?php else: ?>
        <p style="text-align:center;margin-top:12px;">
            <a href="index.php" style="font-size:1.1rem;font-weight:600;">Ir al login →</a>
        </p>
    <?php endif; ?>

    <div class="alerta">
        <strong>Importante:</strong> Después de instalar, <strong>eliminá este archivo</strong> (setup.php) por seguridad.
    </div>
</div>
</body>
</html>