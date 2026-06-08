<?php
/**
 * 🌱 ECOTIENDA HN - MI HISTORIAL DE PEDIDOS (RESTRINGIDO)
 * Ruta: /mis_pedidos.php
 * Descripción: Permite al cliente autenticado ver los pedidos creados, descargar detalles e inspeccionar su estado de envío en Honduras.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// Validar inicio de sesión
requireLogin();

$pageTitle = "Mis Pedidos Ecológicos";
$error = '';
$orders = [];

try {
    $db = Database::getConnection();

    // Consultar todos los pedidos del usuario
    $sql = "SELECT p.*, COUNT(d.id) AS total_items, pa.referencia AS pago_referencia, pa.estado AS pago_estado
            FROM pedidos p
            LEFT JOIN detalle_pedido d ON p.id = d.pedido_id
            LEFT JOIN pagos pa ON p.id = pa.pedido_id
            WHERE p.usuario_id = :usuario_id
            GROUP BY p.id
            ORDER BY p.fecha DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':usuario_id' => $_SESSION['user_id']]);
    $orders = $stmt->fetchAll();

} catch (Exception $e) {
    $error = "Error al consultar tus pedidos de base: " . $e->getMessage();
}

// Procesar cancelación de pedido (POST + CSRF — ya no GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Solicitud inválida. Recarga la página e intenta de nuevo.";
        redirect('/mis_pedidos.php');
    }
    $cancel_id = (int)$_POST['cancel_id'];
    
    try {
        // Verificar que el pedido pertenezca al usuario y esté en pendiente de aprobación
        $checkStmt = $db->prepare("SELECT id, estado FROM pedidos WHERE id = :id AND usuario_id = :usuario_id LIMIT 1");
        $checkStmt->execute([
            ':id' => $cancel_id,
            ':usuario_id' => $_SESSION['user_id']
        ]);
        $ord = $checkStmt->fetch();

        if ($ord && $ord['estado'] === 'pendiente') {
            // Cancelar de forma segura regresando stock e inventario
            $db->beginTransaction();

            $updateStmt = $db->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = :id");
            $updateStmt->execute([':id' => $cancel_id]);

            // Obtener artículos del pedido para devolver existencias
            $itemsStmt = $db->prepare("SELECT producto_id, cantidad FROM detalle_pedido WHERE pedido_id = :id");
            $itemsStmt->execute([':id' => $cancel_id]);
            $items = $itemsStmt->fetchAll();

            $stockStmt = $db->prepare("UPDATE productos SET stock = stock + :cantidad WHERE id = :producto_id");
            $movStmt = $db->prepare("INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion) VALUES (:producto_id, 'entrada', :cantidad, :descripcion)");

            foreach ($items as $item) {
                $stockStmt->execute([
                    ':cantidad' => $item['cantidad'],
                    ':producto_id' => $item['producto_id']
                ]);

                $movStmt->execute([
                    ':producto_id' => $item['producto_id'],
                    ':cantidad' => $item['cantidad'],
                    ':descripcion' => "Devolución por Cancelación de Pedido #{$cancel_id}"
                ]);
            }

            logAuditoria($_SESSION['user_id'], "Canceló Pedido ID: " . $cancel_id, "pedidos");
            $db->commit();

            $_SESSION['flash_success'] = "Tu pedido #{$cancel_id} ha sido cancelado con éxito y las existencias han retornado al catálogo.";
            redirect('/mis_pedidos.php');
        } else {
            $_SESSION['flash_error'] = "No puedes cancelar un pedido que ya está aprobado, procesado o enviado.";
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['flash_error'] = "Falla al procesar cancelación: " . $e->getMessage();
    }
}
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-5 text-start">
    
    <div class="text-start mb-5">
        <h1 class="fw-bold fs-2" style="font-family: var(--font-display);"><i class="fas fa-file-invoice-dollar text-success me-2"></i> Mis Pedidos Sostenibles</h1>
        <p class="text-secondary">Monitorea el estado de tus compras ecológicas aprobadas por nuestro equipo en Honduras.</p>
    </div>

    <?php if(!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <?php if(empty($orders)): ?>
        <div class="card border-0 shadow-sm p-5 text-center" style="border-radius: 20px;">
            <div class="mb-4 text-success opacity-50"><i class="fas fa-box-open fa-4x"></i></div>
            <h4 class="fw-bold">Aún no registras pedidos</h4>
            <p class="text-secondary col-md-6 mx-auto">No has efectuado transacciones todavía en EcoTienda HN. Únete a miles apoyando la biodiversidad libre de plástico.</p>
            <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-eco-primary mt-3 col-md-3 mx-auto">Ver Catálogo Verde</a>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm p-4 text-start" style="border-radius: 16px;">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="text-secondary small font-mono">
                        <tr>
                            <th scope="col" class="border-0">Pedido #</th>
                            <th scope="col" class="border-0">Fecha</th>
                            <th scope="col" class="border-0 text-center">Artículos</th>
                            <th scope="col" class="border-0 text-end">Total Monto</th>
                            <th scope="col" class="border-0 text-center">Estado de Orden</th>
                            <th scope="col" class="border-0 text-center">Estado del Pago</th>
                            <th scope="col" class="border-0 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($orders as $ord): ?>
                            <tr>
                                <!-- ID Pedido -->
                                <td class="py-3 fw-bold font-mono">
                                    #<?php echo $ord['id']; ?>
                                </td>

                                <!-- Fecha -->
                                <td class="py-3 text-secondary small">
                                    <?php echo date('d M, Y h:i A', strtotime($ord['fecha'])); ?>
                                </td>

                                <!-- Items -->
                                <td class="py-3 text-center">
                                    <span class="badge bg-secondary font-mono"><?php echo $ord['total_items']; ?> items</span>
                                </td>

                                <!-- Importe Total -->
                                <td class="py-3 text-end fw-bold text-success font-mono">
                                    <?php echo formatCurrency($ord['total']); ?>
                                </td>

                                <!-- Estado del Pedido -->
                                <td class="py-3 text-center">
                                    <?php 
                                    $bColor = 'secondary';
                                    $label = 'Pendiente';
                                    switch ($ord['estado']) {
                                        case 'pendiente': $bColor = 'warning'; $label = 'Pendiente de Control'; break;
                                        case 'pagado': $bColor = 'info'; $label = 'Abonado'; break;
                                        case 'procesando': $bColor = 'primary'; $label = 'En Almacén'; break;
                                        case 'enviado': $bColor = 'info'; $label = 'En Ruta'; break;
                                        case 'entregado': $bColor = 'success'; $label = 'Completado'; break;
                                        case 'cancelado': $bColor = 'danger'; $label = 'Anulado'; break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $bColor; ?> px-2.5 py-1.5 rounded-pill small">
                                        <?php echo $label; ?>
                                    </span>
                                </td>

                                <!-- Estado de Pago -->
                                <td class="py-3 text-center">
                                    <?php 
                                    $pColor = 'warning';
                                    $pLabel = 'No Aprobado';
                                    if ($ord['pago_estado'] === 'aprobado') {
                                        $pColor = 'success';
                                        $pLabel = 'Confirmado';
                                    } elseif ($ord['pago_estado'] === 'rechazado') {
                                        $pColor = 'danger';
                                        $pLabel = 'Denegado';
                                    }
                                    ?>
                                    <span class="badge bg-opacity-20 text-<?php echo $pColor; ?> bg-<?php echo $pColor; ?> px-2.5 py-1.5 small font-mono">
                                        <?php echo $pLabel; ?>
                                    </span>
                                </td>

                                <!-- Acción de Cancelación -->
                                <td class="py-3 text-center">
                                    <?php if($ord['estado'] === 'pendiente'): ?>
                                        <form action="<?php echo BASE_URL; ?>mis_pedidos.php" method="POST"
                                              onsubmit="return confirm('¿Seguro que deseas cancelar esta orden de compra ecológica?')"
                                              style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="cancel_id" value="<?php echo $ord['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm text-xs font-semibold py-1 px-2 border-0" title="Cancelar Pedido">
                                                Anular
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-link text-muted p-0 text-xs border-0" disabled title="Solo se cancelan pendientes">No modificable</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>