<?php
/**
 * 🌱 ECOTIENDA HN - PANEL PRINCIPAL DEL ADMINISTRADOR
 * Ruta: /admin/index.php
 * Descripción: Dashboard con métricas reales, Chart.js (barras + dona),
 *              tabla de últimos 5 pedidos y 4 tarjetas KPI del día/mes.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Panel de Control";
$pageSubtitle = "🌱 Resumen y analíticas de la operación — EcoTienda HN";

// ══════════════════════════════════════════════════════════════
//  MÉTRICAS REALES
// ══════════════════════════════════════════════════════════════
$pedidosHoy        = 0;
$ingresosMes       = 0.00;
$usuariosTotal     = 0;
$productosActivos  = 0;

// Chart data
$mesesLabels       = [];
$ventasMensuales   = [];
$estadosPedidos    = ['pendiente' => 0, 'pagado' => 0, 'procesando' => 0, 'enviado' => 0, 'entregado' => 0, 'cancelado' => 0];

$lowStockProducts  = [];
$recentOrders      = [];

try {
    $db = Database::getConnection();

    // ── KPI 1: Pedidos de hoy ─────────────────────────────────
    $stmt = $db->query("SELECT COUNT(*) FROM pedidos WHERE DATE(fecha) = CURDATE()");
    $pedidosHoy = (int)$stmt->fetchColumn();

    // ── KPI 2: Ingresos del mes en curso ─────────────────────
    $stmt = $db->query("
        SELECT COALESCE(SUM(total), 0)
        FROM pedidos
        WHERE MONTH(fecha) = MONTH(CURDATE())
          AND YEAR(fecha)  = YEAR(CURDATE())
          AND estado NOT IN ('cancelado')
    ");
    $ingresosMes = (float)$stmt->fetchColumn();

    // ── KPI 3: Usuarios registrados ───────────────────────────
    $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol_id = 2");
    $usuariosTotal = (int)$stmt->fetchColumn();

    // ── KPI 4: Productos activos ──────────────────────────────
    $stmt = $db->query("SELECT COUNT(*) FROM productos WHERE estado = 'activo'");
    $productosActivos = (int)$stmt->fetchColumn();

    // ── Gráfica de barras: ventas últimos 6 meses ─────────────
    $stmt = $db->query("
        SELECT
            DATE_FORMAT(fecha, '%b %Y')  AS mes_label,
            DATE_FORMAT(fecha, '%Y-%m')  AS mes_key,
            COALESCE(SUM(total), 0)      AS total_ventas
        FROM pedidos
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          AND estado NOT IN ('cancelado')
        GROUP BY mes_key, mes_label
        ORDER BY mes_key ASC
    ");
    $ventasRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rellenar los 6 meses aunque no haya ventas
    for ($i = 5; $i >= 0; $i--) {
        $key   = date('Y-m', strtotime("-{$i} months"));
        $label = date('M Y', strtotime("-{$i} months"));
        $mesesLabels[]    = $label;
        $ventasMensuales[$key] = 0;
    }
    foreach ($ventasRows as $row) {
        if (isset($ventasMensuales[$row['mes_key']])) {
            $ventasMensuales[$row['mes_key']] = (float)$row['total_ventas'];
        }
    }
    $ventasMensuales = array_values($ventasMensuales);

    // ── Gráfica de dona: distribución por estado ──────────────
    $stmt = $db->query("
        SELECT estado, COUNT(*) AS cantidad
        FROM pedidos
        GROUP BY estado
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['estado'];
        if (array_key_exists($key, $estadosPedidos)) {
            $estadosPedidos[$key] = (int)$row['cantidad'];
        }
    }

    // ── Stock crítico ─────────────────────────────────────────
    $stmt = $db->query("SELECT id, nombre, stock, precio FROM productos WHERE stock <= 5 ORDER BY stock ASC LIMIT 5");
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Últimos 5 pedidos con cliente ─────────────────────────
    $stmt = $db->query("
        SELECT p.id, p.total, p.estado, p.fecha, p.correo_invitado,
               u.nombre, u.apellido
        FROM pedidos p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        ORDER BY p.fecha DESC
        LIMIT 5
    ");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    logError('ERROR', 'Dashboard admin: error al cargar métricas', [
        'file' => $e->getFile(), 'line' => $e->getLine(), 'msg' => $e->getMessage()
    ]);
}

// ── Fallback visual (BD vacía / instalación fresca) ───────────
$usandoFallback = ($pedidosHoy === 0 && $ingresosMes == 0 && $usuariosTotal === 0);
if ($usandoFallback) {
    $pedidosHoy       = 7;
    $ingresosMes      = 28450.00;
    $usuariosTotal    = 41;
    $productosActivos = 12;
    $ventasMensuales  = [12000, 19500, 31200, 24800, 45000, 28450];
    $estadosPedidos   = ['pendiente' => 8, 'pagado' => 6, 'procesando' => 5, 'enviado' => 11, 'entregado' => 34, 'cancelado' => 3];
    $lowStockProducts = [
        ['id' => 3, 'nombre' => 'Set de Cubiertos de Bambú con Estuche', 'stock' => 2, 'precio' => 85.00],
        ['id' => 4, 'nombre' => 'Termo Acero de Doble Capa Térmica', 'stock' => 0, 'precio' => 350.00],
    ];
    $recentOrders = [
        ['id' => 102, 'nombre' => 'Diana',  'apellido' => 'Mendoza',   'total' => 430.00, 'estado' => 'pendiente',  'fecha' => '2026-06-07 18:25:00'],
        ['id' => 101, 'nombre' => 'Josué',  'apellido' => 'Rodríguez', 'total' => 270.00, 'estado' => 'entregado',  'fecha' => '2026-06-06 11:10:00'],
        ['id' => 100, 'nombre' => 'Andrea', 'apellido' => 'Flores',    'total' => 890.00, 'estado' => 'enviado',    'fecha' => '2026-06-05 09:05:00'],
        ['id' => 99,  'nombre' => 'Carlos', 'apellido' => 'López',     'total' => 150.00, 'estado' => 'procesando', 'fecha' => '2026-06-04 16:40:00'],
        ['id' => 98,  'nombre' => 'María',  'apellido' => 'Turcios',   'total' => 520.00, 'estado' => 'cancelado',  'fecha' => '2026-06-03 14:22:00'],
    ];
}

// JSON para Chart.js
$chartMeses     = json_encode($mesesLabels);
$chartVentas    = json_encode($ventasMensuales);
$chartDonaData  = json_encode(array_values($estadosPedidos));
$chartDonaLabels = json_encode(['Pendiente', 'Pagado', 'Procesando', 'Enviado', 'Entregado', 'Cancelado']);
?>
<?php require_once __DIR__ . '/includes/admin_navbar.php'; ?>

    <?php if ($usandoFallback): ?>
        <div class="d-flex justify-content-end mb-4">
            <span class="fallback-badge"><i class="fas fa-database me-1"></i>Mostrando datos de demostración</span>
        </div>
    <?php endif; ?>

    <!-- ══ KPI: 4 TARJETAS (con animación GSAP CountUp) ══ -->
    <div class="row g-3 mb-4">

        <!-- Pedidos hoy -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div>
                    <div class="kpi-label"><i class="fas fa-calendar-day me-1"></i>Pedidos hoy</div>
                    <div class="kpi-value text-success" id="kpiPedidosHoy" data-count-target="<?php echo (int)$pedidosHoy; ?>">0</div>
                    <div class="mt-1 small text-muted">órdenes recibidas</div>
                </div>
                <div class="kpi-icon" style="background:rgba(16,185,129,.12);color:var(--eco-green)">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>

        <!-- Ingresos del mes -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div>
                    <div class="kpi-label"><i class="fas fa-calendar-alt me-1"></i>Ingresos del mes</div>
                    <div class="kpi-value text-success" style="font-size:1.3rem" id="kpiIngresosMes" data-count-target="<?php echo (float)$ingresosMes; ?>" data-count-currency="1"><?php echo formatCurrency(0); ?></div>
                    <div class="mt-1 small text-muted"><?php echo date('F Y'); ?></div>
                </div>
                <div class="kpi-icon" style="background:rgba(16,185,129,.12);color:var(--eco-green)">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>

        <!-- Usuarios registrados -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div>
                    <div class="kpi-label"><i class="fas fa-users me-1"></i>Clientes registrados</div>
                    <div class="kpi-value text-info" id="kpiUsuariosTotal" data-count-target="<?php echo (int)$usuariosTotal; ?>">0</div>
                    <div class="mt-1 small text-muted">compradores activos</div>
                </div>
                <div class="kpi-icon" style="background:rgba(56,189,248,.12);color:#38bdf8">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <!-- Productos activos -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div>
                    <div class="kpi-label"><i class="fas fa-leaf me-1"></i>Productos activos</div>
                    <div class="kpi-value text-warning" id="kpiProductosActivos" data-count-target="<?php echo (int)$productosActivos; ?>">0</div>
                    <div class="mt-1 small text-muted">en catálogo</div>
                </div>
                <div class="kpi-icon" style="background:rgba(251,191,36,.12);color:#fbbf24">
                    <i class="fas fa-box-open"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ GRÁFICAS ══ -->
    <div class="row g-4 mb-4">

        <!-- Barras: ventas últimos 6 meses -->
        <div class="col-lg-8">
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold m-0" style="font-family:var(--font-display)">
                        <i class="fas fa-chart-bar text-success me-2"></i>Ventas — Últimos 6 Meses
                    </h5>
                    <span class="small text-muted">SUM(total) GROUP BY MONTH</span>
                </div>
                <div style="height:280px;position:relative">
                    <canvas id="chartBarras"></canvas>
                </div>
            </div>
        </div>

        <!-- Dona: distribución por estado -->
        <div class="col-lg-4">
            <div class="admin-card d-flex flex-column" style="height:100%">
                <h5 class="fw-bold mb-4" style="font-family:var(--font-display)">
                    <i class="fas fa-chart-pie text-success me-2"></i>Pedidos por Estado
                </h5>
                <div style="flex:1;position:relative;min-height:200px">
                    <canvas id="chartDona"></canvas>
                </div>
                <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                    <?php
                    $donaColores = [
                        'pendiente'  => ['bg' => 'rgba(251,191,36,.15)',  'text' => '#fbbf24', 'label' => 'Pendiente'],
                        'pagado'     => ['bg' => 'rgba(45,212,191,.15)',  'text' => '#2dd4bf', 'label' => 'Pagado'],
                        'procesando' => ['bg' => 'rgba(56,189,248,.15)',  'text' => '#38bdf8', 'label' => 'Procesando'],
                        'enviado'    => ['bg' => 'rgba(139,92,246,.15)',  'text' => '#a78bfa', 'label' => 'Enviado'],
                        'entregado'  => ['bg' => 'rgba(16,185,129,.15)', 'text' => '#10b981', 'label' => 'Entregado'],
                        'cancelado'  => ['bg' => 'rgba(239,68,68,.15)',  'text' => '#f87171', 'label' => 'Cancelado'],
                    ];
                    foreach ($estadosPedidos as $estado => $qty): ?>
                        <span style="background:<?php echo $donaColores[$estado]['bg']; ?>;color:<?php echo $donaColores[$estado]['text']; ?>;border-radius:8px;padding:.25rem .6rem;font-size:.75rem;font-weight:600">
                            <?php echo $donaColores[$estado]['label']; ?> (<?php echo $qty; ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ ÚLTIMOS 5 PEDIDOS + STOCK CRÍTICO ══ -->
    <div class="row g-4">

        <!-- Tabla últimos 5 pedidos -->
        <div class="col-lg-8">
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold m-0" style="font-family:var(--font-display)">
                        <i class="fas fa-receipt text-success me-2"></i>Últimos 5 Pedidos
                    </h5>
                    <a href="<?php echo BASE_URL; ?>admin/pedidos.php" class="btn btn-sm" style="background:rgba(16,185,129,.1);color:var(--eco-green);border-radius:8px;font-size:.8rem;font-weight:600">
                        Ver todos <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="eco-table">
                        <thead>
                            <tr>
                                <th>#Pedido</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentOrders as $order):
                            $estadoKey = $order['estado'] ?? 'pendiente';
                            $labels = ['pendiente'=>'Pendiente','pagado'=>'Pagado','procesando'=>'Procesando','enviado'=>'Enviado','entregado'=>'Entregado','cancelado'=>'Cancelado'];
                            $label = $labels[$estadoKey] ?? ucfirst($estadoKey);
                        ?>
                            <tr>
                                <td class="text-success fw-bold">#<?php echo $order['id']; ?></td>
                                <td><?php echo !empty($order['nombre']) ? sanitize($order['nombre'] . ' ' . $order['apellido']) : sanitize($order['correo_invitado'] ?? 'Invitado'); ?></td>
                                <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($order['fecha'])); ?></td>
                                <td class="text-end fw-600"><?php echo formatCurrency($order['total']); ?></td>
                                <td class="text-center">
                                    <span class="badge-estado badge-<?php echo $estadoKey; ?>"><?php echo $label; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Stock crítico -->
        <div class="col-lg-4">
            <div class="admin-card h-100">
                <h5 class="fw-bold mb-3" style="font-family:var(--font-display);color:#f87171">
                    <i class="fas fa-triangle-exclamation me-2"></i>Inventario Crítico
                </h5>
                <?php if (empty($lowStockProducts)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-check fa-2x text-success opacity-50 mb-2 d-block"></i>
                        <span class="small text-muted">Todo el inventario sobrepasa las 5 unidades.</span>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                    <?php foreach ($lowStockProducts as $p): ?>
                        <div class="d-flex justify-content-between align-items-center pb-2" style="border-bottom:1px solid var(--admin-border)">
                            <div class="text-truncate" style="max-width:200px">
                                <strong class="small d-block"><?php echo sanitize($p['nombre']); ?></strong>
                                <span class="text-muted" style="font-size:.78rem"><?php echo formatCurrency($p['precio']); ?></span>
                            </div>
                            <span class="badge-estado <?php echo $p['stock'] == 0 ? 'badge-cancelado' : 'badge-pendiente'; ?>">
                                <?php echo $p['stock']; ?> uds
                            </span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <a href="<?php echo BASE_URL; ?>admin/inventario.php" class="btn btn-sm w-100 mt-4 py-2 fw-600" style="background:rgba(239,68,68,.1);color:#f87171;border-radius:10px">
                        <i class="fas fa-boxes me-1"></i>Gestionar inventario
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- GSAP COUNTUP: animación de las 4 tarjetas KPI -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof gsap === 'undefined') return;
    document.querySelectorAll('[data-count-target]').forEach(function (el) {
        var target = parseFloat(el.getAttribute('data-count-target')) || 0;
        var isCurrency = el.hasAttribute('data-count-currency');
        var obj = { value: 0 };
        gsap.to(obj, {
            value: target,
            duration: 1.5,
            ease: 'power2.out',
            onUpdate: function () {
                if (isCurrency) {
                    el.textContent = 'L. ' + obj.value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    el.textContent = Math.round(obj.value).toLocaleString('en-US');
                }
            }
        });
    });
});
</script>

<!-- CHART.JS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.color = '#64748b';
    Chart.defaults.font.family = 'Plus Jakarta Sans';

    // ── Barras: Ventas últimos 6 meses ────────────────────────
    const ctxBar = document.getElementById('chartBarras');
    if (ctxBar) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?php echo $chartMeses; ?>,
                datasets: [{
                    label: 'Ventas (Lps.)',
                    data: <?php echo $chartVentas; ?>,
                    backgroundColor: [
                        'rgba(16,185,129,.6)',
                        'rgba(16,185,129,.55)',
                        'rgba(16,185,129,.5)',
                        'rgba(16,185,129,.6)',
                        'rgba(16,185,129,.7)',
                        'rgba(16,185,129,.85)',
                    ],
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' L. ' + ctx.parsed.y.toLocaleString('es-HN', {minimumFractionDigits:2})
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(255,255,255,.04)' },
                        ticks: {
                            callback: val => 'L. ' + (val/1000).toFixed(0) + 'k'
                        }
                    },
                    x: { grid: { color: 'rgba(255,255,255,.04)' } }
                }
            }
        });
    }

    // ── Dona: distribución por estado ─────────────────────────
    const ctxDona = document.getElementById('chartDona');
    if (ctxDona) {
        new Chart(ctxDona, {
            type: 'doughnut',
            data: {
                labels: <?php echo $chartDonaLabels; ?>,
                datasets: [{
                    data: <?php echo $chartDonaData; ?>,
                    backgroundColor: [
                        'rgba(251,191,36,.75)',
                        'rgba(45,212,191,.75)',
                        'rgba(56,189,248,.75)',
                        'rgba(139,92,246,.75)',
                        'rgba(16,185,129,.75)',
                        'rgba(239,68,68,.75)',
                    ],
                    borderColor: '#1e293b',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed} pedidos`
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
