<?php
/**
 * 🌱 ECOTIENDA HN - MIDDLEWARE DE AUTENTICACIÓN PARA LA API
 * Ruta: /includes/api_auth.php
 * Descripción: Verifica el token enviado por la app móvil (header
 *              Authorization: Bearer <token>) contra la tabla api_tokens.
 *              Provee requireApiAuth() y requireApiAdmin() para proteger
 *              endpoints de la API (Fase 1 del plan EcoTienda HN).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Responde con un error JSON estandarizado y termina la ejecución.
 */
function apiAuthError(int $httpCode, string $mensaje): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => $mensaje,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Extrae el token del header Authorization: Bearer <token>.
 * Contempla que algunos hosts (como InfinityFree) a veces no exponen
 * el header vía $_SERVER['HTTP_AUTHORIZATION'] directamente.
 */
function obtenerTokenDesdeHeader(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

    if (!$header && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $header = $value;
                break;
            }
        }
    }

    if (!$header || stripos($header, 'Bearer ') !== 0) {
        return null;
    }

    return trim(substr($header, 7));
}

/**
 * Verifica el token contra la base de datos.
 * Devuelve el arreglo con los datos del usuario si es válido, o null.
 */
function verificarApiToken(string $token): ?array
{
    $pdo = Database::getConnection();

    $sql = "SELECT u.id, u.rol_id, u.nombre, u.apellido, u.correo, u.estado,
                   t.id AS token_id, t.expira_en, t.revocado
            FROM api_tokens t
            INNER JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.token = :token
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['token' => $token]);
    $fila = $stmt->fetch();

    if (!$fila) {
        return null;
    }

    if ((int)$fila['revocado'] === 1) {
        return null;
    }

    if ($fila['expira_en'] !== null && strtotime($fila['expira_en']) < time()) {
        return null;
    }

    if ($fila['estado'] !== 'activo') {
        return null;
    }

    return $fila;
}

/**
 * Middleware: exige que la petición traiga un token válido.
 * Uso al inicio de cualquier endpoint protegido:
 *   $usuario = requireApiAuth();
 */
function requireApiAuth(): array
{
    $token = obtenerTokenDesdeHeader();

    if (!$token) {
        apiAuthError(401, 'Token de autenticación no proporcionado.');
    }

    $usuario = verificarApiToken($token);

    if (!$usuario) {
        logError('WARNING', 'Intento de acceso con token inválido/expirado a la API', [
            'token_parcial' => substr($token, 0, 8) . '...',
        ]);
        apiAuthError(401, 'Token inválido o expirado. Inicia sesión de nuevo.');
    }

    return $usuario;
}

/**
 * Middleware: exige token válido Y rol de administrador (rol_id = 1,
 * según la tabla roles ya existente en el esquema).
 * Uso:
 *   $admin = requireApiAdmin();
 */
function requireApiAdmin(): array
{
    $usuario = requireApiAuth();

    if ((int)$usuario['rol_id'] !== 1) {
        apiAuthError(403, 'Esta acción requiere permisos de administrador.');
    }

    return $usuario;
}
