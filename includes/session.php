<?php
/**
 * 🌱 ECOTIENDA HN - CONTROL DE SESIÓN
 * Ruta: /includes/session.php
 * Descripción: Inicializador y validador de las sesiones de los usuarios, incluyendo regeneración de ID para evitar Session Fixation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Asegurar que la sesión tenga identificadores regenerados regularmente para seguridad
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // Cada 30 minutos
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

/**
 * Verifica si el usuario actual ha iniciado sesión
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verifica si el usuario actual es un Administrador
 * @return bool
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role_name']) && $_SESSION['user_role_name'] === 'admin';
}

/**
 * Verifica si el usuario actual es un Cliente
 * @return bool
 */
function isCliente() {
    return isLoggedIn() && isset($_SESSION['user_role_name']) && $_SESSION['user_role_name'] === 'cliente';
}

/**
 * Obligar a iniciar sesión (si no redirigir a login)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: " . (defined("BASE_URL") ? BASE_URL : "/") . "login.php");
        exit;
    }
}

/**
 * Obligar a que el usuario sea Admin (si no redirigir)
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: " . (defined("BASE_URL") ? BASE_URL : "/") . "index.php?error=no_authorized");
        exit;
    }
}
?>