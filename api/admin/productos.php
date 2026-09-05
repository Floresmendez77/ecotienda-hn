<?php
/**
 * 🌱 ECOTIENDA HN - CRUD DE PRODUCTOS (API ADMIN)
 * Ruta: /api/admin/productos.php
 * Descripción: CRUD de productos para la app móvil, protegido con
 *              requireApiAdmin(). Reutiliza uploadImage() y ecotienda_mail()
 *              ya existentes en el proyecto (Fase 2 del plan).
 *
 * Acciones (todas requieren header Authorization: Bearer <token> de un admin):
 *   POST   ?accion=crear      (multipart/form-data, incluye 'imagen' opcional)
 *   POST   ?accion=editar     (multipart/form-data, incluye 'id')
 *   POST   ?accion=eliminar   (JSON, incluye 'id')
 *   GET    (sin acción)       Lista todos los productos (incluye inactivos)
 */

require_once __DIR__ . '/../../includes/api_auth.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

function responderApi(bool $success, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// Toda acción de este archivo requiere admin autenticado
$admin = requireApiAdmin();
$pdo = Database::getConnection();

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? ($metodo === 'GET' ? 'listar' : null);

// ────────────────────────────────────────────────────────────
// LISTAR (incluye productos inactivos/agotados, a diferencia
// del endpoint público api/productos.php)
// ────────────────────────────────────────────────────────────
if ($accion === 'listar') {
    $stmt = $pdo->query(
        "SELECT p.id, p.nombre, p.slug, p.descripcion_corta, p.precio, p.precio_oferta,
                p.stock, p.imagen_principal, p.estado, p.categoria_id,
                c.nombre AS categoria_nombre, p.actualizado_en
         FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         ORDER BY p.actualizado_en DESC"
    );
    responderApi(true, ['productos' => $stmt->fetchAll()]);
}

// ────────────────────────────────────────────────────────────
// CREAR
// ────────────────────────────────────────────────────────────
if ($accion === 'crear' && $metodo === 'POST') {
    $nombre             = trim($_POST['nombre'] ?? '');
    $categoria_id       = (int)($_POST['categoria_id'] ?? 0);
    $descripcion_corta  = trim($_POST['descripcion_corta'] ?? '');
    $descripcion_larga  = trim($_POST['descripcion_larga'] ?? '');
    $precio             = (float)($_POST['precio'] ?? 0);
    $precio_oferta      = isset($_POST['precio_oferta']) && $_POST['precio_oferta'] !== ''
                            ? (float)$_POST['precio_oferta'] : null;
    $stock              = (int)($_POST['stock'] ?? 0);
    $estado             = in_array($_POST['estado'] ?? '', ['activo', 'inactivo', 'agotado'])
                            ? $_POST['estado'] : 'activo';

    if ($nombre === '' || $categoria_id <= 0 || $precio <= 0) {
        responderApi(false, ['error' => 'Nombre, categoría y precio son obligatorios.'], 400);
    }

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $nombre), '-'));

    $imagenNombre = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $subido = uploadImage($_FILES['imagen'], __DIR__ . '/../../assets/uploads/productos/');
        if ($subido === false) {
            responderApi(false, ['error' => 'No se pudo subir la imagen (formato o tamaño inválido).'], 400);
        }
        $imagenNombre = $subido;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO productos
            (categoria_id, nombre, slug, descripcion_corta, descripcion_larga,
             precio, precio_oferta, stock, imagen_principal, estado)
         VALUES
            (:categoria_id, :nombre, :slug, :descripcion_corta, :descripcion_larga,
             :precio, :precio_oferta, :stock, :imagen_principal, :estado)"
    );
    $stmt->execute([
        'categoria_id'      => $categoria_id,
        'nombre'            => $nombre,
        'slug'              => $slug,
        'descripcion_corta' => $descripcion_corta,
        'descripcion_larga' => $descripcion_larga,
        'precio'            => $precio,
        'precio_oferta'     => $precio_oferta,
        'stock'             => $stock,
        'imagen_principal'  => $imagenNombre,
        'estado'            => $estado,
    ]);

    $nuevoId = (int)$pdo->lastInsertId();

    if ($stock > 0) {
        $pdo->prepare(
            "INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion)
             VALUES (:pid, 'entrada', :qty, 'Stock inicial (desde app móvil)')"
        )->execute(['pid' => $nuevoId, 'qty' => $stock]);
    }

    logAuditoria($admin['id'], "Creó producto ID: $nuevoId (desde app móvil)", 'productos');

    // Correo de confirmación al admin que publicó (requisito de la Fase 2)
    ecotienda_mail(
        $admin['correo'],
        $admin['nombre'],
        'Producto publicado en EcoTienda HN',
        "<p>Hola {$admin['nombre']},</p><p>El producto <strong>" . htmlspecialchars($nombre) . "</strong> se publicó correctamente desde la app móvil.</p>",
        "Hola {$admin['nombre']}, el producto {$nombre} se publicó correctamente desde la app móvil."
    );

    responderApi(true, ['mensaje' => 'Producto creado correctamente.', 'id' => $nuevoId]);
}

