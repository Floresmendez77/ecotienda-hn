<?php
/**
 * 🌱 ECOTIENDA HN - CHECKOUT: PAGO MANUAL POR TRANSFERENCIA (APP MÓVIL)
 * Ruta: /api/checkout-pago-manual.php  (ARCHIVO NUEVO — Fase 7)
 *
 * Mismo patrón de validación que api/checkout-crear-orden.php (revalida
 * producto/stock/cupón en servidor, nunca confía en lo que mandó la app),
 * pero sin PayPal: crea el pedido en estado 'pendiente', registra el pago
 * como 'pendiente' de conciliación manual, y guarda la foto del
 * comprobante reutilizando uploadImage() (mismo patrón que
 * api/productos-crud.php).
 *
 * A diferencia de checkout-crear-orden.php, esta petición llega como
 * multipart/form-data (por el archivo), no como JSON body. Los ítems del
 * carrito viajan en un campo de texto "items" con un JSON string, que se
 * decodifica igual que el body en el otro endpoint.
 *
 * Petición esperada (POST, multipart/form-data):
 *   items            JSON string: [{"producto_id":1,"cantidad":2}, ...]
 *   correo           string, obligatorio
 *   cupon_codigo     string, opcional
 *   comprobante      archivo (imagen), obligatorio
 *
 * Respuesta exitosa:
 *   { "success": true, "pedido_id": 48, "total_lempiras": 366.00, "descuento": 24.00 }
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError(405, 'Método no permitido. Usa POST.');
}

// ── Fase 8: checkout ahora exige login ──────────────────────────────────
$usuarioAuth = requireApiAuth();
$usuarioId   = (int)$usuarioAuth['id'];
$correo      = $usuarioAuth['correo'];

$itemsRaw    = $_POST['items'] ?? '';
$items       = json_decode($itemsRaw, true);
$cuponCodigo = strtoupper(trim($_POST['cupon_codigo'] ?? ''));

if (!is_array($items) || count($items) === 0) {
    responderError(400, 'El carrito está vacío o llegó en un formato inválido.');
}
if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
    responderError(400, 'Debes adjuntar una foto del comprobante de transferencia.');
}

$db = Database::getConnection();

// ── Revalidar productos, precios y stock (idéntico a checkout-crear-orden.php) ──
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

// ── Revalidar cupón (idéntico a checkout-crear-orden.php) ──────────────────
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

    // ── Un cupón por cliente (Fase 8: por usuario_id, ya no por correo) ──
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

// La imagen se sube ANTES de abrir la transacción: si la subida falla no
// queremos dejar un pedido a medio crear en la base de datos.
$comprobanteNombre = uploadImage($_FILES['comprobante'], __DIR__ . '/../assets/uploads/comprobantes/');
if (!$comprobanteNombre) {
    responderError(400, 'El comprobante debe ser JPG, PNG, WEBP o GIF y pesar menos de 3 MB.');
}
$comprobanteRuta = 'assets/uploads/comprobantes/' . $comprobanteNombre;

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

    registrarHistorialPedido($db, $pedidoId, 'pendiente', 'Pedido creado (transferencia, app) — comprobante subido', $usuarioId);

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

    // Mismo guardia atómico contra el límite de usos que checkout-crear-orden.php:
    // el UPDATE re-verifica limite_usos en el propio WHERE, así que dos pedidos
    // simultáneos con el mismo cupón nunca pueden pasarse del límite.
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

    // ── Descontar stock (Fase 7 — fix) ───────────────────────────────
    // Se descuenta al crear el pedido, igual que hace checkout.php en el
    // sitio web para transferencia: el comprobante ya se adjuntó, así que
    // reservamos el producto mientras el admin concilia el pago. Si el
    // admin rechaza el comprobante más adelante, admin/pedidos.php
    // devuelve este stock automáticamente.
    descontarStockPedido($db, $pedidoId, 'Salida por Pedido (pago manual, en revisión)');

    // metodo_pago_id = 1 → 'Transferencia' (ver tabla metodos_pago).
    // estado = 'pendiente': queda a la espera de que el admin concilie el
    // comprobante en admin/pedidos.php (acción approve_payment).
    $insertPago = $db->prepare(
        "INSERT INTO pagos (pedido_id, metodo_pago_id, monto, comprobante_imagen, estado)
         VALUES (:pedido_id, 1, :monto, :comprobante, 'pendiente')"
    );
    $insertPago->execute([
        'pedido_id'   => $pedidoId,
        'monto'       => $total,
        'comprobante' => $comprobanteRuta,
    ]);

    $db->commit();

    logAuditoria($usuarioId, "Checkout (pago manual): creó pedido ID {$pedidoId} por L. {$total}" .
        ($descuento > 0 ? " (cupón {$cuponCodigo}, descuento L. {$descuento})" : ""), 'pedidos');

    echo json_encode([
        'success'        => true,
        'pedido_id'      => $pedidoId,
        'total_lempiras' => $total,
        'descuento'      => $descuento,
        'mensaje'        => 'Pedido registrado. Tu comprobante quedó en revisión — te avisaremos por correo cuando se apruebe.',
    ], JSON_UNESCAPED_UNICODE);

} catch (CuponNoDisponibleException $e) {
    $db->rollBack();
    // Este SÍ debe llegar tal cual a la app -- es la razón puntual que
    // pediste: si el cupón no sirve, que se lo diga claro al usuario,
    // no un error genérico de pago.
    responderError(409, $e->getMessage());

} catch (StockInsuficienteException $e) {
    $db->rollBack();
    // Igual que el cupón: todavía no se ha cobrado nada (el comprobante
    // es solo una foto pendiente de conciliar), así que es seguro fallar
    // aquí con un mensaje claro en vez de crear un pedido que nunca se
    // podrá surtir.
    responderError(409, $e->getMessage());

} catch (Exception $e) {
    $db->rollBack();
    logError('ERROR', 'checkout-pago-manual: fallo al crear pedido', [
        'file' => $e->getFile(), 'line' => $e->getLine(), 'mensaje' => $e->getMessage(),
    ]);
    responderError(500, 'No se pudo registrar el pedido. Intenta de nuevo.');
}

/**
 * Excepción específica para el caso "el cupón se agotó justo antes de
 * pagar", así el catch la distingue de cualquier otro fallo interno y le
 * pasa el mensaje real al usuario en vez de uno genérico.
 */
class CuponNoDisponibleException extends Exception {}