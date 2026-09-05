<?php
/**
 * 🌱 ECOTIENDA HN - Página 404: No encontrada
 * Ruta: /404.php
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);
logError('WARNING', '404 Not Found', ['url' => $_SERVER['REQUEST_URI'] ?? '', 'referrer' => $_SERVER['HTTP_REFERER'] ?? '']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada | EcoTienda HN</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha384-blOohCVdhjmtROpu8+CfTnUWham9nkX7P7OZQMst+RUnhtoY/9qemFAkIKOYxDI3" crossorigin="anonymous">
    <style>
        :root { --eco-green: #10b981; --eco-dark: #064e3b; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(16,185,129,.15);
            padding: 3.5rem 2.5rem;
            text-align: center;
            max-width: 520px;
            width: 100%;
        }
        .leaf-icon {
            font-size: 5rem;
            color: var(--eco-green);
            animation: sway 3s ease-in-out infinite;
        }
        @keyframes sway {
            0%,100% { transform: rotate(-6deg); }
            50%      { transform: rotate(6deg); }
        }
        .error-code {
            font-size: 7rem;
            font-weight: 800;
            line-height: 1;
            color: var(--eco-green);
            opacity: .15;
            letter-spacing: -4px;
        }
        .btn-eco {
            background: var(--eco-green);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: .75rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            transition: background .2s;
        }
        .btn-eco:hover { background: var(--eco-dark); color: #fff; }
        .btn-outline-eco {
            border: 2px solid var(--eco-green);
            color: var(--eco-green);
            border-radius: 12px;
            padding: .75rem 2rem;
            font-weight: 600;
            background: transparent;
            transition: all .2s;
        }
        .btn-outline-eco:hover { background: var(--eco-green); color: #fff; }
    </style>
</head>
<body>
<div class="error-card mx-3">
    <div class="error-code">404</div>
    <div class="leaf-icon mb-3">
        <i class="fas fa-leaf"></i>
    </div>
    <h1 class="fw-bold fs-2 text-dark mb-2">Página no encontrada</h1>
    <p class="text-muted mb-4">
        Parece que esta hoja cayó del árbol. La página que buscas no existe,
        fue movida o tal vez escribiste mal la dirección.
    </p>
    <div class="d-flex flex-wrap justify-content-center gap-3">
        <a href="<?php echo BASE_URL; ?>" class="btn btn-eco">
            <i class="fas fa-home me-2"></i>Volver al inicio
        </a>
        <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-outline-eco">
            <i class="fas fa-store me-2"></i>Ver tienda
        </a>
    </div>
    <p class="mt-4 text-muted small">
        <i class="fas fa-leaf text-success me-1"></i>
        <strong>EcoTienda HN</strong> &mdash; Ecológico, Sostenible y Hondureño
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>
