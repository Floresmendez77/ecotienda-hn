<?php
/**
 * 🌱 ECOTIENDA HN - CRUD CATEGORÍAS
 * Ruta: /admin/categorias.php
 * Descripción: Permite gestionar las clasificaciones para los ecoproductos comercializados.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Gestión de Categorías";
$error = '';
$success = '';

$db = Database::getConnection();

// Procesar altas o modificaciones (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = "La solicitud no es válida. Por favor, recarga la página e intenta de nuevo.";
    } else {
    $action = $_POST['action'];
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_DEFAULT);
    $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_DEFAULT);

    if (empty($nombre)) {
        $error = "El nombre de la categoría es estrictamente obligatorio.";
    } else {
        try {
            $slug = generateSlug($nombre);

            if ($action === 'create') {
                $sql = "INSERT INTO categorias (nombre, slug, descripcion) VALUES (:nombre, :slug, :descripcion)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':slug' => $slug,
                    ':descripcion' => $descripcion
                ]);

                logAuditoria($_SESSION['user_id'], "Añadió categoría: " . $nombre, "categorias");
                $success = "La categoría '{$nombre}' ha sido ingresada correctamente.";
            } elseif ($action === 'edit') {
                $chk_id = (int)$_POST['id'];
                $sql = "UPDATE categorias SET nombre = :nombre, slug = :slug, descripcion = :descripcion WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':slug' => $slug,
                    ':descripcion' => $descripcion,
                    ':id' => $chk_id
                ]);

                logAuditoria($_SESSION['user_id'], "Modificó categoría ID: " . $chk_id, "categorias");
                $success = "La categoría '{$nombre}' fue editada exitosamente.";
            }
        } catch (Exception $e) {
            logError('ERROR', 'Error al operar categorías: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $error = "Error al operar categorías. Intenta de nuevo.";
        }
    }
    }
}

// Cargar categorías existentes
$categoriesList = [];
try {
    $categoriesList = $db->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {
    //
}

if (empty($categoriesList)) {
    $categoriesList = [
        ['id' => 1, 'nombre' => 'Cuidado Personal', 'descripcion' => 'Alternativas sólidas e hidratantes biodegradables.'],
        ['id' => 2, 'nombre' => 'Hogar Sustentable', 'descripcion' => 'Cubiertos de bambú, cepillos y bolsas ecológicas.'],
        ['id' => 3, 'nombre' => 'Moda Ética', 'descripcion' => 'Fibras naturales y algodón ecológico reciclado.']
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías | Admin EcoTienda</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --admin-primary: #10b981;
            --admin-secondary: #0f172a;
            --admin-dark-card: #1e293b;
            --admin-border-color: rgba(255, 255, 255, 0.08);
            --font-sans: 'Plus Jakarta Sans', sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
        }

        body {
            font-family: var(--font-sans);
            background-color: #0b0f19;
            color: #f1f5f9;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background-color: var(--admin-secondary);
            border-right: 1px solid var(--admin-border-color);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1020;
        }

        .sidebar-brand {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--admin-primary) !important;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--admin-border-color);
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }

        .sidebar-item a {
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.92rem;
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .sidebar-item a:hover, .sidebar-item.active a {
            color: #fff;
            background-color: rgba(16, 185, 129, 0.08);
            border-left-color: var(--admin-primary);
        }

        .sidebar-item i {
            width: 25px;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .admin-card {
            background-color: var(--admin-dark-card);
            border: 1px solid var(--admin-border-color);
            border-radius: 16px;
            padding: 1.5rem;
        }
        
        .table {
            color: #fff;
            border-color: rgba(255,255,255,0.05);
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="<?php echo BASE_URL; ?>admin/index.php" class="sidebar-brand text-decoration-none">
        <i class="fas fa-leaf text-success me-2"></i>
        <span>EcoTienda <span class="text-success">Admin</span></span>
    </a>
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/index.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/productos.php"><i class="fas fa-box"></i> Productos</a>
        </li>
        <li class="sidebar-item active">
            <a href="<?php echo BASE_URL; ?>admin/categorias.php"><i class="fas fa-tags"></i> Categorías</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/usuarios.php"><i class="fas fa-users"></i> Usuarios</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php"><i class="fas fa-shopping-bag"></i> Pedidos</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/reportes.php"><i class="fas fa-chart-line"></i> Reportes</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/inventario.php"><i class="fas fa-warehouse"></i> Inventario</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/configuracion.php"><i class="fas fa-cog"></i> Configuración</a>
        </li>
        <li class="sidebar-item mt-4">
            <a href="<?php echo BASE_URL; ?>index.php" class="text-success"><i class="fas fa-store"></i> Volver a Comercio</a>
        </li>
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
        </li>
    </ul>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div class="main-content">
    
    <header class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom border-secondary border-opacity-10">
        <div>
            <h1 class="h3 fw-bold m-0" style="font-family: var(--font-display);">Clasificaciones de Catálogo</h1>
            <p class="text-secondary small mb-0">🌱 Agrupa ecoproductos de acuerdo a su naturaleza de biodegradabilidad.</p>
        </div>
        <button class="btn btn-eco-primary btn-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
            <i class="fas fa-plus"></i> Añadir Categoría
        </button>
    </header>

    <?php if(!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <?php echo renderAlert($success, 'success'); ?>
    <?php endif; ?>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="text-secondary small font-mono">
                    <tr>
                        <th scope="col" class="border-0" style="width: 80px;">ID</th>
                        <th scope="col" class="border-0">Categoría</th>
                        <th scope="col" class="border-0">Slug Identificador</th>
                        <th scope="col" class="border-0">Descripción o Enfoque Sostenible</th>
                        <th scope="col" class="border-0 text-center" style="width: 150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($categoriesList as $cat): ?>
                        <tr>
                            <td class="font-mono py-3">#<?php echo $cat['id']; ?></td>
                            <td>
                                <strong class="text-white"><?php echo sanitize($cat['nombre']); ?></strong>
                            </td>
                            <td class="font-mono text-success small">
                                /<?php echo sanitize($cat['slug'] ?? generateSlug($cat['nombre'])); ?>
                            </td>
                            <td class="text-secondary small text-wrap">
                                <?php echo sanitize($cat['descripcion'] ?? 'Sin descripción añadida.'); ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-success font-semibold px-2.5 py-1" data-bs-toggle="modal" data-bs-target="#editCatModal<?php echo $cat['id']; ?>">
                                    Modificar
                                </button>
                            </td>
                        </tr>

                        <!-- MODAL EDITAR CATEGORÍA -->
                        <div class="modal fade text-dark" id="editCatModal<?php echo $cat['id']; ?>" tabindex="-1" aria-labelledby="editCatLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Modificar Categoría</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- MODAL CREACIÓN CATEGORÍA -->
<div class="modal fade text-dark" id="createCategoryModal" tabindex="-1" aria-labelledby="createCat" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-folder-plus text-success me-2"></i> Añadir Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
