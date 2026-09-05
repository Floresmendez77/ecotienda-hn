<?php
/**
 * 🌱 ECOTIENDA HN - ADMINISTRACIÓN DE PEDIDOS
 * Ruta: /admin/pedidos.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Gestión de Pedidos";
$pageSubtitle = "🌱 Concilia transferencias, aprueba comprobantes y actualiza estados de transporte.";
$error = '';
$success = '';

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido o expirado. Recargá la página e intentá de nuevo.";
    } else {
    $action    = $_POST['action'];
    $pedido_id = (int)$_POST['pedido_id'];
    try {
        if ($action === 'update_order_state') {
            $nuevo_estado = $_POST['estado'] ?? 'pendiente';
            $db->prepare("UPDATE pedidos SET estado = :estado WHERE id = :id")
               ->execute([':estado' => $nuevo_estado, ':id' => $pedido_id]);
            require_once __DIR__ . '/../includes/mailer.php';
            notify_estado_pedido($db, $pedido_id, $nuevo_estado);
            logAuditoria($_SESSION['user_id'], "Actualizó estado Pedido #{$pedido_id} a {$nuevo_estado}", "pedidos");
            registrarHistorialPedido($db, $pedido_id, $nuevo_estado, 'Actualizado por el administrador', $_SESSION['user_id']);
            $success = "Estado del pedido #{$pedido_id} actualizado a '{$nuevo_estado}'.";
        } elseif ($action === 'approve_payment') {
            $pago_estado = $_POST['pago_estado'] ?? 'pendiente';

            // Estado ANTERIOR del pago, para saber si el stock ya se había
            // devuelto (venía de 'rechazado') o si esta es la primera vez
            // que se rechaza (hay que devolverlo). En cualquier otro punto
            // del flujo (checkout.php del sitio, o checkout-pago-manual.php
            // / checkout-capturar-pago.php de la app) el stock ya se
            // descontó antes de que exista esta fila en `pagos`.
            $pagoAnteriorStmt = $db->prepare("SELECT estado FROM pagos WHERE pedido_id = :id LIMIT 1");
            $pagoAnteriorStmt->execute([':id' => $pedido_id]);
            $pagoEstadoAnterior = $pagoAnteriorStmt->fetchColumn();

            $db->beginTransaction();
            try {
                $db->prepare("UPDATE pagos SET estado = :estado WHERE pedido_id = :pedido_id")
                   ->execute([':estado' => $pago_estado, ':pedido_id' => $pedido_id]);

                if ($pago_estado === 'aprobado') {
                    $db->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = :id")->execute([':id' => $pedido_id]);

                    // Si venía de estar rechazado (stock ya devuelto), hay
                    // que volver a descontarlo antes de aprobar.
                    if ($pagoEstadoAnterior === 'rechazado') {
                        descontarStockPedido($db, $pedido_id, 'Reversión de rechazo: pago vuelto a aprobar');
                    }

                    $db->commit();
                    logAuditoria($_SESSION['user_id'], "Aprobó pago Pedido #{$pedido_id}", "pagos");
                    registrarHistorialPedido($db, $pedido_id, 'pagado', 'Pago conciliado y aprobado por el administrador', $_SESSION['user_id']);
                    $success = "Pago APROBADO. Pedido configurado como PAGADO.";

                } elseif ($pago_estado === 'rechazado' && $pagoEstadoAnterior !== 'rechazado') {
                    // Primera vez que se rechaza: se devuelve el stock que
                    // había quedado reservado para este pedido.
                    restaurarStockPedido($db, $pedido_id, 'Pago rechazado: se devuelve stock reservado');

                    $db->commit();
                    logAuditoria($_SESSION['user_id'], "Rechazó pago Pedido #{$pedido_id} (stock devuelto)", "pagos");
                    registrarHistorialPedido($db, $pedido_id, 'pago_rechazado', 'Comprobante rechazado por el administrador', $_SESSION['user_id']);
                    $success = "Pago RECHAZADO. El stock reservado fue devuelto al inventario.";

                } else {
                    $db->commit();
                    logAuditoria($_SESSION['user_id'], "Modificó pago Pedido #{$pedido_id}", "pagos");
                    $success = "Estado de conciliación actualizado a '{$pago_estado}'.";
                }
            } catch (StockInsuficienteException $e) {
                $db->rollBack();
                $error = "No se pudo aprobar: " . $e->getMessage() . " Ajusta el inventario o contacta al cliente antes de reintentar.";
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        }
    } catch (Exception $e) {
        $error = "Error al procesar: " . $e->getMessage();
    }
    }
}

$pedidosList = [];
$historialPorPedido = [];
try {
    // LEFT JOIN (antes era INNER JOIN): los pedidos de invitado desde la
    // app (usuario_id = NULL, Fase 7 - pago manual) no tienen fila en
    // `usuarios`, así que con INNER JOIN quedaban completamente fuera de
    // esta lista y el admin nunca los veía para aprobarlos. Con LEFT JOIN
    // aparecen igual, usando correo_invitado como respaldo del correo.
    $pedidosList = $db->query("SELECT p.*,
            u.nombre, u.apellido, u.telefono,
            COALESCE(u.correo, p.correo_invitado) AS correo,
            pa.referencia AS pago_ref, pa.estado AS pago_estado, pa.comprobante_imagen,
            mp.nombre AS met_nombre
            FROM pedidos p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN pagos pa ON p.id = pa.pedido_id
            LEFT JOIN metodos_pago mp ON pa.metodo_pago_id = mp.id
            ORDER BY p.fecha DESC")->fetchAll();

    // Historial de estados (timeline) de todos los pedidos listados, para
    // que el admin vea en el modal quién cambió qué y cuándo — mismo dato
    // que la app y el sitio muestran al cliente, más el nombre del admin
    // responsable de cada paso manual.
    if (!empty($pedidosList)) {
        $pedidoIds = array_column($pedidosList, 'id');
        $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
        $histStmt = $db->prepare(
            "SELECT h.pedido_id, h.estado, h.nota, h.fecha,
                    CONCAT(u.nombre, ' ', u.apellido) AS admin_nombre
             FROM pedido_historial h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.pedido_id IN ($placeholders)
             ORDER BY h.fecha ASC, h.id ASC"
        );
        $histStmt->execute($pedidoIds);
        foreach ($histStmt->fetchAll() as $paso) {
            $historialPorPedido[$paso['pedido_id']][] = $paso;
        }
    }
} catch (Exception $e) {}

/**
 * Mismo mapeo de estado -> color/label/ícono que usa el sitio web para el
 * cliente (mis_pedidos.php), reutilizado acá para que el timeline del
 * admin luzca consistente con lo que ve el usuario.
 */
