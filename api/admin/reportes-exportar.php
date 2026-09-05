<?php
/**
 * 🌱 ECOTIENDA HN - EXPORTACIÓN DE REPORTES (API ADMIN)
 * Ruta: /api/admin/reportes-exportar.php  (ARCHIVO NUEVO — Fase 7)
 *
 * Genera el archivo descargable de reportes para la app móvil, usando el
 * mismo cálculo que api/admin/reportes.php (includes/reportes_datos.php).
 *
 * GET ?formato=pdf|csv
 *     &periodo=hoy|este_mes|mes_anterior|30dias|este_anio|personalizado
 *     [&fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD] (solo si periodo=personalizado)
 *     (requiere admin, Bearer token)
 *
 * NOTA sobre "Excel": no hay PhpSpreadsheet instalado en este proyecto
 * (composer.json solo trae TCPDF, PHPMailer, etc.), y agregar una
 * dependencia nueva en hosting compartido (AlwaysData) sin poder probarlo
 * primero es riesgoso. formato=csv genera un CSV real que Excel/Sheets
 * abren directamente — mismo resultado práctico que un .xlsx para tablas,
 * sin la dependencia nueva. Si más adelante hace falta un .xlsx real con
 * formato (colores, hojas separadas), se puede agregar PhpSpreadsheet vía
 * composer y cambiar solo la rama de este archivo.
 */

require_once __DIR__ . '/../../includes/api_auth.php';
require_once __DIR__ . '/../../includes/reportes_datos.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // TCPDF, ya usado en includes/recibo_pdf.php

function exportarError(int $httpCode, string $mensaje): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$admin = requireApiAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    exportarError(405, 'Método no permitido.');
}

$formato = strtolower($_GET['formato'] ?? '');
if (!in_array($formato, ['pdf', 'csv'], true)) {
    exportarError(400, 'Formato no válido. Usa formato=pdf o formato=csv.');
}

$periodo     = $_GET['periodo'] ?? 'este_mes';
$fechaInicio = $_GET['fecha_inicio'] ?? null;
$fechaFin    = $_GET['fecha_fin'] ?? null;

try {
    $datos = calcularReportesAdmin($periodo, $fechaInicio, $fechaFin);
} catch (Exception $e) {
    logError('ERROR', 'Exportar reportes admin: ' . $e->getMessage());
    exportarError(500, 'No se pudieron calcular los reportes.');
}

$slugPeriodo = preg_replace('/[^a-z0-9_-]/i', '', $datos['periodo']);
$nombreArchivo = "reporte-ecotienda-{$slugPeriodo}-" . date('Ymd-His');

