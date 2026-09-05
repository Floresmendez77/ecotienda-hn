<?php
/**
 * 🌱 ECOTIENDA HN - FINALIZAR COMPRA
 * Ruta: /checkout.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$error   = '';
$success = '';

// ──────────────────────────────────────────────────────────────
// 1. CARGAR CARRITO Y DATOS BASE
// ──────────────────────────────────────────────────────────────
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
        redirect('carrito.php');
    }

    // Calcular subtotal
    $subtotal = 0.00;
    foreach ($cartItems as $item) {
        $subtotal += ($item['precio_efectivo'] * $item['cantidad']);
    }

    $envio        = SHIPPING_COST;
    $descuento    = 0.00;
    $cupon_codigo = '';

    // Cupón en sesión
    if (isset($_SESSION['applied_coupon'])) {
        $descuento    = (float)$_SESSION['applied_coupon']['descuento_calculado'];
        $cupon_codigo = $_SESSION['applied_coupon']['codigo'];
    }

    $total = max(0.00, ($subtotal - $descuento) + $envio);

    // Dirección guardada
    $dirStmt = $db->prepare("SELECT * FROM direcciones WHERE usuario_id = :usuario_id LIMIT 1");
    $dirStmt->execute([':usuario_id' => $_SESSION['user_id']]);
    $address = $dirStmt->fetch();

    if (!$address) {
        $address = [
            'pais'         => 'Honduras',
            'departamento' => '',
            'municipio'    => '',
            'colonia'      => '',
            'direccion'    => '',
            'referencia'   => '',
        ];
    }

} catch (Exception $e) {
    logError('ERROR', 'checkout.php – init: ' . $e->getMessage(), [
        'file' => $e->getFile(), 'line' => $e->getLine(),
    ]);
    $error = "No se pudieron cargar los datos del checkout. Intenta de nuevo más tarde.";
    $cartItems = [];
    $subtotal  = 0.00;
    $envio     = SHIPPING_COST;
    $descuento = 0.00;
    $total     = 0.00;
    $address   = ['pais'=>'Honduras','departamento'=>'','municipio'=>'','colonia'=>'','direccion'=>'','referencia'=>''];
}

// ──────────────────────────────────────────────────────────────
// 2. QUITAR CUPÓN
// ──────────────────────────────────────────────────────────────
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    $descuento    = 0.00;
    $cupon_codigo = '';
    $total        = max(0.00, $subtotal + $envio);
    $success      = "Cupón eliminado correctamente.";
}

// ──────────────────────────────────────────────────────────────
// 3. APLICAR CUPÓN
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_coupon') {

    // Regenerar CSRF después de validar para evitar problemas en hosting gratuito
    $tokenOk = verifyCsrfToken($_POST['csrf_token'] ?? '');
    if (!$tokenOk) {
        // InfinityFree a veces pierde la sesión entre requests; intentamos recuperar
        // regenerando el token y devolviendo error claro
        $error = "Token de seguridad inválido. Recarga la página e intenta de nuevo.";
    } else {
        $codigo = strtoupper(trim($_POST['cupon_codigo'] ?? ''));

        if (!empty($codigo)) {
            try {
                $cStmt = $db->prepare(
                    "SELECT * FROM cupones
                     WHERE codigo = :codigo
                       AND estado = 'activo'
                       AND fecha_inicio <= CURDATE()
                       AND fecha_fin    >= CURDATE()
                     LIMIT 1"
                );
                $cStmt->execute([':codigo' => $codigo]);
                $cupon = $cStmt->fetch();

                $yaUsado = false;
                if ($cupon) {
                    $yaUsadoStmt = $db->prepare(
                        "SELECT COUNT(*) FROM pedidos
                         WHERE cupon_codigo = :codigo AND usuario_id = :uid AND estado NOT IN ('cancelado')"
                    );
                    $yaUsadoStmt->execute([':codigo' => $codigo, ':uid' => $_SESSION['user_id']]);
                    $yaUsado = (int)$yaUsadoStmt->fetchColumn() > 0;
                }

                if (!$cupon) {
                    $error = "El cupón <strong>$codigo</strong> no es válido o ya expiró.";
                } elseif ($cupon['limite_usos'] > 0 && $cupon['cantidad_usos'] >= $cupon['limite_usos']) {
                    $error = "El cupón <strong>$codigo</strong> ya alcanzó su límite de usos.";
                } elseif ($yaUsado) {
                    $error = "Ya usaste el cupón <strong>$codigo</strong> anteriormente. Cada cliente puede usar cada cupón una sola vez.";
                } else {
                    $descuento_calc = ($cupon['tipo'] === 'porcentaje')
                        ? round($subtotal * ($cupon['valor'] / 100), 2)
                        : min((float)$cupon['valor'], $subtotal);

                    $_SESSION['applied_coupon'] = [
                        'codigo'              => $cupon['codigo'],
                        'tipo'                => $cupon['tipo'],
                        'valor'               => $cupon['valor'],
                        'descuento_calculado' => $descuento_calc,
                    ];

                    $descuento    = $descuento_calc;
                    $cupon_codigo = $cupon['codigo'];
                    $total        = max(0.00, ($subtotal - $descuento) + $envio);
                    $success      = "✅ Cupón <strong>$codigo</strong> aplicado. Ahorraste L. " . number_format($descuento_calc, 2);
                }
            } catch (Exception $e) {
                logError('ERROR', 'checkout.php – cupón: ' . $e->getMessage(), [
                    'file' => $e->getFile(), 'line' => $e->getLine(),
                ]);
                $error = "Error al validar el cupón. Intenta de nuevo.";
            }
        }
        // Si $codigo está vacío → no mostrar error, el cupón es opcional
    }
}

// ──────────────────────────────────────────────────────────────
// 4. PROCESAR PEDIDO
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place_order') {

    // ── Recoger campos ──────────────────────────────────────
    $pais              = trim($_POST['pais']        ?? 'Honduras');
    $departamento      = trim($_POST['departamento'] ?? '');
    $municipio         = trim($_POST['municipio']    ?? '');
    $colonia           = trim($_POST['colonia']      ?? '');
    $direccion_detalle = trim($_POST['direccion']    ?? '');
    $referencia        = trim($_POST['referencia']   ?? '');
    $metodo_pago_id    = (int)($_POST['metodo_pago_id'] ?? 1);

    // ── FIX CLAVE: leer referencia_pago correctamente según método ──
    // El JS envía el campo correcto según el método seleccionado:
    //   - Transferencia → input text "referencia_pago"
    //   - PayPal/Tarjeta → hidden "ref_paypal" / "ref_card" con valor predefinido
    switch ($metodo_pago_id) {
        case 2:
            $referencia_pago = 'Pago vía PayPal (Pendiente confirmación)';
            break;
        case 3:
            $referencia_pago = 'Pago con Tarjeta (Pendiente confirmación)';
            break;
        default: // 1 = Transferencia
            $referencia_pago = trim($_POST['referencia_pago'] ?? '');
            break;
    }

    // ── Validaciones ─────────────────────────────────────────
    $validationErrors = [];
    if (empty($departamento))      $validationErrors[] = "Selecciona un departamento.";
    if (empty($municipio))         $validationErrors[] = "Ingresa el municipio / ciudad.";
    if (empty($direccion_detalle)) $validationErrors[] = "Ingresa la dirección exacta.";
    if ($metodo_pago_id === 1 && empty($referencia_pago)) {
        $validationErrors[] = "Ingresa el número de referencia de tu transferencia bancaria.";
    }

    if (!empty($validationErrors)) {
        $error = implode('<br>', $validationErrors);
    } else {
        try {
            $db->beginTransaction();

            // ── Guardar / actualizar dirección ───────────────
            $checkDir = $db->prepare("SELECT id FROM direcciones WHERE usuario_id = :uid LIMIT 1");
            $checkDir->execute([':uid' => $_SESSION['user_id']]);

            $dirParams = [
                ':usuario_id'   => $_SESSION['user_id'],
                ':pais'         => $pais,
                ':departamento' => $departamento,
                ':municipio'    => $municipio,
                ':colonia'      => $colonia,
                ':direccion'    => $direccion_detalle,
                ':referencia'   => $referencia,
            ];

            if ($checkDir->fetch()) {
                $db->prepare(
                    "UPDATE direcciones
                     SET pais=:pais, departamento=:departamento, municipio=:municipio,
                         colonia=:colonia, direccion=:direccion, referencia=:referencia
                     WHERE usuario_id=:usuario_id"
                )->execute($dirParams);
            } else {
                $db->prepare(
                    "INSERT INTO direcciones
                        (usuario_id,pais,departamento,municipio,colonia,direccion,referencia)
                     VALUES
                        (:usuario_id,:pais,:departamento,:municipio,:colonia,:direccion,:referencia)"
                )->execute($dirParams);
            }

            // ── Un cupón por cliente (verificación final, servidor) ──────
            if (!empty($cupon_codigo)) {
                $yaUsadoStmt = $db->prepare(
                    "SELECT COUNT(*) FROM pedidos
                     WHERE cupon_codigo = :codigo
                       AND usuario_id = :usuario_id
                       AND estado NOT IN ('cancelado')"
                );
                $yaUsadoStmt->execute([':codigo' => $cupon_codigo, ':usuario_id' => $_SESSION['user_id']]);
                if ((int)$yaUsadoStmt->fetchColumn() > 0) {
                    throw new Exception("Ya usaste el cupón {$cupon_codigo} anteriormente. Cada cliente puede usar cada cupón una sola vez.");
                }
            }

            // ── Crear pedido ─────────────────────────────────
            $pedidoStmt = $db->prepare(
                "INSERT INTO pedidos (usuario_id,subtotal,descuento,envio,total,cupon_codigo,estado)
                 VALUES (:usuario_id,:subtotal,:descuento,:envio,:total,:cupon_codigo,'pendiente')"
            );
            $pedidoStmt->execute([
                ':usuario_id'   => $_SESSION['user_id'],
                ':subtotal'     => $subtotal,
                ':descuento'    => $descuento,
                ':envio'        => $envio,
                ':total'        => $total,
                ':cupon_codigo' => !empty($cupon_codigo) ? $cupon_codigo : null,
            ]);
            $pedido_id = (int)$db->lastInsertId();

            if (!$pedido_id) {
                throw new Exception("No se pudo crear el pedido. Intenta de nuevo.");
            }

            // ── Detalle, stock e inventario ───────────────────
            $detStmt   = $db->prepare(
                "INSERT INTO detalle_pedido (pedido_id,producto_id,cantidad,precio,subtotal)
                 VALUES (:pedido_id,:producto_id,:cantidad,:precio,:subtotal)"
            );
            $stockStmt = $db->prepare(
                "UPDATE productos SET stock = stock - :cantidad WHERE id = :producto_id AND stock >= :cantidad2"
            );
            $movStmt   = $db->prepare(
                "INSERT INTO inventario (producto_id,tipo_movimiento,cantidad,descripcion)
                 VALUES (:producto_id,'salida',:cantidad,:descripcion)"
            );

            foreach ($cartItems as $item) {
                // Verificar stock en tiempo real con bloqueo
                $stockCheck = $db->prepare(
                    "SELECT stock FROM productos WHERE id = :id LIMIT 1"
                );
                $stockCheck->execute([':id' => $item['producto_id']]);
                $stockActual = (int)$stockCheck->fetchColumn();

                if ($stockActual < $item['cantidad']) {
                    throw new Exception(
                        "Stock insuficiente de <strong>{$item['nombre']}</strong>. " .
                        "Solo quedan {$stockActual} unidades disponibles."
                    );
                }

                $itemSubtotal = $item['precio_efectivo'] * $item['cantidad'];

                $detStmt->execute([
                    ':pedido_id'  => $pedido_id,
                    ':producto_id'=> $item['producto_id'],
                    ':cantidad'   => $item['cantidad'],
                    ':precio'     => $item['precio_efectivo'],
                    ':subtotal'   => $itemSubtotal,
                ]);

                $affected = $stockStmt->execute([
                    ':cantidad'   => $item['cantidad'],
                    ':cantidad2'  => $item['cantidad'],
                    ':producto_id'=> $item['producto_id'],
                ]);

                if (!$affected || $stockStmt->rowCount() === 0) {
                    throw new Exception(
                        "No se pudo descontar el stock de <strong>{$item['nombre']}</strong>. " .
                        "Por favor intenta de nuevo."
                    );
                }

                $movStmt->execute([
                    ':producto_id' => $item['producto_id'],
                    ':cantidad'    => $item['cantidad'],
                    ':descripcion' => "Salida por Pedido #{$pedido_id}",
                ]);
            }

            // ── Registrar pago ────────────────────────────────
            $db->prepare(
                "INSERT INTO pagos (pedido_id,metodo_pago_id,monto,referencia,estado)
                 VALUES (:pedido_id,:metodo_pago_id,:monto,:referencia,'pendiente')"
            )->execute([
                ':pedido_id'      => $pedido_id,
                ':metodo_pago_id' => $metodo_pago_id,
                ':monto'          => $total,
                ':referencia'     => $referencia_pago,
            ]);

            // ── Incrementar uso de cupón ──────────────────────
            if (!empty($cupon_codigo)) {
                $db->prepare(
                    "UPDATE cupones SET cantidad_usos = cantidad_usos + 1 WHERE codigo = ?"
                )->execute([$cupon_codigo]);
            }

            // ── Vaciar carrito ────────────────────────────────
            $db->prepare("DELETE FROM carrito WHERE usuario_id = :uid")
               ->execute([':uid' => $_SESSION['user_id']]);

            if (isset($_SESSION['applied_coupon'])) {
                unset($_SESSION['applied_coupon']);
            }

            // ── COMMIT antes del email ────────────────────────
            // El email va FUERA de la transacción para que un fallo de correo
            // no haga rollback del pedido ya confirmado.
            $db->commit();

            // ── Auditoría ─────────────────────────────────────
            logAuditoria($_SESSION['user_id'], "Pedido #{$pedido_id} por L. {$total}", "pedidos");

            // ── Email de confirmación (NO dentro de la TX) ────
            try {
                require_once __DIR__ . '/includes/mailer.php';
                notify_pedido_confirmado($db, $pedido_id);
            } catch (Exception $mailEx) {
                // El pedido ya está guardado; solo logueamos el fallo del correo
                logError('WARNING', 'checkout.php – email falló (pedido guardado OK): ' . $mailEx->getMessage(), [
                    'pedido_id' => $pedido_id,
                ]);
            }

            // ── Guardar en sesión y redirigir ─────────────────
            $_SESSION['last_order_id'] = $pedido_id;
            redirect('order_success.php?pedido=' . $pedido_id);

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            logError('ERROR', 'checkout.php – place_order: ' . $e->getMessage(), [
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $_SESSION['user_id'] ?? null,
            ]);
            $error = $e->getMessage() ?: "No se pudo procesar tu compra. Intenta de nuevo.";
        }
    }
}

// ──────────────────────────────────────────────────────────────
// 5. LISTADO DE DEPARTAMENTOS DE HONDURAS
// ──────────────────────────────────────────────────────────────
$departamentos = [
    'Francisco Morazán','Cortés','Atlántida','Yoro','Olancho',
    'Choluteca','El Paraíso','Comayagua','Santa Bárbara','La Paz',
    'Copán','Lempira','Ocotepeque','Intibucá','Valle',
    'Colón','Gracias a Dios','Islas de la Bahía',
];
?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<style>
/* ── Utilidades checkout ───────────────────────────────────── */
.checkout-stepper .step-circle {
    width: 42px; height: 42px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem;
    transition: background .3s;
}
.checkout-stepper .step-circle.done   { background: var(--bs-success); color:#fff; }
.checkout-stepper .step-circle.active { background: var(--bs-success); color:#fff; box-shadow:0 0 0 4px rgba(25,135,84,.2); }
.checkout-stepper .step-circle.idle   { background: #e9ecef; color:#6c757d; }

.pay-option input[type="radio"] { display:none; }
.pay-option label {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px; padding: 14px 10px; border-radius: 12px;
    border: 2px solid #dee2e6; cursor: pointer;
    transition: border-color .2s, box-shadow .2s;
    height: 100%; min-height: 90px;
}
.pay-option input[type="radio"]:checked + label {
    border-color: var(--bs-success);
    box-shadow: 0 0 0 3px rgba(25,135,84,.15);
    background: rgba(25,135,84,.04);
}
.pay-option label .pay-icon { font-size: 1.6rem; }
.pay-option label .pay-name { font-size: .82rem; font-weight: 700; }
.pay-option label .pay-sub  { font-size: .72rem; color: #6c757d; }

.cart-summary-list { max-height: 230px; overflow-y: auto; }
.cart-summary-list::-webkit-scrollbar { width: 4px; }
.cart-summary-list::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }

.badge-eco { background: rgba(25,135,84,.15); color: var(--bs-success); font-size:.75rem; padding:4px 10px; border-radius:20px; }
</style>

<div class="container py-5">

    <!-- Título -->
    <div class="mb-4">
        <h1 class="fw-bold fs-3" style="font-family:var(--font-display);">
            <i class="fas fa-leaf text-success me-2"></i>Finalizar Compra
        </h1>
        <p class="text-muted mb-0">Revisa tu pedido, ingresa tu dirección y elige cómo pagar.</p>
    </div>

    <!-- Stepper -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius:16px;">
        <div class="checkout-stepper d-flex justify-content-between align-items-center position-relative">
            <div class="position-absolute top-50 start-0 end-0 translate-middle-y px-5" style="z-index:0;">
                <div class="progress" style="height:3px;">
                    <div class="progress-bar bg-success" style="width:66%;"></div>
                </div>
            </div>
            <?php
            $steps = [
                ['icon'=>'fa-shopping-basket', 'label'=>'Carrito',      'state'=>'done'],
                ['icon'=>'fa-truck',            'label'=>'Dirección',    'state'=>'active'],
                ['icon'=>'fa-check-circle',     'label'=>'Confirmación', 'state'=>'idle'],
            ];
            foreach ($steps as $s): ?>
            <div class="text-center flex-fill position-relative" style="z-index:1;">
                <div class="step-circle <?= $s['state'] ?> mx-auto mb-1">
                    <i class="fas <?= $s['icon'] ?>"></i>
                </div>
                <div class="small fw-semibold <?= $s['state']==='idle' ? 'text-muted' : 'text-success' ?>">
                    <?= $s['label'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Alertas -->
    <?php if (!empty($error)):   echo renderAlert($error,   'danger');  endif; ?>
    <?php if (!empty($success)): echo renderAlert($success, 'success'); endif; ?>

    <form action="<?= BASE_URL ?>checkout.php" method="POST" id="checkoutForm">
        <input type="hidden" name="action" value="place_order">
        <?= csrfField() ?>

        <div class="row g-4 align-items-start">

            <!-- ── Columna izquierda ─────────────────────────── -->
            <div class="col-lg-7">

                <!-- Dirección -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius:16px;">
                    <h5 class="fw-bold mb-4" style="font-family:var(--font-display);">
                        <i class="fas fa-map-marker-alt text-success me-2"></i>Dirección de Envío
                        <span class="badge-eco ms-2">Honduras</span>
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">País</label>
                            <input type="text" name="pais" class="form-control" value="Honduras" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Departamento <span class="text-danger">*</span></label>
                            <select name="departamento" class="form-select">
                                <option value="">— Selecciona —</option>
                                <?php foreach ($departamentos as $dep): ?>
                                    <option value="<?= htmlspecialchars($dep) ?>"
                                        <?= (($address['departamento'] ?? '') === $dep) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dep) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Municipio / Ciudad <span class="text-danger">*</span></label>
                            <input type="text" name="municipio" class="form-control"
                                   placeholder="Ej: Tegucigalpa"
                                   value="<?= sanitize($address['municipio'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Colonia / Barrio</label>
                            <input type="text" name="colonia" class="form-control"
                                   placeholder="Ej: Tres Caminos"
                                   value="<?= sanitize($address['colonia'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">Dirección Exacta <span class="text-danger">*</span></label>
                            <textarea name="direccion" class="form-control" rows="2"
                                      placeholder="Ej: Casa #45, 2da calle, frente al parque"><?= sanitize($address['direccion'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">Punto de Referencia</label>
                            <input type="text" name="referencia" class="form-control"
                                   placeholder="Ej: Portón verde, a la par de pulpería"
                                   value="<?= sanitize($address['referencia'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Método de pago -->
                <div class="card border-0 shadow-sm p-4" style="border-radius:16px;">
                    <h5 class="fw-bold mb-4" style="font-family:var(--font-display);">
                        <i class="fas fa-credit-card text-success me-2"></i>Método de Pago
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-4 pay-option">
                            <input type="radio" name="metodo_pago_id" value="1" id="payTransfer" checked>
                            <label for="payTransfer">
                                <span class="pay-icon">🏦</span>
                                <span class="pay-name">Transferencia</span>
                                <span class="pay-sub">Bancos nacionales</span>
                            </label>
                        </div>
                        <div class="col-4 pay-option">
                            <input type="radio" name="metodo_pago_id" value="2" id="payPaypal">
                            <label for="payPaypal">
                                <span class="pay-icon">🅿️</span>
                                <span class="pay-name">PayPal</span>
                                <span class="pay-sub">Pago en dólares</span>
                            </label>
                        </div>
                        <div class="col-4 pay-option">
                            <input type="radio" name="metodo_pago_id" value="3" id="payCard">
                            <label for="payCard">
                                <span class="pay-icon">💳</span>
                                <span class="pay-name">Tarjeta</span>
                                <span class="pay-sub">Ficohsa / BAC</span>
                            </label>
                        </div>
                    </div>

                    <!-- Detalle transferencia -->
                    <div id="boxTransfer" class="bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 p-3 small">
                        <p class="fw-bold text-success mb-2">
                            <i class="fas fa-university me-2"></i>Cuentas Bancarias EcoTienda HN
                        </p>
                        <p class="mb-1 text-secondary"><strong>BAC Credomatic:</strong> Cta. Ahorros Lps — <code>742901920</code></p>
                        <p class="mb-3 text-secondary"><strong>Banco de Occidente:</strong> Cta. Ahorros Lps — <code>1120011928</code></p>
                        <p class="text-muted mb-3" style="font-size:.75rem;">
                            A nombre de <strong>EcoTienda HN S. de R.L.</strong> — Los pedidos sin abono después de 48 h serán cancelados.
                        </p>
                        <label class="form-label small fw-bold text-dark">
                            Número de Referencia de tu transferencia <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="referencia_pago" id="referencia_pago"
                               class="form-control form-control-sm"
                               placeholder="Ej: #940294" autocomplete="off">
                    </div>

                    <!-- Detalle PayPal -->
                    <div id="boxPaypal" class="bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 p-3 small" style="display:none;">
                        <p class="fw-bold text-primary mb-1">
                            <i class="fab fa-paypal me-2"></i>Pago vía PayPal
                        </p>
                        <p class="text-muted mb-0">
                            Recibirás instrucciones por correo para completar el pago en dólares. El tipo de cambio aplicado será el del día.
                        </p>
                    </div>

                    <!-- Detalle Tarjeta -->
                    <div id="boxCard" class="bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 p-3 small" style="display:none;">
                        <p class="fw-bold text-warning-emphasis mb-1">
                            <i class="fas fa-lock me-2"></i>Pago Seguro con Tarjeta
                        </p>
                        <p class="text-muted mb-0">
                            Un asesor se pondrá en contacto para procesar tu pago de forma segura con Ficohsa o BAC.
                        </p>
                    </div>
                </div>

            </div><!-- /col izquierda -->

            <!-- ── Columna derecha — Resumen ─────────────────── -->
            <div class="col-lg-5">
                <div class="card border-0 shadow p-4 bg-dark text-light sticky-top"
                     style="top:90px; border-radius:16px;">
                    <h5 class="fw-bold text-white mb-4" style="font-family:var(--font-display);">
                        🛒 Resumen del Pedido
                    </h5>

                    <!-- Productos -->
                    <div class="cart-summary-list d-flex flex-column gap-3 border-bottom border-secondary pb-3 mb-3">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:40px;height:40px;border-radius:8px;background:#fff;overflow:hidden;flex-shrink:0;">
                                <img src="<?= htmlspecialchars(productImageUrl($item['imagen_principal'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                     class="w-100 h-100 object-fit-cover" alt="">
                            </div>
                            <div class="flex-grow-1" style="min-width:0;">
                                <div class="text-light fw-semibold text-truncate" style="font-size:.82rem;">
                                    <?= sanitize($item['nombre']) ?>
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    <?= $item['cantidad'] ?> × <?= formatCurrency($item['precio_efectivo']) ?>
                                </div>
                            </div>
                            <span class="text-success fw-bold font-mono" style="font-size:.82rem;white-space:nowrap;">
                                <?= formatCurrency($item['precio_efectivo'] * $item['cantidad']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Cupón -->
                    <div class="mb-3">
                        <?php if (!empty($cupon_codigo)): ?>
                            <div class="d-flex justify-content-between align-items-center bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 px-3 py-2">
                                <span class="small text-success fw-bold">🎟️ <?= htmlspecialchars($cupon_codigo) ?></span>
                                <a href="?remove_coupon=1" class="btn btn-link text-danger p-0 small fw-bold text-decoration-none">✕ Quitar</a>
                            </div>
                        <?php else: ?>
                            <div class="d-flex gap-2" id="couponForm">
                                <input type="text" id="cuponInput"
                                       class="form-control form-control-sm"
                                       placeholder="¿Tienes un cupón?" style="font-size:.82rem;"
                                       autocomplete="off">
                                <button type="button" onclick="aplicarCupon()" class="btn btn-outline-success btn-sm fw-bold"
                                        style="white-space:nowrap;">
                                    Aplicar
                                </button>
                            </div>
                            <p class="text-muted mt-1 mb-0" style="font-size:.72rem;">
                                El cupón es opcional. Puedes comprar sin él.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Totales -->
                    <ul class="list-unstyled d-flex flex-column gap-2 border-bottom border-secondary pb-3 mb-3 small">
                        <li class="d-flex justify-content-between text-secondary">
                            <span>Subtotal</span>
                            <span class="text-light font-mono"><?= formatCurrency($subtotal) ?></span>
                        </li>
                        <?php if ($descuento > 0): ?>
                        <li class="d-flex justify-content-between text-secondary">
                            <span>Descuento</span>
                            <span class="text-success fw-bold font-mono">−<?= formatCurrency($descuento) ?></span>
                        </li>
                        <?php endif; ?>
                        <li class="d-flex justify-content-between text-secondary">
                            <span>Envío estándar</span>
                            <span class="text-light font-mono"><?= formatCurrency($envio) ?></span>
                        </li>
                    </ul>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fw-bold text-white fs-6">Total a Pagar</span>
                        <span class="fs-4 fw-bold text-success font-mono"><?= formatCurrency($total) ?></span>
                    </div>

                    <button type="submit" id="btnConfirmar"
                            class="btn btn-eco-primary w-100 py-3 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2 fs-6">
                        <i class="fas fa-lock me-1"></i> Confirmar y Pagar
                    </button>

                    <p class="text-center text-muted mt-3 mb-0" style="font-size:.72rem;">
                        <i class="fas fa-shield-alt me-1 text-success"></i>Tus datos están protegidos y no compartimos tu información.
                    </p>
                </div>
            </div>

        </div><!-- /row -->
    </form>
</div>

<script>
// ── Aplicar cupón via fetch (sin form anidado) ──────────────
async function aplicarCupon() {
    const codigo = document.getElementById('cuponInput').value.trim();
    const csrf   = document.querySelector('input[name="csrf_token"]').value;

    const formData = new FormData();
    formData.append('action', 'apply_coupon');
    formData.append('csrf_token', csrf);
    formData.append('cupon_codigo', codigo);

    try {
        const res  = await fetch(window.location.href, { method: 'POST', body: formData });
        const text = await res.text();
        // Recargar para mostrar el resultado
        window.location.reload();
    } catch (e) {
        alert('Error al aplicar cupón. Intenta de nuevo.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const radios   = document.querySelectorAll('input[name="metodo_pago_id"]');
    const boxes    = {
        '1': document.getElementById('boxTransfer'),
        '2': document.getElementById('boxPaypal'),
        '3': document.getElementById('boxCard'),
    };
    const refInput = document.getElementById('referencia_pago');

    function updatePayMethod() {
        const val = document.querySelector('input[name="metodo_pago_id"]:checked').value;

        Object.entries(boxes).forEach(([k, box]) => {
            if (box) box.style.display = (k === val) ? 'block' : 'none';
        });

        // Solo Transferencia necesita el campo de referencia
        if (refInput) {
            if (val === '1') {
                refInput.required = true;
                refInput.disabled = false;
            } else {
                refInput.required = false;
                refInput.disabled = true;
                refInput.value    = '';
            }
        }
    }

    radios.forEach(r => r.addEventListener('change', updatePayMethod));
    updatePayMethod();
    // Sin validación JS — el PHP maneja todo
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>