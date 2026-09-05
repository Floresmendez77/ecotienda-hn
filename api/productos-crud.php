<?php
/**
 * 🌱 ECOTIENDA HN - CRUD DE PRODUCTOS VÍA API (APP MÓVIL)
 * Ruta: /api/productos-crud.php
 * Descripción: Endpoint protegido (requiere token de admin) para que la app
 *              NativeScript cree, edite o elimine (soft-delete) productos.
 *              Reutiliza los mismos patrones que admin/productos.php:
 *              uploadImage(), generateSlug(), logAuditoria(), ecotienda_mail().
 *              (Fase 2 del plan EcoTienda HN)
 *
 * Petición esperada (POST, multipart/form-data, header Authorization: Bearer <token>):
 *   accion=crear|editar|eliminar
 *
 *   Para crear/editar:
 *     nombre, categoria_id, precio          (obligatorios)
 *     descripcion_corta, descripcion_larga, precio_oferta, stock, peso,
 *     codigo_barras, estado                 (opcionales)
 *     imagen                                (archivo, opcional)
 *     id                                    (obligatorio solo en editar/eliminar)
 *
 * Respuesta exitosa:
 *   { "success": true, "mensaje": "...", "producto": { ... } }
 */

require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

// Los correos promocionales a varios clientes pueden tardar; damos margen
// extra sin dejarlo indefinido.
set_time_limit(120);

function responderError(int $httpCode, string $mensaje): void
{
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError(405, 'Método no permitido. Usa POST.');
}

// Requiere token válido Y rol de administrador
$admin = requireApiAdmin();

$accion = $_POST['accion'] ?? '';

if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    responderError(400, 'Acción inválida. Usa: crear, editar o eliminar.');
}

$db = Database::getConnection();

/**
 * Envía el correo de confirmación al admin que hizo la acción.
 * Nunca lanza excepción hacia afuera: un fallo de correo no debe
 * tumbar la respuesta de la API.
 */
function notificarAdmin(array $admin, string $accion, string $nombreProducto): void
{
    try {
        $verbo = [
            'crear'    => 'publicado',
            'editar'   => 'actualizado',
            'eliminar' => 'desactivado',
        ][$accion];

        $body_html = <<<HTML
<h2 style="color:#1a5c2a;font-size:1.3rem;margin:0 0 8px;">🌱 Producto {$verbo}</h2>
<p style="color:#555;font-size:.9rem;margin:0 0 20px;">
    Hola <b>{$admin['nombre']}</b>, confirmamos que el producto <b>{$nombreProducto}</b>
    fue {$verbo} correctamente desde la app de EcoTienda HN.
</p>
HTML;

        $html  = ecotienda_email_template('Confirmación de producto', $body_html);
        $plain = "Hola {$admin['nombre']}, el producto \"{$nombreProducto}\" fue {$verbo} correctamente.";

        ecotienda_mail(
            $admin['correo'],
            $admin['nombre'] . ' ' . $admin['apellido'],
            "✅ Producto {$verbo} — EcoTienda HN",
            $html,
            $plain
        );
    } catch (\Throwable $e) {
        logError('ERROR', 'notificarAdmin: fallo al enviar correo de confirmación', [
            'file' => $e->getFile(), 'line' => $e->getLine(),
        ]);
    }
}

/**
 * Envía el correo promocional a todos los clientes activos anunciando
 * el nuevo producto. Cada envío va en su propio try/catch: si uno falla
 * (correo inválido, límite de Gmail, etc.) no detiene a los demás.
 */
