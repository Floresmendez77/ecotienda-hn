<?php
/**
 * 🌱 ECOTIENDA HN - CONFIRMACIÓN DE PEDIDO EXITOSO
 * Ruta: /order_success.php
 * Descripción: Página de confirmación mostrada tras un checkout exitoso.
 *              Recupera el pedido_id desde sesión o GET, muestra el resumen
 *              completo con datos bancarios si el pago fue por transferencia.
 */

$pageTitle = "¡Pedido Confirmado!";
require_once __DIR__ . '/includes/navbar.php';

requireLogin();

// Resolver el pedido_id: primero de sesión flash, luego GET
$pedido_id = 0;
if (isset($_SESSION['last_order_id'])) {
    $pedido_id = (int)$_SESSION['last_order_id'];
    unset($_SESSION['last_order_id']);
} elseif (isset($_GET['pedido'])) {
    $pedido_id = (int)$_GET['pedido'];
}

$pedido       = null;
$detalle      = [];
$pago         = null;
$metodo_pago  = null;

if ($pedido_id > 0) {
    try {
        $db = Database::getConnection();

        // Verificar que el pedido pertenezca al usuario actual
        $stmt = $db->prepare("SELECT p.*, 
                               DATE_FORMAT(p.fecha_pedido, '%d de %M de %Y a las %H:%i') AS fecha_formateada
                               FROM pedidos p
                               WHERE p.id = :id AND p.usuario_id = :uid
                               LIMIT 1");
        $stmt->execute([':id' => $pedido_id, ':uid' => $_SESSION['user_id']]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            // Detalle de productos
            $detStmt = $db->prepare(
                "SELECT dp.*, pr.nombre, pr.imagen_principal
                 FROM detalle_pedido dp
                 INNER JOIN productos pr ON dp.producto_id = pr.id
                 WHERE dp.pedido_id = :pid"
            );
            $detStmt->execute([':pid' => $pedido_id]);
            $detalle = $detStmt->fetchAll();

            // Datos del pago y método
            $pagoStmt = $db->prepare(
                "SELECT pa.*, mp.nombre AS metodo_nombre, mp.descripcion AS metodo_desc
                 FROM pagos pa
                 LEFT JOIN metodos_pago mp ON pa.metodo_pago_id = mp.id
                 WHERE pa.pedido_id = :pid
                 LIMIT 1"
            );
            $pagoStmt->execute([':pid' => $pedido_id]);
            $pago = $pagoStmt->fetch();
        }

    } catch (Exception $e) {
        error_log("order_success.php: " . $e->getMessage());
    }
}

// Si no se encontró el pedido, redirigir a mis_pedidos
if (!$pedido) {
    redirect('/mis_pedidos.php');
}

// Detectar si es transferencia bancaria (metodo_pago_id = 1)
$es_transferencia = ($pago && (int)$pago['metodo_pago_id'] === 1);

// Cálculo de árboles equivalentes salvados (fun ecological metric)
$total_productos  = array_sum(array_column($detalle, 'cantidad'));
$arboles_equiv    = max(1, round($total_productos * 0.3, 1));

$impacto_msgs = [
    "Cada compra ecológica que haces equivale a plantar {$arboles_equiv} árbol(es) en Honduras. 🌳",
    "Tu pedido evitó el uso de aproximadamente " . ($total_productos * 2) . " botellas plásticas de un solo uso. 🌊",
    "Elegir productos sostenibles como estos reduce hasta un 60% la huella de carbono frente a alternativas convencionales. ♻️",
];
$impacto_msg = $impacto_msgs[array_rand($impacto_msgs)];
?>

<div class="container py-5">

    <!-- CABECERA ÉXITO -->
    <div class="text-center mb-5">
        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle mb-4"
             style="width:90px;height:90px;">
            <i class="fas fa-check-circle text-success" style="font-size:3rem;"></i>
        </div>
        <h1 class="fw-bold mb-2" style="font-family:var(--font-display);">
            ¡Pedido #<?php echo $pedido_id; ?> Confirmado!
        </h1>
        <p class="text-secondary fs-5">
            Gracias, <strong><?php echo sanitize($_SESSION['user_name'] ?? 'EcoCliente'); ?></strong>.
            Tu pedido ecológico está en camino. 🌱
        </p>
        <span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill">
            <i class="fas fa-clock me-1"></i>
            Estado: <?php echo ucfirst(sanitize($pedido['estado'])); ?>
        </span>
    </div>

    <div class="row g-4 justify-content-center">

        <!-- RESUMEN DEL PEDIDO -->
        <div class="col-lg-7">

            <!-- Productos comprados -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold" style="font-family:var(--font-display);">
                        <i class="fas fa-box-open text-success me-2"></i> Productos Adquiridos
                    </h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($detalle as $item): ?>
                            <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                                <div style="width:56px;height:56px;border-radius:10px;overflow:hidden;flex-shrink:0;background:#f1f5f9;">
                                    <img src="<?php echo !empty($item['imagen_principal']) ? $item['imagen_principal'] : 'https://placehold.co/56x56/10b981/white?text=🌿'; ?>"
                                         class="w-100 h-100 object-fit-cover" alt="">
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="d-block text-dark" style="font-size:.95rem;">
                                        <?php echo sanitize($item['nombre']); ?>
                                    </strong>
                                    <span class="text-secondary small">
                                        Cant: <?php echo $item['cantidad']; ?> × <?php echo formatCurrency($item['precio']); ?>
                                    </span>
                                </div>
                                <span class="fw-bold text-success font-mono">
                                    <?php echo formatCurrency($item['subtotal']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Totales -->
                    <div class="mt-4 pt-2">
                        <div class="d-flex justify-content-between text-secondary small mb-2">
                            <span>Subtotal</span>
                            <span class="font-mono"><?php echo formatCurrency($pedido['subtotal']); ?></span>
                        </div>
                        <?php if ((float)$pedido['descuento'] > 0): ?>
                            <div class="d-flex justify-content-between text-secondary small mb-2">
                                <span>Descuento</span>
                                <span class="text-success font-mono">-<?php echo formatCurrency($pedido['descuento']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between text-secondary small mb-3">
                            <span>Envío estándar Honduras</span>
                            <span class="font-mono"><?php echo formatCurrency($pedido['envio']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-3">
                            <span class="fw-bold fs-5">Total Pagado</span>
                            <span class="fw-bold fs-4 text-success font-mono"><?php echo formatCurrency($pedido['total']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Método de pago -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3" style="font-family:var(--font-display);">
                        <i class="fas fa-credit-card text-success me-2"></i> Método de Pago
                    </h5>
                    <p class="mb-1 text-secondary">
                        <strong class="text-dark"><?php echo sanitize($pago['metodo_nombre'] ?? 'Transferencia Bancaria'); ?></strong>
                    </p>
                    <?php if (!empty($pago['referencia'])): ?>
                        <p class="text-secondary small mb-0">
                            Referencia registrada: <code class="text-dark"><?php echo sanitize($pago['referencia']); ?></code>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DATOS BANCARIOS — Solo si es transferencia -->
            <?php if ($es_transferencia): ?>
                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-success" style="border-radius:16px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3 text-success" style="font-family:var(--font-display);">
                            <i class="fas fa-university me-2"></i> Instrucciones de Pago por Transferencia
                        </h5>

                        <div class="alert alert-warning border-0 rounded-3 mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Tienes 24 horas</strong> para realizar y enviar el comprobante de tu transferencia.
                            Los pedidos sin abono vencen automáticamente pasado ese tiempo.
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="bg-success bg-opacity-10 rounded-3 p-3 h-100">
                                    <span class="d-block fw-bold text-success mb-1">
                                        <i class="fas fa-piggy-bank me-1"></i> BAC Credomatic
                                    </span>
                                    <span class="d-block text-secondary small">Tipo: Cuenta de Ahorros Lps.</span>
                                    <code class="d-block fs-5 text-dark mt-1 fw-bold">742901920</code>
                                    <span class="d-block text-secondary small mt-1">A nombre de: EcoTienda HN S. de R.L.</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-success bg-opacity-10 rounded-3 p-3 h-100">
                                    <span class="d-block fw-bold text-success mb-1">
                                        <i class="fas fa-piggy-bank me-1"></i> Banco de Occidente
                                    </span>
                                    <span class="d-block text-secondary small">Tipo: Cuenta de Ahorros Lps.</span>
                                    <code class="d-block fs-5 text-dark mt-1 fw-bold">1120011928</code>
                                    <span class="d-block text-secondary small mt-1">A nombre de: EcoTienda HN S. de R.L.</span>
                                </div>
                            </div>
                        </div>

                        <ol class="text-secondary small mb-0 ps-3" style="line-height:2;">
                            <li>Realiza la transferencia por <strong class="text-dark"><?php echo formatCurrency($pedido['total']); ?></strong> a cualquiera de las cuentas anteriores.</li>
                            <li>Guarda el comprobante (captura de pantalla o PDF).</li>
                            <li>Envíalo por WhatsApp al <strong class="text-dark">+504 9900-1122</strong> o por correo a <strong class="text-dark">pagos@ecotiendahn.com</strong>.</li>
                            <li>Incluye en el mensaje el número de tu pedido: <strong class="text-dark">#<?php echo $pedido_id; ?></strong>.</li>
                            <li>Una vez verificado, tu pedido será aprobado y enviado en 24–48 horas hábiles.</li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- COLUMNA LATERAL -->
        <div class="col-lg-4">

            <!-- Fecha y número de pedido -->
            <div class="card border-0 shadow-sm mb-4 text-center" style="border-radius:16px;">
                <div class="card-body p-4">
                    <div class="bg-success bg-opacity-10 rounded-3 p-3 mb-3 d-inline-block">
                        <i class="fas fa-receipt text-success" style="font-size:2rem;"></i>
                    </div>
                    <h6 class="text-secondary small mb-1">Número de Pedido</h6>
                    <h3 class="fw-bold text-dark font-mono mb-3">#<?php echo str_pad($pedido_id, 6, '0', STR_PAD_LEFT); ?></h3>
                    <p class="text-secondary small mb-0">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <?php echo $pedido['fecha_formateada'] ?? date('d \d\e F \d\e Y'); ?>
                    </p>
                </div>
            </div>

            <!-- Mensaje de impacto ecológico -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;background:linear-gradient(135deg,#064e3b,#10b981);">
                <div class="card-body p-4 text-white">
                    <div class="text-center mb-3">
                        <span style="font-size:2.5rem;">🌍</span>
                    </div>
                    <h6 class="fw-bold mb-2" style="font-family:var(--font-display);">Tu Impacto Ecológico</h6>
                    <p class="small mb-0" style="opacity:.9;line-height:1.6;"><?php echo $impacto_msg; ?></p>
                    <div class="border-top border-white border-opacity-25 mt-3 pt-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-leaf"></i>
                            <small>Honduras agradece tu elección sostenible.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="d-flex flex-column gap-3">
                <a href="<?php echo BASE_URL; ?>mis_pedidos.php"
                   class="btn btn-eco-primary py-3 fw-bold rounded-3 w-100">
                    <i class="fas fa-list-alt me-2"></i> Ver Mis Pedidos
                </a>
                <a href="<?php echo BASE_URL; ?>tienda.php"
                   class="btn btn-outline-success py-3 fw-bold rounded-3 w-100">
                    <i class="fas fa-leaf me-2"></i> Seguir Comprando
                </a>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
