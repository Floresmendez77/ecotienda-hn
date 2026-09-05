<?php
/**
 * 🌱 ECOTIENDA HN - API DINÁMICA DEL CARRITO
 * Ruta: /api/carrito.php
 * Descripción: Endpoint REST para operaciones AJAX del carrito de compras.
 *              Soporta GET, POST, PUT y DELETE. Siempre responde JSON.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Cabeceras obligatorias para respuesta JSON + CORS mismo origen
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Función helper para responder y terminar
function jsonResponse(bool $success, $data = null, string $message = '', int $cart_count = 0, int $status = 200): void {
    http_response_code($status);
    echo json_encode([
        'success'    => $success,
        'data'       => $data,
        'message'    => $message,
        'cart_count' => $cart_count,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar sesión activa
if (!isLoggedIn()) {
    jsonResponse(false, null, 'Debes iniciar sesión para gestionar tu carrito.', 0, 401);
}

$user_id = (int) $_SESSION['user_id'];

// Protección CSRF: cualquier método que modifique datos debe traer el
// token de sesión en el header X-CSRF-Token (GET de solo lectura queda libre).
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfHeader)) {
        jsonResponse(false, null, 'Token de seguridad inválido o expirado. Recargá la página.', 0, 403);
    }
}

// Obtener conteo actual del carrito
function getCartCount(PDO $db, int $user_id): int {
    $stmt = $db->prepare("SELECT COALESCE(SUM(cantidad), 0) AS total FROM carrito WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

// Obtener items del carrito con datos de producto
function getCartItems(PDO $db, int $user_id): array {
    $sql = "SELECT c.id, c.producto_id, c.cantidad,
                   p.nombre, p.imagen_principal, p.stock,
                   COALESCE(p.precio_oferta, p.precio) AS precio_efectivo,
                   (COALESCE(p.precio_oferta, p.precio) * c.cantidad) AS subtotal
            FROM carrito c
            INNER JOIN productos p ON c.producto_id = p.id
            WHERE c.usuario_id = ?
            ORDER BY c.fecha DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $db = Database::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET: Obtener carrito completo ────────────────────────────────────────
    if ($method === 'GET') {
        $items = getCartItems($db, $user_id);
        $total = array_sum(array_column($items, 'subtotal'));
        $count = getCartCount($db, $user_id);

        jsonResponse(true, [
            'items'   => $items,
            'total'   => $total,
            'envio'   => SHIPPING_COST,
            'grand_total' => $total + SHIPPING_COST,
        ], 'Carrito obtenido correctamente.', $count);
    }

    // Leer body JSON para POST / PUT / DELETE
    $body = [];
    $raw  = file_get_contents('php://input');
    if (!empty($raw)) {
        $body = json_decode($raw, true) ?? [];
    }
    // Fallback a $_POST si viene como form-data
    if (empty($body)) {
        $body = $_POST;
    }

    $producto_id = isset($body['producto_id']) ? (int) $body['producto_id'] : 0;
    $cantidad    = isset($body['cantidad'])    ? (int) $body['cantidad']    : 1;

    // ── POST: Agregar producto al carrito ────────────────────────────────────
    if ($method === 'POST') {
        if ($producto_id <= 0) {
            jsonResponse(false, null, 'ID de producto inválido.', getCartCount($db, $user_id), 400);
        }

        // Verificar stock del producto
        $prodStmt = $db->prepare("SELECT id, nombre, stock FROM productos WHERE id = ? AND estado = 'activo' LIMIT 1");
        $prodStmt->execute([$producto_id]);
        $prod = $prodStmt->fetch();

        if (!$prod) {
            jsonResponse(false, null, 'Producto no encontrado o no disponible.', getCartCount($db, $user_id), 404);
        }
        if ($prod['stock'] <= 0) {
            jsonResponse(false, null, "Lo sentimos, '{$prod['nombre']}' está agotado.", getCartCount($db, $user_id), 409);
        }

        // ¿Ya existe en el carrito?
        $existStmt = $db->prepare("SELECT id, cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ? LIMIT 1");
        $existStmt->execute([$user_id, $producto_id]);
        $existing = $existStmt->fetch();

        if ($existing) {
            $nueva = min($existing['cantidad'] + $cantidad, $prod['stock']);
            $upd   = $db->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?");
            $upd->execute([$nueva, $existing['id']]);
        } else {
            $cant = min($cantidad, $prod['stock']);
            $ins  = $db->prepare("INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)");
            $ins->execute([$user_id, $producto_id, $cant]);
        }

        logAuditoria($user_id, "AJAX: Agregó producto ID {$producto_id} al carrito", 'carrito');
        $count = getCartCount($db, $user_id);
        jsonResponse(true, null, "'{$prod['nombre']}' agregado a tu bolsa. 🌱", $count);
    }

    // ── PUT: Actualizar cantidad ─────────────────────────────────────────────
    if ($method === 'PUT') {
        if ($producto_id <= 0) {
            jsonResponse(false, null, 'ID de producto inválido.', getCartCount($db, $user_id), 400);
        }

        // Verificar stock
        $prodStmt = $db->prepare("SELECT stock FROM productos WHERE id = ? LIMIT 1");
        $prodStmt->execute([$producto_id]);
        $prod = $prodStmt->fetch();

        if (!$prod) {
            jsonResponse(false, null, 'Producto no encontrado.', getCartCount($db, $user_id), 404);
        }

        if ($cantidad <= 0) {
            // Eliminar si cantidad llega a 0
            $del = $db->prepare("DELETE FROM carrito WHERE usuario_id = ? AND producto_id = ?");
            $del->execute([$user_id, $producto_id]);
            $count = getCartCount($db, $user_id);
            jsonResponse(true, null, 'Producto removido de tu bolsa.', $count);
        }

        $cant = min($cantidad, $prod['stock']);
        $upd  = $db->prepare("UPDATE carrito SET cantidad = ? WHERE usuario_id = ? AND producto_id = ?");
        $upd->execute([$cant, $user_id, $producto_id]);

        // Devolver subtotal actualizado para el ítem
        $priceStmt = $db->prepare("SELECT COALESCE(precio_oferta, precio) AS precio FROM productos WHERE id = ?");
        $priceStmt->execute([$producto_id]);
        $precio = (float) $priceStmt->fetchColumn();

        // Totales actualizados
        $items = getCartItems($db, $user_id);
        $total = array_sum(array_column($items, 'subtotal'));
        $count = getCartCount($db, $user_id);

        $msg = ($cant < $cantidad) ? 'Cantidad limitada al stock disponible.' : 'Cantidad actualizada.';
        jsonResponse(true, [
            'item_subtotal' => $precio * $cant,
            'total'         => $total,
            'grand_total'   => $total + SHIPPING_COST,
        ], $msg, $count);
    }

    // ── DELETE: Eliminar producto del carrito ────────────────────────────────
    if ($method === 'DELETE') {
        // DELETE puede venir en query string también
        if ($producto_id <= 0) {
            $producto_id = isset($_GET['producto_id']) ? (int) $_GET['producto_id'] : 0;
        }
        if ($producto_id <= 0) {
            jsonResponse(false, null, 'ID de producto requerido.', getCartCount($db, $user_id), 400);
        }

        $del = $db->prepare("DELETE FROM carrito WHERE usuario_id = ? AND producto_id = ?");
        $del->execute([$user_id, $producto_id]);

        if ($del->rowCount() === 0) {
            jsonResponse(false, null, 'El producto no estaba en tu bolsa.', getCartCount($db, $user_id), 404);
        }

        logAuditoria($user_id, "AJAX: Removió producto ID {$producto_id} del carrito", 'carrito');

        $items = getCartItems($db, $user_id);
        $total = array_sum(array_column($items, 'subtotal'));
        $count = getCartCount($db, $user_id);

        jsonResponse(true, [
            'total'       => $total,
            'grand_total' => $total + SHIPPING_COST,
        ], 'Producto removido de tu bolsa.', $count);
    }

    jsonResponse(false, null, 'Método HTTP no soportado.', 0, 405);

} catch (Exception $e) {
    error_log('[EcoTienda Cart API] ' . $e->getMessage());
    jsonResponse(false, null, 'Error interno del servidor. Intenta de nuevo.', 0, 500);
}
