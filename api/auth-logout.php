<?php
/**
 * 🌱 ECOTIENDA HN - LOGOUT DE API (REVOCA TOKEN)
 * Ruta: /api/auth-logout.php
 * Descripción: Marca el token actual (enviado en el header Authorization)
 *              como revocado en api_tokens, para que deje de funcionar.
 *              Usado por la app NativeScript al cerrar sesión (Fase 1).
 *
 * Petición esperada (POST, header Authorization: Bearer <token>)
 */

require_once __DIR__ . '/../includes/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiAuthError(405, 'Método no permitido. Usa POST.');
}

// Requiere que el token sea válido antes de revocarlo
$usuario = requireApiAuth();

$token = obtenerTokenDesdeHeader();

$pdo = Database::getConnection();
$stmt = $pdo->prepare("UPDATE api_tokens SET revocado = 1 WHERE token = :token");
$stmt->execute(['token' => $token]);

logError('INFO', 'Logout exitoso en la API', ['usuario_id' => $usuario['id']]);

echo json_encode(['success' => true, 'mensaje' => 'Sesión cerrada correctamente.'], JSON_UNESCAPED_UNICODE);
