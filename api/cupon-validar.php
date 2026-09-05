<?php
/**
 * 🌱 ECOTIENDA HN - CUPONES: VALIDAR CÓDIGO (APP MÓVIL)
 * Ruta: /api/cupon-validar.php  (ARCHIVO NUEVO)
 * Descripción: Valida un código de cupón contra la tabla `cupones` y calcula
 *              el descuento sobre el subtotal recibido. Replica exactamente
 *              la lógica de negocio de checkout.php del sitio web (líneas
 *              ~106-141) para mantener el mismo comportamiento en ambas
 *              plataformas.
 *
 *              Este endpoint es solo para MOSTRAR el descuento en el carrito
 *              antes de pagar — NO reserva el cupón ni toca cantidad_usos.
 *              api/checkout-crear-orden.php siempre vuelve a validar el
 *              cupón contra la base de datos real al colocar el pedido y
 *              nunca confía en lo que haya devuelto este endpoint (pudo
 *              pasar tiempo o agotarse el límite de usos entre medio).
 *
 * Petición esperada (POST, JSON):
 *   { "codigo": "ECO10", "subtotal": 290.00 }
 *
 * Respuesta exitosa:
 *   {
 *     "success": true,
 *     "codigo": "ECO10",
 *     "tipo": "porcentaje",
 *     "valor": 10.00,
 *     "descuento": 29.00
 *   }
 *
 * Respuesta de error:
 *   { "success": false, "error": "..." }
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

$datos    = json_decode(file_get_contents('php://input'), true);
$codigo   = strtoupper(trim($datos['codigo'] ?? ''));
$subtotal = isset($datos['subtotal']) ? (float)$datos['subtotal'] : -1;

if ($codigo === '') {
    responderError(400, 'Ingresa un código de cupón.');
}
if ($subtotal < 0) {
    responderError(400, 'El subtotal recibido no es válido.');
}

$db = Database::getConnection();

try {
    // Misma validación que checkout.php (sitio web): activo, dentro del
    // rango de fechas, y con usos disponibles (0 = ilimitado).
    $stmt = $db->prepare(
        "SELECT * FROM cupones
         WHERE codigo = :codigo
           AND estado = 'activo'
           AND fecha_inicio <= CURDATE()
           AND fecha_fin    >= CURDATE()
         LIMIT 1"
    );
    $stmt->execute([':codigo' => $codigo]);
    $cupon = $stmt->fetch();

    if (!$cupon) {
        responderError(404, "El cupón {$codigo} no es válido o ya expiró.");
    }
    if ($cupon['limite_usos'] > 0 && $cupon['cantidad_usos'] >= $cupon['limite_usos']) {
        responderError(409, "El cupón {$codigo} ya alcanzó su límite de usos.");
    }

    $descuento = ($cupon['tipo'] === 'porcentaje')
        ? round($subtotal * ($cupon['valor'] / 100), 2)
        : min((float)$cupon['valor'], $subtotal);

    echo json_encode([
        'success'   => true,
        'codigo'    => $cupon['codigo'],
        'tipo'      => $cupon['tipo'],
        'valor'     => (float)$cupon['valor'],
        'descuento' => $descuento,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    logError('ERROR', 'cupon-validar: fallo al validar cupón', [
        'file' => $e->getFile(), 'line' => $e->getLine(), 'mensaje' => $e->getMessage(),
    ]);
    responderError(500, 'Error al validar el cupón. Intenta de nuevo.');
}
