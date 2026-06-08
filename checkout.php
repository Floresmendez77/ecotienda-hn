<?php
/**
 * 🌱 ECOTIENDA HN - FINALIZAR COMPRA (RESTRINGIDO)
 * Ruta: /checkout.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$error = '';
$success = '';

try {
    $db = Database::getConnection();

    $sql = "SELECT c.*, p.nombre, p.precio, p.precio_oferta, p.stock, p.imagen_principal,
            COALESCE(p.precio_oferta, p.precio) AS precio_efectivo
            FROM carrito c
            INNER JOIN productos p ON c.producto_id = p.id
            WHERE c.usuario_id = :usuario_id";
    $cartStmt = $db->prepare($sql);
    $cartStmt->execute([':usuario_id' => $_SESSION['user_id']]);
    $cartItems = $cartStmt->fetchAll();

    if (empty($cartItems)) {
        redirect('/carrito.php');
    }

    $subtotal = 0.00;
    foreach ($cartItems as $item) {
        $subtotal += ($item['precio_efectivo'] * $item['cantidad']);
    }

    $envio = SHIPPING_COST;
    $descuento = 0.00;
    $cupon_codigo = '';

    if (isset($_SESSION['applied_coupon'])) {
        $descuento = (float)$_SESSION['applied_coupon']['descuento_calculado'];
        $cupon_codigo = $_SESSION['applied_coupon']['codigo'];
    }

    $total = ($subtotal - $descuento) + $envio;
    if ($total < 0) $total = 0.00;

    $dirStmt = $db->prepare("SELECT * FROM direcciones WHERE usuario_id = :usuario_id LIMIT 1");
    $dirStmt->execute([':usuario_id' => $_SESSION['user_id']]);
    $address = $dirStmt->fetch();

    if (!$address) {
        $address = [
            'pais' => 'Honduras',
            'departamento' => '',
            'municipio' => '',
            'colonia' => '',
            'direccion' => '',
            'referencia' => ''
        ];
    }

} catch (Exception $e) {
    logError('ERROR', 'Error al inicializar checkout.php: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $error = "No se pudieron cargar los datos del checkout. Intenta de nuevo más tarde.";
}

// Quitar cupón
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    $descuento = 0.00;
    $cupon_codigo = '';
    $total = ($subtotal - $descuento) + $envio;
}

// Aplicar cupón
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_coupon') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = "La solicitud no es válida. Por favor, recarga la página e intenta de nuevo.";
    } else {
        $codigo = strtoupper(trim($_POST['cupon_codigo'] ?? ''));
        try {
            $cStmt = $db->prepare("SELECT * FROM cupones WHERE codigo = :codigo AND estado = 'activo' AND fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() LIMIT 1");
            $cStmt->execute([':codigo' => $codigo]);
            $cupon = $cStmt->fetch();

            if (!$cupon) {
                $error = "El cupón '$codigo' no es válido o ya expiró.";
            } elseif ($cupon['limite_usos'] > 0 && $cupon['cantidad_usos'] >= $cupon['limite_usos']) {
                $error = "El cupón '$codigo' ya alcanzó su límite de usos.";
            } else {
                $descuento_calc = 0.00;
                if ($cupon['tipo'] === 'porcentaje') {
                    $descuento_calc = round($subtotal * ($cupon['valor'] / 100), 2);
                } else {
                    $descuento_calc = min((float)$cupon['valor'], $subtotal);
                }
                $_SESSION['applied_coupon'] = [
                    'codigo'              => $cupon['codigo'],
                    'tipo'                => $cupon['tipo'],
                    'valor'               => $cupon['valor'],
                    'descuento_calculado' => $descuento_calc
                ];
                $descuento    = $descuento_calc;
                $cupon_codigo = $cupon['codigo'];
                $total        = max(0, ($subtotal - $descuento) + $envio);
                $success      = "Cupón '$codigo' aplicado correctamente. Descuento: L. " . number_format($descuento_calc, 2);
            }
        } catch (Exception $e) {
            logError('ERROR', 'Error al validar cupón en checkout: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $error = "Error al validar el cupón. Intenta de nuevo.";
        }
    }
}

// Procesar pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = "La solicitud no es válida. Por favor, recarga la página e intenta de nuevo.";
    } else {
        $pais               = filter_input(INPUT_POST, 'pais', FILTER_DEFAULT);
        $departamento       = filter_input(INPUT_POST, 'departamento', FILTER_DEFAULT);
        $municipio          = filter_input(INPUT_POST, 'municipio', FILTER_DEFAULT);
        $colonia            = filter_input(INPUT_POST, 'colonia', FILTER_DEFAULT);
        $direccion_detalle  = filter_input(INPUT_POST, 'direccion', FILTER_DEFAULT);
        $referencia         = filter_input(INPUT_POST, 'referencia', FILTER_DEFAULT);
        $metodo_pago_id     = (int)($_POST['metodo_pago_id'] ?? 1);
        $comprobante_deposito = filter_input(INPUT_POST, 'referencia_pago', FILTER_DEFAULT) ?? 'Checkout Web';

        if (empty($departamento) || empty($municipio) || empty($direccion_detalle)) {
            $error = "Por favor, especifica un departamento, municipio y dirección detallada válidos para el envío en Honduras.";
        } else {
            try {
                $db->beginTransaction();

                $checkDir = $db->prepare("SELECT id FROM direcciones WHERE usuario_id = :usuario_id LIMIT 1");
                $checkDir->execute([':usuario_id' => $_SESSION['user_id']]);

                if ($checkDir->fetch()) {
                    $dirSql = "UPDATE direcciones SET pais=:pais, departamento=:departamento, municipio=:municipio,
                               colonia=:colonia, direccion=:direccion, referencia=:referencia WHERE usuario_id=:usuario_id";
                } else {
                    $dirSql = "INSERT INTO direcciones (usuario_id,pais,departamento,municipio,colonia,direccion,referencia)
                               VALUES (:usuario_id,:pais,:departamento,:municipio,:colonia,:direccion,:referencia)";
                }
                $dirStmt = $db->prepare($dirSql);
                $dirStmt->execute([
                    ':usuario_id'  => $_SESSION['user_id'],
                    ':pais'        => $pais,
                    ':departamento'=> $departamento,
                    ':municipio'   => $municipio,
                    ':colonia'     => $colonia,
                    ':direccion'   => $direccion_detalle,
                    ':referencia'  => $referencia
                ]);

                $pedidoStmt = $db->prepare("INSERT INTO pedidos (usuario_id,subtotal,descuento,envio,total,estado)
                                            VALUES (:usuario_id,:subtotal,:descuento,:envio,:total,'pendiente')");
                $pedidoStmt->execute([
                    ':usuario_id' => $_SESSION['user_id'],
                    ':subtotal'   => $subtotal,
                    ':descuento'  => $descuento,
                    ':envio'      => $envio,
                    ':total'      => $total
                ]);
                $pedido_id = $db->lastInsertId();

                $detStmt   = $db->prepare("INSERT INTO detalle_pedido (pedido_id,producto_id,cantidad,precio,subtotal) VALUES (:pedido_id,:producto_id,:cantidad,:precio,:subtotal)");
                $stockStmt = $db->prepare("UPDATE productos SET stock = stock - :cantidad WHERE id = :producto_id");
                $movStmt   = $db->prepare("INSERT INTO inventario (producto_id,tipo_movimiento,cantidad,descripcion) VALUES (:producto_id,'salida',:cantidad,:descripcion)");

                foreach ($cartItems as $item) {
                    if ($item['stock'] < $item['cantidad']) {
                        throw new Exception("Stock insuficiente de '{$item['nombre']}' (quedan {$item['stock']} unidades).");
                    }
                    $itemSubtotal = $item['precio_efectivo'] * $item['cantidad'];
                    $detStmt->execute([':pedido_id'=>$pedido_id,':producto_id'=>$item['producto_id'],':cantidad'=>$item['cantidad'],':precio'=>$item['precio_efectivo'],':subtotal'=>$itemSubtotal]);
                    $stockStmt->execute([':cantidad'=>$item['cantidad'],':producto_id'=>$item['producto_id']]);
                    $movStmt->execute([':producto_id'=>$item['producto_id'],':cantidad'=>$item['cantidad'],':descripcion'=>"Salida por Pedido #{$pedido_id}"]);
                }

                $pagoStmt = $db->prepare("INSERT INTO pagos (pedido_id,metodo_pago_id,monto,referencia,estado) VALUES (:pedido_id,:metodo_pago_id,:monto,:referencia,'pendiente')");
                $pagoStmt->execute([':pedido_id'=>$pedido_id,':metodo_pago_id'=>$metodo_pago_id,':monto'=>$total,':referencia'=>$comprobante_deposito]);

                if (!empty($cupon_codigo)) {
                    $db->prepare("UPDATE cupones SET cantidad_usos = cantidad_usos + 1 WHERE codigo = ?")->execute([$cupon_codigo]);
                }

                $db->prepare("DELETE FROM carrito WHERE usuario_id = :usuario_id")->execute([':usuario_id' => $_SESSION['user_id']]);

                if (isset($_SESSION['applied_coupon'])) unset($_SESSION['applied_coupon']);

                require_once __DIR__ . '/includes/mailer.php';
                notify_pedido_confirmado($db, $pedido_id);

                logAuditoria($_SESSION['user_id'], "Efectuó compra de Pedido #{$pedido_id} Monto: " . $total, "pedidos");

                $db->commit();

                $_SESSION['last_order_id'] = $pedido_id;
                redirect('/order_success.php?pedido=' . $pedido_id);

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                logError('ERROR', 'Error al procesar pedido en checkout: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'user_id' => $_SESSION['user_id'] ?? null,
                ]);
                $error = "No se pudo procesar tu compra. Verifica el stock disponible e intenta de nuevo.";
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-5">
    <div class="text-start mb-4">
        <h1 class="fw-bold fs-2" style="font-family: var(--font-display);"><i class="fas fa-file-invoice-dollar text-success me-2"></i> Finalizar Compra</h1>
        <p class="text-secondary">Suministra tu dirección de envío en Honduras y registra tu método de pago para concluir la adquisición.</p>
    </div>

    <!-- Stepper visual: Carrito -> Dirección -> Confirmación -->
    <div class="card border-0 shadow-sm p-4 mb-5" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center position-relative">
            <div class="position-absolute top-50 start-0 end-0 translate-middle-y px-4" style="z-index:0;">
                <div class="progress" style="height:4px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width:66%;" aria-valuenow="66" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>

            <div class="text-center flex-fill position-relative" style="z-index:1;">
                <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-2" style="width:40px;height:40px;">
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <div class="small fw-bold text-success">Carrito</div>
            </div>

            <div class="text-center flex-fill position-relative" style="z-index:1;">
                <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-2" style="width:40px;height:40px;">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="small fw-bold text-success">Dirección</div>
            </div>

            <div class="text-center flex-fill position-relative" style="z-index:1;">
                <div class="rounded-circle bg-light text-secondary border d-inline-flex align-items-center justify-content-center mb-2" style="width:40px;height:40px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="small fw-bold text-secondary">Confirmación</div>
            </div>
        </div>
    </div>

    <?php if(!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <?php echo renderAlert($success, 'success'); ?>
    <?php endif; ?>

    <form action="<?php echo BASE_URL; ?>checkout.php" method="POST">
        <input type="hidden" name="action" value="place_order">
        <?php echo csrfField(); ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 16px;">
                    <h5 class="fw-bold mb-4" style="font-family: var(--font-display);"><i class="fas fa-truck text-success me-2"></i> Dirección de Envío</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-bold">País</label>
                            <input type="text" name="pais" class="form-control" value="Honduras" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-bold">Departamento *</label>
                            <select name="departamento" class="form-select" required>
                                <option value="">Selecciona Departamento</option>
                                <option value="Francisco Morazán" <?php echo $address['departamento'] === 'Francisco Morazán' ? 'selected' : ''; ?>>Francisco Morazán</option>
                                <option value="Cortés" <?php echo $address['departamento'] === 'Cortés' ? 'selected' : ''; ?>>Cortés</option>
                                <option value="Atlántida" <?php echo $address['departamento'] === 'Atlántida' ? 'selected' : ''; ?>>Atlántida</option>
                                <option value="Yoro" <?php echo $address['departamento'] === 'Yoro' ? 'selected' : ''; ?>>Yoro</option>
                                <option value="Olancho" <?php echo $address['departamento'] === 'Olancho' ? 'selected' : ''; ?>>Olancho</option>
                                <option value="Choluteca" <?php echo $address['departamento'] === 'Choluteca' ? 'selected' : ''; ?>>Choluteca</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-bold">Municipio / Ciudad *</label>
                            <input type="text" name="municipio" class="form-control" placeholder="Ej: Tegucigalpa" required value="<?php echo sanitize($address['municipio']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-bold">Colonia / Barrio</label>
                            <input type="text" name="colonia" class="form-control" placeholder="Ej: Tres Caminos" value="<?php echo sanitize($address['colonia']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-bold">Dirección Exacta *</label>
                            <textarea name="direccion" class="form-control" rows="3" required><?php echo sanitize($address['direccion']); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-bold">Referencias del Lugar</label>
                            <input type="text" name="referencia" class="form-control" placeholder="Ej: Portón verde, a la par de pulpería" value="<?php echo sanitize($address['referencia']); ?>">
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
                    <h5 class="fw-bold mb-4" style="font-family: var(--font-display);"><i class="fas fa-credit-card text-success me-2"></i> Método de Pago</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                <input type="radio" name="metodo_pago_id" value="1" id="payMethodTransfer" class="form-check-input mb-2" checked>
                                <label for="payMethodTransfer" class="fw-bold small d-block">Transferencia</label>
                                <span class="text-muted text-xs font-mono">Bancos Nacionales</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                <input type="radio" name="metodo_pago_id" value="2" id="payMethodPaypal" class="form-check-input mb-2">
                                <label for="payMethodPaypal" class="fw-bold small d-block">PayPal</label>
                                <span class="text-muted text-xs">Pago en dólares</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                                <input type="radio" name="metodo_pago_id" value="3" id="payMethodCard" class="form-check-input mb-2">
                                <label for="payMethodCard" class="fw-bold small d-block">Tarjeta</label>
                                <span class="text-muted text-xs">Ficohsa / Bac</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-success bg-opacity-10 border border-success border-opacity-10 rounded-3 p-3 mt-4 small" id="transferDetailsBox">
                        <span class="text-success fw-bold d-block mb-1"><i class="fas fa-bank me-2"></i>Cuentas Bancarias EcoTienda HN:</span>
                        <span class="d-block text-secondary"><strong>BAC Credomatic:</strong> Cuenta Ahorros Lps - <code>742901920</code></span>
                        <span class="d-block text-secondary mt-1"><strong>Banco de Occidente:</strong> Cuenta Ahorros Lps - <code>1120011928</code></span>
                        <span class="d-block text-secondary mt-1">A nombre de: EcoTienda HN S. de R.L.</span>
                        <div class="mt-3">
                            <label for="referencia_pago" class="form-label text-dark fw-bold mb-1">Número de Referencia</label>
                            <input type="text" name="referencia_pago" id="referencia_pago" class="form-control form-control-sm" placeholder="Ej: #940294" required value="Transferencia Bancaria">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm p-4 bg-dark text-light sticky-top" style="top: 100px; border-radius: 16px;">
                    <h5 class="fw-bold mb-4 text-white" style="font-family: var(--font-display);">Resumen de Pedido</h5>

                    <div class="d-flex flex-column gap-3 border-bottom border-secondary pb-4 mb-4" style="max-height: 250px; overflow-y: auto;">
                        <?php foreach($cartItems as $item): ?>
                            <div class="d-flex justify-content-between align-items-start gap-3 text-light text-opacity-80">
                                <div style="width:40px;height:40px;border-radius:6px;background:#fff;overflow:hidden;flex-shrink:0;">
                                    <img src="<?php echo htmlspecialchars(productImageUrl($item['imagen_principal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-100 h-100 object-fit-cover" alt="">
                                </div>
                                <div class="flex-grow-1 text-xs">
                                    <span class="d-block fw-semibold"><?php echo sanitize($item['nombre']); ?></span>
                                    <span class="text-muted" style="font-size:.72rem;">Cant: <?php echo $item['cantidad']; ?> × <?php echo formatCurrency($item['precio_efectivo']); ?></span>
                                </div>
                                <span class="fw-bold text-success text-xs font-mono"><?php echo formatCurrency($item['precio_efectivo'] * $item['cantidad']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <?php if (!empty($cupon_codigo)): ?>
                            <div class="d-flex justify-content-between align-items-center bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 px-3 py-2 mb-2">
                                <span class="small text-success fw-bold">🎟️ Cupón: <?php echo htmlspecialchars($cupon_codigo); ?></span>
                                <a href="?remove_coupon=1" class="btn btn-link text-danger p-0 small">✕ Quitar</a>
                            </div>
                        <?php else: ?>
                            <form action="<?php echo BASE_URL; ?>checkout.php" method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="apply_coupon">
                                <?php echo csrfField(); ?>
                                <input type="text" name="cupon_codigo" class="form-control form-control-sm" placeholder="Código de cupón" style="font-size:.82rem;">
                                <button type="submit" class="btn btn-outline-success btn-sm fw-bold" style="white-space:nowrap;">Aplicar</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <ul class="list-unstyled d-flex flex-column gap-3 border-bottom border-secondary pb-4 mb-4 small text-secondary">
                        <li class="d-flex justify-content-between">
                            <span>Subtotal</span>
                            <span class="fw-semibold text-light font-mono"><?php echo formatCurrency($subtotal); ?></span>
                        </li>
                        <li class="d-flex justify-content-between">
                            <span>Descuento</span>
                            <span class="text-success fw-bold font-mono">-<?php echo formatCurrency($descuento); ?></span>
                        </li>
                        <li class="d-flex justify-content-between">
                            <span>Envío estándar</span>
                            <span class="text-success font-mono">L. 150.00</span>
                        </li>
                    </ul>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold m-0 text-white">Total</h6>
                        <span class="fs-4 fw-bold text-success font-mono"><?php echo formatCurrency($total); ?></span>
                    </div>

                    <p class="text-secondary small mb-4">Los pedidos sin abono después de 48 horas serán cancelados por el administrador.</p>

                    <button type="submit" class="btn btn-eco-primary w-100 py-3 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2">
                        Autorizar Orden y Finalizar Pedido <i class="fas fa-check-circle"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const r1 = document.getElementById("payMethodTransfer");
    const r2 = document.getElementById("payMethodPaypal");
    const r3 = document.getElementById("payMethodCard");
    const referenceInput = document.getElementById("referencia_pago");
    const transferBox = document.getElementById("transferDetailsBox");
    if (r1 && r2 && r3) {
        const toggleRef = () => {
            if (r1.checked) {
                referenceInput.value = "";
                referenceInput.required = true;
                transferBox.style.display = "block";
            } else if (r2.checked) {
                referenceInput.value = "Pago vía PayPal (Pendiente)";
                referenceInput.required = false;
                transferBox.style.display = "none";
            } else {
                referenceInput.value = "Pago Seguro con Tarjeta";
                referenceInput.required = false;
                transferBox.style.display = "none";
            }
        };
        r1.addEventListener("change", toggleRef);
        r2.addEventListener("change", toggleRef);
        r3.addEventListener("change", toggleRef);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
