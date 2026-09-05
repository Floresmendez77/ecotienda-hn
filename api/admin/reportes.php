<?php
/**
 * 🌱 ECOTIENDA HN - REPORTES (API ADMIN — DATOS)
 * Ruta: /api/admin/reportes.php
 * Descripción: Devuelve en JSON exactamente los mismos datos que
 *              /admin/reportes.php calcula para alimentar sus tablas y
 *              gráficas (jsPDF/ExcelJS corren en el navegador ahí).
 *
 * ACTUALIZADO (Fase 7): el cálculo ahora vive en
 * includes/reportes_datos.php (calcularReportesAdmin()), compartido con
 * api/admin/reportes-exportar.php, para no tener la misma lógica de
 * fechas/consultas duplicada en dos archivos.
 *
 * GET ?periodo=hoy|este_mes|mes_anterior|30dias|este_anio|personalizado
 *     [&fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD] (solo si periodo=personalizado)
 *     (requiere admin)
 */

require_once __DIR__ . '/../../includes/api_auth.php';
require_once __DIR__ . '/../../includes/reportes_datos.php';

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

$periodo      = $_GET['periodo'] ?? 'este_mes';
$fechaInicio  = $_GET['fecha_inicio'] ?? null;
$fechaFin     = $_GET['fecha_fin'] ?? null;

try {
    $datos = calcularReportesAdmin($periodo, $fechaInicio, $fechaFin);
} catch (Exception $e) {
    logError('ERROR', 'Reportes admin (API): ' . $e->getMessage());
    responderApi(false, ['error' => 'No se pudieron calcular los reportes.'], 500);
}

responderApi(true, $datos);