<?php
/**
 * 🌱 ECOTIENDA HN - ARCHIVO DE CONFIGURACIÓN GLOBAL
 * Ruta: /includes/config.php
 * Descripción: Carga variables de entorno con vlucas/phpdotenv y define
 *              constantes del sistema, BD, correo y rutas base.
 */

// ── Autoload de Composer (phpdotenv + PHPMailer) ─────────────────────────────
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// ── Cargar .env con vlucas/phpdotenv ─────────────────────────────────────────
$dotenvPath = __DIR__ . '/..';
if (file_exists($dotenvPath . '/.env') && class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable($dotenvPath);
    $dotenv->load();
    // Campos obligatorios
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER'])->notEmpty();
}

// Zona horaria para Honduras
date_default_timezone_set('America/Tegucigalpa');

// ── Base de datos ─────────────────────────────────────────────────────────────
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3307');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'ecotienda_pro');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// ── Nombre del sitio ──────────────────────────────────────────────────────────
define('SITE_NAME',    'EcoTienda HN');
define('SITE_SLOGAN',  'Ecológico, Sostenible y Hondureño');
define('CURRENCY',     'L. ');         // Lempira hondureña (HNL)
define('SHIPPING_COST', 150.00);       // Envío estándar Lps. 150

// ── Entorno de la aplicación ──────────────────────────────────────────────────
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');

// ── BASE_URL ──────────────────────────────────────────────────────────────────
// Prioridad: variable de entorno → detección automática por DOCUMENT_ROOT
if (!empty($_ENV['BASE_URL'])) {
    define('BASE_URL', rtrim($_ENV['BASE_URL'], '/') . '/');
} else {
    $scriptName   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']   ?? '');
    $docRoot      = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot  = str_replace('\\', '/', realpath(__DIR__ . '/..'));

    $docRootClean    = rtrim($docRoot,   '/');
    $projectRootClean = rtrim($projectRoot, '/');

    $baseUrl = '/';
    if (!empty($docRootClean) && strpos($projectRootClean, $docRootClean) === 0) {
        $relativePart = substr($projectRootClean, strlen($docRootClean));
        $baseUrl = '/' . trim(str_replace('\\', '/', $relativePart), '/') . '/';
        if ($baseUrl === '//' || $baseUrl === '///') {
            $baseUrl = '/';
        }
    }
    define('BASE_URL', $baseUrl);
}

// ── PHPMailer / SMTP ──────────────────────────────────────────────────────────
define('MAIL_HOST',      $_ENV['MAIL_HOST']      ?? 'smtp.gmail.com');
define('MAIL_PORT',      (int)($_ENV['MAIL_PORT'] ?? 465));
define('MAIL_USER',      $_ENV['MAIL_USER']      ?? '');
define('MAIL_PASS',      $_ENV['MAIL_PASS']      ?? '');
define('MAIL_FROM',      $_ENV['MAIL_FROM']      ?? '');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'EcoTienda HN');
define('MAIL_ENABLED',   filter_var($_ENV['MAIL_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN));
// ─────────────────────────────────────────────────────────────────────────────

// Iniciar sesión de forma segura si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
