<?php
/**
 * 🌱 ECOTIENDA HN - PIE DE PÁGINA COMÚN
 * Ruta: /includes/footer.php
 * Descripción: Finaliza el documento HTML5, carga los bloques de JavaScript de Bootstrap, FontAwesome y gestiona el interruptor de Dark/Light theme local.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Cargar configs de contacto de forma dinámica si están cargadas en db
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

<footer class="mt-auto py-5 bg-dark text-light border-top border-secondary">
    <div class="container">
        <div class="row g-4 justify-content-between">
            <!-- Columna de Identidad -->
            <div class="col-lg-4 col-md-6">
                <h5 class="text-white fw-bold mb-3 d-flex align-items-center">
                    <span class="text-success me-2"><i class="fas fa-leaf"></i></span>
                    <?php echo sanitize($nombre_tienda); ?>
                </h5>
                <p class="text-secondary" style="font-size: 0.95rem; line-height: 1.6;">
                    La tienda ecológica líder de Honduras. Ofrecemos alternativas ecológicas, orgánicas y sustentables que cuidan de ti y de la naturaleza. comprometidos con el ambiente.
                </p>
                <div class="d-flex gap-2 mt-3">
                    <?php if ($facebook != '#'): ?>
                    <a href="<?php echo sanitize($facebook); ?>" class="btn btn-outline-success btn-sm rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;" target="_blank">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($instagram != '#'): ?>
                    <a href="<?php echo sanitize($instagram); ?>" class="btn btn-outline-success btn-sm rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;" target="_blank">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($tiktok != '#'): ?>
                    <a href="<?php echo sanitize($tiktok); ?>" class="btn btn-outline-success btn-sm rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;" target="_blank">
                        <i class="fab fa-tiktok"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Enlaces Rápidos -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="text-white fw-semibold mb-3">Eco-Navegación</h6>
                <ul class="list-unstyled d-flex flex-column gap-2" style="font-size: 0.9rem;">
                    <li><a href="<?php echo BASE_URL; ?>index.php" class="text-secondary text-decoration-none hover-white">Inicio</a></li>
                    <li><a href="<?php echo BASE_URL; ?>tienda.php" class="text-secondary text-decoration-none hover-white">Eco Tienda</a></li>
                    <li><a href="<?php echo BASE_URL; ?>sobre_nosotros.php" class="text-secondary text-decoration-none hover-white">Sobre Nosotros</a></li>
                    <li><a href="<?php echo BASE_URL; ?>faq.php" class="text-secondary text-decoration-none hover-white">Preguntas Frecuentes</a></li>
                </ul>
            </div>

            <!-- Soporte al Cliente -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="text-white fw-semibold mb-3">Atención</h6>
                <ul class="list-unstyled d-flex flex-column gap-2" style="font-size: 0.9rem;">
                    <li><a href="<?php echo BASE_URL; ?>contacto.php" class="text-secondary text-decoration-none hover-white">Contacto</a></li>
                    <li><a href="<?php echo BASE_URL; ?>perfil.php" class="text-secondary text-decoration-none hover-white">Mi Perfil</a></li>
                    <li><a href="<?php echo BASE_URL; ?>mis_pedidos.php" class="text-secondary text-decoration-none hover-white">Mis Compras</a></li>
                </ul>
            </div>

            <!-- Contacto Directo de Honduras -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-semibold mb-3">Honduras Sostenible</h6>
                <ul class="list-unstyled d-flex flex-column gap-3 text-secondary" style="font-size: 0.9rem;">
                    <li class="d-flex align-items-start gap-2">
                        <i class="fas fa-location-dot text-success mt-1"></i>
                        <span><?php echo sanitize($direccion); ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="fas fa-phone text-success"></i>
                        <span><?php echo sanitize($telefono); ?></span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="fas fa-envelope text-success"></i>
                        <span><?php echo sanitize($correo); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="my-4 border-secondary opacity-25">

        <div class="row g-2 align-items-center justify-content-between text-secondary" style="font-size: 0.85rem;">
            <div class="col-md-6 text-center text-md-start">
                &copy; <?php echo date('Y'); ?> <strong><?php echo sanitize($nombre_tienda); ?></strong>. Hecho con ❤️ para Honduras. Todos los derechos reservados.
            </div>
            <div class="col-md-6 text-center text-md-end d-flex justify-content-center justify-content-md-end gap-3">
                <span title="Pagos Seguros"><i class="fab fa-cc-paypal fa-2x"></i></span>
                <span title="Tarjetas de Crédito de Honduras"><i class="fab fa-cc-visa fa-2x"></i></span>
                <span title="Mastercard Segura"><i class="fab fa-cc-mastercard fa-2x"></i></span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle con Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script dinámico para control de Temas (Light / Dark) -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const themeToggleBtn = document.getElementById("themeToggleBtn");
        const bodyEl = document.body;

        // Comprobar si hay preferencia previa guardada
        const savedTheme = localStorage.getItem("eco-theme") || "dark";
        
        if (savedTheme === "dark") {
            bodyEl.classList.add("dark-theme");
            if(themeToggleBtn) {
                themeToggleBtn.innerHTML = '<i class="fas fa-sun text-warning"></i>';
            }
        } else {
            bodyEl.classList.remove("dark-theme");
            if(themeToggleBtn) {
                themeToggleBtn.innerHTML = '<i class="fas fa-moon text-secondary"></i>';
            }
        }

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener("click", function() {
                if (bodyEl.classList.contains("dark-theme")) {
                    bodyEl.classList.remove("dark-theme");
                    themeToggleBtn.innerHTML = '<i class="fas fa-moon text-secondary"></i>';
                    localStorage.setItem("eco-theme", "light");
                } else {
                    bodyEl.classList.add("dark-theme");
                    themeToggleBtn.innerHTML = '<i class="fas fa-sun text-warning"></i>';
                    localStorage.setItem("eco-theme", "dark");
                }
            });
        }
    });
</script>
</body>
</html>
