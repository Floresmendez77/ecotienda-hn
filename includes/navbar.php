<?php
/**
 * 🌱 ECOTIENDA HN - CABECERA Y NAVEGACIÓN (ORGANIC PREMIUM COMMERCE + GSAP MOTION + STARBORDER)
 * Ruta: /includes/navbar.php
 * Descripción: Inicializa HTML5, carga CSS de Bootstrap 5, Font Awesome 6,
 *              GSAP 3.12.5 (ScrollTrigger, Draggable, InertiaPlugin), StarBorder y el Navbar Flotante.
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
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png">
    <!-- Google Fonts Premium: Space Grotesk & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha384-blOohCVdhjmtROpu8+CfTnUWham9nkX7P7OZQMst+RUnhtoY/9qemFAkIKOYxDI3" crossorigin="anonymous">
    
    <!-- GSAP 3.12.5 Core & Plugins (ScrollTrigger, Draggable, InertiaPlugin) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/Draggable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/InertiaPlugin.min.js"></script>
    <script>
        if (typeof gsap !== 'undefined') {
            gsap.registerPlugin(ScrollTrigger, Draggable, InertiaPlugin);
        }
    </script>
    
    <!-- Sistema de Diseño: Organic Premium Commerce, Specular Motion & StarBorder (React Bits) -->
    <style>
        :root {
            --eco-primary: #10b981;
            --eco-primary-glow: rgba(16, 185, 129, 0.35);
            --eco-primary-dark: #059669;
            --eco-accent-cyan: #06b6d4;
            --eco-amber: #f59e0b;
            --eco-bg-dark: #070a0f;
            --eco-surface-dark: #0f172a;
            --eco-card-bg: rgba(15, 23, 42, 0.65);
            --eco-card-border: rgba(255, 255, 255, 0.08);
            --eco-card-border-hover: rgba(16, 185, 129, 0.3);
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
        }

        /* Base Body Canvas con Atmósfera Radial Multi-capa */
        body {
            font-family: var(--font-sans);
            background-color: var(--eco-bg-dark);
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            position: relative;
            letter-spacing: -0.01em;
        }

        /* Destello Ambiental Superior Izquierdo */
        body::before {
            content: '';
            position: fixed;
            top: -150px;
            left: -150px;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.09) 0%, rgba(16, 185, 129, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        /* Destello Ambiental Inferior Derecha */
        body::after {
            content: '';
            position: fixed;
            bottom: -200px;
            right: -150px;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.07) 0%, rgba(6, 182, 212, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        /* Fondo animado Ferrofluid (WebGL) — capa fija detrás de todo el sitio */
        .ferrofluid-bg {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
            overflow: hidden;
        }

        /* Jerarquía Tipográfica Editorial */
        h1, h2, h3, .font-display {
            font-family: var(--font-display);
            letter-spacing: -0.03em;
        }

        /* Componente StarBorder (React Bits Style) */
        .star-border-container {
            display: inline-block;
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            padding: 1px;
            background: transparent;
            transform-style: preserve-3d;
        }

        .star-border-container .border-gradient-bottom {
            position: absolute;
            width: 300%;
            height: 50%;
            opacity: 0.85;
            bottom: -12px;
            right: -250%;
            border-radius: 50%;
            background: radial-gradient(circle, var(--star-color, #10b981), transparent 22%);
            animation: star-movement-bottom var(--star-speed, 5s) linear infinite alternate;
            z-index: 0;
            pointer-events: none;
        }

        .star-border-container .border-gradient-top {
            position: absolute;
            width: 300%;
            height: 50%;
            opacity: 0.85;
            top: -12px;
            left: -250%;
            border-radius: 50%;
            background: radial-gradient(circle, var(--star-color, #10b981), transparent 22%);
            animation: star-movement-top var(--star-speed, 5s) linear infinite alternate;
            z-index: 0;
            pointer-events: none;
        }

        .star-border-container .inner-content {
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            color: #ffffff;
            border-radius: 23px;
            z-index: 1;
            height: 100%;
            width: 100%;
        }

        @keyframes star-movement-bottom {
            0% { transform: translate(0%, 0%); opacity: 1; }
            100% { transform: translate(-100%, 0%); opacity: 0.2; }
        }

        @keyframes star-movement-top {
            0% { transform: translate(0%, 0%); opacity: 1; }
            100% { transform: translate(100%, 0%); opacity: 0.2; }
        }

        /* 3D Depth Carousel Perspective System (React Bits Style) */
        .depth-carousel-wrapper {
            position: relative;
            width: 100%;
            overflow: hidden;
            padding: 1.5rem 0 3rem 0;
        }

        .depth-carousel-track {
            display: flex;
            gap: 20px;
            perspective: 1000px;
            perspective-origin: 50% 50%;
            transform-style: preserve-3d;
            cursor: grab;
            user-select: none;
        }

        .depth-carousel-track:active {
            cursor: grabbing;
        }

        .depth-carousel-item {
            flex: 0 0 310px;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.45s ease;
            will-change: transform, opacity;
        }

        @media (min-width: 768px) {
            .depth-carousel-item {
                flex: 0 0 330px;
            }
        }

        .depth-carousel-indicators {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 1.5rem;
        }

        .depth-carousel-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .depth-carousel-indicator.active {
            background: var(--eco-primary);
            box-shadow: 0 0 12px var(--eco-primary);
            transform: scale(1.4);
        }

        /* Floating Product Card System */
        .card, .glass-card {
            background: var(--eco-card-bg) !important;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--eco-card-border) !important;
            border-radius: 24px;
            color: #f1f5f9;
            position: relative;
            transform-style: preserve-3d;
            perspective: 1000px;
            will-change: transform;
            transition: border-color 0.35s ease, box-shadow 0.35s ease;
        }

        .card:hover, .glass-card:hover {
            border-color: var(--eco-card-border-hover) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75), 0 0 30px rgba(16, 185, 129, 0.15);
        }

        /* Reflejo Especular Dinámico mediante CSS Custom Properties --mx / --my */
        .card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: radial-gradient(600px circle at var(--mx, 50%) var(--my, 50%), rgba(255, 255, 255, 0.14), transparent 45%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            z-index: 2;
        }

        .card:hover::after {
            opacity: 1;
        }

        /* Specular Shimmer Buttons */
        .btn-eco-primary, .btn-pill-glass, .carousel-arrow-btn {
            position: relative;
            overflow: hidden;
        }

        .btn-eco-primary::before, .btn-pill-glass::before, .carousel-arrow-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 60%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.28), transparent);
            transform: skewX(-20deg);
            pointer-events: none;
        }

        .btn-eco-primary:hover::before, .btn-pill-glass:hover::before, .carousel-arrow-btn:hover::before {
            animation: specularShimmer 0.65s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes specularShimmer {
            0% { left: -100%; }
            100% { left: 200%; }
        }

        /* Modal & Dropdown Glassmorphism */
        .dropdown-menu, .modal-content {
            background-color: rgba(15, 23, 42, 0.92) !important;
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 20px;
            color: #f1f5f9;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8);
        }

        .dropdown-item {
            color: #cbd5e1;
            border-radius: 10px;
            padding: 0.6rem 1rem;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        /* Floating Capsule Navbar */
        .navbar-capsule-wrapper {
            position: sticky;
            top: 16px;
            z-index: 1050;
            padding: 0 1rem;
        }

        .navbar-capsule {
            max-width: 1240px;
            margin: 0 auto;
            background: rgba(11, 15, 25, 0.72) !important;
            backdrop-filter: blur(24px) saturate(190%);
            -webkit-backdrop-filter: blur(24px) saturate(190%);
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 9999px;
            padding: 0.6rem 1.6rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7), 0 0 1px 1px rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.35rem;
            color: #fff !important;
            letter-spacing: -0.02em;
        }

        .navbar-brand i {
            color: var(--eco-primary);
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.6));
        }

        .nav-link {
            font-weight: 500;
            font-size: 0.92rem;
            color: #94a3b8 !important;
            padding: 0.5rem 1.2rem !important;
            border-radius: 9999px;
            transition: all 0.25s ease;
            position: relative;
        }

        .nav-link:hover, .nav-link.active {
            color: #10b981 !important;
            background-color: rgba(16, 185, 129, 0.12);
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 3px;
            background-color: var(--eco-primary);
            border-radius: 9999px;
            box-shadow: 0 0 10px var(--eco-primary);
        }

        /* Botones Organic Premium */
        .btn-eco-primary, .btn-pill-emerald {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff !important;
            font-weight: 600;
            border: none;
            border-radius: 9999px;
            padding: 0.7rem 1.6rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.35);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-eco-primary:hover, .btn-pill-emerald:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 12px 30px rgba(16, 185, 129, 0.5);
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
        }

        .btn-pill-glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            color: #f1f5f9 !important;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 9999px;
            padding: 0.7rem 1.6rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-pill-glass:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(16, 185, 129, 0.4);
            color: #10b981 !important;
            transform: translateY(-2px);
        }

        /* Controls Dark Glass */
        .form-control, .form-select {
            background-color: rgba(15, 23, 42, 0.85) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #f1f5f9 !important;
            border-radius: 14px;
            padding: 0.7rem 1.1rem;
            transition: all 0.25s ease;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(11, 15, 25, 0.95) !important;
            border-color: var(--eco-primary) !important;
            box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25) !important;
            color: #fff !important;
        }

        /* Cart Icon Capsule & Pulsing Badge */
        .cart-icon-wrapper {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
            transition: all 0.3s ease;
        }

        .cart-icon-wrapper:hover {
            background: rgba(16, 185, 129, 0.18);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.4);
            transform: translateY(-2px) scale(1.05);
        }

        .cart-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            font-size: 11px;
            font-weight: 700;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #070a0f;
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.7);
            animation: cartPulse 2.5s infinite cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cartPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.18); }
        }

        /* Insignia de Impacto Organic Premium */
        .impact-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.45rem 1.2rem;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            backdrop-filter: blur(10px);
            font-size: 0.85rem;
            font-weight: 600;
            color: #34d399;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        }

        /* Carousel Horizontal Container */
        .carousel-scroll-container {
            display: flex;
            gap: 1.5rem;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            padding: 1rem 0.5rem 2.5rem 0.5rem;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            touch-action: pan-y;
        }
        .carousel-scroll-container::-webkit-scrollbar {
            display: none;
        }

        .carousel-item-card {
            flex: 0 0 300px;
            scroll-snap-align: start;
        }

        @media (min-width: 768px) {
            .carousel-item-card {
                flex: 0 0 320px;
            }
        }

        .carousel-arrow-btn {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .carousel-arrow-btn:hover {
            background: var(--eco-primary);
            color: #fff;
            border-color: var(--eco-primary);
            transform: scale(1.1);
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.5);
        }

        /* Hover Zoom en Imágenes */
        .img-zoom-hover {
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .card:hover .img-zoom-hover {
            transform: scale(1.06);
        }
    </style>
</head>
<body>

<!-- FONDO FERROFLUID (background WebGL animado, estilo React Bits) -->
<div id="ferrofluidBg" class="ferrofluid-bg"></div>

<!-- Barra de Navegación Flotante en Cápsula -->
<div class="navbar-capsule-wrapper">
    <nav class="navbar navbar-expand-lg navbar-capsule">
        <div class="container-fluid p-0">
            <!-- Logotipo Brand -->
            <a class="navbar-brand d-flex align-items-center me-4" href="<?php echo BASE_URL; ?>index.php">
                <span class="me-2"><i class="fas fa-leaf"></i></span>
                <span>EcoTienda <span class="text-success" style="color: var(--eco-primary) !important;">HN</span></span>
            </a>
            
            <!-- Botón Menú Móvil -->
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Enlaces de Navegación -->
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1">
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

                <!-- Acciones de Usuario & Carrito -->
                <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
                    <!-- Icono Carrito con Badge de Pulso -->
                    <a href="<?php echo isLoggedIn() ? BASE_URL . 'carrito.php' : BASE_URL . 'login.php'; ?>" class="cart-icon-wrapper text-decoration-none" title="Mi Carrito">
                        <i class="fas fa-shopping-basket"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge">
                                <?php echo $cartCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <?php if (isLoggedIn()): ?>
                        <!-- Menú Desplegable de Usuario -->
                        <div class="dropdown">
                            <button class="btn btn-pill-glass dropdown-toggle d-flex align-items-center gap-2" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle text-success"></i>
                                <span><?php echo sanitize($_SESSION['user_name']); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userMenuDropdown">
                                <?php if (isAdmin()): ?>
                                    <li>
                                        <a class="dropdown-item py-2 text-success fw-bold" href="<?php echo BASE_URL; ?>admin/index.php">
                                            <i class="fas fa-gauge-high me-2"></i> Panel Admin
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider border-secondary opacity-25"></li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>perfil.php">
                                        <i class="fas fa-user-cog me-2 text-info"></i> Mi Perfil
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
                                <li><hr class="dropdown-divider border-secondary opacity-25"></li>
                                <li>
                                    <a class="dropdown-item py-2 text-danger" href="<?php echo BASE_URL; ?>logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Botones de Registro / Acceso -->
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-link text-decoration-none fw-semibold text-slate-300 px-3">Acceder</a>
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-eco-primary">Registro</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</div>

<!-- Contenedor General de Notificaciones Flash -->
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
