<?php
/**
 * 🌱 ECOTIENDA HN - LOGIN DE API (GENERA TOKEN)
 * Ruta: /api/auth.php
 * Descripción: Recibe correo y contraseña por POST (JSON), valida contra
 *              la tabla usuarios con password_verify(), y si es correcto
 *              genera un token aleatorio guardado en api_tokens.
 *              Usado por la app NativeScript (Fase 1 del plan).
 *
 * Petición esperada (POST, Content-Type: application/json):
 *   { "correo": "admin@ecotiendahn.com", "password": "..." }
 *
 * Respuesta exitosa:
 *   { "success": true, "token": "...", "usuario": { ... } }
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function responderError(int $httpCode, string $mensaje): void
{
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError(405, 'Método no permitido. Usa POST.');
}

$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    responderError(400, 'Cuerpo de la petición inválido, se esperaba JSON.');
}

$correo = trim($datos['correo'] ?? '');
$password = (string)($datos['password'] ?? '');
$dispositivo = trim($datos['dispositivo'] ?? '');

if ($correo === '' || $password === '') {
    responderError(400, 'Correo y contraseña son obligatorios.');
}

$pdo = Database::getConnection();

$stmt = $pdo->prepare(
    "SELECT id, rol_id, nombre, apellido, correo, password, estado
     FROM usuarios
     WHERE correo = :correo
     LIMIT 1"
);
$stmt->execute(['correo' => $correo]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($password, $usuario['password'])) {
    logError('WARNING', 'Intento de login fallido en la API', ['correo' => $correo]);
    responderError(401, 'Correo o contraseña incorrectos.');
}

if ($usuario['estado'] !== 'activo') {
    responderError(403, 'Tu cuenta no está activa. Contacta al administrador.');
}

// Generar un token aleatorio y seguro (64 caracteres hexadecimales)
$token = bin2hex(random_bytes(32));

// El token expira en 30 días; la app deberá volver a hacer login después
$expiraEn = date('Y-m-d H:i:s', strtotime('+30 days'));

$insert = $pdo->prepare(
    "INSERT INTO api_tokens (usuario_id, token, dispositivo, expira_en)
     VALUES (:usuario_id, :token, :dispositivo, :expira_en)"
);
$insert->execute([
    'usuario_id'  => $usuario['id'],
    'token'       => $token,
    'dispositivo' => $dispositivo !== '' ? $dispositivo : null,
    'expira_en'   => $expiraEn,
]);

logError('INFO', 'Login exitoso en la API', ['usuario_id' => $usuario['id']]);

echo json_encode([
    'success' => true,
    'token'   => $token,
    'usuario' => [
        'id'       => (int)$usuario['id'],
        'nombre'   => $usuario['nombre'],
        'apellido' => $usuario['apellido'],
        'correo'   => $usuario['correo'],
        'rol_id'   => (int)$usuario['rol_id'],
        'es_admin' => (int)$usuario['rol_id'] === 1,
    ],
], JSON_UNESCAPED_UNICODE);
