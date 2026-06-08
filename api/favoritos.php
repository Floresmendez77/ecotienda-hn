<?php
/**
 * 🌱 ECOTIENDA HN - API DE FAVORITOS
 * Ruta: /api/favoritos.php
 * Descripción: Endpoint JSON para agregar/quitar favoritos (toggle) via AJAX.
 *              POST: { producto_id: int }  →  { success, favorito: bool, message }
 *              GET:  ?producto_id=int      →  { success, favorito: bool }
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.', 'redirect' => BASE_URL . 'login.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getConnection();

    // GET: consultar si un producto está en favoritos
    if ($method === 'GET') {
        $pid = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'message' => 'producto_id inválido.']);
            exit;
        }
        $stmt = $db->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND producto_id = ? LIMIT 1");
        $stmt->execute([$user_id, $pid]);
        echo json_encode(['success' => true, 'favorito' => (bool)$stmt->fetch()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // POST: toggle favorito
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (empty($body)) $body = $_POST;

        $pid = isset($body['producto_id']) ? (int)$body['producto_id'] : 0;
        if ($pid <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'producto_id inválido.']);
            exit;
        }

        // Verificar que el producto existe
        $prodStmt = $db->prepare("SELECT id FROM productos WHERE id = ? AND estado != 'inactivo' LIMIT 1");
        $prodStmt->execute([$pid]);
        if (!$prodStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado.']);
            exit;
        }

        // Verificar estado actual
        $chkStmt = $db->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND producto_id = ? LIMIT 1");
        $chkStmt->execute([$user_id, $pid]);
        $existing = $chkStmt->fetch();

        if ($existing) {
            // Quitar favorito
            $db->prepare("DELETE FROM favoritos WHERE usuario_id = ? AND producto_id = ?")->execute([$user_id, $pid]);
            $msg = '¡Quitado de tus favoritos!';
            $favorito = false;
        } else {
            // Agregar favorito
            $db->prepare("INSERT INTO favoritos (usuario_id, producto_id) VALUES (?, ?)")->execute([$user_id, $pid]);
            $msg = '¡Guardado en tus favoritos! ❤️';
            $favorito = true;
        }

        logAuditoria($user_id, "Toggle favorito producto ID {$pid}: " . ($favorito ? 'agregado' : 'quitado'), 'favoritos');

        echo json_encode(['success' => true, 'favorito' => $favorito, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no soportado.']);

} catch (Exception $e) {
    error_log('[API favoritos] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.'], JSON_UNESCAPED_UNICODE);
}
