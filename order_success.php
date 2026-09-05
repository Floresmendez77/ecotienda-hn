<?php
/**
 * 🌱 ECOTIENDA HN - CONFIRMACIÓN DE PEDIDO EXITOSO
 * Ruta: /order_success.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pageTitle = "¡Pedido Confirmado!";

$pedido_id = 0;
if (isset($_SESSION['last_order_id'])) {
    $pedido_id = (int)$_SESSION['last_order_id'];
    unset($_SESSION['last_order_id']);
} elseif (isset($_GET['pedido'])) {
    $pedido_id = (int)$_GET['pedido'];
}

$pedido  = null;
$detalle = [];
$pago    = null;

if ($pedido_id > 0) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT p.*, DATE_FORMAT(p.fecha_pedido, '%d de %M de %Y a las %H:%i') AS fecha_formateada
             FROM pedidos p WHERE p.id = :id AND p.usuario_id = :uid LIMIT 1"
        );
        $stmt->execute([':id' => $pedido_id, ':uid' => $_SESSION['user_id']]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            $detStmt = $db->prepare(
                "SELECT dp.*, pr.nombre, pr.imagen_principal
                 FROM detalle_pedido dp
                 INNER JOIN productos pr ON dp.producto_id = pr.id
                 WHERE dp.pedido_id = :pid"
            );
            $detStmt->execute([':pid' => $pedido_id]);
            $detalle = $detStmt->fetchAll();

            $pagoStmt = $db->prepare(
                "SELECT pa.*, mp.nombre AS metodo_nombre
                 FROM pagos pa
                 LEFT JOIN metodos_pago mp ON pa.metodo_pago_id = mp.id
                 WHERE pa.pedido_id = :pid LIMIT 1"
            );
            $pagoStmt->execute([':pid' => $pedido_id]);
            $pago = $pagoStmt->fetch();
        }
    } catch (Exception $e) {
        error_log("order_success.php: " . $e->getMessage());
    }
}

if (!$pedido) redirect('mis_pedidos.php');

$es_transferencia = ($pago && (int)$pago['metodo_pago_id'] === 1);
$total_productos  = array_sum(array_column($detalle, 'cantidad'));
$arboles_equiv    = max(1, round($total_productos * 0.3, 1));

require_once __DIR__ . '/includes/navbar.php';
?>

<style>
@keyframes popIn {
    0%   { transform: scale(0.5); opacity: 0; }
    70%  { transform: scale(1.1); }
    100% { transform: scale(1);   opacity: 1; }
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes confetti {
    0%   { transform: translateY(-10px) rotate(0deg);   opacity: 1; }
    100% { transform: translateY(100px) rotate(720deg); opacity: 0; }
}

.success-icon   { animation: popIn  .6s cubic-bezier(.36,.07,.19,.97) both; }
.fade-up        { animation: fadeUp .6s ease both; }
.fade-up-delay1 { animation: fadeUp .6s ease .15s both; }
.fade-up-delay2 { animation: fadeUp .6s ease .30s both; }
.fade-up-delay3 { animation: fadeUp .6s ease .45s both; }

.order-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    overflow: hidden;
    transition: transform .2s;
}
.order-card:hover { transform: translateY(-2px); }

.steps-list { list-style: none; padding: 0; margin: 0; }
.steps-list li {
    display: flex; gap: 14px; align-items: flex-start;
    padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.08);
}
.steps-list li:last-child { border-bottom: none; }
.step-num {
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--eco-primary); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem; font-weight: 700; flex-shrink: 0;
}

.bank-card {
    background: rgba(16,185,129,.08);
    border: 1px solid rgba(16,185,129,.2);
    border-radius: 14px; padding: 18px;
}

.confetti-piece {
    position: fixed; width: 10px; height: 10px;
    border-radius: 2px; top: -20px;
    animation: confetti 2s ease-in forwards;
    pointer-events: none; z-index: 9999;
}

.hero-banner {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%);
    border-radius: 24px;
    padding: 48px 32px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.hero-banner::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
</style>

<div class="container py-5">

    <!-- HERO BANNER -->
    <div class="hero-banner mb-5 fade-up">
        <div class="success-icon d-inline-flex align-items-center justify-content-center rounded-circle mb-4"
             style="width:90px;height:90px;background:rgba(255,255,255,.15);backdrop-filter:blur(8px);">
            <i class="fas fa-check-circle text-white" style="font-size:3rem;"></i>
        </div>
        <h1 class="fw-bold text-white mb-2" style="font-family:var(--font-display);font-size:2.2rem;">
            ¡Pedido #<?= str_pad($pedido_id, 6, '0', STR_PAD_LEFT) ?> Confirmado!
        </h1>
        <p class="text-white mb-3" style="opacity:.85;font-size:1.1rem;">
            Gracias, <strong><?= sanitize($_SESSION['user_name'] ?? 'EcoCliente') ?></strong> 🌱
            Tu pedido ecológico está siendo procesado.
        </p>
        <span class="badge px-4 py-2 rounded-pill fw-semibold"
              style="background:rgba(255,255,255,.2);color:#fff;font-size:.9rem;backdrop-filter:blur(4px);">
            <i class="fas fa-clock me-2"></i>Estado: <?= ucfirst(sanitize($pedido['estado'])) ?>
        </span>
    </div>

    <div class="row g-4 justify-content-center">

        <!-- COLUMNA PRINCIPAL -->
        <div class="col-lg-7">

            <!-- Productos -->
            <div class="card order-card mb-4 fade-up-delay1">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0" style="font-family:var(--font-display);">
                        <i class="fas fa-box-open text-success me-2"></i>Productos Adquiridos
                    </h5>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    <?php foreach ($detalle as $item): ?>
                    <div class="d-flex align-items-center gap-3 py-3" style="border-bottom:1px solid rgba(0,0,0,.06);">
                        <div style="width:60px;height:60px;border-radius:12px;overflow:hidden;flex-shrink:0;background:#f8fafc;border:1px solid rgba(0,0,0,.06);">
                            <img src="<?= !empty($item['imagen_principal']) ? htmlspecialchars($item['imagen_principal']) : 'https://placehold.co/60x60/10b981/white?text=🌿' ?>"
                                 class="w-100 h-100 object-fit-cover" alt="">
                        </div>
                        <div class="flex-grow-1">
                            <strong class="d-block" style="font-size:.95rem;"><?= sanitize($item['nombre']) ?></strong>
                            <span class="text-secondary small"><?= $item['cantidad'] ?> unidad(es) × <?= formatCurrency($item['precio']) ?></span>
                        </div>
                        <span class="fw-bold text-success font-mono"><?= formatCurrency($item['subtotal']) ?></span>
                    </div>
                    <?php endforeach; ?>

                    <!-- Totales -->
                    <div class="mt-4 rounded-3 p-3" style="background:#f8fafc;">
                        <div class="d-flex justify-content-between small text-secondary mb-2">
                            <span>Subtotal productos</span>
                            <span class="font-mono"><?= formatCurrency($pedido['subtotal']) ?></span>
                        </div>
                        <?php if ((float)$pedido['descuento'] > 0): ?>
                        <div class="d-flex justify-content-between small text-secondary mb-2">
                            <span>Descuento aplicado</span>
                            <span class="text-success font-mono fw-bold">− <?= formatCurrency($pedido['descuento']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between small text-secondary mb-3">
                            <span>Envío estándar</span>
                            <span class="font-mono"><?= formatCurrency($pedido['envio']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2" style="border-top:2px solid #e2e8f0;">
                            <span class="fw-bold fs-6">Total Pagado</span>
                            <span class="fw-bold fs-4 text-success font-mono"><?= formatCurrency($pedido['total']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Método de pago -->
            <div class="card order-card mb-4 fade-up-delay1">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3" style="font-family:var(--font-display);">
                        <i class="fas fa-credit-card text-success me-2"></i>Método de Pago
                    </h5>
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:44px;height:44px;background:rgba(16,185,129,.1);flex-shrink:0;">
                            <i class="fas fa-university text-success"></i>
                        </div>
                        <div>
                            <strong><?= sanitize($pago['metodo_nombre'] ?? 'Transferencia Bancaria') ?></strong>
                            <?php if (!empty($pago['referencia'])): ?>
                            <div class="text-secondary small">Ref: <code><?= sanitize($pago['referencia']) ?></code></div>
                            <?php endif; ?>
                        </div>
                        <span class="ms-auto badge bg-warning text-dark rounded-pill px-3 py-2">
                            <i class="fas fa-hourglass-half me-1"></i>Pendiente
                        </span>
                    </div>
                </div>
            </div>

            <!-- Instrucciones transferencia -->
            <?php if ($es_transferencia): ?>
            <div class="card order-card mb-4 fade-up-delay2" style="border-left:4px solid #10b981 !important;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 text-success" style="font-family:var(--font-display);">
                        <i class="fas fa-university me-2"></i>Instrucciones de Pago
                    </h5>
                    <div class="alert alert-warning border-0 rounded-3 mb-4 d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-triangle fa-lg"></i>
                        <div><strong>Tienes 24 horas</strong> para enviar el comprobante. Pedidos sin abono se cancelan automáticamente.</div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="bank-card h-100">
                                <div class="fw-bold text-success mb-1"><i class="fas fa-piggy-bank me-1"></i> BAC Credomatic</div>
                                <div class="text-secondary small">Cta. Ahorros Lempiras</div>
                                <code class="d-block fs-4 fw-bold text-dark my-1">742901920</code>
                                <div class="text-secondary small">EcoTienda HN S. de R.L.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bank-card h-100">
                                <div class="fw-bold text-success mb-1"><i class="fas fa-piggy-bank me-1"></i> Banco de Occidente</div>
                                <div class="text-secondary small">Cta. Ahorros Lempiras</div>
                                <code class="d-block fs-4 fw-bold text-dark my-1">1120011928</code>
                                <div class="text-secondary small">EcoTienda HN S. de R.L.</div>
                            </div>
                        </div>
                    </div>
                    <ul class="steps-list">
                        <li><span class="step-num">1</span><span class="text-secondary small">Transfiere <strong class="text-dark"><?= formatCurrency($pedido['total']) ?></strong> a cualquiera de las cuentas.</span></li>
                        <li><span class="step-num">2</span><span class="text-secondary small">Guarda el comprobante (captura o PDF).</span></li>
                        <li><span class="step-num">3</span><span class="text-secondary small">Envíalo por WhatsApp al <strong class="text-dark">+504 9900-1122</strong> o correo <strong class="text-dark">pagos@ecotiendahn.com</strong>.</span></li>
                        <li><span class="step-num">4</span><span class="text-secondary small">Indica el número de pedido: <strong class="text-dark">#<?= $pedido_id ?></strong>.</span></li>
                        <li><span class="step-num">5</span><span class="text-secondary small">Tu pedido será enviado en <strong class="text-dark">24–48 horas hábiles</strong> tras la verificación.</span></li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- COLUMNA LATERAL -->
        <div class="col-lg-4">

            <!-- Número pedido -->
            <div class="card order-card mb-4 text-center fade-up-delay1">
                <div class="card-body p-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                         style="width:64px;height:64px;background:rgba(16,185,129,.1);">
                        <i class="fas fa-receipt text-success fa-xl"></i>
                    </div>
                    <div class="text-secondary small mb-1">Número de Pedido</div>
                    <div class="fw-bold font-mono mb-1" style="font-size:2rem;letter-spacing:2px;">
                        #<?= str_pad($pedido_id, 6, '0', STR_PAD_LEFT) ?>
                    </div>
                    <div class="text-secondary small">
                        <i class="fas fa-calendar-alt me-1 text-success"></i>
                        <?= $pedido['fecha_formateada'] ?? date('d \d\e F \d\e Y') ?>
                    </div>
                </div>
            </div>

            <!-- Impacto ecológico -->
            <div class="card order-card mb-4 fade-up-delay2 text-white"
                 style="background:linear-gradient(135deg,#064e3b,#059669);">
                <div class="card-body p-4 text-center">
                    <div style="font-size:3rem;" class="mb-2">🌍</div>
                    <h6 class="fw-bold mb-2" style="font-family:var(--font-display);">Tu Impacto Ecológico</h6>
                    <p class="small mb-3" style="opacity:.9;line-height:1.6;">
                        Tu compra equivale a plantar <strong><?= $arboles_equiv ?> árbol(es)</strong> en Honduras
                        y evitó el uso de <strong><?= ($total_productos * 2) ?> botellas plásticas</strong>. 🌳
                    </p>
                    <div class="d-flex align-items-center justify-content-center gap-2 small" style="opacity:.8;">
                        <i class="fas fa-leaf"></i>
                        <span>Honduras agradece tu elección.</span>
                    </div>
                </div>
            </div>

            <!-- Próximos pasos -->
            <div class="card order-card mb-4 fade-up-delay2">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3" style="font-family:var(--font-display);">
                        <i class="fas fa-route text-success me-2"></i>¿Qué sigue?
                    </h6>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:32px;height:32px;background:#fef3c7;">
                                <i class="fas fa-envelope text-warning small"></i>
                            </div>
                            <div class="small text-secondary">Revisa tu correo — ya te enviamos la confirmación del pedido.</div>
                        </div>
                        <?php if ($es_transferencia): ?>
                        <div class="d-flex gap-3 align-items-start">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:32px;height:32px;background:#d1fae5;">
                                <i class="fas fa-money-bill-transfer text-success small"></i>
                            </div>
                            <div class="small text-secondary">Realiza la transferencia y envía el comprobante para aprobar tu pedido.</div>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-3 align-items-start">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:32px;height:32px;background:#e0f2fe;">
                                <i class="fas fa-truck text-info small"></i>
                            </div>
                            <div class="small text-secondary">Tu pedido será enviado en 24–48 horas hábiles tras la verificación del pago.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="d-flex flex-column gap-3 fade-up-delay3">
                <a href="<?= BASE_URL ?>mis_pedidos.php"
                   class="btn btn-eco-primary py-3 fw-bold rounded-3 w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="fas fa-list-alt"></i> Ver Mis Pedidos
                </a>
                <a href="<?= BASE_URL ?>tienda.php"
                   class="btn btn-outline-success py-3 fw-bold rounded-3 w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="fas fa-leaf"></i> Seguir Comprando
                </a>
            </div>

        </div>
    </div>
</div>

<script>
// Confetti celebration
(function() {
    const colors = ['#10b981','#059669','#34d399','#6ee7b7','#fbbf24','#f59e0b'];
    for (let i = 0; i < 60; i++) {
        setTimeout(() => {
            const el = document.createElement('div');
            el.className = 'confetti-piece';
            el.style.cssText = `
                left: ${Math.random() * 100}vw;
                background: ${colors[Math.floor(Math.random() * colors.length)]};
                width: ${6 + Math.random() * 8}px;
                height: ${6 + Math.random() * 8}px;
                border-radius: ${Math.random() > .5 ? '50%' : '2px'};
                animation-duration: ${1.5 + Math.random() * 2}s;
                animation-delay: ${Math.random() * .5}s;
            `;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3500);
        }, i * 30);
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>