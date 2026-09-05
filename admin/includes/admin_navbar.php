<?php
/**
 * 🌱 ECOTIENDA HN - NAVEGACIÓN Y CABECERA GLOBAL DE ADMINISTRACIÓN
 * Ruta: /admin/includes/admin_navbar.php
 * Descripción: Carga estilos del panel admin, GSAP 3.12.5, Chart.js 4.4.2, StarBorder CSS,
 *              Menú lateral dinámico (sin hardcode) y Header superior con notificaciones reales.
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/database.php';
    require_once __DIR__ . '/../../includes/session.php';
    require_once __DIR__ . '/../../includes/functions.php';
}

requireAdmin();

$currentScript = basename($_SERVER['PHP_SELF']);

// ── Verificación de Conexión a BD (con fallback visual de datos demo) ──────
$dbConectada = false;
try {
    $dbCheck = Database::getConnection();
    $dbCheck->query('SELECT 1');
    $dbConectada = true;
} catch (Exception $e) {
    $dbConectada = false;
}

// Notificaciones reales (Stock bajo y Pedidos pendientes)
$notifLowStock = [];
$notifPendingOrdersCount = 0;

if ($dbConectada) {
    try {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT id, nombre, stock FROM productos WHERE stock <= 5 AND estado != 'inactivo' ORDER BY stock ASC LIMIT 4");
        $notifLowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'");
        $notifPendingOrdersCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // Silencioso: se muestran los fallbacks visuales de la página
    }
}

$notifTotalCount = count($notifLowStock) + ($notifPendingOrdersCount > 0 ? 1 : 0);

function renderAdminNavItem($file, $icon, $label, $currentScript, $glassColor = 'green') {
    $isActive = ($currentScript === $file) ? 'active' : '';
    $url = BASE_URL . 'admin/' . $file;
    $glassIcon = "<span class=\"glass-icon glass-icon--{$glassColor}\">"
               . "<span class=\"glass-icon__back\"></span>"
               . "<span class=\"glass-icon__front\"><i class=\"{$icon}\"></i></span>"
               . "</span>";
    return "<li class=\"sidebar-item {$isActive}\"><a href=\"{$url}\">{$glassIcon}<span>{$label}</span></a></li>";
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | Admin EcoTienda' : 'Panel de Administración | EcoTienda HN'; ?></title>

    <!-- Google Fonts Premium: Space Grotesk & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha384-blOohCVdhjmtROpu8+CfTnUWham9nkX7P7OZQMst+RUnhtoY/9qemFAkIKOYxDI3" crossorigin="anonymous">
    
    <!-- Chart.js 4.4.2 UMD -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js" integrity="sha384-e6cc9LaIG7xZ3XD5B+jtr1NhTWPQGQdRCh6xiZ+ZFUtWCpg4ycv3Sh+SkZoopvUY" crossorigin="anonymous"></script>

    <!-- GSAP 3.12.5 Core -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>

    <!-- Estilos de Diseño: Admin Ultra-Premium Dark Glass & StarBorder -->
    <style>
        :root {
            --eco-primary: #10b981;
            --eco-primary-glow: rgba(16, 185, 129, 0.35);
            --eco-primary-dark: #059669;
            --eco-accent-cyan: #06b6d4;
            --eco-amber: #f59e0b;
            --admin-bg: #070a0f;
            --admin-card: rgba(15, 23, 42, 0.78);
            --admin-border: rgba(255, 255, 255, 0.08);
            --font-sans: 'Plus Jakarta Sans', -apple-system, sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--admin-bg);
            color: #f1f5f9;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Fondo animado Ferrofluid (WebGL) — capa fija detrás de todo el panel */
        .ferrofluid-bg {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
            overflow: hidden;
            background: var(--admin-bg);
        }

        /* Badge de datos de demostración (fallback visual sin BD) */
        .fallback-badge {
            display: inline-flex;
            align-items: center;
            font-size: .72rem;
            background: rgba(251,191,36,.15);
            color: #fbbf24;
            border: 1px solid rgba(251,191,36,.25);
            border-radius: 999px;
            padding: .3em .8em;
        }

        /* Pure CSS StarBorder Component */
        .star-border-container {
            display: inline-block;
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            padding: 1px;
            background: transparent;
        }

        .star-border-container .border-gradient-bottom {
            position: absolute;
            width: 300%;
            height: 50%;
            opacity: 0.85;
            bottom: -12px;
            right: -250%;
            border-radius: 50%;
            background: radial-gradient(circle, var(--star-color, #10b981), transparent 20%);
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
            background: radial-gradient(circle, var(--star-color, #10b981), transparent 20%);
            animation: star-movement-top var(--star-speed, 5s) linear infinite alternate;
            z-index: 0;
            pointer-events: none;
        }

        .star-border-container .inner-content {
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            color: #ffffff;
            border-radius: 19px;
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

        /* Sidebar Flotante */
        .sidebar {
            width: 260px;
            background: rgba(11, 15, 25, 0.85);
            backdrop-filter: blur(24px);
            border-right: 1px solid var(--admin-border);
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0;
            z-index: 1040;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .sidebar-brand {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff !important;
            padding: 1.4rem 1.5rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--admin-border);
            text-decoration: none;
        }

        .sidebar-brand i {
            color: var(--eco-primary);
            filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.6));
        }

        .sidebar-menu { list-style: none; padding: 1rem 0; margin: 0; }

        .sidebar-item a {
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.92rem;
            border-left: 3px solid transparent;
            transition: all 0.25s ease;
            gap: 0.85rem;
        }

        .sidebar-item a:hover, .sidebar-item.active a {
            color: #ffffff;
            background: rgba(16, 185, 129, 0.12);
            border-left-color: var(--eco-primary);
        }

        .sidebar-item.active a i {
            color: var(--eco-primary);
            filter: drop-shadow(0 0 8px rgba(16, 185, 129, 0.8));
        }

        .sidebar-item i { width: 22px; text-align: center; }

        /* ── GlassIcons: tarjeta 3D con rotate() + backdrop-filter (íconos del sidebar) ── */
        .glass-icon {
            position: relative;
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: inline-grid;
            place-items: center;
            border-radius: 12px;
        }

        .glass-icon__back {
            position: absolute;
            inset: 0;
            border-radius: 12px;
            background: var(--glass-grad, linear-gradient(135deg, var(--eco-primary), var(--eco-primary-dark)));
            box-shadow: 0 4px 14px -2px var(--glass-shadow, rgba(16, 185, 129, .45));
            transform: rotate(0deg) scale(0.94);
            opacity: 0.85;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
        }

        .glass-icon__front {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            color: #fff;
            font-size: 0.82rem;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .glass-icon__front i { width: auto; }

        .sidebar-item a:hover .glass-icon__back,
        .sidebar-item.active a .glass-icon__back {
            transform: rotate(16deg) scale(1.05);
            opacity: 1;
        }

        .sidebar-item a:hover .glass-icon__front,
        .sidebar-item.active a .glass-icon__front {
            transform: translateY(-2px) scale(1.04);
        }

        .glass-icon--green  { --glass-grad: linear-gradient(135deg, var(--eco-primary), var(--eco-primary-dark)); --glass-shadow: rgba(16, 185, 129, .5); }
        .glass-icon--cyan   { --glass-grad: linear-gradient(135deg, var(--eco-accent-cyan), #0891b2); --glass-shadow: rgba(6, 182, 212, .5); }
        .glass-icon--amber  { --glass-grad: linear-gradient(135deg, var(--eco-amber), #d97706); --glass-shadow: rgba(245, 158, 11, .5); }
        .glass-icon--slate  { --glass-grad: linear-gradient(135deg, #475569, #1e293b); --glass-shadow: rgba(71, 85, 105, .4); }
        .glass-icon--danger { --glass-grad: linear-gradient(135deg, #ef4444, #b91c1c); --glass-shadow: rgba(239, 68, 68, .45); }

        @media (prefers-reduced-motion: reduce) {
            .glass-icon__back, .glass-icon__front { transition: none !important; }
        }

        /* Main Content Layout */
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        @media (max-width: 991.98px) {
            .sidebar { margin-left: -260px; }
            .sidebar.open { margin-left: 0; }
            .main-content { margin-left: 0; }
        }

        .admin-card {
            background: var(--admin-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--admin-border);
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
        }

        /* ═══════════════════════════════════════════════════════════════════
           SISTEMA DE DISEÑO ADMIN — restaurado y mejorado
           (recupera clases que se perdieron al centralizar aquí el bloque de estilos,
            y las eleva visualmente sin cambiar ningún nombre de clase usado
            en las páginas: productos, categorías, usuarios, pedidos,
            reportes, inventario y configuración)
           ═══════════════════════════════════════════════════════════════════ */

        /* ── KPI Cards (Dashboard) ── */
        .kpi-card {
            background: var(--admin-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--admin-border);
            border-radius: 20px;
            padding: 1.4rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            box-shadow: 0 12px 28px -14px rgba(0, 0, 0, 0.45);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -16px rgba(16, 185, 129, 0.22);
            border-color: rgba(16, 185, 129, 0.28);
        }
        .kpi-icon {
            width: 52px; height: 52px;
            border-radius: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            color: #fff;
            box-shadow: 0 8px 20px -6px var(--kpi-shadow, rgba(16, 185, 129, .45));
        }
        .kpi-icon.bg-success, .kpi-icon.text-success { background: linear-gradient(135deg, var(--eco-primary), var(--eco-primary-dark)) !important; --kpi-shadow: rgba(16,185,129,.45); }
        .kpi-icon.bg-info,    .kpi-icon.text-info    { background: linear-gradient(135deg, var(--eco-accent-cyan), #0891b2) !important; --kpi-shadow: rgba(6,182,212,.45); }
        .kpi-icon.bg-warning, .kpi-icon.text-warning { background: linear-gradient(135deg, var(--eco-amber), #d97706) !important; --kpi-shadow: rgba(245,158,11,.45); }
        .kpi-icon.bg-secondary,.kpi-icon.text-secondary{ background: linear-gradient(135deg, #64748b, #334155) !important; --kpi-shadow: rgba(100,116,139,.4); }
        .kpi-icon.bg-danger,  .kpi-icon.text-danger  { background: linear-gradient(135deg, #ef4444, #b91c1c) !important; --kpi-shadow: rgba(239,68,68,.4); }
        .kpi-label { font-size: .78rem; color: #64748b; font-weight: 600; margin-bottom: .3rem; text-transform: uppercase; letter-spacing: .04em; }
        .kpi-value { font-family: var(--font-display); font-size: 1.7rem; font-weight: 700; line-height: 1.1; letter-spacing: -.02em; color: #f8fafc; }

        /* ── Tarjeta genérica alterna (usada en reportes.php) ── */
        .eco-card {
            background: var(--admin-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--admin-border);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 12px 28px -14px rgba(0, 0, 0, 0.45);
        }

        /* ── Botón primario de marca ── */
        .btn-eco-primary {
            background: linear-gradient(135deg, var(--eco-primary), var(--eco-primary-dark));
            color: #fff;
            border: none;
            font-weight: 600;
            border-radius: 10px;
            padding: 0.55rem 1.35rem;
            font-size: 0.875rem;
            letter-spacing: .01em;
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
            box-shadow: 0 6px 18px -6px rgba(16, 185, 129, 0.5);
        }
        .btn-eco-primary:hover {
            color: #fff;
            filter: brightness(1.08);
            transform: translateY(-1px);
            box-shadow: 0 10px 24px -6px rgba(16, 185, 129, 0.6);
        }
        .btn-eco-primary:active { transform: translateY(0); }

        /* ── Botones de acción secundarios usados en reportes.php ── */
        .btn-pdf {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: none; color: #fff; font-weight: 600;
            padding: .65rem 1.4rem; border-radius: 50px;
            display: inline-flex; align-items: center; gap: .5rem;
            cursor: pointer; font-family: var(--font-sans); font-size: .9rem;
            transition: all .25s ease;
            box-shadow: 0 8px 20px -8px rgba(220, 38, 38, .5);
        }
        .btn-pdf:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -8px rgba(220, 38, 38, .55); color:#fff; }
        .btn-pdf:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow:none; }

        .btn-csv {
            background: linear-gradient(135deg, #059669, #047857);
            border: none; color: #fff; font-weight: 600;
            padding: .65rem 1.4rem; border-radius: 50px;
            display: inline-flex; align-items: center; gap: .5rem;
            cursor: pointer; font-family: var(--font-sans); font-size: .9rem;
            text-decoration: none; transition: all .25s ease;
            box-shadow: 0 8px 20px -8px rgba(5, 150, 105, .5);
        }
        .btn-csv:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -8px rgba(5,150,105,.55); color: #fff; }

        .filtro-btn {
            background: rgba(255, 255, 255, .04);
            border: 1px solid var(--admin-border);
            color: #94a3b8;
            padding: .5rem 1rem;
            border-radius: 50px;
            font-size: .82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s ease;
            display: inline-block;
        }
        .filtro-btn:hover { color: #fff; border-color: var(--eco-primary); }
        .filtro-btn.active { background: var(--eco-primary); border-color: var(--eco-primary); color: #06231a; }

        .bar-fill {
            background: linear-gradient(90deg, var(--eco-primary), var(--eco-primary-dark));
            height: 10px; border-radius: 5px; transition: width .8s ease;
        }

        /* ── Sistema unificado de tablas (una sola definición para todas las
               variantes de nombre usadas por cada página: eco-table, tabla-*, inv-table) ── */
        .eco-table, .tabla-productos, .tabla-cats, .tabla-usuarios, .tabla-pedidos, .inv-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--font-sans);
            color: #f1f5f9;
        }
        .eco-table th, .eco-table thead th,
        .tabla-productos thead th, .tabla-cats thead th,
        .tabla-usuarios thead th, .tabla-pedidos thead th,
        .inv-table thead th {
            color: #64748b !important;
            font-size: .76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--admin-border);
            background: transparent !important;
            white-space: nowrap;
        }
        .eco-table td, .eco-table tbody td,
        .tabla-productos tbody td, .tabla-cats tbody td,
        .tabla-usuarios tbody td, .tabla-pedidos tbody td,
        .inv-table tbody td {
            padding: .9rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, .045);
            vertical-align: middle;
            font-size: .89rem;
            color: #e2e8f0 !important;
            background: transparent !important;
        }
        .eco-table tr:last-child td,
        .tabla-productos tbody tr:last-child td, .tabla-cats tbody tr:last-child td,
        .tabla-usuarios tbody tr:last-child td, .tabla-pedidos tbody tr:last-child td,
        .inv-table tbody tr:last-child td { border-bottom: none; }

        .eco-table tr, .tabla-productos tbody tr, .tabla-cats tbody tr,
        .tabla-usuarios tbody tr, .tabla-pedidos tbody tr, .inv-table tbody tr {
            transition: background .15s ease;
        }
        .eco-table tr:hover td,
        .tabla-productos tbody tr:hover td, .tabla-cats tbody tr:hover td,
        .tabla-usuarios tbody tr:hover td, .tabla-pedidos tbody tr:hover td,
        .inv-table tbody tr:hover td { background: rgba(16, 185, 129, .045) !important; }

        .nombre-producto { color: #ffffff !important; font-weight: 600; font-size: .95rem; }

        .img-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 10px; border: 1px solid var(--admin-border); }
        .img-placeholder { width: 48px; height: 48px; border-radius: 10px; background: rgba(16,185,129,.08); border: 1px solid var(--admin-border); display: flex; align-items: center; justify-content: center; color: var(--eco-primary); font-size: 1.2rem; }

        /* ── Celdas especiales de inventario ── */
        .inv-table .cell-id { font-family: var(--font-display), monospace; font-size: .82rem; color: #475569 !important; font-weight: 600; }
        .inv-table .cell-product strong { color: #f1f5f9 !important; font-size: .9rem; }
        .inv-table .cell-center { text-align: center; }
        .inv-table .cell-qty { text-align: center; font-family: var(--font-display), monospace; font-weight: 700; font-size: .92rem; color: #f1f5f9 !important; }
        .inv-table .cell-date { font-size: .82rem; color: #64748b !important; white-space: nowrap; }
        .inv-table .cell-desc { font-size: .82rem; color: #94a3b8 !important; max-width: 240px; }

        /* ── Badges de estado (dos convenciones de nombre usadas por distintas páginas) ── */
        .badge-estado, .estado-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: .35em .8em;
            border-radius: 999px;
            font-size: .73rem;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .badge-pendiente,  .estado-pendiente  { background: rgba(251, 191, 36, .15);  color: #fbbf24; }
        .badge-pagado,     .estado-pagado     { background: rgba(45, 212, 191, .15);  color: #2dd4bf; }
        .badge-procesando, .estado-procesando { background: rgba(99, 102, 241, .15);  color: #818cf8; }
        .badge-enviado,    .estado-enviado    { background: rgba(59, 130, 246, .15);  color: #60a5fa; }
        .badge-entregado,  .estado-entregado  { background: rgba(16, 185, 129, .15);  color: #10b981; }
        .badge-cancelado,  .estado-cancelado  { background: rgba(239, 68, 68, .15);   color: #f87171; }

        /* ── Badges de movimiento de inventario ── */
        .badge-entrada {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px; border-radius: 7px; font-size: .75rem; font-weight: 700;
            background: rgba(16, 185, 129, .15) !important; color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, .25);
        }
        .badge-salida {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px; border-radius: 7px; font-size: .75rem; font-weight: 700;
            background: rgba(239, 68, 68, .15) !important; color: #f87171 !important;
            border: 1px solid rgba(239, 68, 68, .25);
        }

        /* ── Contraseña con ojo + medidor de fuerza (configuracion.php) ── */
        .pwd-wrap { position: relative; }
        .pwd-wrap input { padding-right: 2.6rem; }
        .pwd-toggle {
            position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #64748b; cursor: pointer;
            padding: 0; font-size: .95rem; transition: color .2s ease;
        }
        .pwd-toggle:hover { color: var(--eco-primary); }
        .pwd-strength-bar { height: 4px; border-radius: 2px; transition: width .3s ease, background .3s ease; width: 0%; }

        /* ── Formularios: tema oscuro coherente dentro de tarjetas (no afecta modales) ── */
        .admin-card .form-control, .admin-card .form-select,
        .eco-card .form-control, .eco-card .form-select {
            background-color: rgba(255, 255, 255, .035);
            border: 1px solid var(--admin-border);
            color: #f1f5f9;
            border-radius: 10px;
            padding: .55rem .85rem;
        }
        .admin-card .form-control::placeholder, .eco-card .form-control::placeholder { color: #64748b; }
        .admin-card .form-control:focus, .admin-card .form-select:focus,
        .eco-card .form-control:focus, .eco-card .form-select:focus {
            background-color: rgba(255, 255, 255, .05);
            border-color: var(--eco-primary);
            color: #f8fafc;
            box-shadow: 0 0 0 .2rem rgba(16, 185, 129, .18);
        }
        .admin-card .form-label, .eco-card .form-label { color: #cbd5e1; }
        .admin-card .form-control[type="number"]::-webkit-inner-spin-button { filter: invert(1) opacity(.6); }

        /* ── Scrollbar sutil a juego con el tema ── */
        .main-content::-webkit-scrollbar, .admin-card::-webkit-scrollbar { width: 8px; height: 8px; }
        .main-content::-webkit-scrollbar-thumb, .admin-card::-webkit-scrollbar-thumb { background: rgba(16,185,129,.25); border-radius: 8px; }

        /* ── Contenedor responsivo para tablas anchas en pantallas pequeñas ── */
        .table-responsive-eco { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        @media (prefers-reduced-motion: reduce) {
            .kpi-card, .btn-eco-primary, .btn-pdf, .btn-csv, .filtro-btn { transition: none !important; }
        }
        /* Dropdown & Modals */
        .dropdown-menu, .modal-content {
            background-color: rgba(15, 23, 42, 0.94) !important;
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 20px;
            color: #f1f5f9;
        }
    </style>
</head>
<body>

<!-- FONDO FERROFLUID (background WebGL animado, estilo React Bits) -->
<div id="ferrofluidBg" class="ferrofluid-bg"></div>

<!-- SIDEBAR ADMIN GLOBAL -->
<div class="sidebar" id="adminSidebar">
    <a href="<?php echo BASE_URL; ?>admin/index.php" class="sidebar-brand">
        <i class="fas fa-leaf me-2"></i>
        <span>EcoTienda <span class="text-success" style="color: var(--eco-primary) !important;">Admin</span></span>
    </a>
    <ul class="sidebar-menu">
        <?php
        echo renderAdminNavItem('index.php',         'fas fa-gauge-high',   'Dashboard',    $currentScript, 'green');
        echo renderAdminNavItem('productos.php',     'fas fa-box',          'Productos',    $currentScript, 'cyan');
        echo renderAdminNavItem('categorias.php',    'fas fa-tags',         'Categorías',   $currentScript, 'amber');
        echo renderAdminNavItem('usuarios.php',      'fas fa-users',        'Usuarios',     $currentScript, 'slate');
        echo renderAdminNavItem('pedidos.php',       'fas fa-shopping-bag', 'Pedidos',      $currentScript, 'green');
        echo renderAdminNavItem('reportes.php',      'fas fa-chart-line',   'Reportes',     $currentScript, 'cyan');
        echo renderAdminNavItem('inventario.php',    'fas fa-warehouse',    'Inventario',   $currentScript, 'amber');
        echo renderAdminNavItem('configuracion.php', 'fas fa-cog',          'Configuración',$currentScript, 'slate');
        ?>
        <li class="sidebar-item mt-4"><a href="<?php echo BASE_URL; ?>index.php" class="text-success">
            <span class="glass-icon glass-icon--green"><span class="glass-icon__back"></span><span class="glass-icon__front"><i class="fas fa-store"></i></span></span>
            <span>Ver Tienda</span>
        </a></li>
        <li class="sidebar-item"><a href="<?php echo BASE_URL; ?>logout.php" class="text-danger">
            <span class="glass-icon glass-icon--danger"><span class="glass-icon__back"></span><span class="glass-icon__front"><i class="fas fa-sign-out-alt"></i></span></span>
            <span>Cerrar Sesión</span>
        </a></li>
    </ul>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div class="main-content">
    <!-- Header Admin Superior -->
    <header class="d-flex justify-content-between align-items-center mb-4 pb-3" style="border-bottom: 1px solid var(--admin-border);">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.getElementById('adminSidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="h3 fw-bold m-0 font-display"><?php echo $pageTitle ?? 'Panel de Administración'; ?></h1>
                <p class="text-slate-400 small mb-0" style="color: #94a3b8;"><?php echo $pageSubtitle ?? '🌱 Gestión integral — EcoTienda HN'; ?></p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Reloj y Fecha -->
            <span class="small text-slate-300 d-none d-md-inline" style="color: #cbd5e1;">
                <i class="fas fa-calendar-day text-success me-1"></i><?php echo date('d M, Y'); ?>
            </span>

            <!-- Estado de Conexión a BD -->
            <?php if ($dbConectada): ?>
                <span class="small d-none d-lg-inline-flex align-items-center gap-1" style="color:#10b981; background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.25); padding:.3em .7em; border-radius:999px;">
                    <i class="fas fa-circle" style="font-size:.5rem;"></i> BD Conectada
                </span>
            <?php else: ?>
                <span class="small d-none d-lg-inline-flex align-items-center gap-1" style="color:#fbbf24; background:rgba(251,191,36,.12); border:1px solid rgba(251,191,36,.25); padding:.3em .7em; border-radius:999px;" title="Sin acceso a la base de datos remota desde esta IP local. Mostrando datos de demostración.">
                    <i class="fas fa-triangle-exclamation" style="font-size:.65rem;"></i> Modo Demo
                </span>
            <?php endif; ?>

            <!-- Centro de Notificaciones con Datos Reales -->
            <div class="dropdown">
                <button class="btn btn-pill-glass btn-sm position-relative p-2" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px; border-radius: 50%;">
                    <i class="fas fa-bell"></i>
                    <?php if ($notifTotalCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-dark rounded-circle" style="width: 10px; height: 10px;"></span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-3 mt-2" aria-labelledby="notifDropdown" style="min-width: 290px;">
                    <li class="fw-bold mb-2 small d-flex align-items-center justify-content-between">
                        <span>Notificaciones de Operación</span>
                        <span class="badge bg-success rounded-pill"><?php echo $notifTotalCount; ?></span>
                    </li>
                    <li><hr class="dropdown-divider border-secondary opacity-25"></li>
                    <?php if ($notifPendingOrdersCount > 0): ?>
                        <li class="py-1">
                            <a href="<?php echo BASE_URL; ?>admin/pedidos.php" class="dropdown-item small text-warning p-2 rounded-3">
                                <i class="fas fa-clock me-2"></i><?php echo $notifPendingOrdersCount; ?> pedidos pendientes por atender.
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($notifLowStock)): ?>
                        <?php foreach ($notifLowStock as $item): ?>
                            <li class="py-1">
                                <a href="<?php echo BASE_URL; ?>admin/inventario.php" class="dropdown-item small text-danger p-2 rounded-3 text-truncate">
                                    <i class="fas fa-triangle-exclamation me-2"></i>Stock bajo: <?php echo sanitize($item['nombre']); ?> (<?php echo $item['stock']; ?> uds)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($notifTotalCount === 0): ?>
                        <li class="text-slate-400 small text-center py-2" style="color: #94a3b8;">Sin alertas de inventario o pedidos.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Avatar Admin -->
            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: #fff; box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);">
                ADM
            </div>
        </div>
    </header>