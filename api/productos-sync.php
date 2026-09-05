<?php
/**
 * 🌱 ECOTIENDA HN - SINCRONIZACIÓN INCREMENTAL DE PRODUCTOS (APP MÓVIL)
 * Ruta: /api/productos-sync.php
 * Descripción: Endpoint público (sin autenticación, igual que api/productos.php)
 *              usado por la app NativeScript para refrescar su base SQLite
 *              local (Fase 3 del plan, ver sync.service.ts).
 *
 *              Si se manda ?desde=<fecha>, devuelve solo los productos con
 *              actualizado_en posterior a esa fecha (sync incremental).
 *              Sin ese parámetro, devuelve el catálogo activo completo
 *              (primera sincronización, cuando la app aún no tiene nada
 *              guardado localmente).
 *
 * Petición esperada (GET):
 *   /api/productos-sync.php
 *   /api/productos-sync.php?desde=2026-08-20 14:30:00
 *
 * Respuesta exitosa:
 *   {
 *     "success": true,
 *     "productos": [ ... mismo formato que api/productos.php, + actualizado_en ... ],
 *     "total": 12,
 *     "sincronizado_en": "2026-08-22 20:15:03"
 *   }
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Se toma ANTES de consultar la base, para no perder cambios que ocurran
// entre el momento de la consulta y el momento en que la app guarda este
// valor como su próximo "desde" (evita una pequeña condición de carrera).
$sincronizadoEn = date('Y-m-d H:i:s');

$desde = trim($_GET['desde'] ?? '');

try {
    $db = Database::getConnection();

    $whereClauses = ["p.estado != 'inactivo'"];
    $params = [];

    if ($desde !== '') {
        // Validación estricta de formato (defensa extra además de PDO prepared).
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $desde)) {
            http_response_code(400);
            echo json_encode(
                ['success' => false, 'message' => 'Formato de fecha inválido en "desde". Usa YYYY-MM-DD HH:MM:SS.'],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }
        $whereClauses[] = 'p.actualizado_en > :desde';
        $params[':desde'] = $desde;
    }

    $whereSQL = implode(' AND ', $whereClauses);

    $sql = "SELECT p.id, p.nombre, p.slug, p.descripcion_corta, p.precio, p.precio_oferta, p.stock,
                   p.imagen_principal, c.nombre AS categoria_nombre,
                   COALESCE(p.precio_oferta, p.precio) AS precio_actual,
                   p.actualizado_en
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE $whereSQL
            ORDER BY p.actualizado_en ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();

    $resultado = array_map(function ($p) {
        return [
            'id'                => (int)$p['id'],
            'nombre'            => $p['nombre'],
            'slug'              => $p['slug'],
            'descripcion_corta' => $p['descripcion_corta'],
            'precio'            => (float)$p['precio'],
            'precio_oferta'     => $p['precio_oferta'] ? (float)$p['precio_oferta'] : null,
            'precio_actual'     => (float)$p['precio_actual'],
            'precio_fmt'        => 'L. ' . number_format($p['precio_actual'], 2, '.', ','),
            'precio_orig_fmt'   => 'L. ' . number_format($p['precio'], 2, '.', ','),
            'stock'             => (int)$p['stock'],
            'imagen_principal'  => $p['imagen_principal'] ? BASE_URL . ltrim($p['imagen_principal'], '/') : 'https://placehold.co/500x500/10b981/white?text=%F0%9F%8C%BF',
            'categoria_nombre'  => $p['categoria_nombre'] ?? '',
            'en_oferta'         => !empty($p['precio_oferta']),
            'url'               => BASE_URL . 'producto.php?id=' . $p['id'],
            'actualizado_en'    => $p['actualizado_en'],
        ];
    }, $productos);

    echo json_encode([
        'success'         => true,
        'productos'       => $resultado,
        'total'           => count($resultado),
        'sincronizado_en' => $sincronizadoEn,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.'], JSON_UNESCAPED_UNICODE);
    error_log('[API productos-sync] ' . $e->getMessage());
}