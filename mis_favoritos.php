<?php
/**
 * 🌱 ECOTIENDA HN - MIS FAVORITOS
 * Ruta: /mis_favoritos.php
 * Descripción: Página protegida que muestra todos los productos que el usuario
 *              ha marcado como favoritos. Permite quitar favoritos via AJAX.
 */

$pageTitle = "Mis Favoritos";
require_once __DIR__ . '/includes/navbar.php';

requireLogin();

$favoritos = [];

try {
    $db = Database::getConnection();
    $stmt = $db->prepare(
        "SELECT f.id AS fav_id, f.producto_id,
                p.nombre, p.descripcion_corta, p.precio, p.precio_oferta,
                p.stock, p.imagen_principal, c.nombre AS categoria_nombre,
                COALESCE(p.precio_oferta, p.precio) AS precio_actual
         FROM favoritos f
         INNER JOIN productos p ON f.producto_id = p.id
         LEFT JOIN  categorias c ON p.categoria_id = c.id
         WHERE f.usuario_id = :uid AND p.estado != 'inactivo'
         ORDER BY f.fecha DESC"
    );
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    $favoritos = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("mis_favoritos.php: " . $e->getMessage());
}
?>

<div class="container py-5">

    <!-- CABECERA -->
    <div class="d-flex align-items-center justify-content-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="fw-bold mb-1" style="font-family:var(--font-display);">
                <i class="fas fa-heart text-danger me-2"></i> Mis Favoritos
            </h1>
            <p class="text-secondary mb-0">
                Los productos ecológicos que has guardado con cariño.
            </p>
        </div>
        <?php if (!empty($favoritos)): ?>
            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-bold fs-6">
                <?php echo count($favoritos); ?> producto<?php echo count($favoritos) !== 1 ? 's' : ''; ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($favoritos)): ?>
        <!-- ESTADO VACÍO -->
        <div class="text-center py-5 my-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 mb-4"
                 style="width:110px;height:110px;">
                <i class="far fa-heart text-danger" style="font-size:3.2rem;"></i>
            </div>
            <h3 class="fw-bold mb-3" style="font-family:var(--font-display);">
                Aún no tienes favoritos
            </h3>
            <p class="text-secondary mb-4 col-md-6 mx-auto" style="font-size:1.05rem;line-height:1.7;">
                Explora nuestra tienda y toca el corazón ❤️ de los productos que te gustan para guardarlos aquí.
                ¡Cada elección sostenible suma por Honduras!
            </p>
            <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-eco-primary btn-lg px-5 fw-bold rounded-3">
                <i class="fas fa-leaf me-2"></i> Explorar la EcoTienda
            </a>
        </div>

    <?php else: ?>
        <!-- GRID DE FAVORITOS -->
        <div class="row g-4" id="favGrid">
            <?php foreach ($favoritos as $fav): ?>
                <div class="col-xl-3 col-lg-4 col-md-6" id="fav-item-<?php echo $fav['fav_id']; ?>">
                    <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius:16px;transition:transform .2s;">

                        <!-- Badges -->
                        <?php if (!empty($fav['precio_oferta'])): ?>
                            <span class="badge bg-danger position-absolute top-0 start-0 m-3 z-3">¡Oferta!</span>
                        <?php endif; ?>
                        <?php if ($fav['stock'] <= 0): ?>
                            <span class="badge bg-secondary position-absolute top-0 end-0 m-3 z-3">Agotado</span>
                        <?php endif; ?>

                        <!-- Imagen -->
                        <div class="position-relative overflow-hidden" style="height:210px;background:#fff;">
                            <img src="<?php echo !empty($fav['imagen_principal']) ? $fav['imagen_principal'] : 'https://placehold.co/500x500/10b981/white?text=🌿'; ?>"
                                 class="w-100 h-100 object-fit-cover" alt="<?php echo sanitize($fav['nombre']); ?>">
                        </div>

                        <div class="card-body p-4 d-flex flex-column">
                            <span class="text-success small fw-bold mb-1">
                                <?php echo sanitize($fav['categoria_nombre'] ?? 'Ecológico'); ?>
                            </span>

                            <h5 class="fw-bold mb-2" style="font-size:.97rem;line-height:1.4;">
                                <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $fav['producto_id']; ?>"
                                   class="text-decoration-none text-reset">
                                    <?php echo sanitize($fav['nombre']); ?>
                                </a>
                            </h5>

                            <p class="text-secondary small mb-4" style="font-size:.83rem;flex-grow:1;
                               display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                <?php echo sanitize($fav['descripcion_corta']); ?>
                            </p>

                            <!-- Precio -->
                            <div class="mb-3">
                                <?php if (!empty($fav['precio_oferta'])): ?>
                                    <span class="text-danger fw-bold" style="font-size:1.1rem;">
                                        <?php echo formatCurrency($fav['precio_oferta']); ?>
                                    </span>
                                    <span class="text-secondary text-decoration-line-through small d-block">
                                        <?php echo formatCurrency($fav['precio']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="fw-bold" style="font-size:1.1rem;">
                                        <?php echo formatCurrency($fav['precio']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Acciones -->
                            <div class="d-flex gap-2 mt-auto">
                                <?php if ($fav['stock'] > 0): ?>
                                    <form action="<?php echo BASE_URL; ?>carrito.php" method="POST" class="m-0 flex-grow-1">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="producto_id" value="<?php echo $fav['producto_id']; ?>">
                                        <input type="hidden" name="cantidad" value="1">
                                        <button type="submit" class="btn btn-eco-primary btn-sm w-100 fw-bold">
                                            <i class="fas fa-cart-plus me-1"></i> Añadir
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-sm flex-grow-1" disabled>
                                        <i class="fas fa-ban me-1"></i> Agotado
                                    </button>
                                <?php endif; ?>

                                <!-- Quitar de favoritos -->
                                <button class="btn btn-outline-danger btn-sm px-3 fav-remove-btn"
                                        data-fav-id="<?php echo $fav['fav_id']; ?>"
                                        data-prod-id="<?php echo $fav['producto_id']; ?>"
                                        title="Quitar de favoritos">
                                    <i class="fas fa-heart-broken"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- FOOTER ACCIONES -->
        <div class="mt-5 text-center">
            <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-outline-success px-4 fw-bold rounded-3">
                <i class="fas fa-plus-circle me-2"></i> Descubrir más productos
            </a>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
    const BASE = '<?php echo BASE_URL; ?>';

    function showToast(msg, danger) {
        let t = document.getElementById('ecoToastFav');
        if (!t) {
            t = document.createElement('div');
            t.id = 'ecoToastFav';
            t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:.9rem;box-shadow:0 4px 20px rgba(0,0,0,.3);transition:opacity .3s ease;color:#fff;';
            document.body.appendChild(t);
        }
        t.style.background = danger ? '#dc2626' : '#064e3b';
        t.textContent = msg;
        t.style.opacity = '1';
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.style.opacity = '0', 2800);
    }

    document.querySelectorAll('.fav-remove-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const favId  = this.dataset.favId;
            const prodId = parseInt(this.dataset.prodId);
            const card   = document.getElementById('fav-item-' + favId);

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(BASE + 'api/favoritos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ producto_id: prodId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && !data.favorito) {
                    // Animar y quitar la tarjeta
                    card.style.transition = 'opacity .35s ease, transform .35s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(.92)';
                    setTimeout(() => {
                        card.remove();
                        // Si no quedan favoritos, recargar para mostrar estado vacío
                        if (!document.querySelector('[id^="fav-item-"]')) {
                            location.reload();
                        }
                    }, 380);
                    showToast('¡Quitado de favoritos!', false);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-heart-broken"></i>';
                    showToast('No se pudo quitar. Intenta de nuevo.', true);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-heart-broken"></i>';
                showToast('Error de conexión. Intenta de nuevo.', true);
            });
        });
    });
})();
</script>
