<?php
/**
 * 🌱 ECOTIENDA HN - CHECKOUT: CREAR ORDEN (APP MÓVIL)
 * Ruta: /api/checkout-crear-orden.php  (ACTUALIZACIÓN — Fase 6: exige correo)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paypal.php';
require_once __DIR__ . '/../includes/api_auth.php';

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

// ── Fase 8: checkout ahora exige login ──────────────────────────────────
$usuarioAuth = requireApiAuth();
$usuarioId   = (int)$usuarioAuth['id'];
$correo      = $usuarioAuth['correo'];

$datos       = json_decode(file_get_contents('php://input'), true);
$items       = $datos['items'] ?? null;
$cuponCodigo = strtoupper(trim($datos['cupon_codigo'] ?? ''));

if (!is_array($items) || count($items) === 0) {
    responderError(400, 'El carrito está vacío o llegó en un formato inválido.');
}

$db = Database::getConnection();

$detalle  = [];
$subtotal = 0.0;

foreach ($items as $item) {
    $productoId = (int)($item['producto_id'] ?? 0);
    $cantidad   = (int)($item['cantidad'] ?? 0);

    if ($productoId <= 0 || $cantidad <= 0) {
        responderError(400, 'Cada ítem del carrito necesita producto_id y cantidad válidos.');
    }

    $stmt = $db->prepare(
        "SELECT id, nombre, stock, COALESCE(precio_oferta, precio) AS precio_actual
         FROM productos
         WHERE id = :id AND estado = 'activo'
         LIMIT 1"
    );
    $stmt->execute(['id' => $productoId]);
    $producto = $stmt->fetch();

    if (!$producto) {
        responderError(404, "El producto con ID {$productoId} ya no está disponible.");
    }
    if ($producto['stock'] < $cantidad) {
        responderError(409, "Solo quedan {$producto['stock']} unidades de '{$producto['nombre']}'.");
    }

    $precio        = (float)$producto['precio_actual'];
    $subtotalLinea = round($precio * $cantidad, 2);
    $subtotal     += $subtotalLinea;

    $detalle[] = [
        'producto_id' => $productoId,
        'cantidad'    => $cantidad,
        'precio'      => $precio,
        'subtotal'    => $subtotalLinea,
    ];
}

$descuento = 0.0;

if ($cuponCodigo !== '') {
    $cuponStmt = $db->prepare(
        "SELECT * FROM cupones
         WHERE codigo = :codigo
           AND estado = 'activo'
           AND fecha_inicio <= CURDATE()
           AND fecha_fin    >= CURDATE()
         LIMIT 1"
    );
    $cuponStmt->execute([':codigo' => $cuponCodigo]);
    $cupon = $cuponStmt->fetch();

    if (!$cupon) {
        responderError(404, "El cupón {$cuponCodigo} no es válido o ya expiró.");
    }
    if ($cupon['limite_usos'] > 0 && $cupon['cantidad_usos'] >= $cupon['limite_usos']) {
        responderError(409, "El cupón {$cuponCodigo} ya alcanzó su límite de usos.");
    }

    // ── Un cupón por cliente ────────────────────────────────────────────
    // Fase 8: ahora se identifica por usuario_id (login obligatorio),
    // más confiable que el correo de invitado que se usaba antes.
    $yaUsadoStmt = $db->prepare(
        "SELECT COUNT(*) FROM pedidos
         WHERE cupon_codigo = :codigo
           AND usuario_id = :usuario_id
           AND estado NOT IN ('cancelado')"
    );
    $yaUsadoStmt->execute([':codigo' => $cuponCodigo, ':usuario_id' => $usuarioId]);
    if ((int)$yaUsadoStmt->fetchColumn() > 0) {
        responderError(409, "Ya usaste el cupón {$cuponCodigo} anteriormente. Cada cliente puede usar cada cupón una sola vez.");
    }

    $descuento = ($cupon['tipo'] === 'porcentaje')
        ? round($subtotal * ($cupon['valor'] / 100), 2)
        : min((float)$cupon['valor'], $subtotal);
}

$envio = SHIPPING_COST;
$total = round($subtotal - $descuento + $envio, 2);

try {
    $db->beginTransaction();

    $insertPedido = $db->prepare(
        "INSERT INTO pedidos (usuario_id, correo_invitado, subtotal, descuento, envio, total, cupon_codigo, estado)
         VALUES (:usuario_id, :correo, :subtotal, :descuento, :envio, :total, :cupon_codigo, 'pendiente')"
    );
    $insertPedido->execute([
        'usuario_id'   => $usuarioId,
        'correo'       => $correo,
        'subtotal'     => $subtotal,
        'descuento'    => $descuento,
        'envio'        => $envio,
        'total'        => $total,
        'cupon_codigo' => $cuponCodigo !== '' ? $cuponCodigo : null,
    ]);
    $pedidoId = (int)$db->lastInsertId();

    registrarHistorialPedido($db, $pedidoId, 'pendiente', 'Pedido creado (PayPal, app)', $usuarioId);

    $insertDetalle = $db->prepare(
        "INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio, subtotal)
         VALUES (:pedido_id, :producto_id, :cantidad, :precio, :subtotal)"
    );
    foreach ($detalle as $linea) {
        $insertDetalle->execute([
            'pedido_id'   => $pedidoId,
            'producto_id' => $linea['producto_id'],
            'cantidad'    => $linea['cantidad'],
            'precio'      => $linea['precio'],
            'subtotal'    => $linea['subtotal'],
        ]);
    }

    if ($cuponCodigo !== '') {
        $cuponUpdate = $db->prepare(
            "UPDATE cupones
             SET cantidad_usos = cantidad_usos + 1
             WHERE codigo = :codigo
               AND estado = 'activo'
               AND fecha_inicio <= CURDATE()
               AND fecha_fin    >= CURDATE()
               AND (limite_usos = 0 OR cantidad_usos < limite_usos)"
        );
        $cuponUpdate->execute([':codigo' => $cuponCodigo]);

        if ($cuponUpdate->rowCount() === 0) {
            throw new CuponNoDisponibleException(
                "El cupón {$cuponCodigo} ya no está disponible. Quítalo e intenta de nuevo."
            );
        }
    }

    $paypal = paypalCrearOrden($total);

    $db->commit();

    logAuditoria($usuarioId, "Checkout: creó pedido ID {$pedidoId} por L. {$total}" .
        ($descuento > 0 ? " (cupón {$cuponCodigo}, descuento L. {$descuento})" : ""), 'pedidos');

    echo json_encode([
        'success'            => true,
        'pedido_id'          => $pedidoId,
        'paypal_order_id'    => $paypal['paypal_order_id'],
        'paypal_approve_url' => $paypal['approve_url'],
        'total_lempiras'     => $total,
        'total_usd'          => $paypal['monto_usd'],
        'descuento'          => $descuento,
    ], JSON_UNESCAPED_UNICODE);

} catch (CuponNoDisponibleException $e) {
    $db->rollBack();
    // Este SÍ debe llegar tal cual a la app: si el cupón ya no sirve, que
    // se lo diga claro al usuario ("cupón no válido"), no un error
    // genérico de pago que lo hace reintentar sin entender por qué falla.
    responderError(409, $e->getMessage());

} catch (Exception $e) {
    $db->rollBack();
    logError('ERROR', 'checkout-crear-orden: fallo al crear pedido/orden PayPal', [
        'file' => $e->getFile(), 'line' => $e->getLine(), 'mensaje' => $e->getMessage(),
    ]);
    responderError(500, 'No se pudo iniciar el pago. Intenta de nuevo.');
}

/**
 * Excepción específica para el caso "el cupón se agotó justo antes de
 * pagar", así el catch la distingue de cualquier otro fallo interno y le
 * pasa el mensaje real al usuario en vez de uno genérico.
 */
class CuponNoDisponibleException extends Exception {}