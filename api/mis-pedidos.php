<?php
/**
 * 🌱 ECOTIENDA HN - MIS PEDIDOS CON TIMELINE (APP MÓVIL)
 * Ruta: /api/mis-pedidos.php  (ARCHIVO NUEVO — Fase 9)
 *
 * Devuelve los pedidos del usuario autenticado, cada uno con su detalle de
 * productos y su historial de estados (pedido_historial), para que la app
 * pinte el timeline en "Mis pedidos".
 *
 * Petición: GET, requiere Authorization: Bearer <token>
 *   Opcional: ?pedido_id=68 para traer solo un pedido puntual (detalle).
 *
 * Respuesta:
 *   {
 *     "success": true,
 *     "pedidos": [
 *       {
 *         "id": 68, "total": 375.00, "estado": "pagado",
 *         "fecha": "2026-09-01 02:56:07", "metodo_pago": "PayPal",
 *         "token_recibo": "...",
 *         "items": [{ "producto_id":10, "nombre":"...", "imagen":"...", "cantidad":1, "precio":225.00 }],
 *         "historial": [
 *           { "estado": "pendiente", "nota": "Pedido creado (PayPal, app)", "fecha": "..." },
 *           { "estado": "pagado", "nota": "Pago aprobado vía PayPal", "fecha": "..." }
 *         ]
 *       }
 *     ]
 *   }
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

function responderError(int $httpCode, string $mensaje): void
{
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderError(405, 'Método no permitido. Usa GET.');
}

$usuarioAuth = requireApiAuth();
$usuarioId   = (int)$usuarioAuth['id'];

$pedidoIdFiltro = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : null;

$db = Database::getConnection();

try {
    $sql = "SELECT p.id, p.total, p.subtotal, p.descuento, p.envio, p.estado,
                   p.cupon_codigo, p.token_recibo, p.fecha,
                   mp.nombre AS metodo_pago,
                   pa.estado AS pago_estado
            FROM pedidos p
            LEFT JOIN pagos pa ON pa.pedido_id = p.id
            LEFT JOIN metodos_pago mp ON mp.id = pa.metodo_pago_id
            WHERE p.usuario_id = :usuario_id"
            . ($pedidoIdFiltro ? " AND p.id = :pedido_id" : "") .
            " ORDER BY p.fecha DESC";

    $stmt = $db->prepare($sql);
    $params = [':usuario_id' => $usuarioId];
    if ($pedidoIdFiltro) {
        $params[':pedido_id'] = $pedidoIdFiltro;
    }
    $stmt->execute($params);
    $pedidosRows = $stmt->fetchAll();

    if ($pedidoIdFiltro && count($pedidosRows) === 0) {
        // No se revela si el pedido existe pero es de otro usuario.
        responderError(404, 'Pedido no encontrado.');
    }

    $pedidoIds = array_column($pedidosRows, 'id');
    $itemsPorPedido     = [];
    $historialPorPedido = [];

    if (count($pedidoIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));

        $itemsStmt = $db->prepare(
            "SELECT d.pedido_id, d.producto_id, d.cantidad, d.precio, d.subtotal,
                    pr.nombre, pr.imagen_principal
             FROM detalle_pedido d
             LEFT JOIN productos pr ON pr.id = d.producto_id
             WHERE d.pedido_id IN ($placeholders)"
        );
        $itemsStmt->execute($pedidoIds);
        foreach ($itemsStmt->fetchAll() as $item) {
            $itemsPorPedido[$item['pedido_id']][] = [
                'producto_id' => (int)$item['producto_id'],
                'nombre'      => $item['nombre'] ?? 'Producto no disponible',
                'imagen'      => $item['imagen_principal'],
                'cantidad'    => (int)$item['cantidad'],
                'precio'      => (float)$item['precio'],
                'subtotal'    => (float)$item['subtotal'],
            ];
        }

        $historialStmt = $db->prepare(
            "SELECT pedido_id, estado, nota, fecha
             FROM pedido_historial
             WHERE pedido_id IN ($placeholders)
             ORDER BY fecha ASC, id ASC"
        );
        $historialStmt->execute($pedidoIds);
        foreach ($historialStmt->fetchAll() as $paso) {
            $historialPorPedido[$paso['pedido_id']][] = [
                'estado' => $paso['estado'],
                'nota'   => $paso['nota'],
                'fecha'  => $paso['fecha'],
            ];
        }
    }

    $pedidos = [];
    foreach ($pedidosRows as $p) {
        $pid = (int)$p['id'];
        $pedidos[] = [
            'id'            => $pid,
            'total'         => (float)$p['total'],
            'subtotal'      => (float)$p['subtotal'],
            'descuento'     => (float)$p['descuento'],
            'envio'         => (float)$p['envio'],
            'estado'        => $p['estado'],
            'cupon_codigo'  => $p['cupon_codigo'],
            'token_recibo'  => $p['token_recibo'],
            'fecha'         => $p['fecha'],
            'metodo_pago'   => $p['metodo_pago'] ?? 'Transferencia',
            'pago_estado'   => $p['pago_estado'] ?? 'pendiente',
            'items'         => $itemsPorPedido[$pid] ?? [],
            'historial'     => $historialPorPedido[$pid] ?? [],
        ];
    }

    echo json_encode(['success' => true, 'pedidos' => $pedidos], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    logError('ERROR', 'mis-pedidos: fallo al consultar pedidos', [
        'file' => $e->getFile(), 'line' => $e->getLine(), 'mensaje' => $e->getMessage(),
    ]);
    responderError(500, 'No se pudieron cargar tus pedidos. Intenta de nuevo.');
}