function notificarClientesNuevoProducto(PDO $db, array $producto): void
{
    $stmt = $db->query(
        "SELECT id, nombre, apellido, correo
         FROM usuarios
         WHERE rol_id = 2 AND estado = 'activo'"
    );
    $clientes = $stmt->fetchAll();

    if (empty($clientes)) {
        return;
    }

    $precioMostrar = $producto['precio_oferta']
        ? number_format($producto['precio_oferta'], 2, '.', ',') . ' <span style="text-decoration:line-through;color:#999;font-size:.8rem;">L. ' . number_format($producto['precio'], 2, '.', ',') . '</span>'
        : number_format($producto['precio'], 2, '.', ',');

    $imagenUrl = $producto['imagen_principal']
        ? BASE_URL . ltrim($producto['imagen_principal'], '/')
        : 'https://placehold.co/500x500/10b981/white?text=🌿';

    $productoUrl = BASE_URL . 'producto.php?id=' . $producto['id'];

    foreach ($clientes as $cliente) {
        try {
            $body_html = <<<HTML
<h2 style="color:#1a5c2a;font-size:1.3rem;margin:0 0 8px;">🌿 ¡Nuevo producto en EcoTienda HN!</h2>
<p style="color:#555;font-size:.9rem;margin:0 0 20px;">
    Hola <b>{$cliente['nombre']}</b>, acabamos de agregar algo nuevo a nuestro catálogo ecológico.
</p>

<div style="background:#f0faf2;border:1px solid #b2dfb8;border-radius:10px;padding:16px;margin-bottom:20px;text-align:center;">
    <img src="{$imagenUrl}" alt="{$producto['nombre']}" style="max-width:220px;border-radius:8px;margin-bottom:12px;">
    <p style="margin:0 0 6px;font-size:1.05rem;color:#1a5c2a;font-weight:700;">{$producto['nombre']}</p>
    <p style="margin:0;font-size:1rem;color:#2d8a45;font-weight:700;">L. {$precioMostrar}</p>
</div>

<p style="text-align:center;margin:28px 0;">
    <a href="{$productoUrl}"
       style="background:linear-gradient(135deg,#1a5c2a,#2d8a45);color:#fff;text-decoration:none;
              padding:14px 36px;border-radius:10px;font-weight:700;font-size:.95rem;display:inline-block;letter-spacing:.5px;">
        🛒 VER PRODUCTO
    </a>
</p>
HTML;

            $html  = ecotienda_email_template('Nuevo producto disponible', $body_html);
            $plain = "Nuevo producto en EcoTienda HN: {$producto['nombre']} — L. {$producto['precio']}. Míralo aquí: {$productoUrl}";

            ecotienda_mail(
                $cliente['correo'],
                $cliente['nombre'] . ' ' . $cliente['apellido'],
                "🌿 Nuevo en EcoTienda HN: {$producto['nombre']}",
                $html,
                $plain
            );
        } catch (\Throwable $e) {
            logError('ERROR', 'notificarClientesNuevoProducto: fallo al enviar a un cliente', [
                'cliente_id' => $cliente['id'],
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);
            // Seguimos con el siguiente cliente, no interrumpimos el envío masivo.
        }
    }
}

// ── ELIMINAR (soft-delete) ──────────────────────────────────────────────────
if ($accion === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        responderError(400, 'Falta el id del producto a eliminar.');
    }

    $check = $db->prepare("SELECT id, nombre FROM productos WHERE id = :id LIMIT 1");
    $check->execute(['id' => $id]);
    $producto = $check->fetch();

    if (!$producto) {
        responderError(404, 'Producto no encontrado.');
    }

    $db->prepare("UPDATE productos SET estado = 'inactivo' WHERE id = :id")
       ->execute(['id' => $id]);

    logAuditoria($admin['id'], "Desactivó producto ID: {$id} (vía API)", "productos");
    notificarAdmin($admin, 'eliminar', $producto['nombre']);

    echo json_encode([
        'success' => true,
        'mensaje' => "Producto '{$producto['nombre']}' desactivado correctamente.",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CREAR / EDITAR ───────────────────────────────────────────────────────────
$nombre            = trim($_POST['nombre'] ?? '');
$categoria_id      = (int)($_POST['categoria_id'] ?? 0);
$descripcion_corta = trim($_POST['descripcion_corta'] ?? '');
$descripcion_larga = trim($_POST['descripcion_larga'] ?? '');
$precio            = (float)($_POST['precio'] ?? 0);
$precio_oferta     = !empty($_POST['precio_oferta']) ? (float)$_POST['precio_oferta'] : null;
$stock             = (int)($_POST['stock'] ?? 0);
$peso              = !empty($_POST['peso']) ? (float)$_POST['peso'] : null;
$codigo_barras     = trim($_POST['codigo_barras'] ?? '');
$estado            = $_POST['estado'] ?? 'activo';

if ($nombre === '' || $categoria_id <= 0 || $precio <= 0) {
    responderError(400, 'Nombre, categoria_id y precio son obligatorios.');
}

if (!in_array($estado, ['activo', 'inactivo', 'agotado'], true)) {
    responderError(400, 'Estado inválido.');
}

// Verificar que la categoría exista, para no insertar una FK inválida
$catCheck = $db->prepare("SELECT id FROM categorias WHERE id = :id LIMIT 1");
$catCheck->execute(['id' => $categoria_id]);
if (!$catCheck->fetch()) {
    responderError(400, 'La categoría indicada no existe.');
}

// Imagen: si mandan una nueva, se sube; si no, se conserva la actual (en edición)
$imagen_principal = trim($_POST['imagen_actual'] ?? '');
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $subida = uploadImage($_FILES['imagen'], __DIR__ . '/../assets/uploads/productos/');
    if ($subida) {
        $imagen_principal = 'assets/uploads/productos/' . $subida;
    } else {
        responderError(400, 'La imagen debe ser JPG, PNG, WEBP o GIF y pesar menos de 3 MB.');
    }
}

$slug = generateSlug($nombre);

try {
    if ($accion === 'crear') {
        $stmt = $db->prepare("INSERT INTO productos
            (categoria_id, nombre, slug, descripcion_corta, descripcion_larga,
             precio, precio_oferta, stock, peso, codigo_barras, imagen_principal, estado)
            VALUES
            (:categoria_id,:nombre,:slug,:descripcion_corta,:descripcion_larga,
             :precio,:precio_oferta,:stock,:peso,:codigo_barras,:imagen_principal,:estado)");
        $stmt->execute([
            ':categoria_id'      => $categoria_id,
            ':nombre'            => $nombre,
            ':slug'              => $slug,
            ':descripcion_corta' => $descripcion_corta,
            ':descripcion_larga' => $descripcion_larga,
            ':precio'            => $precio,
            ':precio_oferta'     => $precio_oferta,
            ':stock'             => $stock,
            ':peso'              => $peso,
            ':codigo_barras'     => $codigo_barras,
            ':imagen_principal'  => $imagen_principal,
            ':estado'            => $estado,
        ]);
        $id = (int)$db->lastInsertId();

        if ($stock > 0) {
            $db->prepare("INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion)
                          VALUES (:pid,'entrada',:qty,'Stock inicial (vía API)')")
               ->execute([':pid' => $id, ':qty' => $stock]);
        }

        logAuditoria($admin['id'], "Creó producto ID: {$id} (vía API)", "productos");

    } else { // editar
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            responderError(400, 'Falta el id del producto a editar.');
        }

        $existe = $db->prepare("SELECT id FROM productos WHERE id = :id LIMIT 1");
        $existe->execute(['id' => $id]);
        if (!$existe->fetch()) {
            responderError(404, 'Producto no encontrado.');
        }

        $stmt = $db->prepare("UPDATE productos SET
            categoria_id=:categoria_id, nombre=:nombre, slug=:slug,
            descripcion_corta=:descripcion_corta, descripcion_larga=:descripcion_larga,
            precio=:precio, precio_oferta=:precio_oferta, stock=:stock,
            peso=:peso, codigo_barras=:codigo_barras,
            imagen_principal=:imagen_principal, estado=:estado
            WHERE id=:id");
        $stmt->execute([
            ':categoria_id'      => $categoria_id,
            ':nombre'            => $nombre,
            ':slug'              => $slug,
            ':descripcion_corta' => $descripcion_corta,
            ':descripcion_larga' => $descripcion_larga,
            ':precio'            => $precio,
            ':precio_oferta'     => $precio_oferta,
            ':stock'             => $stock,
            ':peso'              => $peso,
            ':codigo_barras'     => $codigo_barras,
            ':imagen_principal'  => $imagen_principal,
            ':estado'            => $estado,
            ':id'                => $id,
        ]);

        $db->prepare("INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion)
                      VALUES (:pid,'entrada',:qty,'Ajuste manual de stock (vía API)')")
           ->execute([':pid' => $id, ':qty' => $stock]);

        logAuditoria($admin['id'], "Editó producto ID: {$id} (vía API)", "productos");
    }
} catch (Exception $e) {
    logError('ERROR', 'productos-crud: fallo al guardar producto', [
        'file' => $e->getFile(), 'line' => $e->getLine(),
    ]);
    responderError(500, 'Error interno al guardar el producto.');
}

// Releer el producto ya guardado, con el nombre de categoría, para la respuesta y el correo
$final = $db->prepare(
    "SELECT p.*, c.nombre AS categoria_nombre
     FROM productos p
     LEFT JOIN categorias c ON p.categoria_id = c.id
     WHERE p.id = :id LIMIT 1"
);
$final->execute(['id' => $id]);
$producto = $final->fetch();

// Correo de confirmación al admin (crear y editar)
notificarAdmin($admin, $accion, $producto['nombre']);

// Correo promocional a clientes SOLO al crear (no en cada edición)
if ($accion === 'crear') {
    notificarClientesNuevoProducto($db, $producto);
}

echo json_encode([
    'success'  => true,
    'mensaje'  => $accion === 'crear'
        ? "Producto '{$producto['nombre']}' creado y notificado correctamente."
        : "Producto '{$producto['nombre']}' actualizado correctamente.",
    'producto' => [
        'id'                => (int)$producto['id'],
        'nombre'            => $producto['nombre'],
        'slug'              => $producto['slug'],
        'categoria_id'      => (int)$producto['categoria_id'],
        'categoria_nombre'  => $producto['categoria_nombre'],
        'descripcion_corta' => $producto['descripcion_corta'],
        'descripcion_larga' => $producto['descripcion_larga'],
        'precio'            => (float)$producto['precio'],
        'precio_oferta'     => $producto['precio_oferta'] ? (float)$producto['precio_oferta'] : null,
        'stock'             => (int)$producto['stock'],
        'peso'              => $producto['peso'] !== null ? (float)$producto['peso'] : null,
        'codigo_barras'     => $producto['codigo_barras'],
        'imagen_principal'  => $producto['imagen_principal'] ? BASE_URL . ltrim($producto['imagen_principal'], '/') : null,
        'estado'            => $producto['estado'],
        'actualizado_en'    => $producto['actualizado_en'],
    ],
], JSON_UNESCAPED_UNICODE);