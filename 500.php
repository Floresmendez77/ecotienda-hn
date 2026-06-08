<?php
/**
 * 🌱 ECOTIENDA HN - Página 500: Error interno del servidor
 * Ruta: /500.php
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

http_response_code(500);
logError('ERROR', '500 Internal Server Error', ['url' => $_SERVER['REQUEST_URI'] ?? '']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error del servidor | EcoTienda HN</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
        .gear-icon {
            font-size: 5rem;
            color: var(--eco-green);
            animation: spin 4s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
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
        .error-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 1rem;
            font-size: .85rem;
            color: #065f46;
        }
    </style>
</head>
<body>
<div class="error-card mx-3">
    <div class="error-code">500</div>
    <div class="gear-icon mb-3">
        <i class="fas fa-cog"></i>
    </div>
    <h1 class="fw-bold fs-2 text-dark mb-2">Error interno del servidor</h1>
    <p class="text-muted mb-3">
        Algo inesperado ocurrió en nuestros servidores. Nuestro equipo
        ya fue notificado y estamos trabajando para resolverlo lo antes posible.
    </p>
    <div class="error-info mb-4">
        <i class="fas fa-clock me-2 text-success"></i>
        Por favor intenta de nuevo en unos minutos. Si el problema persiste,
        <a href="<?php echo BASE_URL; ?>contacto.php" class="text-success fw-bold">contáctanos</a>.
    </div>
    <div class="d-flex flex-wrap justify-content-center gap-3">
        <a href="<?php echo BASE_URL; ?>" class="btn btn-eco">
            <i class="fas fa-home me-2"></i>Volver al inicio
        </a>
        <a href="javascript:history.back()" class="btn btn-outline-eco">
            <i class="fas fa-arrow-left me-2"></i>Página anterior
        </a>
    </div>
    <p class="mt-4 text-muted small">
        <i class="fas fa-leaf text-success me-1"></i>
        <strong>EcoTienda HN</strong> &mdash; Ecológico, Sostenible y Hondureño
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
