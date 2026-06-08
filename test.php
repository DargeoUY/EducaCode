<?php
/**
 * test.php — Diagnóstico rápido. Subir a /plataforma/ y visitar.
 * Eliminar después de usar.
 */
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico</title>
    <style>
        body{font-family:Segoe UI,sans-serif;background:#0f172a;color:#f1f5f9;max-width:700px;margin:40px auto;padding:20px}
        .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:24px;margin-bottom:16px}
        h1{color:#818cf8;font-size:1.3rem;margin-bottom:16px}
        .ok{color:#10b981}.fail{color:#ef4444}.warn{color:#f59e0b}
        table{width:100%;border-collapse:collapse;font-size:.9rem}
        td{padding:8px 12px;border-bottom:1px solid #334155}
        td:first-child{font-weight:600;color:#e2e8f0;width:200px}
        td:last-child{font-family:Consolas,monospace;word-break:break-all}
    </style>
</head>
<body>
<h1>🔍 Diagnóstico de la plataforma</h1>

<div class="card">
    <h1>1. PHP</h1>
    <table>
        <tr><td>Versión PHP</td><td class="ok"><?= phpversion() ?></td></tr>
        <tr><td>SAPI</td><td><?= php_sapi_name() ?></td></tr>
        <tr><td>Errores visibles</td><td><?= ini_get('display_errors') ? '<span class="fail">Sí (mostrará errores)</span>' : '<span class="ok">No</span>' ?></td></tr>
        <tr><td>PDO disponible</td><td><?= extension_loaded('pdo_mysql') ? '<span class="ok">✅ Sí</span>' : '<span class="fail">❌ No</span>' ?></td></tr>
        <tr><td>mysqli disponible</td><td><?= extension_loaded('mysqli') ? '<span class="ok">✅ Sí</span>' : '<span class="fail">❌ No</span>' ?></td></tr>
        <tr><td>Directorio actual</td><td><?= __DIR__ ?></td></tr>
        <tr><td>Archivo actual</td><td><?= __FILE__ ?></td></tr>
    </table>
</div>

<div class="card">
    <h1>2. Archivos</h1>
    <table>
        <?php
        $files = ['config.php','setup.php','index.php','registrar.php','logout.php',
                   'includes/auth.php','includes/funciones.php','includes/db_setup.sql',
                   'admin/dashboard.php','docente/dashboard.php','estudiante/dashboard.php',
                   'assets/css/estilos.css'];
        foreach ($files as $f):
            $path = __DIR__ . '/' . $f;
            $exists = file_exists($path);
        ?>
        <tr><td><?= $f ?></td><td class="<?= $exists ? 'ok' : 'fail' ?>"><?= $exists ? '✅ Existe' : '❌ NO EXISTE' ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h1>3. Servidor</h1>
    <table>
        <tr><td>HTTP_HOST</td><td><?= $_SERVER['HTTP_HOST'] ?? '—' ?></td></tr>
        <tr><td>HTTPS</td><td><?= $_SERVER['HTTPS'] ?? '—' ?></td></tr>
        <tr><td>SERVER_PORT</td><td><?= $_SERVER['SERVER_PORT'] ?? '—' ?></td></tr>
        <tr><td>REQUEST_URI</td><td><?= $_SERVER['REQUEST_URI'] ?? '—' ?></td></tr>
        <tr><td>SCRIPT_FILENAME</td><td><?= $_SERVER['SCRIPT_FILENAME'] ?? '—' ?></td></tr>
        <tr><td>DOCUMENT_ROOT</td><td><?= $_SERVER['DOCUMENT_ROOT'] ?? '—' ?></td></tr>
        <tr><td>Permisos dir</td><td><?= is_writable(__DIR__) ? '<span class="ok">Escritura OK</span>' : '<span class="fail">Sin permisos de escritura</span>' ?></td></tr>
    </table>
</div>

<div class="card">
    <h1>4. Config test</h1>
    <table>
    <?php
    // Leer config.php sin include (para evitar el redirect)
    if (file_exists(__DIR__.'/config.php')):
        $cfg = file_get_contents(__DIR__.'/config.php');
        preg_match("/define\('DB_HOST',\s*'([^']+)'\)/", $cfg, $mh);
        preg_match("/define\('DB_NAME',\s*'([^']+)'\)/", $cfg, $mn);
        preg_match("/define\('DB_USER',\s*'([^']+)'\)/", $cfg, $mu);
        $host = $mh[1] ?? '?';
        $name = $mn[1] ?? '?';
        $user = $mu[1] ?? '?';
        $esPlaceholder = strpos($host, 'sqlXXX') !== false || $host === '?';
    ?>
        <tr><td>DB_HOST leído</td><td class="<?= $esPlaceholder ? 'warn' : 'ok' ?>"><?= $host ?></td></tr>
        <tr><td>DB_NAME leído</td><td class="<?= $esPlaceholder ? 'warn' : 'ok' ?>"><?= $name ?></td></tr>
        <tr><td>DB_USER leído</td><td class="<?= $esPlaceholder ? 'warn' : 'ok' ?>"><?= $user ?></td></tr>
        <tr><td>Credenciales</td><td><?= $esPlaceholder ? '<span class="warn">⚠️ Son placeholder — hay que configurar</span>' : '<span class="ok">✅ Reales configuradas</span>' ?></td></tr>
        <?php if (!$esPlaceholder): ?>
        <tr><td>Test conexión</td><td>
        <?php
        try {
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            new PDO($dsn, $user, $cfg_replace = null);
            echo '<span class="ok">✅ Conecta OK</span>';
        } catch (Exception $e) {
            echo '<span class="fail">❌ Falla: ' . htmlspecialchars($e->getMessage()) . '</span>';
        }
        ?>
        </td></tr>
        <?php endif; ?>
    <?php else: ?>
        <tr><td colspan="2" class="fail">config.php no existe</td></tr>
    <?php endif; ?>
    </table>
</div>

<div class="card">
    <h1>5. ¿Qué hacer ahora?</h1>
    <ul style="line-height:1.8;padding-left:20px;">
        <li>Si <strong>faltan archivos</strong> → subir los archivos que figuran "NO EXISTE" por FTP</li>
        <li>Si las <strong>credenciales son placeholder</strong> → ir a <a href="setup.php" style="color:#818cf8;">setup.php</a> para configurar</li>
        <li>Si <strong>PDO no está disponible</strong> → el hosting no soporta MySQL PDO</li>
        <li>Si <strong>todo OK menos la conexión</strong> → revisar credenciales en el panel de hosting</li>
    </ul>
</div>

<p style="text-align:center;color:#64748b;margin-top:20px;">Eliminá este archivo (test.php) después del diagnóstico por seguridad.</p>
</body>
</html>
