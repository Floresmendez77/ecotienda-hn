<?php
/**
 * 🌱 ECOTIENDA HN — REPORTES AVANZADOS + EXPORTACIÓN PDF PREMIUM (jsPDF)
 * Fase 8: filtros de fecha, comparación contra período anterior,
 * métricas adicionales (ticket promedio, clientes nuevos/recurrentes,
 * stock crítico, reporte de cupones) y exportación a CSV.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = Database::getConnection();

$pageTitle       = "Reportes Avanzados";
$pageSubtitle    = "📊 Análisis financiero y operativo de EcoTienda HN";
$salesCalculated = 0.00;
$bestSellersList = [];
$ventasMes       = [];
$resumenEstados  = [];
$pedidosTabla    = [];

// ═══════════════════════════════════════════════════════════════════════
//  FILTRO DE FECHAS — presets + rango libre
// ═══════════════════════════════════════════════════════════════════════
$periodo = $_GET['periodo'] ?? 'este_mes';
$permitidos = ['hoy', 'este_mes', 'mes_anterior', '30dias', 'este_anio', 'personalizado'];
if (!in_array($periodo, $permitidos, true)) $periodo = 'este_mes';

$hoy = new DateTime('today');

switch ($periodo) {
    case 'hoy':
        $inicio = (clone $hoy);
        $fin    = (clone $hoy);
        $prevInicio = (clone $hoy)->modify('-1 day');
        $prevFin    = (clone $hoy)->modify('-1 day');
        $periodoLabel = 'Hoy';
        break;

    case 'mes_anterior':
        $inicio = (new DateTime('first day of last month'));
        $fin    = (new DateTime('last day of last month'));
        $prevInicio = (clone $inicio)->modify('-1 month');
        $prevFin    = (clone $fin)->modify('-1 month');
        $periodoLabel = 'Mes anterior (' . $inicio->format('M Y') . ')';
        break;

    case '30dias':
        $inicio = (clone $hoy)->modify('-29 days');
        $fin    = (clone $hoy);
        $prevInicio = (clone $inicio)->modify('-30 days');
        $prevFin    = (clone $inicio)->modify('-1 day');
        $periodoLabel = 'Últimos 30 días';
        break;

    case 'este_anio':
        $inicio = new DateTime($hoy->format('Y') . '-01-01');
        $fin    = (clone $hoy);
        $prevInicio = (clone $inicio)->modify('-1 year');
        $prevFin    = (clone $fin)->modify('-1 year');
        $periodoLabel = 'Este año (' . $hoy->format('Y') . ')';
        break;

    case 'personalizado':
        try {
            $inicio = new DateTime($_GET['fecha_inicio'] ?? 'first day of this month');
            $fin    = new DateTime($_GET['fecha_fin'] ?? 'today');
            if ($inicio > $fin) { $tmp = $inicio; $inicio = $fin; $fin = $tmp; }
        } catch (Exception $e) {
            $inicio = new DateTime('first day of this month');
            $fin    = clone $hoy;
        }
        $dias = (int)$inicio->diff($fin)->days + 1;
        $prevFin    = (clone $inicio)->modify('-1 day');
        $prevInicio = (clone $prevFin)->modify('-' . ($dias - 1) . ' days');
        $periodoLabel = $inicio->format('d/m/Y') . ' – ' . $fin->format('d/m/Y');
        break;

    case 'este_mes':
    default:
        $inicio = new DateTime('first day of this month');
        $fin    = (clone $hoy);
        $prevInicio = (clone $inicio)->modify('-1 month');
        $prevFin    = (clone $fin)->modify('-1 month');
        $periodoLabel = 'Este mes (' . $inicio->format('M Y') . ')';
        break;
}

$fInicio     = $inicio->format('Y-m-d') . ' 00:00:00';
$fFin        = $fin->format('Y-m-d') . ' 23:59:59';
$fPrevInicio = $prevInicio->format('Y-m-d') . ' 00:00:00';
$fPrevFin    = $prevFin->format('Y-m-d') . ' 23:59:59';

// Helper: variación porcentual entre dos valores
function pctChange(float $actual, float $anterior): ?float {
    if ($anterior == 0.0) return $actual > 0 ? null : 0.0; // null = "sin base de comparación"
    return round((($actual - $anterior) / $anterior) * 100, 1);
}

$ventasPeriodo        = 0.00;
$ventasPeriodoAnt     = 0.00;
$pedidosPeriodo       = 0;
$pedidosPeriodoAnt    = 0;
$ticketPromedio       = 0.00;
$ticketPromedioAnt    = 0.00;
$clientesNuevos       = 0;
$clientesRecurrentes  = 0;
$stockCritico         = [];
$reporteCupones       = [];
$detalleCuponesPorCodigo = [];

try {
    // ── Ventas y pedidos del período + período anterior ──────────────────
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0), COUNT(*) FROM pedidos WHERE fecha BETWEEN :i AND :f AND estado NOT IN ('cancelado')");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    [$ventasPeriodo, $pedidosPeriodo] = $stmt->fetch(PDO::FETCH_NUM);
    $ventasPeriodo  = (float)$ventasPeriodo;
    $pedidosPeriodo = (int)$pedidosPeriodo;

    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0), COUNT(*) FROM pedidos WHERE fecha BETWEEN :i AND :f AND estado NOT IN ('cancelado')");
    $stmt->execute([':i' => $fPrevInicio, ':f' => $fPrevFin]);
    [$ventasPeriodoAnt, $pedidosPeriodoAnt] = $stmt->fetch(PDO::FETCH_NUM);
    $ventasPeriodoAnt  = (float)$ventasPeriodoAnt;
    $pedidosPeriodoAnt = (int)$pedidosPeriodoAnt;

    $ticketPromedio    = $pedidosPeriodo    > 0 ? $ventasPeriodo    / $pedidosPeriodo    : 0.00;
    $ticketPromedioAnt = $pedidosPeriodoAnt > 0 ? $ventasPeriodoAnt / $pedidosPeriodoAnt : 0.00;

    // ── Productos más vendidos (dentro del período) ──────────────────────
    $stmt = $db->prepare("
        SELECT p.nombre, SUM(dp.cantidad) AS total_unidades, SUM(dp.subtotal) AS total_generado
        FROM detalle_pedido dp
        JOIN productos p ON dp.producto_id = p.id
        JOIN pedidos pe ON dp.pedido_id = pe.id
        WHERE pe.fecha BETWEEN :i AND :f AND pe.estado NOT IN ('cancelado')
        GROUP BY p.id ORDER BY total_unidades DESC LIMIT 8
    ");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    $bestSellersList = $stmt->fetchAll();

    // ── Tendencia de ventas, últimos 6 meses (informativa, no depende del filtro) ──
    $ventasMes = $db->query("
        SELECT DATE_FORMAT(fecha,'%b %Y') AS mes_label,
               SUM(total) AS total_mes,
               COUNT(*) AS num_pedidos
        FROM pedidos
        WHERE fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          AND estado NOT IN ('cancelado')
        GROUP BY YEAR(fecha), MONTH(fecha)
        ORDER BY fecha ASC
    ")->fetchAll();

    // ── Resumen por estado (dentro del período) ──────────────────────────
    $stmt = $db->prepare("
        SELECT estado, COUNT(*) AS total, SUM(total) AS monto
        FROM pedidos WHERE fecha BETWEEN :i AND :f
        GROUP BY estado ORDER BY total DESC
    ");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    $resumenEstados = $stmt->fetchAll();

    // ── Últimos pedidos del período (para tabla PDF/CSV) ─────────────────
    $stmt = $db->prepare("
        SELECT p.id, u.nombre, u.apellido, u.correo, p.correo_invitado,
               p.subtotal, p.descuento, p.envio, p.total,
               p.estado, p.fecha, p.cupon_codigo
        FROM pedidos p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.fecha BETWEEN :i AND :f
        ORDER BY p.fecha DESC LIMIT 200
    ");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    $pedidosTabla = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Clientes nuevos vs. recurrentes (dentro del período, usuarios registrados) ──
    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN primer_pedido BETWEEN :i AND :f THEN 1 ELSE 0 END) AS nuevos,
            SUM(CASE WHEN primer_pedido < :i2 THEN 1 ELSE 0 END) AS recurrentes
        FROM (
            SELECT usuario_id, MIN(fecha) AS primer_pedido
            FROM pedidos
            WHERE usuario_id IS NOT NULL AND usuario_id IN (
                SELECT DISTINCT usuario_id FROM pedidos
                WHERE fecha BETWEEN :i3 AND :f2 AND usuario_id IS NOT NULL
            )
            GROUP BY usuario_id
        ) t
    ");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin, ':i2' => $fInicio, ':i3' => $fInicio, ':f2' => $fFin]);
    $clientesRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $clientesNuevos      = (int)($clientesRow['nuevos'] ?? 0);
    $clientesRecurrentes = (int)($clientesRow['recurrentes'] ?? 0);

    // ── Stock crítico (estado actual, no depende del período) ────────────
    $stockCritico = $db->query("
        SELECT id, nombre, stock, precio FROM productos
        WHERE estado = 'activo' AND stock <= 5
        ORDER BY stock ASC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Reporte de cupones (uso dentro del período) ───────────────────────
    $stmt = $db->prepare("
        SELECT p.cupon_codigo AS codigo,
               COUNT(*) AS veces_usado,
               SUM(p.descuento) AS total_descontado,
               c.tipo, c.valor
        FROM pedidos p
        LEFT JOIN cupones c ON c.codigo = p.cupon_codigo
        WHERE p.cupon_codigo IS NOT NULL AND p.cupon_codigo <> ''
          AND p.fecha BETWEEN :i AND :f
          AND p.estado NOT IN ('cancelado')
        GROUP BY p.cupon_codigo, c.tipo, c.valor
        ORDER BY veces_usado DESC
    ");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    $reporteCupones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Detalle: qué cliente usó cada cupón (dentro del período) ──────────
    $stmt = $db->prepare("
        SELECT p.cupon_codigo AS codigo, p.id AS pedido_id, p.fecha, p.descuento, p.estado,
               u.nombre, u.apellido, u.correo, p.correo_invitado
        FROM pedidos p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.cupon_codigo IS NOT NULL AND p.cupon_codigo <> ''
          AND p.fecha BETWEEN :i AND :f
          AND p.estado NOT IN ('cancelado')
        ORDER BY p.cupon_codigo ASC, p.fecha DESC
    ");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    $detalleCupones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $detalleCuponesPorCodigo = [];
    foreach ($detalleCupones as $d) {
        $detalleCuponesPorCodigo[$d['codigo']][] = $d;
    }

} catch (Exception $e) {
    logError('ERROR', 'Reportes admin: ' . $e->getMessage());
}

// Fallback demo (solo si no hay datos reales en absoluto)
if (empty($bestSellersList) && empty($pedidosTabla) && $ventasPeriodo == 0) {
    $bestSellersList = [
        ['nombre'=>'Café Orgánico Marcala 340g',       'total_unidades'=>48, 'total_generado'=>6960],
        ['nombre'=>'Filtro de Agua Cerámico',           'total_unidades'=>25, 'total_generado'=>11250],
        ['nombre'=>'Kit Aromaterapia con Difusor',      'total_unidades'=>22, 'total_generado'=>8360],
        ['nombre'=>'Camiseta Unisex Algodón Reciclado', 'total_unidades'=>19, 'total_generado'=>6080],
        ['nombre'=>'Jabón Artesanal Coco y Aloe',       'total_unidades'=>15, 'total_generado'=>1275],
    ];
    $salesCalculated = 54340.00;
    $ventasPeriodo   = 54340.00;
    $ticketPromedio  = 1509.44;
    $ventasMes = [
        ['mes_label'=>'Ene 2026','total_mes'=>6200, 'num_pedidos'=>8],
        ['mes_label'=>'Feb 2026','total_mes'=>9800, 'num_pedidos'=>14],
        ['mes_label'=>'Mar 2026','total_mes'=>12400,'num_pedidos'=>18],
        ['mes_label'=>'Abr 2026','total_mes'=>10200,'num_pedidos'=>13],
        ['mes_label'=>'May 2026','total_mes'=>15740,'num_pedidos'=>21],
    ];
    $resumenEstados = [
        ['estado'=>'entregado', 'total'=>34, 'monto'=>28900],
        ['estado'=>'procesando','total'=>18, 'monto'=>15200],
        ['estado'=>'pendiente', 'total'=>11, 'monto'=>7400],
        ['estado'=>'cancelado', 'total'=>4,  'monto'=>2840],
    ];
}

if (empty($pedidosTabla)) {
    $pedidosTabla = [
        ['id'=>15,'nombre'=>'Ana',    'apellido'=>'García',   'correo'=>'ana@email.com',  'correo_invitado'=>null,'subtotal'=>450.00,'descuento'=>0,  'envio'=>50,'total'=>500.00, 'estado'=>'entregado', 'fecha'=>'2026-06-09','cupon_codigo'=>null],
        ['id'=>14,'nombre'=>'Luis',   'apellido'=>'Martínez', 'correo'=>'luis@email.com', 'correo_invitado'=>null,'subtotal'=>1200.00,'descuento'=>100,'envio'=>0, 'total'=>1100.00,'estado'=>'procesando','fecha'=>'2026-06-08','cupon_codigo'=>'ECO10'],
        ['id'=>13,'nombre'=>'María',  'apellido'=>'López',    'correo'=>'maria@email.com','correo_invitado'=>null,'subtotal'=>800.00,'descuento'=>0,  'envio'=>50,'total'=>850.00, 'estado'=>'pendiente', 'fecha'=>'2026-06-07','cupon_codigo'=>null],
        ['id'=>12,'nombre'=>'Carlos', 'apellido'=>'Reyes',    'correo'=>'carlos@email.com','correo_invitado'=>null,'subtotal'=>3200.00,'descuento'=>200,'envio'=>0,'total'=>3000.00,'estado'=>'entregado','fecha'=>'2026-06-06','cupon_codigo'=>'VERDE50'],
        ['id'=>11,'nombre'=>'Sofía',  'apellido'=>'Mejía',    'correo'=>'sofia@email.com','correo_invitado'=>null,'subtotal'=>560.00,'descuento'=>0,  'envio'=>50,'total'=>610.00, 'estado'=>'enviado',   'fecha'=>'2026-06-05','cupon_codigo'=>null],
    ];
}

$salesCalculated = $ventasPeriodo;
$jsonMeses      = json_encode(array_column($ventasMes, 'mes_label'));
$jsonVentas     = json_encode(array_map(fn($r)=>(float)$r['total_mes'], $ventasMes));
$jsonPeds       = json_encode(array_map(fn($r)=>(int)$r['num_pedidos'], $ventasMes));
$maxVendido     = $bestSellersList ? max(array_column($bestSellersList, 'total_unidades')) : 1;
$jsonPedidosPDF = json_encode($pedidosTabla, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsonBestSellers= json_encode($bestSellersList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsonEstados    = json_encode($resumenEstados,  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsonVentasMes  = json_encode($ventasMes,       JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsonPeriodoLabel = json_encode($periodoLabel);
$jsonStockCritico   = json_encode($stockCritico,   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsonReporteCupones = json_encode($reporteCupones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsonDetalleCupones = json_encode($detalleCupones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$totalGeneral   = array_sum(array_column($pedidosTabla, 'total'));
$totalPedidos   = $pedidosPeriodo > 0 ? $pedidosPeriodo : array_sum(array_column($resumenEstados, 'total'));
$totalUnidades  = $bestSellersList ? array_sum(array_column($bestSellersList, 'total_unidades')) : 0;

$pctVentas   = pctChange($ventasPeriodo, $ventasPeriodoAnt);
$pctPedidos  = pctChange((float)$pedidosPeriodo, (float)$pedidosPeriodoAnt);
$pctTicket   = pctChange($ticketPromedio, $ticketPromedioAnt);

/** Devuelve el HTML de la flechita de variación porcentual. */
function renderPctBadge(?float $pct): string {
    if ($pct === null) return '<span class="small" style="color:var(--muted);">sin comparación</span>';
    if ($pct > 0)  return '<span class="small fw-bold" style="color:#10b981;"><i class="fas fa-arrow-up"></i> ' . $pct . '%</span>';
    if ($pct < 0)  return '<span class="small fw-bold" style="color:#f87171;"><i class="fas fa-arrow-down"></i> ' . abs($pct) . '%</span>';
    return '<span class="small" style="color:var(--muted);">= sin cambio</span>';
}
?>
<?php require_once __DIR__ . '/includes/admin_navbar.php'; ?>

    <!-- Estilos específicos de Reportes (tablas, filtros, exportación) que no forman
         parte del sistema global admin_navbar.php, conservados intactos. -->
    <style>
        .eco-card { background: var(--admin-card); border: 1px solid var(--admin-border); border-radius: 16px; padding: 1.5rem; }
        .bar-fill { background:linear-gradient(90deg,#10b981,#059669); height:10px; border-radius:5px; transition:width .8s ease; }
        .btn-pdf { background:linear-gradient(135deg,#dc2626,#b91c1c); border:none; color:#fff; font-weight:600; padding:.65rem 1.4rem; border-radius:50px; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; font-family:var(--font-sans); font-size:.9rem; transition:all .3s; }
        .btn-pdf:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(220,38,38,.35); }
        .btn-pdf:disabled { opacity:.6; cursor:not-allowed; transform:none; }
        .estado-badge { padding:.3em .75em; border-radius:50px; font-size:.75rem; font-weight:600; }
        .estado-pendiente{background:rgba(245,158,11,.15);color:#f59e0b;}
        .estado-pagado{background:rgba(45,212,191,.15);color:#2dd4bf;}
        .estado-procesando{background:rgba(99,102,241,.15);color:#818cf8;}
        .estado-enviado{background:rgba(59,130,246,.15);color:#60a5fa;}
        .estado-entregado{background:rgba(16,185,129,.15);color:#10b981;}
        .estado-cancelado{background:rgba(239,68,68,.15);color:#f87171;}
        .filtro-btn { background:rgba(255,255,255,.04); border:1px solid var(--admin-border); color:#94a3b8; padding:.5rem 1rem; border-radius:50px; font-size:.82rem; font-weight:600; text-decoration:none; transition:all .2s; display:inline-block; }
        .filtro-btn:hover { color:#fff; border-color:var(--eco-primary); }
        .filtro-btn.active { background:var(--eco-primary); border-color:var(--eco-primary); color:#06231a; }
        .btn-csv { background:linear-gradient(135deg,#059669,#047857); border:none; color:#fff; font-weight:600; padding:.65rem 1.4rem; border-radius:50px; display:inline-flex; align-items:center; gap:.5rem; cursor:pointer; font-family:var(--font-sans); font-size:.9rem; transition:all .3s; text-decoration:none; }
        .btn-csv:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(5,150,105,.35); color:#fff; }

        /* ══ Reporte de Cupones — rediseño en tarjetas ══ */
        .cupon-card { background:rgba(255,255,255,.03); border:1px solid var(--admin-border); border-radius:14px; padding:1rem 1.15rem; margin-bottom:.85rem; transition:border-color .2s, background .2s; }
        .cupon-card:hover { border-color:rgba(16,185,129,.35); background:rgba(255,255,255,.045); }
        .cupon-card:last-child { margin-bottom:0; }
        .cupon-code-pill { display:inline-flex; align-items:center; gap:.4rem; background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3); color:#10b981; font-weight:800; font-family:var(--font-display); font-size:.95rem; letter-spacing:.03em; padding:.3em .85em; border-radius:8px; }
        .cupon-tipo-pill { font-size:.72rem; font-weight:600; color:#94a3b8; background:rgba(148,163,184,.12); padding:.25em .7em; border-radius:999px; }
        .cupon-stat { text-align:center; }
        .cupon-stat-value { font-size:1.15rem; font-weight:800; font-family:var(--font-display); line-height:1; }
        .cupon-stat-label { font-size:.68rem; color:#64748b; text-transform:uppercase; letter-spacing:.04em; margin-top:.2rem; }
        .cupon-bar-track { background:rgba(255,255,255,.06); border-radius:5px; height:6px; margin-top:.7rem; overflow:hidden; }
        .cupon-bar-fill { background:linear-gradient(90deg,#10b981,#059669); height:100%; border-radius:5px; transition:width .8s ease; }
        .cupon-toggle-uso { background:none; border:none; color:#64748b; font-size:.78rem; font-weight:600; padding:0; margin-top:.65rem; display:inline-flex; align-items:center; gap:.35rem; cursor:pointer; transition:color .2s; }
        .cupon-toggle-uso:hover { color:#10b981; }
        .cupon-toggle-uso i { transition:transform .2s; font-size:.7rem; }
        .cupon-toggle-uso.open i { transform:rotate(180deg); }
        .cupon-uso-list { display:none; margin-top:.65rem; padding-top:.65rem; border-top:1px dashed var(--admin-border); }
        .cupon-uso-list.open { display:block; }
        .cupon-uso-item { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.4rem 0; font-size:.8rem; }
        .cupon-uso-item + .cupon-uso-item { border-top:1px solid rgba(255,255,255,.04); }
        .cupon-uso-cliente { color:#e2e8f0; }
        .cupon-uso-correo { color:#60a5fa; }
        .cupon-uso-meta { color:#64748b; white-space:nowrap; font-family:var(--font-display); font-size:.75rem; }
    </style>

    <!-- Carga de librerías específicas de Reportes (export PDF/Excel) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha384-JcnsjUPPylna1s1fvi1u12X5qjY5OL56iySh75FdtrwhO/SWXgMjoVqcKyIIWOLk" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js" integrity="sha384-fCAW/rDWORTbQXSiB7mOg0QtQ5c+r0f544y6XoKjuVva0nMBlCpNUjiFeG5iMdS3" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <button class="btn-csv" id="btnExportExcel">
            <i class="fas fa-file-excel"></i> Excel
        </button>
        <button class="btn-pdf" id="btnExportPDF">
            <i class="fas fa-file-pdf"></i> <span id="btnPDFLabel">Exportar PDF Ejecutivo</span>
        </button>
    </div>

    <!-- ══ FILTROS DE FECHA ══ -->
    <form method="GET" class="eco-card mb-4 d-flex flex-wrap align-items-center gap-2" id="formFiltro">
        <span class="small fw-bold me-2" style="color:var(--muted);"><i class="fas fa-calendar-days me-1"></i> Período:</span>
        <a href="?periodo=hoy" class="filtro-btn <?php echo $periodo==='hoy'?'active':''; ?>">Hoy</a>
        <a href="?periodo=este_mes" class="filtro-btn <?php echo $periodo==='este_mes'?'active':''; ?>">Este mes</a>
        <a href="?periodo=mes_anterior" class="filtro-btn <?php echo $periodo==='mes_anterior'?'active':''; ?>">Mes anterior</a>
        <a href="?periodo=30dias" class="filtro-btn <?php echo $periodo==='30dias'?'active':''; ?>">Últimos 30 días</a>
        <a href="?periodo=este_anio" class="filtro-btn <?php echo $periodo==='este_anio'?'active':''; ?>">Este año</a>
        <span class="mx-1" style="color:var(--border);">|</span>
        <input type="date" name="fecha_inicio" class="form-control form-control-sm" style="width:auto;background:rgba(255,255,255,.04);border-color:var(--border);color:#fff;" value="<?php echo $inicio->format('Y-m-d'); ?>">
        <span style="color:var(--muted);">–</span>
        <input type="date" name="fecha_fin" class="form-control form-control-sm" style="width:auto;background:rgba(255,255,255,.04);border-color:var(--border);color:#fff;" value="<?php echo $fin->format('Y-m-d'); ?>">
        <input type="hidden" name="periodo" value="personalizado">
        <button type="submit" class="filtro-btn <?php echo $periodo==='personalizado'?'active':''; ?>"><i class="fas fa-filter me-1"></i>Aplicar rango</button>
        <span class="ms-auto small" style="color:var(--muted);"><i class="fas fa-info-circle me-1"></i>Mostrando: <strong style="color:#fff;"><?php echo sanitize($periodoLabel); ?></strong></span>
    </form>

    <!-- ══ COMPARACIÓN CONTRA PERÍODO ANTERIOR ══ -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="eco-card text-center">
                <div style="font-size:.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Ventas del período</div>
                <div style="font-size:1.7rem;font-weight:800;color:#10b981;font-family:var(--disp);">L. <?php echo number_format($ventasPeriodo, 2, '.', ','); ?></div>
                <?php echo renderPctBadge($pctVentas); ?>
            </div>
        </div>
        <div class="col-md-3">
            <div class="eco-card text-center">
                <div style="font-size:.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Pedidos del período</div>
                <div style="font-size:1.7rem;font-weight:800;color:#60a5fa;font-family:var(--disp);"><?php echo $pedidosPeriodo; ?></div>
                <?php echo renderPctBadge($pctPedidos); ?>
            </div>
        </div>
        <div class="col-md-3">
            <div class="eco-card text-center">
                <div style="font-size:.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Ticket promedio</div>
                <div style="font-size:1.7rem;font-weight:800;color:#fbbf24;font-family:var(--disp);">L. <?php echo number_format($ticketPromedio, 2); ?></div>
                <?php echo renderPctBadge($pctTicket); ?>
            </div>
        </div>
        <div class="col-md-3">
            <div class="eco-card text-center">
                <div style="font-size:.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Clientes nuevos / recurrentes</div>
                <div style="font-size:1.7rem;font-weight:800;font-family:var(--disp);"><span style="color:#a78bfa;"><?php echo $clientesNuevos; ?></span> <span style="color:var(--muted);font-size:1rem;">/</span> <span style="color:#38bdf8;"><?php echo $clientesRecurrentes; ?></span></div>
                <span class="small" style="color:var(--muted);">nuevos / recurrentes</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="eco-card h-100">
                <h6 class="fw-bold mb-3" style="font-family:var(--disp)"><i class="fas fa-chart-line text-success me-2"></i>Ventas Últimos 6 Meses</h6>
                <div style="height:300px;position:relative;"><canvas id="chartVentasMes"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="eco-card h-100">
                <h6 class="fw-bold mb-3" style="font-family:var(--disp)"><i class="fas fa-list text-success me-2"></i>Pedidos por Estado</h6>
                <?php foreach ($resumenEstados as $est): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="estado-badge estado-<?php echo $est['estado']; ?>"><?php echo ucfirst($est['estado']); ?></span>
                        <span style="font-size:.85rem;color:var(--muted);"><?php echo $est['total']; ?> · L. <?php echo number_format($est['monto'],2); ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($resumenEstados)): ?><p class="text-muted text-center py-3 small">Sin datos aún</p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="eco-card mb-4">
        <h6 class="fw-bold mb-4 m-0" style="font-family:var(--disp)"><i class="fas fa-trophy text-warning me-2"></i>Productos Más Vendidos</h6>
        <?php foreach ($bestSellersList as $i => $prod): ?>
            <?php $pct = $maxVendido > 0 ? round(($prod['total_unidades'] / $maxVendido) * 100) : 0; ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div style="font-size:.9rem;font-weight:500;"><span style="color:var(--muted);font-size:.8rem;margin-right:.5rem;">#<?php echo $i+1; ?></span><?php echo sanitize($prod['nombre']); ?></div>
                    <div style="font-size:.85rem;color:var(--muted);"><?php echo $prod['total_unidades']; ?> uds · L. <?php echo number_format($prod['total_generado'],2); ?></div>
                </div>
                <div style="background:rgba(255,255,255,0.06);border-radius:5px;height:10px;"><div class="bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($bestSellersList)): ?><p class="text-muted text-center py-3 small">Sin ventas en este período.</p><?php endif; ?>
    </div>

    <!-- ══ STOCK CRÍTICO + REPORTE DE CUPONES ══ -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="eco-card h-100">
                <h6 class="fw-bold mb-3" style="font-family:var(--disp)"><i class="fas fa-triangle-exclamation text-danger me-2"></i>Stock Crítico</h6>
                <?php if (empty($stockCritico)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-check fa-2x text-success opacity-50 mb-2 d-block"></i>
                        <span class="small text-muted">Todo el inventario sobrepasa las 5 unidades.</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($stockCritico as $p): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom:1px solid var(--border);">
                            <div class="text-truncate" style="max-width:220px;">
                                <span class="small fw-500"><?php echo sanitize($p['nombre']); ?></span>
                            </div>
                            <span class="estado-badge <?php echo $p['stock'] == 0 ? 'estado-cancelado' : 'estado-pendiente'; ?>"><?php echo $p['stock']; ?> uds</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="eco-card h-100">
                <h6 class="fw-bold mb-3 d-flex align-items-center justify-content-between" style="font-family:var(--disp)">
                    <span><i class="fas fa-ticket text-success me-2"></i>Reporte de Cupones</span>
                    <span class="small fw-normal" style="color:var(--muted);"><?php echo sanitize($periodoLabel); ?></span>
                </h6>
                <?php if (empty($reporteCupones)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-ticket fa-2x opacity-25 mb-2 d-block" style="color:#64748b;"></i>
                        <span class="small text-muted">Ningún cupón fue usado en este período.</span>
                    </div>
                <?php else: ?>
                    <?php
                        $maxDescontado = max(array_column($reporteCupones, 'total_descontado')) ?: 1;
                    ?>
                    <?php foreach ($reporteCupones as $idx => $cup): ?>
                        <?php
                            $usos = $detalleCuponesPorCodigo[$cup['codigo']] ?? [];
                            $barPct = round(((float)$cup['total_descontado'] / $maxDescontado) * 100);
                            $valorTxt = $cup['tipo'] === 'porcentaje'
                                ? sanitize($cup['valor']) . '% de descuento'
                                : ($cup['tipo'] ? 'L. ' . number_format((float)$cup['valor'], 2) . ' fijo' : 'Tipo no definido');
                        ?>
                        <div class="cupon-card">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="cupon-code-pill"><i class="fas fa-tag"></i> <?php echo sanitize($cup['codigo']); ?></span>
                                    <span class="cupon-tipo-pill"><?php echo $valorTxt; ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-4">
                                    <div class="cupon-stat">
                                        <div class="cupon-stat-value" style="color:#60a5fa;"><?php echo (int)$cup['veces_usado']; ?></div>
                                        <div class="cupon-stat-label">Usos</div>
                                    </div>
                                    <div class="cupon-stat">
                                        <div class="cupon-stat-value" style="color:#10b981;">L. <?php echo number_format((float)$cup['total_descontado'], 2); ?></div>
                                        <div class="cupon-stat-label">Descontado</div>
                                    </div>
                                </div>
                            </div>

                            <div class="cupon-bar-track"><div class="cupon-bar-fill" style="width:<?php echo $barPct; ?>%;"></div></div>

                            <?php if (!empty($usos)): ?>
                                <button type="button" class="cupon-toggle-uso" data-cupon-toggle="cuponUso<?php echo $idx; ?>">
                                    <i class="fas fa-chevron-down"></i>
                                    Ver <?php echo count($usos); ?> cliente<?php echo count($usos) === 1 ? '' : 's'; ?> que lo usaron
                                </button>
                                <div class="cupon-uso-list" id="cuponUso<?php echo $idx; ?>">
                                    <?php foreach ($usos as $uso): ?>
                                        <?php
                                            $clienteNombre = $uso['nombre'] ? trim($uso['nombre'] . ' ' . ($uso['apellido'] ?? '')) : 'Invitado';
                                            $clienteCorreo = $uso['correo'] ?: ($uso['correo_invitado'] ?: '—');
                                        ?>
                                        <div class="cupon-uso-item">
                                            <span class="cupon-uso-cliente"><i class="fas fa-user small me-1" style="color:#475569;"></i><?php echo sanitize($clienteNombre); ?> · <span class="cupon-uso-correo"><?php echo sanitize($clienteCorreo); ?></span></span>
                                            <span class="cupon-uso-meta">Pedido #<?php echo (int)$uso['pedido_id']; ?> · <?php echo date('d/m/Y', strtotime($uso['fecha'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<!-- /contenido de reportes (el <div class="main-content"> lo abre y cierra el sistema global admin_navbar.php / admin_footer.php) -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Acordeón de "clientes que usaron este cupón"
    document.querySelectorAll('[data-cupon-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.getAttribute('data-cupon-toggle'));
            if (!target) return;
            const isOpen = target.classList.toggle('open');
            btn.classList.toggle('open', isOpen);
            btn.querySelector('i').style.transform = isOpen ? '' : '';
        });
    });

    const gc = 'rgba(255,255,255,0.05)', tc = '#64748b';
    const ctx = document.getElementById('chartVentasMes');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo $jsonMeses; ?>,
                datasets: [
                    { label:'Ventas (L.)', data:<?php echo $jsonVentas; ?>, borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.08)', borderWidth:3, fill:true, tension:.35, pointRadius:5, pointBackgroundColor:'#10b981', yAxisID:'y' },
                    { label:'Pedidos', data:<?php echo $jsonPeds; ?>, borderColor:'#60a5fa', backgroundColor:'transparent', borderWidth:2, borderDash:[5,5], tension:.35, pointRadius:4, pointBackgroundColor:'#60a5fa', yAxisID:'y1' }
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{ labels:{ color:tc, usePointStyle:true } } },
                scales:{
                    y:{ grid:{color:gc}, ticks:{color:tc, callback:v=>'L.'+v.toLocaleString()}, position:'left' },
                    y1:{ grid:{drawOnChartArea:false}, ticks:{color:tc}, position:'right' },
                    x:{ grid:{color:gc}, ticks:{color:tc} }
                }
            }
        });
    }
});

// ═══════════════════════════════════════════════════════════════════════════════
// PDF EJECUTIVO PREMIUM — jsPDF
// ═══════════════════════════════════════════════════════════════════════════════
const pedidosData  = <?php echo $jsonPedidosPDF; ?>;
const bestSellers  = <?php echo $jsonBestSellers; ?>;
const estadosData  = <?php echo $jsonEstados; ?>;
const ventasMes    = <?php echo $jsonVentasMes; ?>;
const totalGeneral = <?php echo json_encode($totalGeneral); ?>;
const totalPedidos = <?php echo json_encode($totalPedidos); ?>;
const totalUnidades= <?php echo json_encode($totalUnidades); ?>;
const salesTotal   = <?php echo json_encode($salesCalculated); ?>;
const periodoLabel = <?php echo $jsonPeriodoLabel; ?>;
const stockCriticoData   = <?php echo $jsonStockCritico; ?>;
const reporteCuponesData = <?php echo $jsonReporteCupones; ?>;
const detalleCuponesData = <?php echo $jsonDetalleCupones; ?>;
const resumenKPIs = {
    ventas: <?php echo json_encode($ventasPeriodo); ?>,
    ventasAnt: <?php echo json_encode($ventasPeriodoAnt); ?>,
    pctVentas: <?php echo json_encode($pctVentas); ?>,
    pedidos: <?php echo json_encode($pedidosPeriodo); ?>,
    pedidosAnt: <?php echo json_encode($pedidosPeriodoAnt); ?>,
    pctPedidos: <?php echo json_encode($pctPedidos); ?>,
    ticket: <?php echo json_encode($ticketPromedio); ?>,
    ticketAnt: <?php echo json_encode($ticketPromedioAnt); ?>,
    pctTicket: <?php echo json_encode($pctTicket); ?>,
    clientesNuevos: <?php echo json_encode($clientesNuevos); ?>,
    clientesRecurrentes: <?php echo json_encode($clientesRecurrentes); ?>,
};

// ═══════════════════════════════════════════════════════════════════════════════
// EXPORTAR EXCEL (.xlsx) — con estilo real: colores, negritas, formato de moneda
// ═══════════════════════════════════════════════════════════════════════════════
document.getElementById('btnExportExcel').addEventListener('click', async function () {
    const btn = this;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';

    try {
        const wb = new ExcelJS.Workbook();
        wb.creator = 'EcoTienda HN';
        wb.created = new Date();

        const VERDE   = 'FF10B981';
        const VERDE_OSC = 'FF064E3B';
        const GRIS_CLARO = 'FFF1F5F9';
        const BLANCO  = 'FFFFFFFF';
        const BORDE   = { style: 'thin', color: { argb: 'FFCBD5E1' } };
        const thinBorder = { top: BORDE, left: BORDE, bottom: BORDE, right: BORDE };
        const MONEDA = '"L. "#,##0.00';

        function styleHeaderRow(row, fillColor = VERDE) {
            row.eachCell(cell => {
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: fillColor } };
                cell.font = { bold: true, color: { argb: BLANCO }, size: 11 };
                cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
                cell.border = thinBorder;
            });
            row.height = 22;
        }

        function borderOnly(ws, startRow, endRow) {
            for (let r = startRow; r <= endRow; r++) {
                ws.getRow(r).eachCell(cell => { cell.border = thinBorder; });
            }
        }

        function bandRows(ws, startRow, endRow) {
            for (let r = startRow; r <= endRow; r++) {
                const row = ws.getRow(r);
                if ((r - startRow) % 2 === 1) {
                    row.eachCell(cell => {
                        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: GRIS_CLARO } };
                    });
                }
                row.eachCell(cell => { cell.border = thinBorder; });
            }
        }

        // ── Hoja 1: Resumen ────────────────────────────────────────────────
        const wsResumen = wb.addWorksheet('Resumen', { properties: { tabColor: { argb: VERDE } } });
        wsResumen.mergeCells('A1:C1');
        wsResumen.getCell('A1').value = '🌱 EcoTienda HN — Reporte Ejecutivo';
        wsResumen.getCell('A1').font = { bold: true, size: 16, color: { argb: VERDE_OSC } };
        wsResumen.mergeCells('A2:C2');
        wsResumen.getCell('A2').value = 'Período: ' + periodoLabel;
        wsResumen.getCell('A2').font = { italic: true, color: { argb: 'FF64748B' } };
        wsResumen.addRow([]);

        const kpiHeaderRow = wsResumen.addRow(['Métrica', 'Este período', 'Período anterior']);
        styleHeaderRow(kpiHeaderRow);

        const pctTxt = (p) => p === null ? 'sin comparación' : (p > 0 ? '▲ ' + p + '%' : (p < 0 ? '▼ ' + Math.abs(p) + '%' : '= 0%'));
        const kpiRows = [
            ['Ventas totales', resumenKPIs.ventas, resumenKPIs.ventasAnt, pctTxt(resumenKPIs.pctVentas)],
            ['Pedidos', resumenKPIs.pedidos, resumenKPIs.pedidosAnt, pctTxt(resumenKPIs.pctPedidos)],
            ['Ticket promedio', resumenKPIs.ticket, resumenKPIs.ticketAnt, pctTxt(resumenKPIs.pctTicket)],
        ];
        const kpiStartRow = wsResumen.rowCount + 1;
        kpiRows.forEach(([label, val, valAnt, variacion]) => {
            const row = wsResumen.addRow([label, val, valAnt]);
            row.getCell(4).value = variacion;
            if (typeof val === 'number' && label !== 'Pedidos') { row.getCell(2).numFmt = MONEDA; row.getCell(3).numFmt = MONEDA; }
        });
        bandRows(wsResumen, kpiStartRow, wsResumen.rowCount);

        wsResumen.addRow([]);
        const clientesHeaderRow = wsResumen.addRow(['Clientes nuevos', 'Clientes recurrentes']);
        styleHeaderRow(clientesHeaderRow, VERDE_OSC);
        const clientesRow = wsResumen.addRow([resumenKPIs.clientesNuevos, resumenKPIs.clientesRecurrentes]);
        bandRows(wsResumen, clientesRow.number, clientesRow.number);

        wsResumen.columns = [{ width: 22 }, { width: 20 }, { width: 20 }, { width: 18 }];

        // ── Hoja 2: Pedidos ─────────────────────────────────────────────────
        const wsPedidos = wb.addWorksheet('Pedidos', { properties: { tabColor: { argb: VERDE } } });
        const pedidosHead = wsPedidos.addRow(['ID', 'Cliente', 'Correo', 'Subtotal', 'Descuento', 'Cupón', 'Envío', 'Total', 'Estado', 'Fecha']);
        styleHeaderRow(pedidosHead);
        const pedidosStart = wsPedidos.rowCount + 1;
        pedidosData.forEach(p => {
            const cliente = p.nombre ? (p.nombre + ' ' + (p.apellido || '')).trim() : 'Invitado';
            const correo  = p.correo || p.correo_invitado || '';
            const row = wsPedidos.addRow([
                p.id, cliente, correo,
                Number(p.subtotal), Number(p.descuento),
                p.cupon_codigo || '—', Number(p.envio), Number(p.total),
                (p.estado || '').charAt(0).toUpperCase() + (p.estado || '').slice(1),
                p.fecha ? p.fecha.substring(0, 10) : ''
            ]);
            [4, 5, 7, 8].forEach(c => { row.getCell(c).numFmt = MONEDA; });
            row.getCell(9).font = { bold: true, color: { argb: 'FF' + (estadoColorsHex[(p.estado || '').toLowerCase()] || '475569') } };
        });
        bandRows(wsPedidos, pedidosStart, wsPedidos.rowCount);
        if (wsPedidos.rowCount >= pedidosStart) {
            wsPedidos.autoFilter = { from: { row: pedidosHead.number, column: 1 }, to: { row: pedidosHead.number, column: 10 } };
        }
        wsPedidos.views = [{ state: 'frozen', ySplit: pedidosHead.number }];
        wsPedidos.columns = [
            { width: 7 }, { width: 24 }, { width: 28 }, { width: 13 }, { width: 13 },
            { width: 12 }, { width: 12 }, { width: 14 }, { width: 14 }, { width: 13 },
        ];

        // ── Hoja 3: Top Productos ──────────────────────────────────────────
        const wsProd = wb.addWorksheet('Top Productos', { properties: { tabColor: { argb: VERDE } } });
        const prodHead = wsProd.addRow(['#', 'Producto', 'Unidades vendidas', 'Total generado']);
        styleHeaderRow(prodHead);
        const prodStart = wsProd.rowCount + 1;
        bestSellers.forEach((prod, i) => {
            const row = wsProd.addRow([i + 1, prod.nombre, Number(prod.total_unidades), Number(prod.total_generado)]);
            row.getCell(4).numFmt = MONEDA;
        });
        bandRows(wsProd, prodStart, wsProd.rowCount);
        wsProd.columns = [{ width: 6 }, { width: 40 }, { width: 18 }, { width: 16 }];

        // ── Hoja 4: Cupones ─────────────────────────────────────────────────
        const wsCup = wb.addWorksheet('Cupones', { properties: { tabColor: { argb: VERDE } } });
        const cupHead = wsCup.addRow(['Código', 'Tipo', 'Valor', 'Usos en el período', 'Total descontado']);
        styleHeaderRow(cupHead);
        const cupStart = wsCup.rowCount + 1;
        reporteCuponesData.forEach(c => {
            const valorTxt = c.tipo === 'porcentaje' ? (c.valor + '%') : (c.tipo ? Number(c.valor) : '—');
            const row = wsCup.addRow([c.codigo, c.tipo || '—', valorTxt, Number(c.veces_usado), Number(c.total_descontado)]);
            row.font = { bold: true };
            if (c.tipo !== 'porcentaje' && c.tipo) row.getCell(3).numFmt = MONEDA;
            row.getCell(5).numFmt = MONEDA;

            // Detalle: qué cliente usó este cupón
            const usos = detalleCuponesData.filter(u => u.codigo === c.codigo);
            if (usos.length > 0) {
                const detHead = wsCup.addRow(['', 'Cliente', 'Correo', 'Pedido #', 'Fecha']);
                detHead.eachCell(cell => { cell.font = { italic: true, color: { argb: 'FF64748B' }, size: 9 }; });
                usos.forEach(u => {
                    const cliente = u.nombre ? (u.nombre + ' ' + (u.apellido || '')).trim() : 'Invitado';
                    const correo  = u.correo || u.correo_invitado || '—';
                    wsCup.addRow(['', cliente, correo, '#' + u.pedido_id, u.fecha ? u.fecha.substring(0, 10) : '']);
                });
                wsCup.addRow([]);
            }
        });
        if (reporteCuponesData.length === 0) {
            const row = wsCup.addRow(['Ningún cupón fue usado en este período.']);
            wsCup.mergeCells(row.number, 1, row.number, 5);
            row.getCell(1).alignment = { horizontal: 'center' };
            row.getCell(1).font = { italic: true, color: { argb: 'FF94A3B8' } };
        }
        wsCup.columns = [{ width: 16 }, { width: 22 }, { width: 26 }, { width: 12 }, { width: 14 }];
        borderOnly(wsCup, cupStart, wsCup.rowCount);

        // ── Hoja 5: Stock Crítico ───────────────────────────────────────────
        const wsStock = wb.addWorksheet('Stock Crítico', { properties: { tabColor: { argb: 'FFEF4444' } } });
        const stockHead = wsStock.addRow(['ID', 'Producto', 'Stock', 'Precio']);
        styleHeaderRow(stockHead, 'FFDC2626');
        const stockStart = wsStock.rowCount + 1;
        stockCriticoData.forEach(p => {
            const row = wsStock.addRow([p.id, p.nombre, Number(p.stock), Number(p.precio)]);
            row.getCell(4).numFmt = MONEDA;
            if (Number(p.stock) === 0) row.getCell(3).font = { bold: true, color: { argb: 'FFDC2626' } };
        });
        if (stockCriticoData.length === 0) {
            const row = wsStock.addRow(['Todo el inventario activo tiene más de 5 unidades en stock.']);
            wsStock.mergeCells(row.number, 1, row.number, 4);
            row.getCell(1).alignment = { horizontal: 'center' };
            row.getCell(1).font = { italic: true, color: { argb: 'FF94A3B8' } };
        }
        bandRows(wsStock, stockStart, wsStock.rowCount);
        wsStock.columns = [{ width: 8 }, { width: 36 }, { width: 10 }, { width: 14 }];

        // ── Descargar ────────────────────────────────────────────────────────
        const buffer = await wb.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        a.download = 'EcoTienda_Reporte_' + periodoLabel.replace(/[^\w-]+/g, '_') + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch (err) {
        console.error('Error Excel:', err);
        alert('Error al generar el Excel: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
});

// Paleta de colores
const C = {
    verde:      [16, 185, 129],
    verdeOsc:   [6,  78,  59],
    verdeClaro: [240,253, 244],
    verdeText:  [6,  78,  59],
    azul:       [59, 130, 246],
    morado:     [139,92,  246],
    amarillo:   [245,158, 11],
    rojo:       [239,68,  68],
    gris:       [148,163, 184],
    grisOsc:    [51, 65,  85],
    blanco:     [255,255, 255],
    negro:      [15, 23,  42],
    fondo:      [248,250, 252],
    cardBg:     [241,245, 249],
};

const estadoColors = {
    pendiente:  C.amarillo,
    procesando: [99, 102, 241],
    enviado:    C.azul,
    entregado:  C.verde,
    cancelado:  C.rojo,
    pagado:     C.verde,
};

const estadoColorsHex = {
    pendiente:  'B45309',
    procesando: '4338CA',
    enviado:    '1D4ED8',
    entregado:  '047857',
    cancelado:  'B91C1C',
    pagado:     '047857',
};

function fmt(n) {
    return 'L. ' + Number(n).toLocaleString('es-HN', { minimumFractionDigits:2, maximumFractionDigits:2 });
}

function drawRoundRect(doc, x, y, w, h, r, fillColor, strokeColor) {
    doc.setFillColor(...fillColor);
    if (strokeColor) doc.setDrawColor(...strokeColor);
    doc.roundedRect(x, y, w, h, r, r, strokeColor ? 'FD' : 'F');
}

document.getElementById('btnExportPDF').addEventListener('click', function () {
    const btn = this;
    const lbl = document.getElementById('btnPDFLabel');
    btn.disabled = true;
    lbl.textContent = 'Generando...';

    setTimeout(() => {
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            const PW  = doc.internal.pageSize.getWidth();   // 210
            const PH  = doc.internal.pageSize.getHeight();  // 297
            const now = new Date();
            const fechaStr = now.toLocaleDateString('es-HN', { day:'2-digit', month:'long', year:'numeric' });
            const horaStr  = now.toLocaleTimeString('es-HN', { hour:'2-digit', minute:'2-digit' });

            // ─────────────────────────────────────────────────────────────────
            // PÁGINA 1 — PORTADA EJECUTIVA
            // ─────────────────────────────────────────────────────────────────

            // Fondo degradado simulado (rectángulos superpuestos)
            doc.setFillColor(6, 78, 59);
            doc.rect(0, 0, PW, PH, 'F');

            // Patrón decorativo: círculos grandes semitransparentes
            doc.setFillColor(16, 185, 129);
            doc.setGState(doc.GState({ opacity: 0.08 }));
            doc.circle(190, 40, 80, 'F');
            doc.circle(20,  240, 60, 'F');
            doc.setGState(doc.GState({ opacity: 1 }));

            // Línea decorativa superior
            doc.setFillColor(16, 185, 129);
            doc.rect(0, 0, PW, 3, 'F');

            // Logo / Ícono área
            drawRoundRect(doc, 20, 35, 22, 22, 4, [16, 185, 129]);
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(16);
            doc.text('🌿', 26, 50);

            // Título empresa
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.text('ECOTIENDA HN', 48, 43);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(167, 243, 208);
            doc.text('Ecológico · Sostenible · Hondureño', 48, 50);

            // Línea separadora
            doc.setDrawColor(16, 185, 129);
            doc.setLineWidth(0.5);
            doc.line(20, 65, PW - 20, 65);

            // Título del reporte
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(32);
            doc.text('REPORTE', 20, 90);
            doc.setFontSize(32);
            doc.setTextColor(167, 243, 208);
            doc.text('EJECUTIVO', 20, 106);

            doc.setFontSize(13);
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'normal');
            doc.text('Análisis Financiero y Operativo', 20, 118);

            doc.setFontSize(9);
            doc.setTextColor(167, 243, 208);
            doc.text('Período: ' + periodoLabel, 20, 126);

            // Año destacado
            doc.setFontSize(80);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(16, 185, 129);
            doc.setGState(doc.GState({ opacity: 0.15 }));
            doc.text('2026', 20, 175);
            doc.setGState(doc.GState({ opacity: 1 }));

            // ── 3 KPI Cards en la portada ──────────────────────────────────
            const kpis = [
                { label: 'Ingresos Totales', value: fmt(salesTotal), color: C.verde,    icon: 'L.' },
                { label: 'Total Pedidos',    value: String(totalPedidos),  color: C.azul,     icon: '#'  },
                { label: 'Uds. Vendidas',    value: String(totalUnidades), color: [167,92,246], icon: '✦' },
            ];

            kpis.forEach((kpi, i) => {
                const x = 15 + i * 62;
                const y = 185;
                drawRoundRect(doc, x, y, 58, 38, 4, [255,255,255,0.07]);
                doc.setDrawColor(255, 255, 255);
                doc.setGState(doc.GState({ opacity: 0.12 }));
                doc.roundedRect(x, y, 58, 38, 4, 4, 'S');
                doc.setGState(doc.GState({ opacity: 1 }));

                // Ícono pequeño
                doc.setFillColor(...kpi.color);
                doc.roundedRect(x + 4, y + 5, 8, 8, 1, 1, 'F');
                doc.setTextColor(255, 255, 255);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(6);
                doc.text(kpi.icon, x + 6.5, y + 10.5);

                // Valor
                doc.setFontSize(13);
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(255, 255, 255);
                // Ajustar tamaño si el texto es largo
                const valFontSize = kpi.value.length > 10 ? 9 : 13;
                doc.setFontSize(valFontSize);
                doc.text(kpi.value, x + 4, y + 22);

                // Label
                doc.setFontSize(7);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(167, 243, 208);
                doc.text(kpi.label, x + 4, y + 29);
            });

            // Fecha generación
            doc.setFontSize(8);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(167, 243, 208);
            doc.text(`Generado el ${fechaStr} a las ${horaStr}`, 20, 240);
            doc.text('CEUTEC Honduras 2026  |  admin@ecotiendahn.com', 20, 247);

            // Línea inferior
            doc.setDrawColor(16, 185, 129);
            doc.setLineWidth(0.3);
            doc.line(20, 255, PW - 20, 255);
            doc.setFontSize(7);
            doc.setTextColor(100, 200, 160);
            doc.text('Documento confidencial — Solo para uso interno de EcoTienda HN', PW / 2, 261, { align: 'center' });

            // ─────────────────────────────────────────────────────────────────
            // PÁGINA 2 — RESUMEN EJECUTIVO + VENTAS POR MES
            // ─────────────────────────────────────────────────────────────────
            doc.addPage();

            // Header página
            doc.setFillColor(...C.verdeOsc);
            doc.rect(0, 0, PW, 18, 'F');
            doc.setTextColor(...C.blanco);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.text('ECOTIENDA HN — Reporte Ejecutivo 2026', 15, 11);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(...C.gris);
            doc.text(`${fechaStr}`, PW - 15, 11, { align: 'right' });

            let curY = 28;

            // Sección: Resumen Financiero
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(...C.negro);
            doc.text('Resumen Financiero', 15, curY);
            doc.setDrawColor(...C.verde);
            doc.setLineWidth(1);
            doc.line(15, curY + 2, 70, curY + 2);
            curY += 10;

            // 4 KPI cards horizontales
            const kpi2 = [
                { label:'Ingresos Totales', value: fmt(salesTotal),        sub:'Pedidos activos',  color: C.verde  },
                { label:'Total Pedidos',    value: String(totalPedidos),   sub:'Registros totales',color: C.azul   },
                { label:'Uds. Vendidas',    value: String(totalUnidades),  sub:'Top 8 productos',  color: [139,92,246] },
                { label:'Ticket Promedio',  value: totalPedidos > 0 ? fmt(salesTotal / totalPedidos) : 'L. 0.00', sub:'Por pedido', color: C.amarillo },
            ];

            kpi2.forEach((k, i) => {
                const x = 12 + i * 47;
                drawRoundRect(doc, x, curY, 43, 28, 3, C.cardBg);
                // Barra de color izquierda
                doc.setFillColor(...k.color);
                doc.roundedRect(x, curY, 3, 28, 1, 1, 'F');
                // Valor
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(k.value.length > 9 ? 8 : 11);
                doc.setTextColor(...C.negro);
                doc.text(k.value, x + 6, curY + 11);
                // Label
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7);
                doc.setTextColor(...C.grisOsc);
                doc.text(k.label, x + 6, curY + 18);
                doc.setFontSize(6);
                doc.setTextColor(...C.gris);
                doc.text(k.sub, x + 6, curY + 23);
            });
            curY += 36;

            // Sección: Ventas por mes (tabla visual)
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(...C.negro);
            doc.text('Ventas por Período', 15, curY);
            doc.setDrawColor(...C.verde);
            doc.setLineWidth(1);
            doc.line(15, curY + 2, 72, curY + 2);
            curY += 8;

            if (ventasMes.length > 0) {
                const maxMes = Math.max(...ventasMes.map(m => parseFloat(m.total_mes)));

                ventasMes.forEach((mes, i) => {
                    const rowH = 10;
                    const bg = i % 2 === 0 ? C.fondo : C.blanco;
                    drawRoundRect(doc, 12, curY, PW - 24, rowH, 2, bg);

                    // Mes
                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(8);
                    doc.setTextColor(...C.negro);
                    doc.text(mes.mes_label, 18, curY + 6.5);

                    // Barra proporcional
                    const barW = 80;
                    const fillW = maxMes > 0 ? (parseFloat(mes.total_mes) / maxMes) * barW : 0;
                    doc.setFillColor(226, 232, 240);
                    doc.roundedRect(55, curY + 3, barW, 4, 1, 1, 'F');
                    doc.setFillColor(...C.verde);
                    doc.roundedRect(55, curY + 3, fillW, 4, 1, 1, 'F');

                    // Valores
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(7.5);
                    doc.setTextColor(...C.grisOsc);
                    doc.text(fmt(mes.total_mes), 142, curY + 6.5, { align: 'right' });

                    doc.setFontSize(7);
                    doc.setTextColor(...C.gris);
                    doc.text(`${mes.num_pedidos} pedidos`, PW - 18, curY + 6.5, { align: 'right' });

                    curY += rowH + 1;
                });
            } else {
                doc.setFontSize(9);
                doc.setTextColor(...C.gris);
                doc.text('Sin datos de ventas por período.', 15, curY + 6);
                curY += 14;
            }

            curY += 6;

            // Sección: Pedidos por Estado
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(...C.negro);
            doc.text('Distribución por Estado de Pedidos', 15, curY);
            doc.setDrawColor(...C.verde);
            doc.setLineWidth(1);
            doc.line(15, curY + 2, 103, curY + 2);
            curY += 8;

            doc.autoTable({
                startY: curY,
                head: [['Estado', 'Cantidad', 'Monto Total', '% del Total']],
                body: estadosData.map(e => {
                    const pct = totalPedidos > 0 ? ((e.total / totalPedidos) * 100).toFixed(1) : '0.0';
                    return [
                        e.estado.charAt(0).toUpperCase() + e.estado.slice(1),
                        e.total + ' pedidos',
                        fmt(e.monto),
                        pct + '%'
                    ];
                }),
                theme: 'plain',
                headStyles: { fillColor: C.verdeOsc, textColor: C.blanco, fontStyle: 'bold', fontSize: 8, halign: 'center' },
                bodyStyles:  { fontSize: 8, textColor: C.negro },
                alternateRowStyles: { fillColor: C.fondo },
                columnStyles: {
                    0: { cellWidth: 45 },
                    1: { halign: 'center', cellWidth: 35 },
                    2: { halign: 'right',  cellWidth: 50 },
                    3: { halign: 'center', cellWidth: 30 },
                },
                didParseCell: function(data) {
                    if (data.section === 'body' && data.column.index === 0) {
                        const estado = (estadosData[data.row.index]?.estado || '').toLowerCase();
                        data.cell.styles.textColor = estadoColors[estado] || C.gris;
                        data.cell.styles.fontStyle = 'bold';
                    }
                },
                margin: { left: 12, right: 12 }
            });

            // Footer pág 2
            const p2Y = doc.lastAutoTable.finalY + 6;
            doc.setFontSize(7);
            doc.setTextColor(...C.gris);
            doc.text('EcoTienda HN — Reporte Ejecutivo 2026  |  Página 2', PW / 2, PH - 8, { align: 'center' });

            // ─────────────────────────────────────────────────────────────────
            // PÁGINA 3 — TOP PRODUCTOS + TABLA DE PEDIDOS
            // ─────────────────────────────────────────────────────────────────
            doc.addPage();

            // Header
            doc.setFillColor(...C.verdeOsc);
            doc.rect(0, 0, PW, 18, 'F');
            doc.setTextColor(...C.blanco);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.text('ECOTIENDA HN — Productos y Detalle de Pedidos', 15, 11);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(...C.gris);
            doc.text(`Página 3  |  ${fechaStr}`, PW - 15, 11, { align: 'right' });

            curY = 28;

            // Sección: Top productos
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(...C.negro);
            doc.text('Top Productos Más Vendidos', 15, curY);
            doc.setDrawColor(...C.verde);
            doc.setLineWidth(1);
            doc.line(15, curY + 2, 90, curY + 2);
            curY += 8;

            const maxUnits = bestSellers.length ? Math.max(...bestSellers.map(p => p.total_unidades)) : 1;

            bestSellers.forEach((prod, i) => {
                const rowH = 11;
                drawRoundRect(doc, 12, curY, PW - 24, rowH, 2, i % 2 === 0 ? C.fondo : C.blanco);

                // Ranking badge
                const badgeColors = [[255,215,0], [192,192,192], [205,127,50]];
                const bc = badgeColors[i] || C.cardBg;
                doc.setFillColor(...bc);
                doc.circle(20, curY + 5.5, 3.5, 'F');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(6);
                doc.setTextColor(i < 3 ? 60 : 80, i < 3 ? 40 : 80, i < 3 ? 0 : 80);
                doc.text(String(i + 1), 20, curY + 7, { align: 'center' });

                // Nombre
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8);
                doc.setTextColor(...C.negro);
                const nombre = prod.nombre.length > 38 ? prod.nombre.substring(0, 36) + '…' : prod.nombre;
                doc.text(nombre, 27, curY + 5);

                // Barra
                const barW = 55;
                const fillW = (prod.total_unidades / maxUnits) * barW;
                doc.setFillColor(226, 232, 240);
                doc.roundedRect(27, curY + 6.5, barW, 3, 0.5, 0.5, 'F');
                doc.setFillColor(...C.verde);
                doc.roundedRect(27, curY + 6.5, fillW, 3, 0.5, 0.5, 'F');

                // Stats
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(7.5);
                doc.setTextColor(...C.verde);
                doc.text(`${prod.total_unidades} uds`, 87, curY + 5.5, { align: 'right' });
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7);
                doc.setTextColor(...C.grisOsc);
                doc.text(fmt(prod.total_generado), PW - 18, curY + 5.5, { align: 'right' });

                curY += rowH + 1;
            });

            curY += 8;

            // Sección: Tabla detalle pedidos
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(...C.negro);
            doc.text('Detalle de Últimos Pedidos', 15, curY);
            doc.setDrawColor(...C.verde);
            doc.setLineWidth(1);
            doc.line(15, curY + 2, 83, curY + 2);
            curY += 6;

            const rows = pedidosData.map(p => {
                const clienteNombre = p.nombre ? (p.nombre + ' ' + (p.apellido || '')).trim() : 'Invitado';
                const clienteCorreo = p.correo || p.correo_invitado || '';
                return [
                '#' + p.id,
                clienteNombre.substring(0, 22),
                clienteCorreo.substring(0, 24),
                'L. ' + Number(p.subtotal).toFixed(2),
                p.descuento ? 'L. ' + Number(p.descuento).toFixed(2) : '—',
                'L. ' + Number(p.envio).toFixed(2),
                'L. ' + Number(p.total).toFixed(2),
                (p.estado || '').charAt(0).toUpperCase() + (p.estado || '').slice(1),
                p.fecha ? p.fecha.substring(0, 10) : ''
                ];
            });

            doc.autoTable({
                startY: curY,
                head: [['#', 'Cliente', 'Correo', 'Subtotal', 'Desc.', 'Envío', 'Total', 'Estado', 'Fecha']],
                body: rows,
                theme: 'grid',
                headStyles: { fillColor: C.verde, textColor: C.blanco, fontStyle:'bold', fontSize:7, halign:'center' },
                bodyStyles: { fontSize: 7, textColor: C.negro },
                alternateRowStyles: { fillColor: C.fondo },
                columnStyles: {
                    0: { halign:'center', cellWidth:10 },
                    1: { cellWidth:32 },
                    2: { cellWidth:38 },
                    3: { halign:'right', cellWidth:20 },
                    4: { halign:'right', cellWidth:18 },
                    5: { halign:'right', cellWidth:16 },
                    6: { halign:'right', fontStyle:'bold', cellWidth:22 },
                    7: { halign:'center', cellWidth:20 },
                    8: { halign:'center', cellWidth:20 },
                },
                didParseCell: function(data) {
                    if (data.section === 'body' && data.column.index === 7) {
                        const estado = (pedidosData[data.row.index]?.estado || '').toLowerCase();
                        data.cell.styles.textColor = estadoColors[estado] || C.gris;
                        data.cell.styles.fontStyle = 'bold';
                    }
                },
                foot: [[
                    { content: 'TOTAL GENERAL', colSpan: 6, styles: { halign:'right', fontStyle:'bold', fillColor: C.verdeOsc, textColor: C.blanco, fontSize: 8 } },
                    { content: fmt(totalGeneral), styles: { halign:'right', fontStyle:'bold', fillColor: C.verdeOsc, textColor: C.blanco, fontSize: 8 } },
                    { content: '', colSpan: 2, styles: { fillColor: C.verdeOsc } }
                ]],
                margin: { left: 8, right: 8 }
            });

            // ── Footer en todas las páginas ───────────────────────────────
            const totalPages = doc.internal.getNumberOfPages();
            for (let pg = 1; pg <= totalPages; pg++) {
                doc.setPage(pg);
                doc.setFillColor(6, 78, 59);
                doc.rect(0, PH - 12, PW, 12, 'F');
                doc.setFontSize(6.5);
                doc.setTextColor(167, 243, 208);
                doc.setFont('helvetica', 'normal');
                doc.text('EcoTienda HN  |  Ecológico · Sostenible · Hondureño  |  CEUTEC Honduras 2026  |  admin@ecotiendahn.com', PW / 2, PH - 6.5, { align:'center' });
                doc.setTextColor(255, 255, 255);
                doc.setFont('helvetica', 'bold');
                doc.text(`${pg} / ${totalPages}`, PW - 10, PH - 6.5, { align:'right' });
            }

            const filename = 'EcoTienda_Reporte_Ejecutivo_' + now.toISOString().slice(0, 10) + '.pdf';
            doc.save(filename);

        } catch (err) {
            console.error('Error PDF:', err);
            alert('Error al generar PDF: ' + err.message);
        } finally {
            btn.disabled = false;
            lbl.textContent = 'Exportar PDF Ejecutivo';
        }
    }, 50);
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>