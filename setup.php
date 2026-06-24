<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';

$mensaje = '';
$paso = $_GET['paso'] ?? 'form';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $paso === 'form') {
    $host = trim($_POST['db_host'] ?? 'mariadb');
    $name = trim($_POST['db_name'] ?? 'materiales1');
    $user = trim($_POST['db_user'] ?? 'app_user');
    $pass = $_POST['db_pass'] ?? '';
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? '';

    if (strlen($adminPass) < 6) $mensaje = 'La contraseña admin debe tener al menos 6 caracteres.';
    else {
        try {
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $pdo->exec(file_get_contents(__DIR__ . '/includes/db_setup.sql'));

            $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE usuarios SET password_hash = :h WHERE username = 'admin' OR id = 1")->execute([':h' => $hash]);

            $config = "<?php\ndefine('DB_HOST', '$host');\ndefine('DB_NAME', '$name');\ndefine('DB_USER', '$user');\ndefine('DB_PASS', '$pass');\n" . substr(file_get_contents(__DIR__ . '/config.php'), strpos(file_get_contents(__DIR__ . '/config.php'), "define('DB_CHARSET'")));
            file_put_contents(__DIR__ . '/config.php', $config);

            $mensaje = 'exito';
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
        }
    }
}
?><!DOCTYPE html><html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Setup — EducaCode</title>
<style>:root{--bg:#0a0e17;--card:rgba(15,23,42,.9);--text:#f1f5f9;--text2:#94a3b8;--prim:#6366f1;--border:rgba(99,102,241,.15);--input-bg:rgba(15,23,42,.8);--ok:#10b981;--err:#ef4444}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:36px;max-width:500px;width:100%}
h1{font-size:1.3rem;margin-bottom:6px;text-align:center}p{color:var(--text2);text-align:center;margin-bottom:20px;font-size:.85rem}
label{display:block;font-size:.8rem;color:var(--text2);margin-bottom:4px;font-weight:500}
input{width:100%;padding:10px 12px;background:var(--input-bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;margin-bottom:12px;outline:none}input:focus{border-color:var(--prim)}
button{width:100%;padding:12px;background:var(--prim);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer}
.msg{padding:10px;border-radius:8px;margin-bottom:12px;font-size:.85rem;text-align:center}.msg-err{background:rgba(239,68,68,.1);color:var(--err)}.msg-ok{background:rgba(16,185,129,.1);color:var(--ok)}
</style></head><body>
<div class="card"><h1>⚙️ Configuración inicial</h1><p>Configurá la base de datos y creá el admin</p>
<?php if ($mensaje === 'exito'): ?><div class="msg msg-ok">✅ Plataforma configurada. <a href="index.php">Ir al login</a>.<br><strong>IMPORTANTE: eliminá setup.php del servidor.</strong></div>
<?php else: ?><?php if ($mensaje): ?><div class="msg msg-err"><?= sanitizar($mensaje) ?></div><?php endif; ?>
<form method="POST">
    <label>Host DB</label><input type="text" name="db_host" value="mariadb">
    <label>Nombre DB</label><input type="text" name="db_name" value="materiales1">
    <label>Usuario DB</label><input type="text" name="db_user" value="app_user">
    <label>Password DB</label><input type="password" name="db_pass">
    <label>Usuario Admin</label><input type="text" name="admin_user" value="admin">
    <label>Password Admin</label><input type="password" name="admin_pass" placeholder="Mínimo 6 caracteres">
    <button type="submit">Instalar</button>
</form><?php endif; ?>
</div></body></html>
