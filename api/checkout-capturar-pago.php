<?php
/**
 * 🌱 ECOTIENDA HN - CHECKOUT: CAPTURAR PAGO (APP MÓVIL)
 * Ruta: /api/checkout-capturar-pago.php  (ACTUALIZACIÓN — Fase 6)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paypal.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/recibo_pdf.php';
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

// ── Fase 8: exige login y valida que el pedido sea del que lo pide ──────
$usuarioAuth = requireApiAuth();

$datos          = json_decode(file_get_contents('php://input'), true);
$pedidoId       = (int)($datos['pedido_id'] ?? 0);
$paypalOrderId  = trim($datos['paypal_order_id'] ?? '');

if ($pedidoId <= 0 || $paypalOrderId === '') {
    responderError(400, 'Faltan pedido_id o paypal_order_id.');
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT id, usuario_id, total, estado, token_recibo FROM pedidos WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    responderError(404, 'Pedido no encontrado.');
}
if ((int)$pedido['usuario_id'] !== (int)$usuarioAuth['id']) {
    // No se revela si el pedido existe o no (evita enumeración de IDs
    // ajenos) — mismo código y mensaje que "no encontrado".
    responderError(404, 'Pedido no encontrado.');
}
if ($pedido['estado'] === 'pagado') {
    // Idempotencia: reusa el token ya generado, no vuelve a capturar en PayPal.
    echo json_encode([
        'success'      => true,
        'pedido_id'    => $pedidoId,
        'estado'       => 'pagado',
        'token_recibo' => $pedido['token_recibo'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $captura = paypalCapturarOrden($paypalOrderId);

    if (!$captura['capturado']) {
        responderError(402, 'PayPal no pudo completar el pago (estado: ' . $captura['estado'] . ').');
    }

    $tokenRecibo = bin2hex(random_bytes(32));

    $db->beginTransaction();

    $db->prepare("UPDATE pedidos SET estado = 'pagado', token_recibo = :token WHERE id = :id")
       ->execute(['id' => $pedidoId, 'token' => $tokenRecibo]);

    $db->prepare(
        "INSERT INTO pagos (pedido_id, metodo_pago_id, monto, referencia_paypal, estado)
         VALUES (:pedido_id, 2, :monto, :referencia_paypal, 'aprobado')"
    )->execute([
        'pedido_id'         => $pedidoId,
        'monto'             => $pedido['total'],
        'referencia_paypal' => $captura['capture_id'],
    ]);

    registrarHistorialPedido($db, $pedidoId, 'pagado', 'Pago aprobado vía PayPal', (int)$usuarioAuth['id']);

    // ── Descontar stock (Fase 7 — fix) ───────────────────────────────
    // Se descuenta AQUÍ (al confirmar el pago), no en checkout-crear-orden.php,
    // porque entre crear la orden y que el cliente apruebe en PayPal puede
    // pasar un buen rato (o nunca aprobarlo) — descontar antes reservaría
    // el producto para alguien que quizás ni termine de pagar.
    //
    // Si el stock ya no alcanza (carrera rarísima: checkout-crear-orden.php
    // ya lo validó hace instantes), NO hacemos fallar esta respuesta: el
    // dinero ya lo cobró PayPal y no tiene sentido decirle a alguien que ya
    // pagó "no se pudo procesar tu pago". Se deja como aviso para
    // resolver el backorder manualmente desde el panel admin.
    try {
        descontarStockPedido($db, $pedidoId, 'Salida por Pedido (PayPal app)');
    } catch (StockInsuficienteException $e) {
        logError('WARNING', 'checkout-capturar-pago: stock insuficiente al capturar pago ya cobrado por PayPal — requiere backorder manual', [
            'pedido_id' => $pedidoId, 'mensaje' => $e->getMessage(),
        ]);
    }

    $db->commit();

    logAuditoria((int)$usuarioAuth['id'], "Pedido ID {$pedidoId} pagado vía PayPal Sandbox", 'pedidos');

    // Envío del recibo por correo: nunca debe tumbar la respuesta de éxito
    // del pago si falla (ej. SMTP caído). Se registra el error y se sigue.
    try {
        $pdfContenido = generarReciboPdfPedido($pedidoId);
        if ($pdfContenido !== null) {
            notify_recibo_pedido_invitado($db, $pedidoId, $pdfContenido);
        }
    } catch (\Throwable $e) {
        logError('WARNING', 'checkout-capturar-pago: no se pudo enviar el recibo por correo', [
            'pedido_id' => $pedidoId, 'mensaje' => $e->getMessage(),
        ]);
    }

    echo json_encode([
        'success'      => true,
        'pedido_id'    => $pedidoId,
        'estado'       => 'pagado',
        'token_recibo' => $tokenRecibo,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    logError('ERROR', 'checkout-capturar-pago: fallo al capturar pago', [
        'file' => $e->getFile(), 'line' => $e->getLine(), 'mensaje' => $e->getMessage(),
    ]);
    responderError(500, 'No se pudo confirmar el pago. Intenta de nuevo.');
}