// ────────────────────────────────────────────────────────────
// CSV (compatible con Excel/Sheets)
// ────────────────────────────────────────────────────────────
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 para que Excel en Windows detecte los acentos correctamente.
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Reporte EcoTienda HN']);
    fputcsv($out, ['Período', $datos['periodo_label']]);
    fputcsv($out, ['Rango', $datos['rango']['inicio'] . ' a ' . $datos['rango']['fin']]);
    fputcsv($out, []);

    fputcsv($out, ['KPIs']);
    fputcsv($out, ['Ventas del período', $datos['kpis']['ventas_periodo']]);
    fputcsv($out, ['Pedidos del período', $datos['kpis']['pedidos_periodo']]);
    fputcsv($out, ['Ticket promedio', $datos['kpis']['ticket_promedio']]);
    fputcsv($out, ['Clientes nuevos', $datos['kpis']['clientes_nuevos']]);
    fputcsv($out, ['Clientes recurrentes', $datos['kpis']['clientes_recurrentes']]);
    fputcsv($out, []);

    fputcsv($out, ['Productos más vendidos']);
    fputcsv($out, ['Producto', 'Unidades', 'Total generado']);
    foreach ($datos['mas_vendidos'] as $p) {
        fputcsv($out, [$p['nombre'], $p['total_unidades'], $p['total_generado']]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Pedidos por estado']);
    fputcsv($out, ['Estado', 'Cantidad', 'Monto']);
    foreach ($datos['resumen_estados'] as $e) {
        fputcsv($out, [$e['estado'], $e['total'], $e['monto']]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Pedidos del período (máx. 200)']);
    fputcsv($out, ['ID', 'Cliente', 'Correo', 'Subtotal', 'Descuento', 'Envío', 'Total', 'Estado', 'Fecha', 'Cupón']);
    foreach ($datos['pedidos'] as $p) {
        $cliente = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        if ($cliente === '') $cliente = 'Invitado';
        $correo = $p['correo'] ?? $p['correo_invitado'] ?? '';
        fputcsv($out, [
            $p['id'], $cliente, $correo, $p['subtotal'], $p['descuento'],
            $p['envio'], $p['total'], $p['estado'], $p['fecha'], $p['cupon_codigo'] ?? '',
        ]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Stock crítico']);
    fputcsv($out, ['Producto', 'Stock', 'Precio']);
    foreach ($datos['stock_critico'] as $s) {
        fputcsv($out, [$s['nombre'], $s['stock'], $s['precio']]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Uso de cupones']);
    fputcsv($out, ['Código', 'Veces usado', 'Total descontado']);
    foreach ($datos['cupones'] as $c) {
        fputcsv($out, [$c['codigo'], $c['veces_usado'], $c['total_descontado']]);
    }

    fclose($out);
    logAuditoria($admin['id'], 'Exportó reporte en CSV (desde app móvil)', 'reportes');
    exit;
}

// ────────────────────────────────────────────────────────────
// PDF (TCPDF, mismo patrón que includes/recibo_pdf.php)
// ────────────────────────────────────────────────────────────

function filaTabla(string $a, string $b = '', string $c = '', string $d = ''): string
{
    return "<tr><td>{$a}</td><td align='right'>{$b}</td><td align='right'>{$c}</td><td align='right'>{$d}</td></tr>";
}

$filasVendidos = '';
foreach ($datos['mas_vendidos'] as $p) {
    $filasVendidos .= filaTabla(
        htmlspecialchars($p['nombre']),
        number_format((float)$p['total_unidades'], 0),
        'L. ' . number_format((float)$p['total_generado'], 2)
    );
}
if ($filasVendidos === '') {
    $filasVendidos = "<tr><td colspan='4'>Sin ventas registradas en este período</td></tr>";
}

$filasEstados = '';
foreach ($datos['resumen_estados'] as $e) {
    $filasEstados .= filaTabla(
        htmlspecialchars(ucfirst(str_replace('_', ' ', $e['estado']))),
        (string)$e['total'],
        'L. ' . number_format((float)$e['monto'], 2)
    );
}

$filasStock = '';
foreach ($datos['stock_critico'] as $s) {
    $filasStock .= "<tr><td>" . htmlspecialchars($s['nombre']) . "</td><td align='right'>{$s['stock']} und.</td></tr>";
}
if ($filasStock === '') {
    $filasStock = "<tr><td colspan='2'>No hay productos con stock crítico</td></tr>";
}

$filasCupones = '';
foreach ($datos['cupones'] as $c) {
    $filasCupones .= "<tr><td>" . htmlspecialchars($c['codigo']) . "</td><td align='right'>{$c['veces_usado']}x</td><td align='right'>L. " . number_format((float)$c['total_descontado'], 2) . "</td></tr>";
}
if ($filasCupones === '') {
    $filasCupones = "<tr><td colspan='3'>Ningún cupón fue usado en este período</td></tr>";
}

$k = $datos['kpis'];

$html = "
    <h1 style='color:#1a5c2a;font-size:16pt;margin:0;'>EcoTienda HN — Reporte</h1>
    <p style='color:#666;font-size:9pt;margin:2px 0 12px;'>" . htmlspecialchars($datos['periodo_label']) . "</p>

    <table cellpadding='6' style='width:100%;font-size:9pt;'>
        <tr style='background-color:#e8f5e9;'>
            <td><b>Ventas</b><br>L. " . number_format((float)$k['ventas_periodo'], 2) . "</td>
            <td><b>Pedidos</b><br>{$k['pedidos_periodo']}</td>
            <td><b>Ticket promedio</b><br>L. " . number_format((float)$k['ticket_promedio'], 2) . "</td>
            <td><b>Nuevos / Recurrentes</b><br>{$k['clientes_nuevos']} / {$k['clientes_recurrentes']}</td>
        </tr>
    </table>

    <br>
    <h2 style='font-size:11pt;color:#333;'>Productos más vendidos</h2>
    <table border='1' cellpadding='5' style='width:100%;border-collapse:collapse;font-size:8.5pt;'>
        <thead><tr style='background-color:#e8f5e9;'><th align='left'>Producto</th><th align='right'>Unidades</th><th align='right'>Total</th><th></th></tr></thead>
        <tbody>{$filasVendidos}</tbody>
    </table>

    <br>
    <h2 style='font-size:11pt;color:#333;'>Pedidos por estado</h2>
    <table border='1' cellpadding='5' style='width:100%;border-collapse:collapse;font-size:8.5pt;'>
        <thead><tr style='background-color:#e8f5e9;'><th align='left'>Estado</th><th align='right'>Cantidad</th><th align='right'>Monto</th><th></th></tr></thead>
        <tbody>{$filasEstados}</tbody>
    </table>

    <br>
    <h2 style='font-size:11pt;color:#333;'>Stock crítico</h2>
    <table border='1' cellpadding='5' style='width:100%;border-collapse:collapse;font-size:8.5pt;'>
        <thead><tr style='background-color:#e8f5e9;'><th align='left'>Producto</th><th align='right'>Stock</th></tr></thead>
        <tbody>{$filasStock}</tbody>
    </table>

    <br>
    <h2 style='font-size:11pt;color:#333;'>Uso de cupones</h2>
    <table border='1' cellpadding='5' style='width:100%;border-collapse:collapse;font-size:8.5pt;'>
        <thead><tr style='background-color:#e8f5e9;'><th align='left'>Código</th><th align='right'>Veces usado</th><th align='right'>Descontado</th></tr></thead>
        <tbody>{$filasCupones}</tbody>
    </table>

    <p style='margin-top:16px;font-size:7.5pt;color:#999;text-align:center;'>
        Pedidos del período completos (máx. 200): consulta la sección Pedidos en el panel admin.
        Generado automáticamente por EcoTienda HN el " . date('d/m/Y H:i') . ".
    </p>
";

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('EcoTienda HN');
$pdf->SetAuthor('EcoTienda HN');
$pdf->SetTitle('Reporte EcoTienda HN - ' . $datos['periodo_label']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->writeHTML($html, true, false, true, false, '');

$pdfContenido = $pdf->Output("{$nombreArchivo}.pdf", 'S');

logAuditoria($admin['id'], 'Exportó reporte en PDF (desde app móvil)', 'reportes');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.pdf"');
header('Content-Length: ' . strlen($pdfContenido));
echo $pdfContenido;
