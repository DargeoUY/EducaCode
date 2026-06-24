<?php
require_once __DIR__ . '/config.php';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';
    if (strlen($pw) < 6) $mensaje = 'Mínimo 6 caracteres.';
    elseif ($pw !== $pw2) $mensaje = 'No coinciden.';
    else {
        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE usuarios SET password_hash = :h WHERE rol = 'admin' LIMIT 1")->execute([':h' => $hash]);
        $mensaje = 'exito';
    }
}
?><!DOCTYPE html><html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Setup Admin — EducaCode</title>
<style>:root{--bg:#0a0e17;--card:rgba(15,23,42,.9);--text:#f1f5f9;--text2:#94a3b8;--prim:#6366f1;--border:rgba(99,102,241,.15);--input-bg:rgba(15,23,42,.8);--ok:#10b981;--err:#ef4444}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:36px;max-width:420px;width:100%}
h1{font-size:1.3rem;text-align:center;margin-bottom:6px}p{color:var(--text2);text-align:center;margin-bottom:20px;font-size:.85rem}
label{display:block;font-size:.8rem;color:var(--text2);margin-bottom:4px;font-weight:500}
input{width:100%;padding:10px 12px;background:var(--input-bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;margin-bottom:12px;outline:none}input:focus{border-color:var(--prim)}
button{width:100%;padding:12px;background:var(--prim);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer}
.msg{padding:10px;border-radius:8px;margin-bottom:12px;font-size:.85rem;text-align:center}.msg-err{background:rgba(239,68,68,.1);color:var(--err)}.msg-ok{background:rgba(16,185,129,.1);color:var(--ok)}
</style></head><body>
<div class="card"><h1>⚙️ Configurar Admin</h1><p>Cambiá la contraseña del administrador</p>
<?php if ($mensaje === 'exito'): ?><div class="msg msg-ok">✅ Contraseña actualizada. <a href="index.php">Ir al login</a>.<br><strong>IMPORTANTE: borrá setup.php del servidor.</strong></div>
<?php else: ?><?php if ($mensaje): ?><div class="msg msg-err"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
<form method="POST">
    <label>Nueva contraseña</label><input type="password" name="password" placeholder="Mínimo 6 caracteres" required>
    <label>Repetir contraseña</label><input type="password" name="password2" placeholder="Repetila" required>
    <button type="submit">Guardar</button>
</form><?php endif; ?>
</div></body></html>
