<?php
/**
 * 🌱 ECOTIENDA HN - CARRITO DE COMPRAS (AJAX-POWERED)
 * Ruta: /carrito.php
 * Descripción: Vista dinámica del carrito. Todas las acciones (agregar, quitar,
 *              actualizar) funcionan sin recargar la página vía fetch() + api/carrito.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pageTitle = "Bolsa de Compras";

// Carga inicial SSR (los items se renderizan en PHP para SEO y primera carga rápida)
$cartItems   = [];
$totalGeneral = 0.00;
$error = '';

try {
    $db  = Database::getConnection();
    $sql = "SELECT c.*, p.nombre, p.imagen_principal, p.stock, p.precio, p.precio_oferta,
                   COALESCE(p.precio_oferta, p.precio) AS precio_efectivo
            FROM carrito c
            INNER JOIN productos p ON c.producto_id = p.id
            WHERE c.usuario_id = :uid
            ORDER BY c.fecha DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    $cartItems = $stmt->fetchAll();

    foreach ($cartItems as $item) {
        $totalGeneral += $item['precio_efectivo'] * $item['cantidad'];
    }
} catch (Exception $e) {
    logError('ERROR', 'Error al cargar carrito.php: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $error = "No se pudo cargar la bolsa. Intenta de nuevo más tarde.";
}

require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5" id="carrito-container">

    <div class="text-start mb-5">
        <h1 class="fw-bold fs-2" style="font-family: var(--font-display);">
            <i class="fas fa-shopping-basket text-success me-2"></i> Bolsa de Compras
        </h1>
        <p class="text-secondary">Revisa tus ecoproductos antes de finalizar tu orden.</p>
    </div>

    <!-- Notificación Toast AJAX -->
    <div id="eco-toast-container" style="position:fixed;top:90px;right:24px;z-index:9999;min-width:300px;"></div>

    <?php if (!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
        <div class="card border-0 shadow-sm p-5 text-center" style="border-radius:20px;" id="empty-cart-msg">
            <div class="mb-4 text-success opacity-50"><i class="fas fa-cart-arrow-down fa-4x"></i></div>
            <h4 class="fw-bold">Tu bolsa está vacía</h4>
            <p class="text-secondary col-md-6 mx-auto">No tienes productos agregados. Explora nuestra eco-tienda y descubre alternativas al plástico.</p>
            <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-eco-primary mt-3 col-md-3 mx-auto">Ir a la Tienda</a>
        </div>
    <?php else: ?>
        <div class="row g-4" id="cart-main-row">

            <!-- ── Tabla de Artículos ─────────────────────────────────── -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm p-4" style="border-radius:16px;">
                    <div class="table-responsive">
                        <table class="table align-middle" id="cart-table">
                            <thead class="text-secondary small">
                                <tr>
                                    <th class="border-0">Producto</th>
                                    <th class="border-0 text-center">Cantidad</th>
                                    <th class="border-0 text-end">Precio</th>
                                    <th class="border-0 text-end">Subtotal</th>
                                    <th class="border-0 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="cart-tbody">
                                <?php foreach ($cartItems as $item): ?>
                                    <?php $sub = $item['precio_efectivo'] * $item['cantidad']; ?>
                                    <tr id="row-<?php echo $item['producto_id']; ?>">
                                        <!-- Imagen + Nombre -->
                                        <td class="py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div style="width:55px;height:55px;border-radius:8px;border:1px solid rgba(0,0,0,.05);overflow:hidden;flex-shrink:0;">
                                                    <img src="<?php echo htmlspecialchars(productImageUrl($item['imagen_principal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                         class="w-100 h-100 object-fit-cover"
                                                         alt="<?php echo sanitize($item['nombre']); ?>">
                                                </div>
                                                <div>
                                                    <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $item['producto_id']; ?>"
                                                       class="d-block fw-bold text-decoration-none text-reset"
                                                       style="font-size:.95rem;">
                                                        <?php echo sanitize($item['nombre']); ?>
                                                    </a>
                                                    <small class="text-success font-mono">Stock: <?php echo $item['stock']; ?></small>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Controles de Cantidad AJAX -->
                                        <td class="text-center py-3">
                                            <div class="input-group input-group-sm justify-content-center" style="max-width:110px;margin:auto;">
                                                <button class="btn btn-outline-secondary btn-sm"
                                                        onclick="cambiarCantidad(<?php echo $item['producto_id']; ?>, -1)"
                                                        title="Reducir">−</button>
                                                <input type="number"
                                                       id="qty-<?php echo $item['producto_id']; ?>"
                                                       class="form-control text-center font-mono p-1"
                                                       value="<?php echo $item['cantidad']; ?>"
                                                       min="1"
                                                       max="<?php echo $item['stock']; ?>"
                                                       style="min-width:44px;"
                                                       onchange="setCantidad(<?php echo $item['producto_id']; ?>, this.value)"
                                                       readonly>
                                                <button class="btn btn-outline-secondary btn-sm"
                                                        onclick="cambiarCantidad(<?php echo $item['producto_id']; ?>, 1)"
                                                        title="Aumentar">+</button>
                                            </div>
                                        </td>

                                        <!-- Precio unitario -->
                                        <td class="text-end py-3 fw-medium font-mono">
                                            L. <?php echo number_format($item['precio_efectivo'], 2); ?>
                                        </td>

                                        <!-- Subtotal (actualizable) -->
                                        <td class="text-end py-3 fw-bold text-success font-mono"
                                            id="subtotal-<?php echo $item['producto_id']; ?>">
                                            L. <?php echo number_format($sub, 2); ?>
                                        </td>

                                        <!-- Eliminar -->
                                        <td class="text-center py-3">
                                            <button class="btn btn-link text-danger p-1"
                                                    onclick="eliminarProducto(<?php echo $item['producto_id']; ?>)"
                                                    title="Quitar de la bolsa">
                                                <i class="far fa-trash-can fa-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
                        <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-link text-success p-0 text-decoration-none fw-semibold">
                            <i class="fas fa-chevron-left me-1"></i> Seguir Comprando
                        </a>
                        <button class="btn btn-outline-danger btn-sm"
                                onclick="vaciarBolsa()"
                                title="Vaciar carrito completo">
                            <i class="fas fa-trash-can me-1"></i> Vaciar Bolsa
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Resumen de Costos ──────────────────────────────────── -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm p-4 sticky-top" style="top:100px;border-radius:16px;">
                    <h5 class="fw-bold mb-4" style="font-family:var(--font-display);">Resumen de Pedido</h5>

                    <ul class="list-unstyled d-flex flex-column gap-3 border-bottom pb-4 mb-4 small text-secondary">
                        <li class="d-flex justify-content-between">
                            <span>Subtotal</span>
                            <span class="fw-semibold text-dark font-mono" id="resumen-subtotal">
                                L. <?php echo number_format($totalGeneral, 2); ?>
                            </span>
                        </li>
                        <li class="d-flex justify-content-between">
                            <span>Envío estándar</span>
                            <span class="text-success fw-bold font-mono">L. <?php echo number_format(SHIPPING_COST, 2); ?></span>
                        </li>
                        <li class="d-flex justify-content-between">
                            <span>Impuesto Ecológico (0%)</span>
                            <span class="text-success font-mono">L. 0.00</span>
                        </li>
                    </ul>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold m-0 text-dark">Total Estimado</h6>
                        <span class="fs-4 fw-bold text-success font-mono" id="resumen-total">
                            L. <?php echo number_format($totalGeneral + SHIPPING_COST, 2); ?>
                        </span>
                    </div>

                    <a href="<?php echo BASE_URL; ?>checkout.php"
                       class="btn btn-eco-primary w-100 py-3 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2 shadow">
                        Proceder al Pago <i class="fas fa-credit-card"></i>
                    </a>
                </div>
            </div>

        </div>
    <?php endif; ?>
</div>

<!-- ── JAVASCRIPT AJAX DEL CARRITO ──────────────────────────────────────── -->
<script>
const API_URL  = '<?php echo BASE_URL; ?>api/carrito.php';
const BASE_URL = '<?php echo BASE_URL; ?>';
const CSRF_TOKEN = '<?php echo addslashes(generateCsrfToken()); ?>';

// ── Toast de notificaciones ──────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const id = 'toast-' + Date.now();
    const bg  = type === 'success' ? '#1a5c2a' : (type === 'warning' ? '#b45309' : '#b91c1c');
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle');
    const html = `
        <div id="${id}" style="
            background:${bg};color:#fff;padding:12px 18px;border-radius:12px;
            margin-bottom:10px;font-size:.87rem;display:flex;align-items:center;gap:10px;
            box-shadow:0 4px 20px rgba(0,0,0,.2);animation:slideIn .3s ease;
        ">
            <i class="fas ${icon}"></i>
            <span>${msg}</span>
        </div>`;
    document.getElementById('eco-toast-container').insertAdjacentHTML('beforeend', html);
    setTimeout(() => {
        const el = document.getElementById(id);
        if (el) el.style.opacity = '0', setTimeout(() => el.remove(), 400);
    }, 3200);
}

// ── Actualizar contador del navbar ───────────────────────────────────────
function updateNavbarBadge(count) {
    let badge = document.querySelector('.cart-badge');
    const cartLink = document.querySelector('a[title="Mi Carrito"]') || document.querySelector('a[href*="carrito.php"]');

    if (count > 0) {
        if (badge) {
            badge.textContent = count;
        } else if (cartLink) {
            cartLink.style.position = 'relative';
            cartLink.insertAdjacentHTML('beforeend',
                `<span class="position-absolute translate-middle badge rounded-pill bg-danger cart-badge"
                       style="top:6px;left:calc(100% - 6px);font-size:.65rem;min-width:18px;">
                    ${count}
                </span>`
            );
        }
    } else {
        if (badge) badge.remove();
    }
}

// ── Actualizar resumen de costos ─────────────────────────────────────────
function updateResumen(total, grand_total) {
    const subEl   = document.getElementById('resumen-subtotal');
    const totalEl = document.getElementById('resumen-total');
    if (subEl)   subEl.textContent   = 'L. ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (totalEl) totalEl.textContent = 'L. ' + grand_total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ── Cambiar cantidad ─────────────────────────────────────────────────────
async function cambiarCantidad(producto_id, delta) {
    const input  = document.getElementById('qty-' + producto_id);
    if (!input) return;
    const actual = parseInt(input.value);
    const nueva  = actual + delta;
    if (nueva < 1) {
        eliminarProducto(producto_id);
        return;
    }
    await setCantidad(producto_id, nueva);
}

async function setCantidad(producto_id, cantidad) {
    cantidad = parseInt(cantidad);
    if (isNaN(cantidad) || cantidad < 0) return;

    const input = document.getElementById('qty-' + producto_id);
    if (input) input.readOnly = true; // bloquear mientras procesa

    try {
        const res  = await fetch(API_URL, {
            method:  'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body:    JSON.stringify({ producto_id, cantidad }),
        });
        const json = await res.json();

        if (json.success) {
            if (cantidad <= 0) {
                // El servidor lo eliminó
                document.getElementById('row-' + producto_id)?.remove();
                checkEmptyCart();
            } else {
                // Actualizar UI del ítem
                if (input) input.value = cantidad;
                const subEl = document.getElementById('subtotal-' + producto_id);
                if (subEl && json.data?.item_subtotal !== undefined) {
                    subEl.textContent = 'L. ' + json.data.item_subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                updateResumen(json.data.total, json.data.grand_total);
            }
            updateNavbarBadge(json.cart_count);
            showToast(json.message, 'success');
        } else {
            showToast(json.message, 'warning');
            if (input) input.value = parseInt(input.value); // revertir
        }
    } catch (err) {
        showToast('Error de conexión. Verifica tu internet.', 'error');
    } finally {
        if (input) input.readOnly = false;
    }
}

// ── Eliminar producto ─────────────────────────────────────────────────────
async function eliminarProducto(producto_id) {
    if (!confirm('¿Quitar este producto de tu bolsa?')) return;

    try {
        const res  = await fetch(API_URL + '?producto_id=' + producto_id, {
            method:  'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        });
        const json = await res.json();

        if (json.success) {
            const row = document.getElementById('row-' + producto_id);
            if (row) {
                row.style.transition = 'opacity .3s';
                row.style.opacity    = '0';
                setTimeout(() => { row.remove(); checkEmptyCart(); }, 300);
            }
            updateResumen(json.data.total, json.data.grand_total);
            updateNavbarBadge(json.cart_count);
            showToast(json.message, 'success');
        } else {
            showToast(json.message, 'error');
        }
    } catch (err) {
        showToast('Error de conexión.', 'error');
    }
}

// ── Vaciar bolsa completa ─────────────────────────────────────────────────
async function vaciarBolsa() {
    if (!confirm('¿Seguro que deseas vaciar tu bolsa por completo?')) return;

    const tbody = document.getElementById('cart-tbody');
    if (!tbody) return;

    const rows = tbody.querySelectorAll('tr[id^="row-"]');
    const promises = [];

    for (const row of rows) {
        const pid = row.id.replace('row-', '');
        promises.push(
            fetch(API_URL + '?producto_id=' + pid, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            }).then(r => r.json())
        );
    }

    try {
        await Promise.all(promises);
        checkEmptyCart(true);
        updateNavbarBadge(0);
        updateResumen(0, <?php echo SHIPPING_COST; ?>);
        showToast('Bolsa vaciada completamente.', 'success');
    } catch (err) {
        showToast('Error al vaciar la bolsa.', 'error');
    }
}

// ── Revisar si el carrito quedó vacío ────────────────────────────────────
function checkEmptyCart(force = false) {
    const tbody  = document.getElementById('cart-tbody');
    const mainRow = document.getElementById('cart-main-row');
    const container = document.getElementById('carrito-container');

    const isEmpty = force || (tbody && tbody.querySelectorAll('tr[id^="row-"]').length === 0);
    if (isEmpty && mainRow) {
        mainRow.remove();
        container.insertAdjacentHTML('beforeend', `
            <div class="card border-0 shadow-sm p-5 text-center" style="border-radius:20px;">
                <div class="mb-4 text-success opacity-50"><i class="fas fa-cart-arrow-down fa-4x"></i></div>
                <h4 class="fw-bold">Tu bolsa está vacía</h4>
                <p class="text-secondary col-md-6 mx-auto">Explora nuestra eco-tienda y descubre alternativas al plástico.</p>
                <a href="${BASE_URL}tienda.php" class="btn btn-eco-primary mt-3 col-md-3 mx-auto">Ir a la Tienda</a>
            </div>`
        );
    }
}

// ── CSS slide-in para toasts ──────────────────────────────────────────────
const style = document.createElement('style');
style.textContent = `@keyframes slideIn { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }`;
document.head.appendChild(style);
</script>

<?php
renderAddToCartScript();
require_once __DIR__ . '/includes/footer.php';
?>
