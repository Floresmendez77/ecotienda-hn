<?php
/**
 * 🌱 ECOTIENDA HN - API DE BÚSQUEDA DE PRODUCTOS
 * Ruta: /api/productos.php
 * Descripción: Endpoint JSON para búsqueda AJAX de productos en tienda.php.
 *              Acepta ?q=texto&categoria=id y devuelve la lista de productos.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$q          = trim($_GET['q'] ?? '');
$catFilter  = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$sort       = $_GET['orden'] ?? 'recientes';

$allowedSorts = [
    'precio_asc'  => 'precio_actual ASC',
    'precio_desc' => 'precio_actual DESC',
    'nombre_asc'  => 'p.nombre ASC',
    'recientes'   => 'p.fecha_creacion DESC',
];
$orderBy = $allowedSorts[$sort] ?? 'p.fecha_creacion DESC';

try {
    $db = Database::getConnection();

    $whereClauses = ["p.estado != 'inactivo'"];
    $params       = [];

    if (!empty($q)) {
        $whereClauses[] = "(p.nombre LIKE :q OR p.descripcion_corta LIKE :q OR p.descripcion_larga LIKE :q)";
        $params[':q']   = '%' . $q . '%';
    }

    if ($catFilter > 0) {
        $whereClauses[]      = "p.categoria_id = :cat";
        $params[':cat']      = $catFilter;
    }

    $whereSQL = implode(' AND ', $whereClauses);

    $sql = "SELECT p.id, p.nombre, p.descripcion_corta, p.precio, p.precio_oferta, p.stock,
                   p.imagen_principal, c.nombre AS categoria_nombre,
                   COALESCE(p.precio_oferta, p.precio) AS precio_actual
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE $whereSQL
            ORDER BY $orderBy
            LIMIT 48";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Formatear precios y construir URLs
    $result = array_map(function($p) {
        return [
            'id'               => (int)$p['id'],
            'nombre'           => $p['nombre'],
            'descripcion_corta'=> $p['descripcion_corta'],
            'precio'           => (float)$p['precio'],
            'precio_oferta'    => $p['precio_oferta'] ? (float)$p['precio_oferta'] : null,
            'precio_actual'    => (float)$p['precio_actual'],
            'precio_fmt'       => 'L. ' . number_format($p['precio_actual'], 2, '.', ','),
            'precio_orig_fmt'  => 'L. ' . number_format($p['precio'], 2, '.', ','),
            'stock'            => (int)$p['stock'],
            'imagen_principal' => $p['imagen_principal'] ? BASE_URL . ltrim($p['imagen_principal'], '/') : 'https://placehold.co/500x500/10b981/white?text=%F0%9F%8C%BF',
            'categoria_nombre' => $p['categoria_nombre'] ?? '',
            'en_oferta'        => !empty($p['precio_oferta']),
            'url'              => BASE_URL . 'producto.php?id=' . $p['id'],
        ];
    }, $products);

    echo json_encode(['success' => true, 'productos' => $result, 'total' => count($result)], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.'], JSON_UNESCAPED_UNICODE);
    error_log('[API productos] ' . $e->getMessage());
}