<?php
/**
 * 🌱 ECOTIENDA HN - CONFIGURACIÓN DEL SISTEMA
 * Ruta: /admin/configuracion.php
 * Descripción: Permite gestionar claves del sistema ecológico como teléfono de contacto, costo de envío, nombre corporativo o correos oficiales.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Configuración Planetaria";
$error = '';
$success = '';

$db = Database::getConnection();

// Procesar Actualización de Configuración (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $nombre_sitio = filter_input(INPUT_POST, 'nombre_sitio', FILTER_DEFAULT);
    $telefono_contacto = filter_input(INPUT_POST, 'telefono_contacto', FILTER_DEFAULT);
    $correo_notificaciones = filter_input(INPUT_POST, 'correo_notificaciones', FILTER_SANITIZE_EMAIL);
    $costo_envio = (float)($_POST['costo_envio'] ?? 150.00);

    try {
        $db->beginTransaction();

        // Guardar valores en la tabla `configuracion` (llave, valor) o equivalentemente
        // Dado que la base de datos puede tener una clave primaria, creamos una transacción robusta de un solo paso
        $params = [
            'nombre_sitio' => $nombre_sitio,
            'telefono_contacto' => $telefono_contacto,
            'correo_notificaciones' => $correo_notificaciones,
            'costo_envio' => $costo_envio
        ];

        $updateStmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor) 
                                    ON DUPLICATE KEY UPDATE valor = :valor");
        
        foreach ($params as $clave => $valor) {
            $updateStmt->execute([
                ':clave' => $clave,
                ':valor' => (string)$valor
            ]);
        }

        logAuditoria($_SESSION['user_id'], "Actualizó configuración global del sitio", "configuracion");
        $db->commit();

        $success = "¡Fabuloso! Todos los parámetros globales de la EcoTienda HN se han actualizado correctamente.";
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Error al guardar parámetros globales: " . $e->getMessage();
    }
}

// Cargar variables existentes
$settings = [
    'nombre_sitio' => '🌱 EcoTienda HN',
    'telefono_contacto' => '+504 3192-3329',
    'correo_notificaciones' => 'soporte@ecotiendahn.com',
    'costo_envio' => 150.00
];

try {
    $rows = $db->query("SELECT clave, valor FROM configuracion")->fetchAll();
    foreach ($rows as $row) {
        if (array_key_exists($row['clave'], $settings)) {
            $settings[$row['clave']] = $row['valor'];
        }
    }
} catch (Exception $e) {
    //
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración | Admin EcoTienda</title>
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
        <li class="sidebar-item active">
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
<div class="main-content text-start">
    
    <header class="mb-5 pb-3 border-bottom border-secondary border-opacity-10">
        <h1 class="h3 fw-bold m-0" style="font-family: var(--font-display);">Parámetros Globales</h1>
        <p class="text-secondary small mb-0 font-sans">🌱 Adapta variables clave de comercio, costo logístico y canales informativos.</p>
    </header>

    <?php if(!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <?php echo renderAlert($success, 'success'); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="admin-card">
                <form action="<?php echo BASE_URL; ?>admin/configuracion.php" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="row g-4 text-start">
                        <!-- Nombre del Sitio -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nombre de la Ecoplataforma *</label>
                            <input type="text" name="nombre_sitio" class="form-control" value="<?php echo sanitize($settings['nombre_sitio']); ?>" required>
                        </div>

                        <!-- Costo Envío -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Costo Envío Estándar (Honduras Lps.) *</label>
                            <input type="number" step="0.01" name="costo_envio" class="form-control" value="<?php echo $settings['costo_envio']; ?>" required>
                        </div>

                        <!-- Correo oficiales -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Correo de Soporte y Notificaciones *</label>
                            <input type="email" name="correo_notificaciones" class="form-control" value="<?php echo sanitize($settings['correo_notificaciones']); ?>" required>
                        </div>

                        <!-- Teléfonos de ayuda -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Número de Ayuda WhatsApp *</label>
                            <input type="text" name="telefono_contacto" class="form-control" value="<?php echo sanitize($settings['telefono_contacto']); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-sm btn-eco-primary mt-4 px-4 py-2">Guardar Parámetros de EcoTienda</button>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
