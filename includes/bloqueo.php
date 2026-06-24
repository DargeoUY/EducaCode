<?php
function verificar_bloqueo_actividad($pdo, $usuario_id) {
    $stmt = $pdo->prepare("SELECT * FROM bloqueos_actividad WHERE usuario_id = :u AND desbloqueado_en IS NULL ORDER BY creado_en DESC LIMIT 1");
    $stmt->execute([':u' => $usuario_id]);
    $bloqueo = $stmt->fetch();
    return $bloqueo ?: null;
}

function mostrar_overlay_bloqueo($motivo = 'Has perdido el foco de la plataforma.') {
    ?><div id="bloqueo-overlay" class="bloqueo-overlay" style="display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(239,68,68,.95);color:#fff;z-index:9999;text-align:center;padding-top:20vh;flex-direction:column;align-items:center;justify-content:start">
        <h1>⛔ Bloqueado ⛔</h1>
        <p><?= sanitizar($motivo) ?></p>
        <p style="margin-top:16px;">Contactá a tu docente para que te desbloquee.</p>
    </div><?php
}
