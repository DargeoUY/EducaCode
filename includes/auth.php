<?php
/**
 * auth.php — Middleware de autenticacion y autorizacion
 * require_admin() / require_docente() / require_estudiante() / require_login()
 */

function require_login() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . 'index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_rol($roles_permitidos) {
    require_login();
    if (!in_array($_SESSION['usuario_rol'], $roles_permitidos)) {
        $rol = $_SESSION['usuario_rol'];
        if ($rol === 'admin') redirigir('admin/dashboard.php');
        elseif ($rol === 'docente') redirigir('docente/dashboard.php');
        else redirigir('estudiante/dashboard.php');
    }
}

function require_admin() { require_rol(['admin']); }
function require_docente() { require_rol(['docente', 'admin']); }
function require_estudiante() { require_rol(['estudiante']); }

function usuario_actual($pdo) {
    if (!isset($_SESSION['usuario_id'])) return null;
    return obtenerUsuario($pdo, $_SESSION['usuario_id']);
}
