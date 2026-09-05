<?php
/**
 * 🌱 ECOTIENDA HN - INVENTARIO (API ADMIN)
 * Ruta: /api/admin/inventario.php
 * Descripción: Espejo de /admin/inventario.php para la app móvil.
 *
 * GET  (sin accion)      → lista de productos (para el selector) + últimos
 *                          movimientos de inventario
 * POST ?accion=ajustar   → { producto_id, tipo_movimiento, cantidad, descripcion }
 *                          registra el movimiento y ajusta el stock real
 *                          (misma transacción que admin/inventario.php)
 */

require_once __DIR__ . '/../../includes/api_auth.php';

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
// LISTAR
// ────────────────────────────────────────────────────────────
if ($accion === 'listar' && $metodo === 'GET') {
    $productos = $pdo->query(
        "SELECT id, nombre, stock FROM productos ORDER BY nombre ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $movimientos = $pdo->query("
        SELECT i.*, p.nombre AS producto_nombre
        FROM inventario i
        LEFT JOIN productos p ON i.producto_id = p.id
        ORDER BY i.fecha DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

    responderApi(true, ['productos' => $productos, 'movimientos' => $movimientos]);
}

// ────────────────────────────────────────────────────────────
// AJUSTAR STOCK (entrada / salida)
// ────────────────────────────────────────────────────────────
if ($accion === 'ajustar' && $metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];

    $producto_id     = (int)($datos['producto_id'] ?? 0);
    $tipo_movimiento = $datos['tipo_movimiento'] ?? '';
    $cantidad        = (int)($datos['cantidad'] ?? 0);
    $descripcion     = trim($datos['descripcion'] ?? '');

    if ($producto_id <= 0 || $cantidad <= 0 || !in_array($tipo_movimiento, ['entrada', 'salida'], true)) {
        responderApi(false, ['error' => 'Completa un producto, movimiento y cantidad válidos.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            "INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion)
             VALUES (:producto_id, :tipo_movimiento, :cantidad, :descripcion)"
        )->execute([
            ':producto_id'     => $producto_id,
            ':tipo_movimiento' => $tipo_movimiento,
            ':cantidad'        => $cantidad,
            ':descripcion'     => $descripcion,
        ]);

        $sqlStock = $tipo_movimiento === 'entrada'
            ? "UPDATE productos SET stock = stock + :cantidad WHERE id = :producto_id"
            : "UPDATE productos SET stock = stock - :cantidad WHERE id = :producto_id";

        $pdo->prepare($sqlStock)->execute([
            ':cantidad'    => $cantidad,
            ':producto_id' => $producto_id,
        ]);

        logAuditoria(
            $admin['id'],
            "Ajustó stock (tipo: {$tipo_movimiento}, cantidad: {$cantidad}) del producto ID: {$producto_id} (desde app móvil)",
            'inventario'
        );
        $pdo->commit();

        responderApi(true, ['mensaje' => 'El ajuste de existencias se efectuó correctamente.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        responderApi(false, ['error' => 'Error al operar movimiento: ' . $e->getMessage()], 500);
    }
}

responderApi(false, ['error' => 'Acción no reconocida.'], 400);
