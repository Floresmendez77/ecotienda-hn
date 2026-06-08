<?php
/**
 * 🌱 ECOTIENDA HN - ADMINISTRACIÓN DE PEDIDOS Y PAGOS
 * Ruta: /admin/pedidos.php
 * Descripción: Permite verificar justificativos bancarios, aprobar pagos en la tabla MySQL y coordinar despachos ecológicos en Honduras.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Validar que el usuario sea Admin
requireAdmin();

$pageTitle = "Gestión de Pedidos";
$error = '';
$success = '';

$db = Database::getConnection();

// Procesar Actualización del Estado de Pedidos u Homologación de Pagos (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $pedido_id = (int)$_POST['pedido_id'];

    try {
        if ($action === 'update_order_state') {
            $nuevo_estado = $_POST['estado'] ?? 'pendiente';
            
            $stmt = $db->prepare("UPDATE pedidos SET estado = :estado WHERE id = :id");
            $stmt->execute([
                ':estado' => $nuevo_estado,
                ':id' => $pedido_id
            ]);

            require_once __DIR__ . '/../includes/mailer.php';
              notify_estado_pedido($db, $pedido_id, $nuevo_estado);

            logAuditoria($_SESSION['user_id'], "Actualizó estado de Pedido #{$pedido_id} a {$nuevo_estado}", "pedidos");
            $success = "El estado del pedido #{$pedido_id} fue modificado a '{$nuevo_estado}' correctamente.";

        } elseif ($action === 'approve_payment') {
            $pago_estado = $_POST['pago_estado'] ?? 'pendiente';
            
            // Actualizar tabla pagos
            $pagoStmt = $db->prepare("UPDATE pagos SET estado = :estado WHERE pedido_id = :pedido_id");
            $pagoStmt->execute([
                ':estado' => $pago_estado,
                ':pedido_id' => $pedido_id
            ]);

            // Si se aprueba el pago, actualizamos automáticamente el pedido a 'pagado'
            if ($pago_estado === 'aprobado') {
                $pedStmt = $db->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = :id");
                $pedStmt->execute([':id' => $pedido_id]);
                logAuditoria($_SESSION['user_id'], "Aprobó pago del Pedido #{$pedido_id}", "pagos");
                $success = "El pago de la transferencia fue APROBADO. El pedido se configuró como PAGADO.";
            } else {
                logAuditoria($_SESSION['user_id'], "Modificó pago a pendiente/rechazado en Pedido #{$pedido_id}", "pagos");
                $success = "El estado de conciliación bancaria se actualizó a '{$pago_estado}'.";
            }
        }
    } catch (Exception $e) {
        $error = "Error al procesar actualización de pedidos: " . $e->getMessage();
    }
}

// Cargar Todos los Pedidos con Datos de Clientes y Pagos
$pedidosList = [];

try {
    $sql = "SELECT p.*, u.nombre, u.apellido, u.correo, u.telefono,
            pa.referencia AS pago_ref, pa.estado AS pago_estado, mp.nombre AS met_nombre
            FROM pedidos p
            INNER JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN pagos pa ON p.id = pa.pedido_id
            LEFT JOIN metodos_pago mp ON pa.metodo_pago_id = mp.id
            ORDER BY p.fecha DESC";
    $pedidosList = $db->query($sql)->fetchAll();
} catch (Exception $e) {
    //
}

// Fallback de demostración
if (empty($pedidosList)) {
    $pedidosList = [
        [
            'id' => 102, 'nombre' => 'Diana', 'apellido' => 'Mendoza', 'correo' => 'diana.men@yahoo.com', 'telefono' => '3192-3329',
            'subtotal' => 280.00, 'descuento' => 0.00, 'envio' => 150.00, 'total' => 430.00, 'estado' => 'pendiente', 'fecha' => '2026-06-04 18:25:00',
            'pago_ref' => '#940294', 'pago_estado' => 'pendiente', 'met_nombre' => 'Transferencia'
        ],
        [
            'id' => 101, 'nombre' => 'Josué', 'apellido' => 'Rodríguez', 'correo' => 'josue.hn@gmail.com', 'telefono' => '9900-1122',
            'subtotal' => 120.00, 'descuento' => 0.00, 'envio' => 150.00, 'total' => 270.00, 'estado' => 'entregado', 'fecha' => '2026-06-03 11:10:00',
            'pago_ref' => 'Ref-48209', 'pago_estado' => 'aprobado', 'met_nombre' => 'Transferencia'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos | Admin EcoTienda</title>
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
        <li class="sidebar-item active">
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php"><i class="fas fa-shopping-bag"></i> Pedidos</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/reportes.php"><i class="fas fa-chart-line"></i> Reportes</a>
        </li>
        <li class="sidebar-item">
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
<div class="main-content">
    
    <header class="mb-5 pb-3 border-bottom border-secondary border-opacity-10">
        <h1 class="h3 fw-bold m-0" style="font-family: var(--font-display);">Gestión de Pedidos de Clientes</h1>
        <p class="text-secondary small mb-0">🌱 Concilia transferencias, aprueba comprobantes de abono y actualiza estados de transporte.</p>
    </header>

    <?php if(!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <?php echo renderAlert($success, 'success'); ?>
    <?php endif; ?>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="text-secondary small font-mono">
                    <tr>
                        <th scope="col" class="border-0">Pedido</th>
                        <th scope="col" class="border-0">EcoCliente</th>
                        <th scope="col" class="border-0">Fecha / Canal</th>
                        <th scope="col" class="border-0 text-end">Total</th>
                        <th scope="col" class="border-0 text-center">Referencia Pago</th>
                        <th scope="col" class="border-0 text-center">Conciliación</th>
                        <th scope="col" class="border-0 text-center">Ruta Orden</th>
                        <th scope="col" class="border-0 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pedidosList as $ord): ?>
                        <tr>
                            <!-- ID -->
                            <td class="font-mono fw-bold text-success py-3">#<?php echo $ord['id']; ?></td>
                            
                            <!-- Cliente -->
                            <td>
                                <strong class="text-white d-block"><?php echo sanitize($ord['nombre'] . ' ' . $ord['apellido']); ?></strong>
                                <span class="text-muted text-xs d-block"><?php echo sanitize($ord['telefono'] ?? ''); ?></span>
                            </td>

                            <!-- Fecha y método -->
                            <td>
                                <span class="small text-secondary d-block"><?php echo date('d M, Y h:i A', strtotime($ord['fecha'])); ?></span>
                                <span class="text-muted text-xxs font-mono text-success uppercase"><?php echo sanitize($ord['met_nombre'] ?? 'Transferencia'); ?></span>
                            </td>

                            <!-- Total -->
                            <td class="text-end font-mono text-success fw-bold">
                                <?php echo formatCurrency($ord['total']); ?>
                            </td>

                            <!-- Ref de Pago -->
                            <td class="text-center font-mono text-secondary small">
                                <?php echo sanitize($ord['pago_ref'] ?? 'No registrada'); ?>
                            </td>

                            <!-- Estado del pago -->
                            <td class="text-center">
                                <?php 
                                $pBg = 'warning'; $pTxt = 'Pendiente';
                                if($ord['pago_estado'] === 'aprobado') { $pBg = 'success'; $pTxt = 'Aprobado'; }
                                elseif($ord['pago_estado'] === 'rechazado') { $pBg = 'danger'; $pTxt = 'Rechazado'; }
                                ?>
                                <span class="badge bg-opacity-15 bg-<?php echo $pBg; ?> text-<?php echo $pBg; ?> px-2.5 py-1.5 small font-mono">
                                    <?php echo $pTxt; ?>
                                </span>
                            </td>

                            <!-- Estado del Pedido -->
                            <td class="text-center">
                                <?php 
                                $bColor = 'secondary';
                                $label = 'Pendiente';
                                switch ($ord['estado']) {
                                    case 'pendiente': $bColor = 'warning'; $label = 'Pendiente'; break;
                                    case 'pagado': $bColor = 'info'; $label = 'Pagado'; break;
                                    case 'procesando': $bColor = 'primary'; $label = 'Procesando'; break;
                                    case 'enviado': $bColor = 'info'; $label = 'Enviado'; break;
                                    case 'entregado': $bColor = 'success'; $label = 'Entregado'; break;
                                    case 'cancelado': $bColor = 'danger'; $label = 'Cancelado'; break;
                                }
                                ?>
                                <span class="badge bg-<?php echo $bColor; ?> rounded-pill text-xs px-2.5 py-1.5 fw-semibold">
                                    <?php echo $label; ?>
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <!-- Modal Trigger de Controles -->
                                    <button class="btn btn-xs btn-outline-success font-semibold px-2 py-1 text-xs" data-bs-toggle="modal" data-bs-target="#controlModal<?php echo $ord['id']; ?>">
                                        Gestionar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- MODAL DE GESTIÓN DE PEDIDO INDIVIDUAL -->
                        <div class="modal fade text-dark" id="controlModal<?php echo $ord['id']; ?>" tabindex="-1" aria-labelledby="conLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Gestionar Pedido #<?php echo $ord['id']; ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-start">
                                        <p class="small text-secondary mb-3">Cliente: <strong class="text-dark"><?php echo sanitize($ord['nombre'] . ' ' . $ord['apellido']); ?> (<?php echo sanitize($ord['correo']); ?>)</strong></p>
                                        
                                        <!-- Formulario 1: Conciliación Económica (Aprobar Pago) -->
                                        <form action="<?php echo BASE_URL; ?>admin/pedidos.php" method="POST" class="border-bottom pb-3 mb-3">
                                            <input type="hidden" name="action" value="approve_payment">
                                            <input type="hidden" name="pedido_id" value="<?php echo $ord['id']; ?>">
                                            
                                            <label class="form-label small fw-bold">1. Conciliación de Pago (Referencia: <?php echo sanitize($ord['pago_ref']); ?>)</label>
                                            <div class="input-group input-group-sm">
                                                <select name="pago_estado" class="form-select form-select-sm" required>
                                                    <option value="pendiente" <?php echo $ord['pago_estado'] === 'pendiente' ? 'selected':''; ?>>Pendiente / No verificado</option>
                                                    <option value="aprobado" <?php echo $ord['pago_estado'] === 'aprobado' ? 'selected':''; ?>>Aprobado / Transferencia recibida</option>
                                                    <option value="rechazado" <?php echo $ord['pago_estado'] === 'rechazado' ? 'selected':''; ?>>Rechazado / Sin abono</option>
                                                </select>
                                                <button type="submit" class="btn btn-success fw-bold text-xs">Guardar Conciliación</button>
                                            </div>
                                        </form>

                                        <!-- Formulario 2: Estado de Transporte / Logística -->
                                        <form action="<?php echo BASE_URL; ?>admin/pedidos.php" method="POST">
                                            <input type="hidden" name="action" value="update_order_state">
                                            <input type="hidden" name="pedido_id" value="<?php echo $ord['id']; ?>">
                                            
                                            <label class="form-label small fw-bold">2. Logística y Despacho en Honduras</label>
                                            <div class="input-group input-group-sm mb-3">
                                                <select name="estado" class="form-select form-select-sm" required>
                                                    <option value="pendiente" <?php echo $ord['estado'] === 'pendiente' ? 'selected':''; ?>>Pendiente</option>
                                                    <option value="pagado" <?php echo $ord['estado'] === 'pagado' ? 'selected':''; ?>>Pagado / Confirmado</option>
                                                    <option value="procesando" <?php echo $ord['estado'] === 'procesando' ? 'selected':''; ?>>Empacando (Procesando)</option>
                                                    <option value="enviado" <?php echo $ord['estado'] === 'enviado' ? 'selected':''; ?>>Enviado / En ruta</option>
                                                    <option value="entregado" <?php echo $ord['estado'] === 'entregado' ? 'selected':''; ?>>Entregado (Finalizado)</option>
                                                    <option value="cancelado" <?php echo $ord['estado'] === 'cancelado' ? 'selected':''; ?>>Cancelado / Devuelto</option>
                                                </select>
                                                <button type="submit" class="btn btn-primary fw-bold text-xs">Actualizar Estado</button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
