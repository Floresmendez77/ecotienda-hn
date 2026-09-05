<?php
/**
 * 🌱 ECOTIENDA HN - CRUD CATEGORÍAS
 * Ruta: /admin/categorias.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Gestión de Categorías";
$pageSubtitle = "🌱 Agrupa ecoproductos de acuerdo a su naturaleza de biodegradabilidad.";
$error = '';
$success = '';

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "La solicitud no es válida. Por favor, recarga la página e intenta de nuevo.";
    } else {
        $action      = $_POST['action'];
        $nombre      = filter_input(INPUT_POST, 'nombre',      FILTER_DEFAULT);
        $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_DEFAULT);

        if (empty($nombre)) {
            $error = "El nombre de la categoría es estrictamente obligatorio.";
        } else {
            try {
                $slug = generateSlug($nombre);
                if ($action === 'create') {
                    $db->prepare("INSERT INTO categorias (nombre, slug, descripcion) VALUES (:nombre, :slug, :descripcion)")
                       ->execute([':nombre' => $nombre, ':slug' => $slug, ':descripcion' => $descripcion]);
                    logAuditoria($_SESSION['user_id'], "Añadió categoría: " . $nombre, "categorias");
                    $success = "La categoría '{$nombre}' ha sido ingresada correctamente.";
                } elseif ($action === 'edit') {
                    $chk_id = (int)$_POST['id'];
                    $db->prepare("UPDATE categorias SET nombre=:nombre, slug=:slug, descripcion=:descripcion WHERE id=:id")
                       ->execute([':nombre' => $nombre, ':slug' => $slug, ':descripcion' => $descripcion, ':id' => $chk_id]);
                    logAuditoria($_SESSION['user_id'], "Modificó categoría ID: " . $chk_id, "categorias");
                    $success = "La categoría '{$nombre}' fue editada exitosamente.";
                }
            } catch (Exception $e) {
                $error = "Error al operar categorías. Intenta de nuevo.";
            }
        }
    }
}

$categoriesList = [];
try {
    $categoriesList = $db->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {}
?>
<?php require_once __DIR__ . '/includes/admin_navbar.php'; ?>

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-eco-primary btn-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
            <i class="fas fa-plus"></i> Añadir Categoría
        </button>
    </div>
    

    <?php if(!empty($error)): echo renderAlert($error, 'danger'); endif; ?>
    <?php if(!empty($success)): echo renderAlert($success, 'success'); endif; ?>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="tabla-cats">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>Categoría</th>
                        <th>Slug Identificador</th>
                        <th>Descripción o Enfoque Sostenible</th>
                        <th style="text-align:center;width:150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($categoriesList as $cat): ?>
                        <tr>
                            <td style="color:#94a3b8;font-family:monospace;">#<?php echo $cat['id']; ?></td>
                            <td><strong style="color:#ffffff;font-weight:600;"><?php echo sanitize($cat['nombre']); ?></strong></td>
                            <td style="color:#10b981;font-family:monospace;font-size:.85rem;">
                                /<?php echo sanitize($cat['slug'] ?? generateSlug($cat['nombre'])); ?>
                            </td>
                            <td style="color:#94a3b8;font-size:.88rem;">
                                <?php echo sanitize($cat['descripcion'] ?? 'Sin descripción añadida.'); ?>
                            </td>
                            <td style="text-align:center;">
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editCatModal<?php echo $cat['id']; ?>">
                                    Modificar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODALES MODIFICAR (fuera de la tabla a propósito: un <form> dentro de un
         <tbody> es HTML inválido y el navegador lo cierra vacío al parsearlo) -->
    <?php foreach($categoriesList as $cat): ?>
    <div class="modal fade" id="editCatModal<?php echo $cat['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Modificar Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?php echo BASE_URL; ?>admin/categorias.php" method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                    <div class="modal-body text-start">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nombre de Categoría *</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo sanitize($cat['nombre']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Descripción / Objetivo Sostenible</label>
                            <textarea name="descripcion" class="form-control" rows="3"><?php echo sanitize($cat['descripcion'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-eco-primary btn-sm">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

<!-- MODAL CREAR -->
<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-folder-plus text-success me-2"></i> Añadir Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>admin/categorias.php" method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre de Categoría *</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Energía solar" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Descripción / Objetivo Sostenible</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Ingresa el tipo de ecoproductos que integrarán esta categoría."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-eco-primary btn-sm">Añadir Categoría</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
