<?php
/**
 * 🌱 ECOTIENDA HN - CIERRE DE SESIÓN SECURE
 * Ruta: /logout.php
 * Descripción: Limpia, desvincula y destruye de forma segura la sesión del cliente o administrador registrado.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    logAuditoria($_SESSION['user_id'], "Cierre de sesión", "usuarios");
}

// Vaciar todas las variables de sesión
$_SESSION = [];

// Si se desea destruir la cookie de sesión por completo
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión física del servidor
session_destroy();

// Redirigir al inicio
redirect('/index.php');
?>
