<?php
/**
 * index.php — Pagina de Login
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_rol'] === 'admin') redirigir('admin/dashboard.php');
    elseif ($_SESSION['usuario_rol'] === 'docente') redirigir('docente/dashboard.php');
    else redirigir('estudiante/dashboard.php');
}

$error = '';
$redirect = $_GET['redirect'] ?? '';
$codigo = $_GET['codigo'] ?? '';

// Validar redirect: solo rutas locales
if ($redirect !== '') {
    $rutasPermitidas = ['estudiante', 'docente', 'admin'];
    $esValida = false;
    foreach ($rutasPermitidas as $r) {
        if (strpos($redirect, $r . '/') === 0 || strpos($redirect, $r . '.') === 0) {
            $esValida = true; break;
        }
    }
    if (!$esValida) $redirect = '';
}
if ($codigo !== '') {
    $redirect = 'estudiante/dashboard.php?codigo=' . urlencode($codigo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCSRF()) { $error = 'Token de seguridad inválido. Recargá la página.'; }
    else {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Completa todos los campos.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = :u OR email = :e LIMIT 1");
            $stmt->execute([':u' => $username, ':e' => $username]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                if (estaBloqueado($usuario)) {
                    $tiempo = strtotime($usuario['bloqueo_hasta']) - time();
                    $min = ceil($tiempo / 60);
                    $error = "Cuenta bloqueada. Intenta de nuevo en $min minuto(s).";
                } elseif (password_verify($password, $usuario['password_hash'])) {
                    $pdo->prepare("UPDATE usuarios SET intentos_login = 0, bloqueo_hasta = NULL, ultimo_login = NOW() WHERE id = :id")
                        ->execute([':id' => $usuario['id']]);

                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_rol'] = $usuario['rol'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];

                    registrarSesion($pdo, $usuario['id'], 'login');

                    if ($redirect !== '') {
                        header('Location: ' . BASE_URL . $redirect);
                    } elseif ($usuario['rol'] === 'admin') {
                        redirigir('admin/dashboard.php');
                    } elseif ($usuario['rol'] === 'docente') {
                        redirigir('docente/dashboard.php');
                    } else {
                        redirigir('estudiante/dashboard.php');
                    }
                    exit;
                } else {
                    $intentos = $usuario['intentos_login'] + 1;
                    if ($intentos >= MAX_INTENTOS_LOGIN) {
                        $bloqueo = date('Y-m-d H:i:s', strtotime('+' . BLOQUEO_MINUTOS . ' minutes'));
                        $pdo->prepare("UPDATE usuarios SET intentos_login = :i, bloqueado = 1, bloqueo_hasta = :b WHERE id = :id")
                            ->execute([':i' => $intentos, ':b' => $bloqueo, ':id' => $usuario['id']]);
                        $error = "Demasiados intentos. Cuenta bloqueada por " . BLOQUEO_MINUTOS . " minutos.";
                    } else {
                        $pdo->prepare("UPDATE usuarios SET intentos_login = :i WHERE id = :id")
                            ->execute([':i' => $intentos, ':id' => $usuario['id']]);
                        $error = "Contraseña incorrecta. Te quedan " . (MAX_INTENTOS_LOGIN - $intentos) . " intento(s).";
                    }
                }
            } else {
                $error = 'Credenciales inválidas.';  // Mensaje genérico para no revelar si el usuario existe
            }
        }
    } catch (Exception $e) {
        $error = 'Error del servidor. Intenta más tarde.';
        error_log('Login error: ' . $e->getMessage());
    }
    }
}

$titulo = 'Iniciar Sesión';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EducaCode — Plataforma Educativa</title>
    <link rel="icon" href="<?= BASE_URL ?>img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0e17;
            --card: rgba(15,23,42,.85);
            --accent1: #6366f1;
            --accent2: #06b6d4;
            --accent3: #8b5cf6;
            --text: #f1f5f9;
            --text2: #94a3b8;
            --border: rgba(99,102,241,.2);
            --input-bg: rgba(15,23,42,.8);
        }
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Inter','Segoe UI',sans-serif;
            background:var(--bg);
            color:var(--text);
            min-height:100vh;
            display:flex;
            overflow-x:hidden;
        }
        .bg-layer{
            position:fixed;top:0;left:0;width:100%;height:100%;
            background:url('<?= BASE_URL ?>img/fondo.jpg') center/cover no-repeat;
            opacity:.12;z-index:0;
        }
        .bg-gradient{
            position:fixed;top:0;left:0;width:100%;height:100%;
            background:radial-gradient(ellipse at 30% 50%, rgba(99,102,241,.15) 0%, transparent 60%),
                       radial-gradient(ellipse at 70% 30%, rgba(6,182,212,.1) 0%, transparent 60%),
                       radial-gradient(ellipse at 50% 80%, rgba(139,92,246,.08) 0%, transparent 50%);
            z-index:0;
        }
        .brand-panel{
            flex:1;display:flex;align-items:center;justify-content:center;
            padding:60px 40px;position:relative;z-index:1;
        }
        .brand-content{max-width:480px}
        .brand-logo{width:80px;height:80px;margin-bottom:24px;filter:drop-shadow(0 8px 24px rgba(99,102,241,.35))}
        .brand-content h1{
            font-size:3rem;font-weight:900;
            background:linear-gradient(135deg,#6366f1,#06b6d4);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent;
            background-clip:text;
            margin-bottom:12px;line-height:1.15;
        }
        .brand-subtitle{font-size:1.1rem;color:var(--text2);margin-bottom:32px;line-height:1.5}
        .features{display:flex;flex-direction:column;gap:16px}
        .feature{
            display:flex;align-items:flex-start;gap:14px;
            padding:12px 16px;background:rgba(99,102,241,.06);
            border-radius:12px;border:1px solid rgba(99,102,241,.1);
            transition:all .3s;
        }
        .feature:hover{background:rgba(99,102,241,.1);border-color:rgba(99,102,241,.25)}
        .feature-icon{
            width:40px;height:40px;min-width:40px;
            background:linear-gradient(135deg,rgba(99,102,241,.2),rgba(6,182,212,.15));
            border-radius:10px;display:flex;align-items:center;justify-content:center;
            font-size:1.2rem;
        }
        .feature-text h4{font-size:.9rem;font-weight:600;margin-bottom:2px;color:var(--text)}
        .feature-text p{font-size:.78rem;color:var(--text2);line-height:1.4}
        .login-panel{
            flex:1;display:flex;align-items:center;justify-content:center;
            padding:60px 40px;position:relative;z-index:1;
        }
        .login-wrapper{width:100%;max-width:420px}
        .login-card{
            background:var(--card);border:1px solid var(--border);
            border-radius:20px;padding:40px 36px;
            backdrop-filter:blur(24px);
            box-shadow:0 8px 40px rgba(0,0,0,.4),0 0 0 1px rgba(99,102,241,.08) inset;
        }
        .login-card-top{
            text-align:center;margin-bottom:28px;
        }
        .login-avatar{
            width:56px;height:56px;margin:0 auto 16px;
            background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(6,182,212,.1));
            border-radius:16px;display:flex;align-items:center;justify-content:center;
            font-size:1.5rem;
        }
        .login-card-top h2{font-size:1.3rem;font-weight:700;margin-bottom:4px}
        .login-card-top p{font-size:.82rem;color:var(--text2)}
        .flash{
            padding:10px 14px;border-radius:10px;font-size:.82rem;margin-bottom:16px;
            text-align:center;font-weight:500;
        }
        .flash-exito{background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.2)}
        .flash-error{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.15)}
        .flash-info{background:rgba(99,102,241,.1);color:#818cf8;border:1px solid rgba(99,102,241,.15)}
        .form-group{margin-bottom:16px}
        .form-group label{
            display:block;font-size:.8rem;font-weight:600;color:var(--text2);
            margin-bottom:6px;letter-spacing:.01em;
        }
        .input-icon-wrap{position:relative}
        .input-icon{
            position:absolute;left:14px;top:50%;transform:translateY(-50%);
            font-size:.95rem;color:#64748b;pointer-events:none;z-index:2;
        }
        .form-group input{
            width:100%;padding:12px 14px 12px 42px;
            background:var(--input-bg);color:var(--text);
            border:2px solid rgba(51,65,85,.6);border-radius:12px;
            font-size:.93rem;font-family:inherit;
            outline:none;transition:all .25s;
        }
        .form-group input:focus{
            border-color:var(--accent1);
            box-shadow:0 0 0 3px rgba(99,102,241,.12);
        }
        .form-group input::placeholder{color:#475569}
        .btn-login{
            width:100%;padding:13px;margin-top:4px;
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            color:#fff;border:none;border-radius:12px;
            font-size:.95rem;font-weight:700;font-family:inherit;
            cursor:pointer;transition:all .3s;
            letter-spacing:.02em;
        }
        .btn-login:hover{
            transform:translateY(-2px);
            box-shadow:0 6px 24px rgba(99,102,241,.35);
        }
        .btn-login:active{transform:translateY(0)}
        .links-row{
            display:flex;justify-content:center;gap:16px;margin-top:20px;
        }
        .links-row a{
            font-size:.78rem;color:var(--text2);text-decoration:none;
            transition:color .2s;font-weight:500;
        }
        .links-row a:hover{color:var(--accent1)}
        .links-row span{color:#334155}
        @media(max-width:860px){
            body{flex-direction:column}
            .brand-panel{padding:40px 24px 20px;text-align:center}
            .brand-content h1{font-size:2rem}
            .features{display:none}
            .brand-logo{width:56px;height:56px;margin-bottom:12px}
            .brand-subtitle{font-size:.95rem;margin-bottom:0}
            .login-panel{padding:20px 24px 40px}
            .login-card{padding:28px 22px}
        }
    </style>
</head>
<body>
<div class="bg-layer"></div>
<div class="bg-gradient"></div>

<div class="brand-panel">
    <div class="brand-content">
        <img src="<?= BASE_URL ?>img/Logo.png" alt="EducaCode" class="brand-logo">
        <h1>EducaCode</h1>
        <p class="brand-subtitle">La plataforma educativa que transforma la forma de enseñar y aprender programación.</p>
        <div class="features">
            <div class="feature">
                <div class="feature-icon">📚</div>
                <div class="feature-text">
                    <h4>Materiales interactivos</h4>
                    <p>Recursos didácticos organizados por grupo y materia.</p>
                </div>
            </div>
            <div class="feature">
                <div class="feature-icon">📝</div>
                <div class="feature-text">
                    <h4>Evaluaciones inteligentes</h4>
                    <p>Exámenes con temporizador, anti-trampa y corrección automática.</p>
                </div>
            </div>
            <div class="feature">
                <div class="feature-icon">📊</div>
                <div class="feature-text">
                    <h4>Estadísticas en tiempo real</h4>
                    <p>Seguimiento del progreso, promedios y tasas de aprobación.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="login-panel">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-card-top">
                <div class="login-avatar">🔐</div>
                <h2>Iniciar Sesión</h2>
                <p>Ingresá a tu cuenta para continuar</p>
            </div>

            <?php if (isset($_SESSION['flash'])): ?>
                <div class="flash flash-<?= $_SESSION['flash']['tipo'] ?? 'info' ?>"><?= sanitizar($_SESSION['flash']['mensaje']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash flash-error"><?= sanitizar($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfInput() ?>
                <div class="form-group">
                    <label for="username">Usuario o Email</label>
                    <div class="input-icon-wrap">
                        <span class="input-icon">👤</span>
                        <input type="text" id="username" name="username" placeholder="Tu usuario o email" value="<?= sanitizar($_POST['username'] ?? '') ?>" required autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-icon-wrap">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="password" name="password" placeholder="Tu contraseña" required autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" class="btn-login">Ingresar</button>
            </form>

            <div class="links-row">
                <a href="<?= BASE_URL ?>registrar.php">Crear cuenta</a>
                <span>|</span>
                <a href="<?= BASE_URL ?>registrar-docente.php">Solicitar docente</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>