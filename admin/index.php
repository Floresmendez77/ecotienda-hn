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
$estadosPedidos    = ['pendiente' => 0, 'procesando' => 0, 'enviado' => 0, 'entregado' => 0, 'cancelado' => 0];

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
    $stmt = $db->query("SELECT COUNT(*) FROM productos WHERE activo = 1 OR activo IS NULL");
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
        SELECT p.id, p.total, p.estado, p.fecha,
               u.nombre, u.apellido
        FROM pedidos p
        INNER JOIN usuarios u ON p.usuario_id = u.id
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
    $estadosPedidos   = ['pendiente' => 8, 'procesando' => 5, 'enviado' => 11, 'entregado' => 34, 'cancelado' => 3];
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
$chartDonaLabels = json_encode(['Pendiente', 'Procesando', 'Enviado', 'Entregado', 'Cancelado']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Admin EcoTienda</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --eco-green:    #10b981;
            --eco-green2:   #059669;
            --eco-green3:   #34d399;
            --admin-bg:     #0b0f19;
            --admin-card:   #1e293b;
            --admin-border: rgba(255,255,255,.08);
            --font-sans:    'Plus Jakarta Sans', sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: var(--font-sans);
            background-color: var(--admin-bg);
            color: #f1f5f9;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 260px;
            background-color: #0f172a;
            border-right: 1px solid var(--admin-border);
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0;
            z-index: 1020;
            transition: margin .3s;
        }
        .sidebar-brand {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--eco-green) !important;
            padding: 1.4rem 1.5rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--admin-border);
            text-decoration: none;
        }
        .sidebar-menu { list-style: none; padding: 1rem 0; margin: 0; }
        .sidebar-item a {
            padding: .8rem 1.5rem;
            display: flex;
            align-items: center;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 500;
            font-size: .9rem;
            border-left: 3px solid transparent;
            transition: all .2s;
            gap: .75rem;
        }
        .sidebar-item a:hover, .sidebar-item.active a {
            color: #fff;
            background: rgba(16,185,129,.08);
            border-left-color: var(--eco-green);
        }
        .sidebar-item i { width: 20px; text-align: center; }

        /* ── MAIN ── */
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }
        @media (max-width: 991.98px) {
            .sidebar { margin-left: -260px; }
            .sidebar.open { margin-left: 0; }
            .main-content { margin-left: 0; }
        }

        /* ── CARDS ── */
        .admin-card {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            border-radius: 16px;
            padding: 1.5rem;
        }

        /* ── KPI CARDS ── */
        .kpi-card {
            background: var(--admin-card);
            border: 1px solid var(--admin-border);
            border-radius: 16px;
            padding: 1.4rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform .2s, box-shadow .2s;
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(16,185,129,.12);
        }
        .kpi-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .kpi-label { font-size: .78rem; color: #64748b; font-weight: 500; margin-bottom: .3rem; }
        .kpi-value { font-size: 1.6rem; font-weight: 800; line-height: 1.1; letter-spacing: -.5px; }

        /* ── BADGE ESTADO ── */
        .badge-estado {
            padding: .35em .75em;
            border-radius: 999px;
            font-size: .73rem;
            font-weight: 600;
            letter-spacing: .3px;
        }
        .badge-pendiente  { background: rgba(251,191, 36,.15); color: #fbbf24; }
        .badge-procesando { background: rgba(56, 189,248,.15); color: #38bdf8; }
        .badge-enviado    { background: rgba(139,92, 246,.15); color: #a78bfa; }
        .badge-entregado  { background: rgba(16, 185,129,.15); color: #10b981; }
        .badge-cancelado  { background: rgba(239,68,  68,.15); color: #f87171; }

        /* ── TABLE ── */
        .eco-table { width: 100%; border-collapse: collapse; }
        .eco-table th {
            color: #64748b; font-size: .78rem; font-weight: 600;
            padding: .6rem 1rem; text-transform: uppercase; letter-spacing: .5px;
            border-bottom: 1px solid var(--admin-border);
        }
        .eco-table td {
            padding: .9rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,.04);
            font-size: .9rem;
            vertical-align: middle;
        }
        .eco-table tr:last-child td { border-bottom: none; }
        .eco-table tr:hover td { background: rgba(255,255,255,.02); }

        /* ── Fallback badge ── */
        .fallback-badge {
            display: inline-block;
            font-size: .72rem;
            background: rgba(251,191,36,.15);
            color: #fbbf24;
            border-radius: 6px;
            padding: .15rem .5rem;
            margin-left: .5rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <a href="<?php echo BASE_URL; ?>admin/index.php" class="sidebar-brand">
        <i class="fas fa-leaf me-2"></i>EcoTienda <span class="text-white ms-1">Admin</span>
    </a>
    <ul class="sidebar-menu">
        <li class="sidebar-item active"><a href="<?php echo BASE_URL; ?>admin/index.php"><i class="fas fa-gauge-high"></i>Dashboard</a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>admin/productos.php"><i class="fas fa-box"></i>Productos</a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>admin/categorias.php"><i class="fas fa-tags"></i>Categorías</a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>admin/usuarios.php"><i class="fas fa-users"></i>Usuarios</a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>admin/pedidos.php"><i class="fas fa-shopping-bag"></i>Pedidos</a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>admin/reportes.php"><i class="fas fa-chart-line"></i>Reportes</a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>admin/inventario.php"><i class="fas fa-warehouse"></i>Inventario</a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>admin/configuracion.php"><i class="fas fa-cog"></i>Configuración</a></li>
        <li class="sidebar-item mt-4"><a href="<?php echo BASE_URL; ?>index.php" class="text-success"><i class="fas fa-store"></i>Ver tienda</a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i>Cerrar sesión</a></li>
    </ul>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div class="main-content">

    <!-- Header -->
    <header class="d-flex justify-content-between align-items-center mb-5 pb-3" style="border-bottom:1px solid var(--admin-border)">
        <div>
            <h1 class="h3 fw-bold m-0" style="font-family:var(--font-display)">
                Rendimiento General
                <?php if ($usandoFallback): ?>
                    <span class="fallback-badge"><i class="fas fa-database me-1"></i>datos demo</span>
                <?php endif; ?>
            </h1>
            <p class="text-secondary small mb-0 mt-1">🌱 Resumen y analíticas de la operación — EcoTienda HN</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="small text-success d-none d-md-inline">
                <i class="fas fa-calendar-check me-1"></i><?php echo date('d M, Y'); ?>
            </span>
            <!-- Hamburger for mobile -->
            <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            <div style="width:38px;height:38px;border-radius:50%;background:var(--eco-green);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem">ADM</div>
        </div>
    </header>

    <!-- ══ KPI: 4 TARJETAS ══ -->
    <div class="row g-3 mb-4">

        <!-- Pedidos hoy -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div>
                    <div class="kpi-label"><i class="fas fa-calendar-day me-1"></i>Pedidos hoy</div>
                    <div class="kpi-value text-success"><?php echo $pedidosHoy; ?></div>
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
                    <div class="kpi-value text-success" style="font-size:1.3rem"><?php echo formatCurrency($ingresosMes); ?></div>
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
                    <div class="kpi-value text-info"><?php echo $usuariosTotal; ?></div>
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
                    <div class="kpi-value text-warning"><?php echo $productosActivos; ?></div>
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
                            $labels = ['pendiente'=>'Pendiente','procesando'=>'Procesando','enviado'=>'Enviado','entregado'=>'Entregado','cancelado'=>'Cancelado'];
                            $label = $labels[$estadoKey] ?? ucfirst($estadoKey);
                        ?>
                            <tr>
                                <td class="text-success fw-bold">#<?php echo $order['id']; ?></td>
                                <td><?php echo sanitize($order['nombre'] . ' ' . $order['apellido']); ?></td>
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

</div><!-- /main-content -->

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
