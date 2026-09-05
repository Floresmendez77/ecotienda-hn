<?php
/**
 * 🌱 ECOTIENDA HN - GESTIÓN DE PEDIDOS (API ADMIN)
 * Ruta: /api/admin/pedidos.php
 * Descripción: Espejo de /admin/pedidos.php para la app móvil. Reutiliza
 *              TODA la lógica de negocio ya existente (descontarStockPedido,
 *              restaurarStockPedido, registrarHistorialPedido,
 *              notify_estado_pedido) en vez de duplicarla.
 *
 * GET  (sin accion)                 → lista todos los pedidos
 * GET  ?accion=detalle&id=123       → detalle de un pedido (items + historial)
 * POST ?accion=update_order_state   → { pedido_id, estado }
 * POST ?accion=approve_payment      → { pedido_id, pago_estado }
 * (todas requieren Authorization: Bearer <token> de un admin)
 */

require_once __DIR__ . '/../../includes/api_auth.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

function responderApi(bool $success, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$admin = requireApiAdmin();
$pdo = Database::getConnection();

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? ($metodo === 'GET' ? 'listar' : null);

// ────────────────────────────────────────────────────────────
// LISTAR (misma consulta que admin/pedidos.php, LEFT JOIN para
// incluir pedidos de invitado hechos desde la app)
// ────────────────────────────────────────────────────────────
if ($accion === 'listar' && $metodo === 'GET') {
    $pedidosList = $pdo->query("
        SELECT p.*,
               u.nombre, u.apellido, u.telefono,
               COALESCE(u.correo, p.correo_invitado) AS correo,
               pa.referencia AS pago_ref, pa.estado AS pago_estado, pa.comprobante_imagen,
               mp.nombre AS met_nombre
        FROM pedidos p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN pagos pa ON p.id = pa.pedido_id
        LEFT JOIN metodos_pago mp ON pa.metodo_pago_id = mp.id
        ORDER BY p.fecha DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    responderApi(true, ['pedidos' => $pedidosList]);
}

// ────────────────────────────────────────────────────────────
// DETALLE de un pedido (items + historial de estados)
// ────────────────────────────────────────────────────────────
if ($accion === 'detalle' && $metodo === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        responderApi(false, ['error' => 'ID de pedido inválido.'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT p.*,
               u.nombre, u.apellido, u.telefono,
               COALESCE(u.correo, p.correo_invitado) AS correo,
               pa.referencia AS pago_ref, pa.estado AS pago_estado, pa.comprobante_imagen,
               mp.nombre AS met_nombre
        FROM pedidos p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN pagos pa ON p.id = pa.pedido_id
        LEFT JOIN metodos_pago mp ON pa.metodo_pago_id = mp.id
        WHERE p.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        responderApi(false, ['error' => 'Pedido no encontrado.'], 404);
    }

    $itemsStmt = $pdo->prepare("
        SELECT dp.*, pr.nombre AS producto_nombre, pr.imagen_principal
        FROM detalle_pedido dp
        LEFT JOIN productos pr ON dp.producto_id = pr.id
        WHERE dp.pedido_id = :id
    ");
    $itemsStmt->execute(['id' => $id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $histStmt = $pdo->prepare(
        "SELECT ph.*, u.nombre AS admin_nombre, u.apellido AS admin_apellido
         FROM pedido_historial ph
         LEFT JOIN usuarios u ON ph.usuario_id = u.id
         WHERE ph.pedido_id = :id ORDER BY ph.fecha ASC"
    );
    $histStmt->execute(['id' => $id]);
    $historial = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    responderApi(true, ['pedido' => $pedido, 'items' => $items, 'historial' => $historial]);
}

// ────────────────────────────────────────────────────────────
// ACTUALIZAR ESTADO DEL PEDIDO
// ────────────────────────────────────────────────────────────
if ($accion === 'update_order_state' && $metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    $pedido_id    = (int)($datos['pedido_id'] ?? 0);
    $nuevo_estado = $datos['estado'] ?? '';

    if ($pedido_id <= 0 || $nuevo_estado === '') {
        responderApi(false, ['error' => 'pedido_id y estado son obligatorios.'], 400);
    }

    try {
        $pdo->prepare("UPDATE pedidos SET estado = :estado WHERE id = :id")
            ->execute([':estado' => $nuevo_estado, ':id' => $pedido_id]);

        notify_estado_pedido($pdo, $pedido_id, $nuevo_estado);
        logAuditoria($admin['id'], "Actualizó estado Pedido #{$pedido_id} a {$nuevo_estado} (desde app móvil)", 'pedidos');
        registrarHistorialPedido($pdo, $pedido_id, $nuevo_estado, 'Actualizado por el administrador (app móvil)', $admin['id']);

        responderApi(true, ['mensaje' => "Estado del pedido #{$pedido_id} actualizado a '{$nuevo_estado}'."]);
    } catch (Exception $e) {
        responderApi(false, ['error' => 'Error al actualizar el estado: ' . $e->getMessage()], 500);
    }
}

// ────────────────────────────────────────────────────────────
// APROBAR / RECHAZAR PAGO (conciliación manual) — misma lógica
// de reversión de stock que admin/pedidos.php
// ────────────────────────────────────────────────────────────
if ($accion === 'approve_payment' && $metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    $pedido_id   = (int)($datos['pedido_id'] ?? 0);
    $pago_estado = $datos['pago_estado'] ?? 'pendiente';

    if ($pedido_id <= 0) {
        responderApi(false, ['error' => 'pedido_id inválido.'], 400);
    }

    $pagoAnteriorStmt = $pdo->prepare("SELECT estado FROM pagos WHERE pedido_id = :id LIMIT 1");
    $pagoAnteriorStmt->execute([':id' => $pedido_id]);
    $pagoEstadoAnterior = $pagoAnteriorStmt->fetchColumn();

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE pagos SET estado = :estado WHERE pedido_id = :pedido_id")
            ->execute([':estado' => $pago_estado, ':pedido_id' => $pedido_id]);

        if ($pago_estado === 'aprobado') {
            $pdo->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = :id")->execute([':id' => $pedido_id]);

            if ($pagoEstadoAnterior === 'rechazado') {
                descontarStockPedido($pdo, $pedido_id, 'Reversión de rechazo: pago vuelto a aprobar (app móvil)');
            }

            $pdo->commit();
            logAuditoria($admin['id'], "Aprobó pago Pedido #{$pedido_id} (desde app móvil)", 'pagos');
            registrarHistorialPedido($pdo, $pedido_id, 'pagado', 'Pago conciliado y aprobado por el administrador (app móvil)', $admin['id']);
            responderApi(true, ['mensaje' => 'Pago APROBADO. Pedido configurado como PAGADO.']);

        } elseif ($pago_estado === 'rechazado' && $pagoEstadoAnterior !== 'rechazado') {
            restaurarStockPedido($pdo, $pedido_id, 'Pago rechazado: se devuelve stock reservado (app móvil)');

            $pdo->commit();
            logAuditoria($admin['id'], "Rechazó pago Pedido #{$pedido_id} (stock devuelto, desde app móvil)", 'pagos');
            registrarHistorialPedido($pdo, $pedido_id, 'pago_rechazado', 'Comprobante rechazado por el administrador (app móvil)', $admin['id']);
            responderApi(true, ['mensaje' => 'Pago RECHAZADO. El stock reservado fue devuelto al inventario.']);

        } else {
            $pdo->commit();
            logAuditoria($admin['id'], "Modificó pago Pedido #{$pedido_id} (desde app móvil)", 'pagos');
            responderApi(true, ['mensaje' => "Estado de conciliación actualizado a '{$pago_estado}'."]);
        }
    } catch (StockInsuficienteException $e) {
        $pdo->rollBack();
        responderApi(false, [
            'error' => 'No se pudo aprobar: ' . $e->getMessage() . ' Ajusta el inventario o contacta al cliente antes de reintentar.'
        ], 409);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        responderApi(false, ['error' => 'Error al procesar: ' . $e->getMessage()], 500);
    }
}

responderApi(false, ['error' => 'Acción no reconocida.'], 400);