// ────────────────────────────────────────────────────────────
// EDITAR
// ────────────────────────────────────────────────────────────
if ($accion === 'editar' && $metodo === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        responderApi(false, ['error' => 'ID de producto inválido.'], 400);
    }

    $existente = $pdo->prepare("SELECT imagen_principal FROM productos WHERE id = :id");
    $existente->execute(['id' => $id]);
    $productoActual = $existente->fetch();

    if (!$productoActual) {
        responderApi(false, ['error' => 'Producto no encontrado.'], 404);
    }

    $nombre            = trim($_POST['nombre'] ?? '');
    $categoria_id      = (int)($_POST['categoria_id'] ?? 0);
    $descripcion_corta = trim($_POST['descripcion_corta'] ?? '');
    $descripcion_larga = trim($_POST['descripcion_larga'] ?? '');
    $precio            = (float)($_POST['precio'] ?? 0);
    $precio_oferta     = isset($_POST['precio_oferta']) && $_POST['precio_oferta'] !== ''
                            ? (float)$_POST['precio_oferta'] : null;
    $stock             = (int)($_POST['stock'] ?? 0);
    $estado            = in_array($_POST['estado'] ?? '', ['activo', 'inactivo', 'agotado'])
                            ? $_POST['estado'] : 'activo';

    $imagenNombre = $productoActual['imagen_principal'];
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $subido = uploadImage($_FILES['imagen'], __DIR__ . '/../../assets/uploads/productos/');
        if ($subido !== false) {
            $imagenNombre = $subido;
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE productos SET
            categoria_id = :categoria_id, nombre = :nombre,
            descripcion_corta = :descripcion_corta, descripcion_larga = :descripcion_larga,
            precio = :precio, precio_oferta = :precio_oferta, stock = :stock,
            imagen_principal = :imagen_principal, estado = :estado
         WHERE id = :id"
    );
    $stmt->execute([
        'categoria_id'      => $categoria_id,
        'nombre'            => $nombre,
        'descripcion_corta' => $descripcion_corta,
        'descripcion_larga' => $descripcion_larga,
        'precio'            => $precio,
        'precio_oferta'     => $precio_oferta,
        'stock'             => $stock,
        'imagen_principal'  => $imagenNombre,
        'estado'            => $estado,
        'id'                => $id,
    ]);

    logAuditoria($admin['id'], "Editó producto ID: $id (desde app móvil)", 'productos');

    responderApi(true, ['mensaje' => 'Producto actualizado correctamente.']);
}

// ────────────────────────────────────────────────────────────
// ELIMINAR
// ────────────────────────────────────────────────────────────
if ($accion === 'eliminar' && $metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);
    $id = (int)($datos['id'] ?? 0);

    if ($id <= 0) {
        responderApi(false, ['error' => 'ID de producto inválido.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = :id");
    $stmt->execute(['id' => $id]);

    logAuditoria($admin['id'], "Eliminó producto ID: $id (desde app móvil)", 'productos');

    responderApi(true, ['mensaje' => 'Producto eliminado correctamente.']);
}

// Si ninguna acción coincidió
responderApi(false, ['error' => 'Acción no reconocida.'], 400);
