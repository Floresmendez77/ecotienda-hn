<?php
/**
 * 🌱 ECOTIENDA HN - PIE DE PÁGINA EDITORIAL (ORGANIC PREMIUM COMMERCE)
 * Ruta: /includes/footer.php
 * Descripción: Pie de página minimalista de alta gama con marca de agua sutil,
 *              microinteracciones sociales y métodos de pago de Honduras.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Cargar configs de contacto dinámicas
$nombre_tienda = SITE_NAME;
$correo = 'contacto@ecotiendahn.com';
$telefono = '+504 9900-1122';
$direccion = 'Tegucigalpa, Honduras. Centroamérica';
$facebook = '#';
$instagram = '#';
$tiktok = '#';

try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT * FROM configuracion LIMIT 1");
    $config = $stmt->fetch();
    if ($config) {
        if (!empty($config['nombre_tienda'])) $nombre_tienda = $config['nombre_tienda'];
        if (!empty($config['correo'])) $correo = $config['correo'];
        if (!empty($config['telefono'])) $telefono = $config['telefono'];
        if (!empty($config['direccion'])) $direccion = $config['direccion'];
        if (!empty($config['facebook'])) $facebook = $config['facebook'];
        if (!empty($config['instagram'])) $instagram = $config['instagram'];
        if (!empty($config['tiktok'])) $tiktok = $config['tiktok'];
    }
} catch (Exception $e) {
    // Silencioso
}
?>

<footer class="mt-auto py-5 position-relative overflow-hidden" style="background: rgba(7, 10, 15, 0.95); backdrop-filter: blur(24px); border-top: 1px solid rgba(255, 255, 255, 0.08);">
    <!-- Marca de agua sutil gigante en el fondo del footer -->
    <div style="position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); font-family: var(--font-display); font-size: clamp(4rem, 12vw, 10rem); font-weight: 800; color: rgba(255, 255, 255, 0.025); white-space: nowrap; pointer-events: none; user-select: none;">
        ECOTIENDA HN
    </div>

    <!-- Destello Ambiental de Cierre -->
    <div style="position: absolute; bottom: -60px; left: 50%; transform: translateX(-50%); width: 700px; height: 180px; background: radial-gradient(ellipse, rgba(16, 185, 129, 0.12) 0%, transparent 70%); pointer-events: none;"></div>

    <div class="container position-relative z-1">
        <div class="row g-4 justify-content-between">
            <!-- Columna de Identidad & Propósito -->
            <div class="col-lg-4 col-md-6">
                <h5 class="text-white fw-bold mb-3 d-flex align-items-center" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                    <span class="text-success me-2"><i class="fas fa-leaf"></i></span>
                    <?php echo sanitize($nombre_tienda); ?>
                </h5>
                <p class="text-slate-400" style="font-size: 0.92rem; line-height: 1.65; color: #94a3b8; max-width: 340px;">
                    Plataforma de e-commerce sostenible en Honduras. Impulsamos alternativas orgánicas, biodegradables y de comercio justo local para una vida consciente.
                </p>
                <div class="d-flex gap-2.5 mt-4">
                    <?php if ($facebook != '#'): ?>
                    <a href="<?php echo sanitize($facebook); ?>" class="btn-social-circle" target="_blank" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($instagram != '#'): ?>
                    <a href="<?php echo sanitize($instagram); ?>" class="btn-social-circle" target="_blank" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($tiktok != '#'): ?>
                    <a href="<?php echo sanitize($tiktok); ?>" class="btn-social-circle" target="_blank" title="TikTok">
                        <i class="fab fa-tiktok"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Navegación -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="text-white fw-semibold mb-3.5" style="font-family: var(--font-display);">Eco-Navegación</h6>
                <ul class="list-unstyled d-flex flex-column gap-2.5" style="font-size: 0.9rem;">
                    <li><a href="<?php echo BASE_URL; ?>index.php" class="footer-link">Inicio</a></li>
                    <li><a href="<?php echo BASE_URL; ?>tienda.php" class="footer-link">Eco Tienda</a></li>
                    <li><a href="<?php echo BASE_URL; ?>sobre_nosotros.php" class="footer-link">Sobre Nosotros</a></li>
                    <li><a href="<?php echo BASE_URL; ?>faq.php" class="footer-link">Preguntas Frecuentes</a></li>
                </ul>
            </div>

            <!-- Atención al Cliente -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="text-white fw-semibold mb-3.5" style="font-family: var(--font-display);">Atención</h6>
                <ul class="list-unstyled d-flex flex-column gap-2.5" style="font-size: 0.9rem;">
                    <li><a href="<?php echo BASE_URL; ?>contacto.php" class="footer-link">Contacto</a></li>
                    <li><a href="<?php echo BASE_URL; ?>perfil.php" class="footer-link">Mi Perfil</a></li>
                    <li><a href="<?php echo BASE_URL; ?>mis_pedidos.php" class="footer-link">Mis Pedidos</a></li>
                </ul>
            </div>

            <!-- Contacto Directo Honduras -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-semibold mb-3.5" style="font-family: var(--font-display);">Honduras Sostenible</h6>
                <ul class="list-unstyled d-flex flex-column gap-3" style="font-size: 0.9rem; color: #94a3b8;">
                    <li class="d-flex align-items-start gap-2.5">
                        <i class="fas fa-location-dot text-success mt-1"></i>
                        <span><?php echo sanitize($direccion); ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-2.5">
                        <i class="fas fa-phone text-success"></i>
                        <span><?php echo sanitize($telefono); ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-2.5">
                        <i class="fas fa-envelope text-success"></i>
                        <span><?php echo sanitize($correo); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="my-4" style="border-color: rgba(255, 255, 255, 0.08);">

        <div class="row g-3 align-items-center justify-content-between text-secondary" style="font-size: 0.85rem; color: #64748b;">
            <div class="col-md-6 text-center text-md-start">
                &copy; <?php echo date('Y'); ?> <strong class="text-slate-300"><?php echo sanitize($nombre_tienda); ?></strong>. Comercio responsable con impacto real en Honduras 🇭🇳.
            </div>
            <div class="col-md-6 text-center text-md-end d-flex justify-content-center justify-content-md-end gap-3 text-slate-400">
                <span title="Pagos Seguros" class="hover-glow-icon"><i class="fab fa-cc-paypal fa-2x"></i></span>
                <span title="Tarjetas de Crédito de Honduras" class="hover-glow-icon"><i class="fab fa-cc-visa fa-2x"></i></span>
                <span title="Mastercard Segura" class="hover-glow-icon"><i class="fab fa-cc-mastercard fa-2x"></i></span>
            </div>
        </div>
    </div>
</footer>

<style>
    .btn-social-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .btn-social-circle:hover {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
        border-color: rgba(16, 185, 129, 0.4);
        transform: translateY(-3px) scale(1.1);
        box-shadow: 0 0 20px rgba(16, 185, 129, 0.35);
    }
    .footer-link {
        color: #94a3b8;
        text-decoration: none;
        transition: all 0.25s ease;
    }
    .footer-link:hover {
        color: #10b981;
        padding-left: 4px;
    }
    .hover-glow-icon {
        transition: all 0.3s ease;
        opacity: 0.65;
    }
    .hover-glow-icon:hover {
        opacity: 1;
        color: #10b981;
        transform: scale(1.12);
    }
</style>

<!-- Bootstrap 5 Bundle con Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- Fondo Ferrofluid (WebGL, port vanilla de React Bits) -->
<script src="<?php echo BASE_URL; ?>assets/js/ferrofluid-bg.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.EcoFerrofluid) {
        window.EcoFerrofluid.init('ferrofluidBg', {
            colors: ['#10B981', '#06B6D4', '#059669'],
            speed: 0.35,
            scale: 1.4,
            turbulence: 0.7,
            fluidity: 0.14,
            rimWidth: 0.16,
            sharpness: 2.4,
            shimmer: 1,
            glow: 1.4,
            flowDirection: 'down',
            opacity: 0.4,
            mouseInteraction: true,
            mouseStrength: 0.7,
            mouseRadius: 0.3
        });
    }
});
</script>

</body>
</html>
