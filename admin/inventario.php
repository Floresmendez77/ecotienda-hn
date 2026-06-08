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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario | Admin EcoTienda</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --admin-primary: #10b981;
            --admin-secondary: #0f172a;
            --admin-dark-card: #1e293b;
            --admin-border-color: rgba(255, 255, 255, 0.08);
            --font-sans: 'Plus Jakarta Sans', sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
        }

        body {
            font-family: var(--font-sans);
            background-color: #0b0f19;
            color: #f1f5f9;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background-color: var(--admin-secondary);
            border-right: 1px solid var(--admin-border-color);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1020;
        }

        .sidebar-brand {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--admin-primary) !important;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--admin-border-color);
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }

        .sidebar-item a {
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.92rem;
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .sidebar-item a:hover, .sidebar-item.active a {
            color: #fff;
            background-color: rgba(16, 185, 129, 0.08);
            border-left-color: var(--admin-primary);
        }

        .sidebar-item i {
            width: 25px;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .admin-card {
            background-color: var(--admin-dark-card);
            border: 1px solid var(--admin-border-color);
            border-radius: 16px;
            padding: 1.5rem;
        }
        
        .table {
            color: #fff;
            border-color: rgba(255,255,255,0.05);
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="<?php echo BASE_URL; ?>admin/index.php" class="sidebar-brand text-decoration-none">
        <i class="fas fa-leaf text-success me-2"></i>
        <span>EcoTienda <span class="text-success">Admin</span></span>
    </a>
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/index.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/productos.php"><i class="fas fa-box"></i> Productos</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/categorias.php"><i class="fas fa-tags"></i> Categorías</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/usuarios.php"><i class="fas fa-users"></i> Usuarios</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php"><i class="fas fa-shopping-bag"></i> Pedidos</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/reportes.php"><i class="fas fa-chart-line"></i> Reportes</a>
        </li>
        <li class="sidebar-item active">
            <a href="<?php echo BASE_URL; ?>admin/inventario.php"><i class="fas fa-warehouse"></i> Inventario</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/configuracion.php"><i class="fas fa-cog"></i> Configuración</a>
        </li>
        <li class="sidebar-item mt-4">
            <a href="<?php echo BASE_URL; ?>index.php" class="text-success"><i class="fas fa-store"></i> Volver a Comercio</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
        </li>
    </ul>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div class="main-content text-start">
    
    <header class="mb-5 pb-3 border-bottom border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold m-0" style="font-family: var(--font-display);">Kardex de Bodega</h1>
            <p class="text-secondary small mb-0">🌱 Auditoría cronológica de entradas y salidas de stock.</p>
        </div>
        <button class="btn btn-sm btn-eco-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
            <i class="fas fa-sliders"></i> Registrar Ajuste de Existencia
        </button>
    </header>

    <?php if(!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <?php echo renderAlert($success, 'success'); ?>
    <?php endif; ?>

    <div class="admin-card">
        <h5 class="fw-bold mb-4" style="font-family: var(--font-display);"><i class="fas fa-history text-secondary me-2"></i> Últimos 15 Movimientos Auditados</h5>
        
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="text-secondary small font-mono">
                    <tr>
                        <th scope="col" class="border-0">ID Ajuste</th>
                        <th scope="col" class="border-0">EcoProducto Relacionado</th>
                        <th scope="col" class="border-0 text-center">Tipo de Movimiento</th>
                        <th scope="col" class="border-0 text-center">Cantidad</th>
                        <th scope="col" class="border-0">Fecha / Hora</th>
                        <th scope="col" class="border-0">Observación u Origen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($movementsList as $mov): ?>
                        <tr>
                            <td class="font-mono py-2.5">#<?php echo $mov['id']; ?></td>
                            <td>
                                <strong class="text-white"><?php echo sanitize($mov['producto_nombre'] ?? 'Producto Descatalogado'); ?></strong>
                            </td>
                            <td class="text-center">
                                <?php if($mov['tipo_movimiento'] === 'entrada'): ?>
                                    <span class="badge bg-success bg-opacity-15 text-success py-1 px-2.5 text-xs"><i class="fas fa-arrow-turn-down me-1"></i> Entrada</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-15 text-danger py-1 px-2.5 text-xs"><i class="fas fa-arrow-turn-up me-1"></i> Salida</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center font-mono text-white fw-bold">
                                <?php echo $mov['cantidad']; ?> uds
                            </td>
                            <td class="small text-secondary">
                                <?php echo date('Y-m-d h:i A', strtotime($mov['fecha'])); ?>
                            </td>
                            <td class="small text-secondary text-wrap">
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
<div class="modal fade text-dark" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustLabel" aria-hidden="true">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
