<?php
/**
 * 🌱 ECOTIENDA HN - CÁLCULO DE REPORTES (LÓGICA COMPARTIDA)
 * Ruta: /includes/reportes_datos.php  (ARCHIVO NUEVO)
 *
 * Extrae el cálculo que antes vivía solo dentro de api/admin/reportes.php
 * para que también lo use api/admin/reportes-exportar.php (Fase 7), sin
 * duplicar la lógica de fechas/consultas en dos lugares.
 *
 * api/admin/reportes.php debe actualizarse para llamar a
 * calcularReportesAdmin() en vez de tener el cálculo inline (ver archivo
 * actualizado que acompaña a este).
 */

require_once __DIR__ . '/database.php';

/** Presets de período válidos — misma lista en reportes.php y reportes-exportar.php. */
function periodosReporteValidos(): array
{
    return ['hoy', 'este_mes', 'mes_anterior', '30dias', 'este_anio', 'personalizado'];
}

function reportePctChange(float $actual, float $anterior): ?float
{
    if ($anterior == 0.0) return $actual > 0 ? null : 0.0;
    return round((($actual - $anterior) / $anterior) * 100, 1);
}

/**
 * Calcula todos los datos de reportes para un período dado.
 * Lanza Exception si algo falla en las consultas (el caller decide cómo
 * responder — JSON en reportes.php, error de descarga en reportes-exportar.php).
 *
 * @param string      $periodo      Uno de periodosReporteValidos().
 * @param string|null $fechaInicio  Solo si $periodo === 'personalizado' (YYYY-MM-DD).
 * @param string|null $fechaFin     Solo si $periodo === 'personalizado' (YYYY-MM-DD).
 */
function calcularReportesAdmin(string $periodo, ?string $fechaInicio = null, ?string $fechaFin = null): array
{
    $pdo = Database::getConnection();

    if (!in_array($periodo, periodosReporteValidos(), true)) {
        $periodo = 'este_mes';
    }

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
                $inicio = new DateTime($fechaInicio ?? 'first day of this month');
                $fin    = new DateTime($fechaFin ?? 'today');
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

    // Ventas y pedidos del período + período anterior
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0), COUNT(*) FROM pedidos WHERE fecha BETWEEN :i AND :f AND estado NOT IN ('cancelado')");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    [$ventasPeriodo, $pedidosPeriodo] = $stmt->fetch(PDO::FETCH_NUM);
    $ventasPeriodo  = (float)$ventasPeriodo;
    $pedidosPeriodo = (int)$pedidosPeriodo;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0), COUNT(*) FROM pedidos WHERE fecha BETWEEN :i AND :f AND estado NOT IN ('cancelado')");
    $stmt->execute([':i' => $fPrevInicio, ':f' => $fPrevFin]);
    [$ventasPeriodoAnt, $pedidosPeriodoAnt] = $stmt->fetch(PDO::FETCH_NUM);
    $ventasPeriodoAnt  = (float)$ventasPeriodoAnt;
    $pedidosPeriodoAnt = (int)$pedidosPeriodoAnt;

    $ticketPromedio    = $pedidosPeriodo    > 0 ? $ventasPeriodo    / $pedidosPeriodo    : 0.00;
    $ticketPromedioAnt = $pedidosPeriodoAnt > 0 ? $ventasPeriodoAnt / $pedidosPeriodoAnt : 0.00;

    // Productos más vendidos
    $stmt = $pdo->prepare("
        SELECT p.nombre, SUM(dp.cantidad) AS total_unidades, SUM(dp.subtotal) AS total_generado
        FROM detalle_pedido dp
        JOIN productos p ON dp.producto_id = p.id
        JOIN pedidos pe ON dp.pedido_id = pe.id
        WHERE pe.fecha BETWEEN :i AND :f AND pe.estado NOT IN ('cancelado')
        GROUP BY p.id ORDER BY total_unidades DESC LIMIT 8
    ");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    $bestSellersList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tendencia últimos 6 meses (no depende del filtro)
    $ventasMes = $pdo->query("
        SELECT DATE_FORMAT(fecha,'%b %Y') AS mes_label,
               SUM(total) AS total_mes,
               COUNT(*) AS num_pedidos
        FROM pedidos
        WHERE fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          AND estado NOT IN ('cancelado')
        GROUP BY YEAR(fecha), MONTH(fecha)
        ORDER BY fecha ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Resumen por estado
    $stmt = $pdo->prepare("
        SELECT estado, COUNT(*) AS total, SUM(total) AS monto
        FROM pedidos WHERE fecha BETWEEN :i AND :f
        GROUP BY estado ORDER BY total DESC
    ");
    $stmt->execute([':i' => $fInicio, ':f' => $fFin]);
    $resumenEstados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pedidos del período (tabla)
    $stmt = $pdo->prepare("
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

    // Clientes nuevos vs. recurrentes
    $stmt = $pdo->prepare("
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

    // Stock crítico (estado actual)
    $stockCritico = $pdo->query("
        SELECT id, nombre, stock, precio FROM productos
        WHERE estado = 'activo' AND stock <= 5
        ORDER BY stock ASC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Reporte de cupones (uso dentro del período)
    $stmt = $pdo->prepare("
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

    $pctVentas  = reportePctChange($ventasPeriodo, $ventasPeriodoAnt);
    $pctPedidos = reportePctChange((float)$pedidosPeriodo, (float)$pedidosPeriodoAnt);
    $pctTicket  = reportePctChange($ticketPromedio, $ticketPromedioAnt);

    return [
        'periodo'       => $periodo,
        'periodo_label' => $periodoLabel,
        'rango' => [
            'inicio' => $inicio->format('Y-m-d'),
            'fin'    => $fin->format('Y-m-d'),
        ],
        'kpis' => [
            'ventas_periodo'        => $ventasPeriodo,
            'ventas_variacion_pct'  => $pctVentas,
            'pedidos_periodo'       => $pedidosPeriodo,
            'pedidos_variacion_pct' => $pctPedidos,
            'ticket_promedio'       => round($ticketPromedio, 2),
            'ticket_variacion_pct'  => $pctTicket,
            'clientes_nuevos'       => $clientesNuevos,
            'clientes_recurrentes'  => $clientesRecurrentes,
        ],
        'mas_vendidos'      => $bestSellersList,
        'ventas_mensuales'  => $ventasMes,
        'resumen_estados'   => $resumenEstados,
        'pedidos'           => $pedidosTabla,
        'stock_critico'     => $stockCritico,
        'cupones'           => $reporteCupones,
    ];
}
