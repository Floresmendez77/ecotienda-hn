<?php
/**
 * 🌱 ECOTIENDA HN - DESCARGA DE RECIBO EN PDF
 * Ruta: /recibo-pedido.php  (ARCHIVO NUEVO)
 * Descripción: Página pública que sirve el PDF del recibo de un pedido.
 *              Protegida por token (pedido_id + token_recibo), ya que el
 *              checkout de invitado no tiene sesión ni usuario_id y el
 *              pedido_id es un entero incremental adivinable.
 *
 * Uso: /recibo-pedido.php?pedido_id=47&token=<token_recibo generado en
 *      checkout-capturar-pago.php>
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/recibo_pdf.php';

$pedidoId = (int)($_GET['pedido_id'] ?? 0);
$token    = trim($_GET['token'] ?? '');

if ($pedidoId <= 0 || $token === '') {
    http_response_code(400);
    exit('Solicitud inválida: falta pedido_id o token.');
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT token_recibo, estado FROM pedidos WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $pedidoId]);
$pedido = $stmt->fetch();

// hash_equals() en vez de === : comparación en tiempo constante, evita
// timing attacks al comparar el token recibido contra el guardado.
if (!$pedido || empty($pedido['token_recibo']) || !hash_equals($pedido['token_recibo'], $token)) {
    http_response_code(404);
    exit('Recibo no encontrado.');
}

if ($pedido['estado'] !== 'pagado') {
    http_response_code(403);
    exit('Este pedido todavía no tiene un pago confirmado.');
}

$pdfContenido = generarReciboPdfPedido($pedidoId);
if ($pdfContenido === null) {
    http_response_code(500);
    exit('No se pudo generar el recibo.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="recibo-pedido-' . $pedidoId . '.pdf"');
header('Content-Length: ' . strlen($pdfContenido));
echo $pdfContenido;
