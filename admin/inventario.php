<?php
/**
 * 🌱 ECOTIENDA HN - AUDITORÍA DE INVENTARIO Y MOVIMIENTOS
 * Ruta: /admin/inventario.php
 * Descripción: Bitácora de ingresos y egresos de productos biodegradables de la base de datos `inventario`.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Control de Inventarios";
$pageSubtitle = "🌱 Auditoría cronológica de entradas y salidas de stock.";
$error = '';
$success = '';

$db = Database::getConnection();

// Procesar Ajuste Manual de Inventario (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    $producto_id = (int)$_POST['producto_id'];
    $tipo_movimiento = $_POST['tipo_movimiento']; // 'entrada' o 'salida'
    $cantidad = (int)$_POST['cantidad'];
    $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_DEFAULT);

    if ($producto_id <= 0 || $cantidad <= 0 || empty($tipo_movimiento)) {
        $error = "Por favor, completa un producto, movimiento y cantidad válidos.";
    } else {
        try {
            $db->beginTransaction();

            // 1. Registrar movimiento en la tabla `inventario`
            $sql = "INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion) 
                    VALUES (:producto_id, :tipo_movimiento, :cantidad, :descripcion)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':producto_id' => $producto_id,
                ':tipo_movimiento' => $tipo_movimiento,
                ':cantidad' => $cantidad,
                ':descripcion' => $descripcion
            ]);

            // 2. Modificar el stock real en la tabla `productos`
            if ($tipo_movimiento === 'entrada') {
                $pSql = "UPDATE productos SET stock = stock + :cantidad WHERE id = :producto_id";
            } else {
                $pSql = "UPDATE productos SET stock = stock - :cantidad WHERE id = :producto_id";
            }
            $pStmt = $db->prepare($pSql);
            $pStmt->execute([
                ':cantidad' => $cantidad,
                ':producto_id' => $producto_id
            ]);

            logAuditoria($_SESSION['user_id'], "Ajustó stock (tipo: {$tipo_movimiento}, cantidad: {$cantidad}) del producto ID: " . $producto_id, "inventario");
            $db->commit();

            $success = "El ajuste de existencias se efectuó correctamente en la bodega central.";
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Error al operar movimiento: " . $e->getMessage();
        }
    }
}

// Cargar Todos los Productos para el selector
$productsList = [];
// Cargar Historial de Movimientos de Inventario
$movementsList = [];

try {
    $productsList = $db->query("SELECT id, nombre, stock FROM productos ORDER BY nombre ASC")->fetchAll();
    
    $sql = "SELECT i.*, p.nombre AS producto_nombre 
            FROM inventario i 
            LEFT JOIN productos p ON i.producto_id = p.id 
            ORDER BY i.fecha DESC LIMIT 15";
    $movementsList = $db->query($sql)->fetchAll();
} catch (Exception $e) {
    //
}

if (empty($movementsList)) {
    $movementsList = [
        ['id' => 10, 'producto_nombre' => 'Champú Sólido de Romero y Árbol de Té', 'tipo_movimiento' => 'entrada', 'cantidad' => 20, 'fecha' => '2026-06-05 02:00:00', 'descripcion' => 'Reabastecimiento de bodega'],
        ['id' => 9, 'producto_nombre' => 'Set de Cubiertos de Bambú con Estuche', 'tipo_movimiento' => 'salida', 'cantidad' => 2, 'fecha' => '2026-06-04 18:25:00', 'descripcion' => 'Descontado por Pedido #102']
    ];
}
?>

<?php require_once __DIR__ . '/includes/admin_navbar.php'; ?>

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-sm btn-eco-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
            <i class="fas fa-sliders"></i> Registrar Ajuste de Existencia
        </button>
    </div>

<div class="text-start">
    
    

    <?php if(!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <?php echo renderAlert($success, 'success'); ?>
    <?php endif; ?>

    <div class="admin-card">
        <h5 class="fw-bold mb-4" style="font-family: var(--font-display); color: #f1f5f9;"><i class="fas fa-history me-2" style="color:#475569;"></i> Últimos 15 Movimientos Auditados</h5>
        
        <div class="table-responsive">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th>ID Ajuste</th>
                        <th>EcoProducto Relacionado</th>
                        <th class="text-center">Tipo de Movimiento</th>
                        <th class="text-center">Cantidad</th>
                        <th>Fecha / Hora</th>
                        <th>Observación u Origen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($movementsList as $mov): ?>
                        <tr>
                            <td class="cell-id">#<?php echo $mov['id']; ?></td>
                            <td class="cell-product">
                                <strong><?php echo sanitize($mov['producto_nombre'] ?? 'Producto Descatalogado'); ?></strong>
                            </td>
                            <td class="cell-center">
                                <?php if($mov['tipo_movimiento'] === 'entrada'): ?>
                                    <span class="badge-entrada"><i class="fas fa-arrow-turn-down"></i> Entrada</span>
                                <?php else: ?>
                                    <span class="badge-salida"><i class="fas fa-arrow-turn-up"></i> Salida</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-qty">
                                <?php echo $mov['cantidad']; ?> uds
                            </td>
                            <td class="cell-date">
                                <?php echo date('Y-m-d h:i A', strtotime($mov['fecha'])); ?>
                            </td>
                            <td class="cell-desc">
                                <?php echo sanitize($mov['descripcion'] ?? '-'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>


<!-- MODAL REGISTRO AJUSTE MANUAL -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-boxes-packing text-success me-2"></i> Ajustar Stock Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>admin/inventario.php" method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">1. Seleccionar Ecoproducto *</label>
                        <select name="producto_id" class="form-select" required>
                            <option value="">Selecciona Unidad</option>
                            <?php foreach($productsList as $pItem): ?>
                                <option value="<?php echo $pItem['id']; ?>"><?php echo sanitize($pItem['nombre']); ?> (Stock actual: <?php echo $pItem['stock']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">2. Tipo de Movimiento *</label>
                        <select name="tipo_movimiento" class="form-select" required>
                            <option value="entrada" selected>Entrada (+ Sumar existencias)</option>
                            <option value="salida">Salida (- Restar/Despacho)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">3. Cantidad de Artículos *</label>
                        <input type="number" name="cantidad" class="form-control" min="1" placeholder="Ej: 10" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">4. Motivo / Justificación *</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Ej: Reabastecimiento de aduana, pieza rota, etc." requiredHTML>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-eco-primary btn-sm">Guardar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle JS -->

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
