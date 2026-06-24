<?php
/**
 * estudiante/dashboard.php — Panel del estudiante: ingresar codigo de grupo
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/auth.php';
require_estudiante();

$usuario = usuario_actual($pdo);
$uid = $usuario['id'];
$mensaje = '';
$codigoUrl = strtoupper(trim($_GET['codigo'] ?? ''));

if ($codigoUrl !== '') {
    $_POST['codigo'] = $codigoUrl;
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo']));

    if ($codigo === '') {
        $mensaje = ['error', 'Ingresa un código de invitación.'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM grupos WHERE codigo_invitacion = :c LIMIT 1");
        $stmt->execute([':c' => $codigo]);
        $grupo = $stmt->fetch();

        if (!$grupo) {
            $mensaje = ['error', 'Código inválido. Verifica e intenta de nuevo.'];
        } elseif ($grupo['codigo_expiracion'] && strtotime($grupo['codigo_expiracion']) < time()) {
            $mensaje = ['error', 'El código de invitación expiró.'];
        } else {
            $existe = $pdo->prepare("SELECT * FROM grupo_miembros WHERE grupo_id = :g AND usuario_id = :u");
            $existe->execute([':g' => $grupo['id'], ':u' => $uid]);
            $m = $existe->fetch();

            if ($m) {
                if ($m['bloqueado']) {
                    $mensaje = ['error', 'Estás bloqueado en este grupo. Contacta a tu docente.'];
                } else {
                    $mensaje = ['info', 'Ya eres miembro de este grupo.'];
                }
            } else {
                $pdo->prepare("INSERT INTO grupo_miembros (grupo_id, usuario_id) VALUES (:g, :u)")
                    ->execute([':g' => $grupo['id'], ':u' => $uid]);
                $_SESSION['flash'] = ['tipo' => 'exito', 'mensaje' => '¡Te uniste al grupo "' . sanitizar($grupo['nombre']) . '" correctamente!'];
                redirigir('../estudiante/mis-grupos.php');
            }
        }
    }
}

$notificaciones = obtenerNotificaciones($pdo, $uid);

$titulo = 'Panel Estudiante';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🏠 Panel de <?= sanitizar($usuario['nombre']) ?></h1>
</div>

<?php if ($mensaje): ?>
    <div class="flash flash-<?= $mensaje[0] ?>"><?= $mensaje[1] ?></div>
<?php endif; ?>

<div class="card" style="max-width:500px;margin:0 auto 24px;">
    <h2>🔑 Unirse a un grupo</h2>
    <form method="POST">
        <div class="grupo-input">
            <label for="codigo">Código de invitación</label>
            <input type="text" id="codigo" name="codigo" class="input-dato input-codigo" placeholder="Ej: ABC123" maxlength="10" style="font-size:1.4rem;text-align:center;letter-spacing:4px;text-transform:uppercase;" autocomplete="off" required>
        </div>
        <button type="submit" class="btn-primario btn-full">🎓 Unirme al grupo</button>
    </form>
</div>

<div class="card">
    <h2>📢 Notificaciones</h2>
    <?php if (empty($notificaciones)): ?>
        <p class="vacio">No tienes notificaciones.</p>
    <?php else: ?>
        <?php foreach ($notificaciones as $notif): ?>
        <div class="notif-item">
            <strong><?= sanitizar($notif['titulo']) ?></strong>
            <span class="notif-grupo">Grupo: <?= sanitizar($notif['grupo_nombre']) ?></span>
            <p><?= sanitizar($notif['mensaje']) ?></p>
            <small><?= formatearFecha($notif['creado_en']) ?></small>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