function estadoPedidoInfoAdmin(string $estado): array {
    switch ($estado) {
        case 'pendiente':      return ['color' => '#fbbf24', 'label' => 'Pendiente',            'icon' => 'fa-clock'];
        case 'pagado':         return ['color' => '#38bdf8', 'label' => 'Pagado',                'icon' => 'fa-check-circle'];
        case 'pago_rechazado': return ['color' => '#f87171', 'label' => 'Comprobante rechazado', 'icon' => 'fa-circle-xmark'];
        case 'procesando':     return ['color' => '#818cf8', 'label' => 'Procesando',             'icon' => 'fa-box'];
        case 'enviado':        return ['color' => '#38bdf8', 'label' => 'Enviado',                'icon' => 'fa-truck'];
        case 'entregado':      return ['color' => '#10b981', 'label' => 'Entregado',              'icon' => 'fa-house-circle-check'];
        case 'cancelado':      return ['color' => '#f87171', 'label' => 'Cancelado',              'icon' => 'fa-ban'];
        default:                return ['color' => '#94a3b8', 'label' => ucfirst($estado),        'icon' => 'fa-circle'];
    }
}
?>
<?php require_once __DIR__ . '/includes/admin_navbar.php'; ?>
    

    <?php if(!empty($error)): echo renderAlert($error, 'danger'); endif; ?>
    <?php if(!empty($success)): echo renderAlert($success, 'success'); endif; ?>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="tabla-pedidos">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>EcoCliente</th>
                        <th>Fecha / Canal</th>
                        <th style="text-align:right">Total</th>
                        <th style="text-align:center">Ref. Pago</th>
                        <th style="text-align:center">Conciliación</th>
                        <th style="text-align:center">Estado</th>
                        <th style="text-align:center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($pedidosList as $ord):
                    // Colores conciliación
                    $pago_estado = $ord['pago_estado'] ?? 'pendiente';
                    if($pago_estado === 'aprobado') { $pBg = 'rgba(16,185,129,.2)'; $pColor = '#10b981'; $pTxt = 'Aprobado'; }
                    elseif($pago_estado === 'rechazado') { $pBg = 'rgba(239,68,68,.2)'; $pColor = '#f87171'; $pTxt = 'Rechazado'; }
                    else { $pBg = 'rgba(251,191,36,.2)'; $pColor = '#fbbf24'; $pTxt = 'Pendiente'; }

                    // Colores estado pedido
                    switch($ord['estado']) {
                        case 'pagado':     $eBg='rgba(56,189,248,.2)';  $eColor='#38bdf8';  $eTxt='Pagado';      break;
                        case 'procesando': $eBg='rgba(99,102,241,.2)';  $eColor='#818cf8';  $eTxt='Procesando';  break;
                        case 'enviado':    $eBg='rgba(56,189,248,.2)';  $eColor='#38bdf8';  $eTxt='Enviado';     break;
                        case 'entregado':  $eBg='rgba(16,185,129,.2)';  $eColor='#10b981';  $eTxt='Entregado';   break;
                        case 'cancelado':  $eBg='rgba(239,68,68,.2)';   $eColor='#f87171';  $eTxt='Cancelado';   break;
                        default:           $eBg='rgba(251,191,36,.2)';  $eColor='#fbbf24';  $eTxt='Pendiente';
                    }
                ?>
                    <tr>
                        <td style="color:#10b981;font-family:monospace;font-weight:700;">#<?php echo $ord['id']; ?></td>
                        <td>
                            <strong style="color:#ffffff;font-weight:600;">
                                <?php echo $ord['nombre']
                                    ? sanitize($ord['nombre'] . ' ' . $ord['apellido'])
                                    : 'Invitado (app)'; ?>
                            </strong>
                            <span style="display:block;color:#94a3b8;font-size:.8rem;"><?php echo sanitize($ord['telefono'] ?? $ord['correo'] ?? ''); ?></span>
                        </td>
                        <td>
                            <span style="color:#94a3b8;font-size:.83rem;display:block;"><?php echo date('d M, Y h:i A', strtotime($ord['fecha'])); ?></span>
                            <span style="color:#10b981;font-size:.78rem;font-family:monospace;"><?php echo sanitize($ord['met_nombre'] ?? 'Transferencia'); ?></span>
                        </td>
                        <td style="text-align:right;color:#10b981;font-family:monospace;font-weight:700;"><?php echo formatCurrency($ord['total']); ?></td>
                        <td style="text-align:center;color:#94a3b8;font-family:monospace;font-size:.83rem;"><?php echo sanitize($ord['pago_ref'] ?? 'No registrada'); ?></td>
                        <td style="text-align:center;">
                            <span style="background:<?php echo $pBg; ?>;color:<?php echo $pColor; ?>;padding:.3em .75em;border-radius:999px;font-size:.75rem;font-weight:700;">
                                <?php echo $pTxt; ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <span style="background:<?php echo $eBg; ?>;color:<?php echo $eColor; ?>;padding:.3em .75em;border-radius:999px;font-size:.75rem;font-weight:700;">
                                <?php echo $eTxt; ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#controlModal<?php echo $ord['id']; ?>">
                                Gestionar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if(empty($pedidosList)): ?>
                <div class="text-center py-5 text-secondary">
                    <i class="fas fa-shopping-bag fa-3x mb-3 opacity-25"></i>
                    <p>No hay pedidos registrados aún.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODALES DE GESTIÓN (fuera de la tabla a propósito: un <form> dentro de un
         <tbody> es HTML inválido y el navegador lo cierra vacío al parsearlo, dejando
         los botones "Guardar"/"Actualizar" sin ningún formulario que enviar) -->
    <?php foreach($pedidosList as $ord):
        $pago_estado = $ord['pago_estado'] ?? 'pendiente';
    ?>
    <div class="modal fade" id="controlModal<?php echo $ord['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Gestionar Pedido #<?php echo $ord['id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <p class="small text-secondary mb-3">Cliente: <strong style="color:#f1f5f9;"><?php
                        echo $ord['nombre'] ? sanitize($ord['nombre'] . ' ' . $ord['apellido']) : 'Invitado (app)';
                    ?> (<?php echo sanitize($ord['correo'] ?? 'sin correo'); ?>)</strong></p>

                    <?php if (!empty($ord['comprobante_imagen'])): ?>
                    <div class="mb-3 text-center">
                        <label class="form-label small fw-bold d-block">Comprobante de transferencia</label>
                        <a href="<?php echo BASE_URL . ltrim($ord['comprobante_imagen'], '/'); ?>" target="_blank">
                            <img src="<?php echo BASE_URL . ltrim($ord['comprobante_imagen'], '/'); ?>"
                                 alt="Comprobante pedido #<?php echo $ord['id']; ?>"
                                 style="max-width:100%;max-height:220px;border-radius:8px;border:1px solid #dee2e6;">
                        </a>
                        <span class="d-block small text-secondary mt-1">Clic para ver en tamaño completo</span>
                    </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>admin/pedidos.php" method="POST" class="border-bottom pb-3 mb-3">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="approve_payment">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$ord['id']; ?>">
                        <label class="form-label small fw-bold">1. Conciliación de Pago (Ref: <?php echo sanitize($ord['pago_ref'] ?? 'N/A'); ?>)</label>
                        <div class="input-group input-group-sm">
                            <select name="pago_estado" class="form-select form-select-sm">
                                <option value="pendiente" <?php echo $pago_estado==='pendiente' ? 'selected':''; ?>>Pendiente / No verificado</option>
                                <option value="aprobado"  <?php echo $pago_estado==='aprobado'  ? 'selected':''; ?>>Aprobado / Transferencia recibida</option>
                                <option value="rechazado" <?php echo $pago_estado==='rechazado' ? 'selected':''; ?>>Rechazado / Sin abono</option>
                            </select>
                            <button type="submit" class="btn btn-success fw-bold" style="font-size:.8rem;">Guardar</button>
                        </div>
                    </form>

                    <form action="<?php echo BASE_URL; ?>admin/pedidos.php" method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="update_order_state">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$ord['id']; ?>">
                        <label class="form-label small fw-bold">2. Estado de Logística</label>
                        <div class="input-group input-group-sm">
                            <select name="estado" class="form-select form-select-sm">
                                <option value="pendiente"  <?php echo $ord['estado']==='pendiente'  ? 'selected':''; ?>>Pendiente</option>
                                <option value="pagado"     <?php echo $ord['estado']==='pagado'     ? 'selected':''; ?>>Pagado / Confirmado</option>
                                <option value="procesando" <?php echo $ord['estado']==='procesando' ? 'selected':''; ?>>Procesando</option>
                                <option value="enviado"    <?php echo $ord['estado']==='enviado'    ? 'selected':''; ?>>Enviado</option>
                                <option value="entregado"  <?php echo $ord['estado']==='entregado'  ? 'selected':''; ?>>Entregado</option>
                                <option value="cancelado"  <?php echo $ord['estado']==='cancelado'  ? 'selected':''; ?>>Cancelado</option>
                            </select>
                            <button type="submit" class="btn btn-primary fw-bold" style="font-size:.8rem;">Actualizar</button>
                        </div>
                    </form>

                    <!-- 3. Historial / timeline del pedido — mismo dato que ve el cliente en el
                         sitio y en la app, con el nombre del admin que hizo cada cambio manual -->
                    <?php $historial = $historialPorPedido[$ord['id']] ?? []; ?>
                    <div class="mt-4 pt-3 border-top">
                        <label class="form-label small fw-bold d-block mb-3">3. Línea de tiempo del pedido</label>
                        <?php if (empty($historial)): ?>
                            <p class="small text-secondary mb-0">Aún no hay pasos registrados.</p>
                        <?php else: ?>
                            <ul class="eco-admin-timeline list-unstyled mb-0">
                                <?php foreach ($historial as $paso):
                                    $pasoInfo = estadoPedidoInfoAdmin($paso['estado']);
                                ?>
                                    <li class="eco-admin-timeline-item">
                                        <span class="eco-admin-timeline-dot" style="background:<?php echo $pasoInfo['color']; ?>;">
                                            <i class="fas <?php echo $pasoInfo['icon']; ?>"></i>
                                        </span>
                                        <div class="eco-admin-timeline-content">
                                            <strong style="color:#f1f5f9;display:block;"><?php echo $pasoInfo['label']; ?></strong>
                                            <?php if (!empty($paso['nota'])): ?>
                                                <span class="d-block small" style="color:#94a3b8;"><?php echo sanitize($paso['nota']); ?></span>
                                            <?php endif; ?>
                                            <span class="d-block small" style="color:#64748b;">
                                                <?php echo date('d M, Y h:i A', strtotime($paso['fecha'])); ?>
                                                <?php if (!empty($paso['admin_nombre']) && trim($paso['admin_nombre']) !== ''): ?>
                                                    &middot; por <?php echo sanitize($paso['admin_nombre']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <style>
        /* Timeline del admin dentro del modal "Gestionar Pedido" */
        .eco-admin-timeline { position: relative; max-height: 260px; overflow-y: auto; padding-right: 4px; }
        .eco-admin-timeline-item { position: relative; display: flex; gap: 12px; padding-bottom: 18px; }
        .eco-admin-timeline-item:last-child { padding-bottom: 0; }
        .eco-admin-timeline-item::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 24px;
            bottom: -4px;
            width: 2px;
            background: rgba(148,163,184,.25);
        }
        .eco-admin-timeline-item:last-child::before { display: none; }
        .eco-admin-timeline-dot {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            font-size: .68rem;
            z-index: 1;
        }
        .eco-admin-timeline-content { padding-top: 2px; }
    </style>



<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
