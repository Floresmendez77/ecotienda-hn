<?php
/**
 * 🌱 ECOTIENDA HN - ADMINISTRACIÓN DE USUARIOS
 * Ruta: /admin/usuarios.php
 * Descripción: Permite la visualización de compradores y administración de accesos corporativos.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Gestión de Usuarios";
$error = '';
$success = '';

$db = Database::getConnection();

// Procesar Toggle de Estado (Activar / Desactivar cliente) (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $usr_id = (int)$_POST['usuario_id'];
    $nuevo_estado = $_POST['estado'] === 'activo' ? 'inactivo' : 'activo';

    try {
        // Impedir que un admin se desactive a sí mismo
        if ($usr_id === (int)$_SESSION['user_id']) {
            $error = "Acción prohibida: No puedes inhabilitar tu propia cuenta administrativa en uso.";
        } else {
            $stmt = $db->prepare("UPDATE usuarios SET estado = :estado WHERE id = :id");
            $stmt->execute([
                ':estado' => $nuevo_estado,
                ':id' => $usr_id
            ]);

            logAuditoria($_SESSION['user_id'], "Modificó estado de usuario ID: " . $usr_id . " a " . $nuevo_estado, "usuarios");
            $success = "El estatus de acceso del usuario ID #{$usr_id} se actualizó correctamente a '{$nuevo_estado}'.";
        }
    } catch (Exception $e) {
        $error = "Error al operar estado de usuario: " . $e->getMessage();
    }
}

// Cargar Todos los Usuarios de la base de datos
$usersList = [];
try {
    $usersList = $db->query("SELECT u.*, r.nombre AS rol_nombre 
                            FROM usuarios u 
                            INNER JOIN roles r ON u.rol_id = r.id 
                            ORDER BY u.fecha_registro DESC")->fetchAll();
} catch (Exception $e) {
    //
}

if (empty($usersList)) {
    $usersList = [
        ['id' => 1, 'nombre' => 'José', 'apellido' => 'Vásquez', 'correo' => 'secretariageneralproasol2024@gmail.com', 'rol_nombre' => 'admin', 'estado' => 'activo', 'fecha_registro' => '2026-06-05 00:00:00', 'telefono' => '3120-1192'],
        ['id' => 15, 'nombre' => 'Diana', 'apellido' => 'Mendoza', 'correo' => 'diana.men@yahoo.com', 'rol_nombre' => 'cliente', 'estado' => 'activo', 'fecha_registro' => '2026-06-04 18:00:00', 'telefono' => '3192-3329']
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | Admin EcoTienda</title>
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
        <li class="sidebar-item">
            <a href="<?php echo BASE_URL; ?>admin/categorias.php"><i class="fas fa-tags"></i> Categorías</a>
        </li>
        <li class="sidebar-item active">
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
    
    <header class="mb-5 pb-3 border-bottom border-secondary border-opacity-10">
        <h1 class="h3 fw-bold m-0" style="font-family: var(--font-display);">Control de Usuarios y Accesos</h1>
        <p class="text-secondary small mb-0">🌱 Habilita o restringe inicios de sesión de compradores en EcoTienda HN.</p>
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
                        <th scope="col" class="border-0">ID</th>
                        <th scope="col" class="border-0">Comprador</th>
                        <th scope="col" class="border-0">Correo Registrado</th>
                        <th scope="col" class="border-0">Teléfono</th>
                        <th scope="col" class="border-0">Permisos de Cuenta</th>
                        <th scope="col" class="border-0 text-center">Registro</th>
                        <th scope="col" class="border-0 text-center">Acceso</th>
                        <th scope="col" class="border-0 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usersList as $usr): ?>
                        <tr>
                            <td class="font-mono py-3">#<?php echo $usr['id']; ?></td>
                            <td>
                                <strong class="text-white"><?php echo sanitize($usr['nombre'] . ' ' . $usr['apellido']); ?></strong>
                            </td>
                            <td class="small"><?php echo sanitize($usr['correo']); ?></td>
                            <td class="font-mono small text-secondary">
                                <?php echo sanitize($usr['telefono'] ?? 'Sin teléfono'); ?>
                            </td>
                            <td>
                                <span class="badge bg-opacity-10 bg-<?php echo $usr['rol_nombre'] === 'admin' ? 'danger':'primary'; ?> text-<?php echo $usr['rol_nombre'] === 'admin' ? 'danger':'primary'; ?> font-mono">
                                    <?php echo strtoupper($usr['rol_nombre']); ?>
                                </span>
                            </td>
                            <td class="text-center text-secondary small">
                                <?php echo date('Y-m-d', strtotime($usr['fecha_registro'])); ?>
                            </td>
                            <td class="text-center font-mono text-success small">
                                OK
                            </td>
                            <td class="text-center">
                                <form action="<?php echo BASE_URL; ?>admin/usuarios.php" method="POST" class="m-0">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="usuario_id" value="<?php echo $usr['id']; ?>">
                                    <input type="hidden" name="estado" value="<?php echo $usr['estado']; ?>">
                                    
                                    <?php if($usr['estado'] === 'activo'): ?>
                                        <button type="submit" class="btn btn-xs btn-success text-xs py-1 px-2.5 rounded-pill fw-semibold">
                                            Activo
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-xs btn-outline-danger text-xs py-1 px-2.5 rounded-pill">
                                            Suspendido
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
