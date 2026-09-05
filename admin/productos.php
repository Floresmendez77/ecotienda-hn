<?php
/**
 * 🌱 ECOTIENDA HN - ADMINISTRACIÓN DE PRODUCTOS
 * Ruta: /admin/productos.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Administración de Productos";
$pageSubtitle = "🌱 Registra, edita precios y sube imágenes de tus productos.";
$error     = '';
$success   = '';

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "La solicitud no es válida. Por favor, recarga la página e intenta de nuevo.";
    } else {
        $action            = $_POST['action'];
        $nombre            = filter_input(INPUT_POST, 'nombre',            FILTER_DEFAULT);
        $categoria_id      = (int)($_POST['categoria_id'] ?? 0);
        $descripcion_corta = filter_input(INPUT_POST, 'descripcion_corta', FILTER_DEFAULT);
        $descripcion_larga = filter_input(INPUT_POST, 'descripcion_larga', FILTER_DEFAULT);
        $precio            = (float)($_POST['precio']       ?? 0);
        $precio_oferta     = !empty($_POST['precio_oferta']) ? (float)$_POST['precio_oferta'] : null;
        $stock             = (int)($_POST['stock']           ?? 0);
        $peso              = !empty($_POST['peso'])          ? (float)$_POST['peso']          : null;
        $codigo_barras     = filter_input(INPUT_POST, 'codigo_barras',     FILTER_DEFAULT);
        $estado            = $_POST['estado'] ?? 'activo';

        $imagen_principal = $_POST['imagen_actual'] ?? '';
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadImage($_FILES['imagen'], __DIR__ . '/../assets/uploads/productos/');
            if ($uploaded) {
                $imagen_principal = 'assets/uploads/productos/' . $uploaded;
            } else {
                $error = "La imagen debe ser JPG, PNG o WEBP y pesar menos de 3 MB.";
            }
        }

        if (empty($error)) {
            if (empty($nombre) || $categoria_id <= 0 || $precio <= 0) {
                $error = "Nombre, Categoría y Precio son obligatorios.";
            } else {
                try {
                    $slug = generateSlug($nombre);
                    if ($action === 'create') {
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
                        $new_id = $db->lastInsertId();
                        if ($stock > 0) {
                            $db->prepare("INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion)
                                          VALUES (:pid,'entrada',:qty,'Stock inicial')")
                               ->execute([':pid' => $new_id, ':qty' => $stock]);
                        }
                        logAuditoria($_SESSION['user_id'], "Creó producto ID: $new_id", "productos");
                        $success = "Producto '$nombre' creado correctamente.";
                    } elseif ($action === 'edit') {
                        $edit_id = (int)$_POST['id'];
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
                            ':id'                => $edit_id,
                        ]);
                        $db->prepare("INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion)
                                      VALUES (:pid,'entrada',:qty,'Ajuste manual de stock')")
                           ->execute([':pid' => $edit_id, ':qty' => $stock]);
                        logAuditoria($_SESSION['user_id'], "Editó producto ID: $edit_id", "productos");
                        $success = "Producto '$nombre' actualizado correctamente.";
                    }
                } catch (Exception $e) {
                    $error = "Error al guardar el producto. Intenta de nuevo.";
                }
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $db->prepare("UPDATE productos SET estado='inactivo' WHERE id=:id")->execute([':id' => $delId]);
        logAuditoria($_SESSION['user_id'], "Desactivó producto ID: $delId", "productos");
        $success = "Producto desactivado correctamente.";
    } catch (Exception $e) {
        $error = "Error al desactivar: " . $e->getMessage();
    }
}

try {
    $productsList = $db->query("SELECT p.*, c.nombre AS categoria_nombre
                                FROM productos p
                                LEFT JOIN categorias c ON p.categoria_id = c.id
                                WHERE p.estado != 'inactivo'
                                ORDER BY p.fecha_creacion DESC")->fetchAll();
    $categoriesList = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {
    $productsList   = [];
    $categoriesList = [];
}
?>
<?php require_once __DIR__ . '/includes/admin_navbar.php'; ?>

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-eco-primary btn-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createProductModal">
            <i class="fas fa-plus"></i> Nuevo Producto
        </button>
    </div>
    

    <?php if (!empty($error)):   echo renderAlert($error,   'danger');  endif; ?>
    <?php if (!empty($success)): echo renderAlert($success, 'success'); endif; ?>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th style="text-align:right">Precio</th>
                        <th style="text-align:right">Oferta</th>
                        <th style="text-align:center">Stock</th>
                        <th style="text-align:center">Estado</th>
                        <th style="text-align:center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($productsList as $prod): ?>
                    <tr>
                        <td>
                            <?php if (!empty($prod['imagen_principal'])): ?>
                                <img src="<?php echo BASE_URL . ltrim($prod['imagen_principal'], '/'); ?>"
                                     class="img-thumb" alt="<?php echo sanitize($prod['nombre']); ?>">
                            <?php else: ?>
                                <div class="img-placeholder"><i class="fas fa-leaf"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="nombre-producto"><?php echo sanitize($prod['nombre']); ?></span></td>
                        <td><span style="background:rgba(100,116,139,.3);color:#cbd5e1;padding:.3em .7em;border-radius:6px;font-size:.78rem;font-weight:500;"><?php echo sanitize($prod['categoria_nombre'] ?? '-'); ?></span></td>
                        <td style="text-align:right;color:#f1f5f9;"><?php echo formatCurrency($prod['precio']); ?></td>
                        <td style="text-align:right;color:#f87171;">
                            <?php echo !empty($prod['precio_oferta']) ? formatCurrency($prod['precio_oferta']) : '<span style="color:#475569">—</span>'; ?>
                        </td>
                        <td style="text-align:center;">
                            <span style="background:<?php echo $prod['stock'] <= 3 ? 'rgba(239,68,68,.2)' : 'rgba(16,185,129,.2)'; ?>;color:<?php echo $prod['stock'] <= 3 ? '#f87171' : '#10b981'; ?>;padding:.3em .75em;border-radius:999px;font-size:.8rem;font-weight:700;">
                                <?php echo $prod['stock']; ?> uds
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($prod['estado'] === 'activo'): ?>
                                <span style="background:rgba(16,185,129,.15);color:#10b981;padding:.3em .75em;border-radius:999px;font-size:.78rem;font-weight:600;">Activo</span>
                            <?php elseif ($prod['estado'] === 'agotado'): ?>
                                <span style="background:rgba(251,191,36,.15);color:#fbbf24;padding:.3em .75em;border-radius:999px;font-size:.78rem;font-weight:600;">Agotado</span>
                            <?php else: ?>
                                <span style="background:rgba(100,116,139,.2);color:#94a3b8;padding:.3em .75em;border-radius:999px;font-size:.78rem;font-weight:600;"><?php echo sanitize($prod['estado']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex;gap:.5rem;justify-content:center;">
                                <button class="btn btn-sm btn-outline-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?php echo $prod['id']; ?>">
                                    <i class="fas fa-pen me-1"></i>Editar
                                </button>
                                <a href="<?php echo BASE_URL; ?>admin/productos.php?delete=<?php echo $prod['id']; ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('¿Desactivar este producto?')">
                                    <i class="fas fa-ban me-1"></i>Desactivar
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($productsList)): ?>
                <div class="text-center py-5 text-secondary">
                    <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i>
                    <p>No hay productos registrados aún.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODALES EDITAR (fuera de la tabla a propósito: un <form> dentro de un <tbody>
         es HTML inválido y el navegador lo cierra vacío al parsearlo) -->
    <?php foreach ($productsList as $prod): ?>
    <div class="modal fade" id="editModal<?php echo $prod['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-pen text-success me-2"></i>Editar: <?php echo sanitize($prod['nombre']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?php echo BASE_URL; ?>admin/productos.php" method="POST" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action"        value="edit">
                    <input type="hidden" name="id"            value="<?php echo $prod['id']; ?>">
                    <input type="hidden" name="imagen_actual" value="<?php echo sanitize($prod['imagen_principal'] ?? ''); ?>">
                    <div class="modal-body">
                        <?php if (!empty($prod['imagen_principal'])): ?>
                        <div class="text-center mb-3">
                            <img src="<?php echo BASE_URL . ltrim($prod['imagen_principal'], '/'); ?>"
                                 style="max-height:120px;border-radius:10px;object-fit:cover;" alt="Imagen actual">
                            <p class="text-muted small mt-1">Imagen actual — sube una nueva para reemplazarla</p>
                        </div>
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold">Nombre *</label>
                                <input type="text" name="nombre" class="form-control" value="<?php echo sanitize($prod['nombre']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Categoría *</label>
                                <select name="categoria_id" class="form-select" required>
                                    <?php foreach ($categoriesList as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo (int)($prod['categoria_id'] ?? 0) === (int)$cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize($cat['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Precio Base (L.) *</label>
                                <input type="number" step="0.01" name="precio" class="form-control" value="<?php echo $prod['precio']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Precio Oferta (opcional)</label>
                                <input type="number" step="0.01" name="precio_oferta" class="form-control" value="<?php echo $prod['precio_oferta'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Stock *</label>
                                <input type="number" name="stock" class="form-control" value="<?php echo $prod['stock']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="activo"   <?php echo $prod['estado']==='activo'   ? 'selected':''; ?>>Activo</option>
                                    <option value="agotado"  <?php echo $prod['estado']==='agotado'  ? 'selected':''; ?>>Agotado</option>
                                    <option value="inactivo" <?php echo $prod['estado']==='inactivo' ? 'selected':''; ?>>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Descripción Corta *</label>
                                <input type="text" name="descripcion_corta" class="form-control" value="<?php echo sanitize($prod['descripcion_corta'] ?? ''); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Descripción Larga</label>
                                <textarea name="descripcion_larga" class="form-control" rows="3"><?php echo sanitize($prod['descripcion_larga'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Peso (kg)</label>
                                <input type="number" step="0.01" name="peso" class="form-control" value="<?php echo $prod['peso'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Código de Barras</label>
                                <input type="text" name="codigo_barras" class="form-control" value="<?php echo sanitize($prod['codigo_barras'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">
                                    <i class="fas fa-image text-success me-1"></i>Nueva Imagen (JPG, PNG, WEBP — máx 3MB)
                                </label>
                                <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                <div class="form-text">Deja vacío para conservar la imagen actual.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-eco-primary btn-sm">
                            <i class="fas fa-save me-1"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

<!-- MODAL CREAR PRODUCTO -->
<div class="modal fade" id="createProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-plus text-success me-2"></i>Nuevo Ecoproducto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>admin/productos.php" method="POST" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nombre del Producto *</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Jabón de Coco Natural" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Categoría *</label>
                            <select name="categoria_id" class="form-select" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($categoriesList as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Precio Base (L.) *</label>
                            <input type="number" step="0.01" name="precio" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Precio Oferta (opcional)</label>
                            <input type="number" step="0.01" name="precio_oferta" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Stock inicial *</label>
                            <input type="number" name="stock" class="form-control" placeholder="10" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="activo" selected>Activo</option>
                                <option value="agotado">Agotado</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Descripción Corta *</label>
                            <input type="text" name="descripcion_corta" class="form-control" placeholder="Breve descripción del producto..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Descripción Larga</label>
                            <textarea name="descripcion_larga" class="form-control" rows="3" placeholder="Detalles, beneficios ecológicos, modo de uso..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Peso (kg)</label>
                            <input type="number" step="0.01" name="peso" class="form-control" placeholder="0.10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Código de Barras</label>
                            <input type="text" name="codigo_barras" class="form-control" placeholder="EAN-13">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">
                                <i class="fas fa-image text-success me-1"></i>Imagen del Producto (JPG, PNG, WEBP — máx 3MB)
                            </label>
                            <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <div id="imgPreviewBox" class="mt-2 text-center d-none">
                                <img id="imgPreview" src="" style="max-height:100px;border-radius:8px;" alt="Preview">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-eco-primary btn-sm">
                        <i class="fas fa-save me-1"></i>Guardar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelector('input[name="imagen"]')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const allowed = ['image/jpeg','image/png','image/webp'];
    if (!allowed.includes(file.type)) { alert('Solo se permiten imágenes JPG, PNG o WEBP.'); this.value = ''; return; }
    if (file.size > 3 * 1024 * 1024) { alert('La imagen no debe superar 3MB.'); this.value = ''; return; }
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('imgPreview').src = e.target.result;
        document.getElementById('imgPreviewBox').classList.remove('d-none');
    };
    reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
