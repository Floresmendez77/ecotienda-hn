<?php
/**
 * 🌱 ECOTIENDA HN - CABECERA Y NAVEGACIÓN
 * Ruta: /includes/navbar.php
 * Descripción: Inicializa el documento HTML5, carga CSS de Bootstrap 5, Font Awesome y gestiona la barra de navegación responsive con sesión dinámica.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';

// Obtener cantidad de artículos en el carrito si el usuario está conectado
$cartCount = 0;
if (isLoggedIn()) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT SUM(cantidad) AS total FROM carrito WHERE usuario_id = :usuario_id");
        $stmt->execute([':usuario_id' => $_SESSION['user_id']]);
        $row = $stmt->fetch();
        $cartCount = isset($row['total']) ? (int)$row['total'] : 0;
    } catch (Exception $e) {
        // Silencioso
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Estilos Customizados Premium (Glassmorphism & Eco-Luxury Theme) -->
    <style>
        :root {
            --eco-primary: #10b981;
            --eco-primary-hover: #059669;
            --eco-secondary: #064e3b;
            --eco-bg-dark: #0f172a;
            --eco-card-dark: rgba(30, 41, 59, 0.7);
            --eco-glass-bg: rgba(255, 255, 255, 0.85);
            --eco-glass-border: rgba(255, 255, 255, 0.4);
            --font-sans: 'Plus Jakarta Sans', sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
        }

        body {
            font-family: var(--font-sans);
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Estilos Tema Oscuro Premium */
        body.dark-theme {
            background-color: var(--eco-bg-dark);
            color: #f1f5f9;
        }
        
        body.dark-theme .card {
            background-color: var(--eco-card-dark);
            border-color: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
        }

        body.dark-theme .modal-content {
            background-color: #1e293b;
            color: #f1f5f9;
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-theme .navbar {
            background: rgba(15, 23, 42, 0.85) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        body.dark-theme .text-muted {
            color: #94a3b8 !important;
        }

        body.dark-theme .form-control, 
        body.dark-theme .form-select {
            background-color: #1e293b;
            border-color: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
        }

        body.dark-theme .form-control:focus, 
        body.dark-theme .form-select:focus {
            background-color: #0f172a;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
        }

        body.dark-theme .list-group-item {
            background-color: rgba(30, 41, 59, 0.5);
            border-color: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
        }

        body.dark-theme .table {
            color: #f1f5f9;
            border-color: rgba(255, 255, 255, 0.08);
        }

        body.dark-theme .table th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        /* Navbar Glassmorphism */
        .navbar {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.85);
            border-bottom: 1px solid var(--eco-glass-border);
            z-index: 1030;
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--eco-secondary) !important;
            letter-spacing: -0.5px;
        }

        body.dark-theme .navbar-brand {
            color: var(--eco-primary) !important;
        }

        .nav-link {
            font-weight: 500;
            font-size: 0.95rem;
            color: #475569 !important;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        body.dark-theme .nav-link {
            color: #cbd5e1 !important;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--eco-primary) !important;
            background-color: rgba(16, 185, 129, 0.08);
        }

        /* Botones Premium */
        .btn-eco-primary {
            background-color: var(--eco-primary);
            color: #ffffff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            transition: all 0.2s ease;
        }

        .btn-eco-primary:hover {
            background-color: var(--eco-primary-hover);
            transform: translateY(-1px);
            color: #fff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-eco-secondary {
            background-color: var(--eco-secondary);
            color: #ffffff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            transition: all 0.2s ease;
        }

        .btn-eco-secondary:hover {
            background-color: #043e2f;
            transform: translateY(-1px);
            color: #fff;
            box-shadow: 0 4px 12px rgba(6, 78, 59, 0.3);
        }

        /* Notificación del Carrito */
        .cart-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            font-size: 10px;
            padding: 3px 6px;
            border: 2px solid #fff;
        }

        body.dark-theme .cart-badge {
            border-color: #1e293b;
        }

        /* Estructura Footer */
        footer {
            margin-top: auto;
        }
    </style>
</head>
<body class="dark-theme"> <!-- Por defecto activamos Dark Mode Moderno como solicita el pliego -->

<!-- Barra de navegación principal -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <!-- Logotipo -->
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>index.php">
            <span class="me-2 text-success"><i class="fas fa-leaf"></i></span>
            <span>EcoTienda <span class="text-success">HN</span></span>
        </a>
        
        <!-- Botón Móvil -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Enlaces -->
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>index.php">
                        <i class="fas fa-house me-1"></i> Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tienda.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>tienda.php">
                        <i class="fas fa-store me-1"></i> Tienda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sobre_nosotros.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>sobre_nosotros.php">
                        <i class="fas fa-info-circle me-1"></i> Nosotros
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'faq.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>faq.php">
                        <i class="fas fa-circle-question me-1"></i> FAQ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contacto.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>contacto.php">
                        <i class="fas fa-envelope me-1"></i> Contacto
                    </a>
                </li>
            </ul>

            <!-- Sección de Usuario / Carrito -->
            <div class="d-flex align-items-center gap-3">
                <!-- Selector de Tema (Opcional - por diseño lo incluimos) -->
                <button class="btn btn-link text-muted p-0 me-1" id="themeToggleBtn" title="Cambiar Tema" style="font-size: 1.2rem;">
                    <i class="fas fa-sun"></i>
                </button>

                <!-- Carrito con indicador contador real -->
                <a href="<?php echo isLoggedIn() ? BASE_URL . 'carrito.php' : BASE_URL . 'login.php'; ?>" class="btn position-relative text-muted p-2" title="Mi Carrito">
                    <i class="fas fa-shopping-basket" style="font-size: 1.3rem;"></i>
                    <?php if ($cartCount > 0): ?>
                        <span class="position-absolute translate-middle badge rounded-pill bg-danger cart-badge">
                            <?php echo $cartCount; ?>
                        </span>
                    <?php endif; ?>
                </a>

                <?php if (isLoggedIn()): ?>
                    <!-- Menú Desplegable con Sesión Activa -->
                    <div class="dropdown">
                        <button class="btn btn-eco-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo sanitize($_SESSION['user_name']); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userMenuDropdown">
                            <?php if (isAdmin()): ?>
                                <li>
                                    <a class="dropdown-item py-2 text-success fw-bold" href="<?php echo BASE_URL; ?>admin/index.php">
                                        <i class="fas fa-gauge-high me-2"></i> Panel Admin
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>perfil.php">
                                    <i class="fas fa-user-cog me-2"></i> Mi Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>mis_pedidos.php">
                                    <i class="fas fa-list-alt me-2 text-success"></i> Mis Pedidos
                                </a>
                                <a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>mis_favoritos.php">
                                    <i class="fas fa-heart me-2 text-danger"></i> Mis Favoritos
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger" href="<?php echo BASE_URL; ?>logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <!-- Botones de Registro / Acceso -->
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-link text-decoration-none fw-semibold text-muted">Aceder</a>
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-eco-primary">Registro</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Contenedor general para avisos y flash messages -->
<div class="container my-3" id="systemNotifications">
    <?php
    if (isset($_SESSION['flash_success'])) {
        echo renderAlert($_SESSION['flash_success'], 'success');
        unset($_SESSION['flash_success']);
    }
    if (isset($_SESSION['flash_error'])) {
        echo renderAlert($_SESSION['flash_error'], 'danger');
        unset($_SESSION['flash_error']);
    }
    ?>
</div>
