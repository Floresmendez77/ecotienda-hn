<?php
/**
 * 🌱 ECOTIENDA HN - DASHBOARD (API ADMIN)
 * Ruta: /api/admin/dashboard.php
 * Descripción: Métricas y gráficas para el panel admin de la app móvil,
 *              espejo exacto de las consultas de /admin/index.php.
 *              Datos en vivo (no offline-first): el dashboard no tiene
 *              sentido mostrando cifras viejas.
 *
 * GET (requiere admin) → todas las métricas en un solo JSON
 */

require_once __DIR__ . '/../../includes/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

function responderApi(bool $success, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

requireApiAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderApi(false, ['error' => 'Método no permitido.'], 405);
}

$pdo = Database::getConnection();

$pedidosHoy       = 0;
$ingresosMes      = 0.00;
$usuariosTotal    = 0;
$productosActivos = 0;
$mesesLabels      = [];
$ventasMensuales  = [];
$estadosPedidos   = ['pendiente' => 0, 'pagado' => 0, 'procesando' => 0, 'enviado' => 0, 'entregado' => 0, 'cancelado' => 0];
$lowStockProducts = [];
$recentOrders     = [];

try {
    // KPI 1: Pedidos de hoy
    $pedidosHoy = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(fecha) = CURDATE()")->fetchColumn();

    // KPI 2: Ingresos del mes en curso
    $ingresosMes = (float)$pdo->query("
        SELECT COALESCE(SUM(total), 0)
        FROM pedidos
        WHERE MONTH(fecha) = MONTH(CURDATE())
          AND YEAR(fecha)  = YEAR(CURDATE())
          AND estado NOT IN ('cancelado')
    ")->fetchColumn();

    // KPI 3: Usuarios registrados (rol_id = 2, clientes)
    $usuariosTotal = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol_id = 2")->fetchColumn();

    // KPI 4: Productos activos
    $productosActivos = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE estado = 'activo'")->fetchColumn();

    // Gráfica de barras: ventas últimos 6 meses
    $stmt = $pdo->query("
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

    $ventasPorMes = [];
    for ($i = 5; $i >= 0; $i--) {
        $key   = date('Y-m', strtotime("-{$i} months"));
        $label = date('M Y', strtotime("-{$i} months"));
        $mesesLabels[]       = $label;
        $ventasPorMes[$key]  = 0;
    }
    foreach ($ventasRows as $row) {
        if (isset($ventasPorMes[$row['mes_key']])) {
            $ventasPorMes[$row['mes_key']] = (float)$row['total_ventas'];
        }
    }
    $ventasMensuales = array_values($ventasPorMes);

    // Gráfica de dona: distribución por estado
    $stmt = $pdo->query("SELECT estado, COUNT(*) AS cantidad FROM pedidos GROUP BY estado");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (array_key_exists($row['estado'], $estadosPedidos)) {
            $estadosPedidos[$row['estado']] = (int)$row['cantidad'];
        }
    }

    // Stock crítico
    $lowStockProducts = $pdo->query(
        "SELECT id, nombre, stock, precio FROM productos WHERE stock <= 5 ORDER BY stock ASC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Últimos 5 pedidos
    $recentOrders = $pdo->query("
        SELECT p.id, p.total, p.estado, p.fecha, p.correo_invitado,
               u.nombre, u.apellido
        FROM pedidos p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        ORDER BY p.fecha DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    logError('ERROR', 'Dashboard admin (API): error al cargar métricas', [
        'file' => $e->getFile(), 'line' => $e->getLine(), 'msg' => $e->getMessage()
    ]);
    responderApi(false, ['error' => 'No se pudieron cargar las métricas del dashboard.'], 500);
}

responderApi(true, [
    'kpis' => [
        'pedidos_hoy'       => $pedidosHoy,
        'ingresos_mes'      => $ingresosMes,
        'usuarios_total'    => $usuariosTotal,
        'productos_activos' => $productosActivos,
    ],
    'ventas_mensuales' => [
        'labels' => $mesesLabels,
        'data'   => $ventasMensuales,
    ],
    'estados_pedidos'    => $estadosPedidos,
    'stock_critico'      => $lowStockProducts,
    'pedidos_recientes'  => $recentOrders,
]);
