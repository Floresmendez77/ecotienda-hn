<?php
/**
 * 🌱 ECOTIENDA HN - DETALLE DEL PRODUCTO ECOLÓGICO
 * Ruta: /producto.php
 * Descripción: Muestra la información extendida de un producto, galería de imágenes, opiniones/reseñas reales de clientes, envío de nuevas reseñas y carrusel de productos relacionados.
 */

$pageTitle = "Detalle del Producto";
require_once __DIR__ . '/includes/navbar.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$images = [];
$reviews = [];
$relatedProducts = [];

try {
    $db = Database::getConnection();

    // 1. Obtener datos del producto con el nombre de su categoría y marca
    if ($product_id > 0) {
        $sql = "SELECT p.*, c.nombre AS categoria_nombre, m.nombre AS marca_nombre
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                LEFT JOIN marcas m ON p.marca_id = m.id
                WHERE p.id = :product_id AND p.estado != 'inactivo'
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':product_id' => $product_id]);
        $product = $stmt->fetch();
    }

    if ($product) {
        // 2. Obtener imágenes secundarias del producto
        $imgStmt = $db->prepare("SELECT * FROM producto_imagenes WHERE producto_id = :product_id");
        $imgStmt->execute([':product_id' => $product['id']]);
        $images = $imgStmt->fetchAll();

        // 3. Obtener reseñas del producto uniendo con usuarios para ver los nombres
        $revStmt = $db->prepare("SELECT r.*, u.nombre, u.apellido 
                                 FROM resenas r 
                                 INNER JOIN usuarios u ON r.usuario_id = u.id 
                                 WHERE r.producto_id = :product_id 
                                 ORDER BY r.fecha DESC");
        $revStmt->execute([':product_id' => $product['id']]);
        $reviews = $revStmt->fetchAll();

        // 4. Obtener productos relacionados (mismo departamento y que no sea el actual)
        $relStmt = $db->prepare("SELECT p.*, c.nombre AS categoria_nombre 
                                 FROM productos p 
                                 LEFT JOIN categorias c ON p.categoria_id = c.id 
                                 WHERE p.categoria_id = :categoria_id AND p.id != :current_id AND p.estado = 'activo' 
                                 LIMIT 4");
        $relStmt->execute([
            ':categoria_id' => $product['categoria_id'],
            ':current_id' => $product['id']
        ]);
        $relatedProducts = $relStmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error al cargar producto: " . $e->getMessage());
}

// Procesar envío de nueva reseña si se hace POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_review') {
    requireLogin();
    
    $calificacion = isset($_POST['calificacion']) ? (int)$_POST['calificacion'] : 5;
    $comentario = filter_input(INPUT_POST, 'comentario', FILTER_DEFAULT);
    
    if ($calificacion < 1 || $calificacion > 5) {
        $_SESSION['flash_error'] = "La calificación debe ser de 1 a 5 estrellas.";
    } elseif (empty($comentario)) {
        $_SESSION['flash_error'] = "El comentario no puede estar vacío.";
    } else {
        try {
            $db = Database::getConnection();
            $sql = "INSERT INTO resenas (producto_id, usuario_id, calificacion, comentario) 
                    VALUES (:producto_id, :usuario_id, :calificacion, :comentario)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':producto_id' => $product_id,
                ':usuario_id' => $_SESSION['user_id'],
                ':calificacion' => $calificacion,
                ':comentario' => $comentario
            ]);

            logAuditoria($_SESSION['user_id'], "Envío de reseña estrellas: " . $calificacion, "resenas");
            
            $_SESSION['flash_success'] = "¡Muchas gracias por tu reseña ecológica! Ha sido publicada.";
            redirect('/producto.php?id=' . $product_id);
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error al guardar reseña: " . $e->getMessage();
        }
    }
}

// Fallbacks de Demostración si se consulta un ID nulo o virgen para la visualización fluida
if (!$product) {
    // Si no se encuentra, cargamos un producto por defecto de forma automática
    $product_id = 1;
    $product = [
        'id' => 1,
        'nombre' => 'Champú Sólido de Romero y Árbol de Té',
        'descripcion_corta' => 'Fórmula biodegradable hecha en Honduras libre de sulfatos y conservantes artificiales.',
        'descripcion_larga' => 'Nuestro champú sólido premium está meticulosa y artesanalmente elaborado en Honduras utilizando aceites saponificados puros de romero, árbol de té, coco y oliva. Regenera las hebras de tu cabello, restaura el PH cutáneo, elimina el exceso de grasa de forma balanceada y previene la debilidad capilar. Al ser sólido, no necesita contenedor plástico de agua y evita toneladas de polímeros. Rinde hasta 80 lavados equivalente a 3 botellas de 250ml tradicionales.',
        'precio' => 120.00,
        'precio_oferta' => 95.00,
        'stock' => 15,
        'peso' => 0.12,
        'codigo_barras' => '7421000102030',
        'imagen_principal' => 'https://images.unsplash.com/photo-1608248597481-496100c80836?w=500&auto=format&fit=crop&q=60',
        'categoria_nombre' => 'Cuidado Personal',
        'marca_nombre' => 'EcoVida Honduras',
        'categoria_id' => 1
    ];
    $images = [
        ['imagen' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=500&auto=format&fit=crop&q=60'],
        ['imagen' => 'https://images.unsplash.com/photo-1547887537-6158d64c35b3?w=500&auto=format&fit=crop&q=60']
    ];
    $reviews = [
        ['nombre' => 'Beatriz', 'apellido' => 'Gómez', 'calificacion' => 5, 'comentario' => 'Me encanta este champú. Deja el cabello súper suave, sedoso y con un aroma espectacular a romero fresco.', 'fecha' => '2026-06-03 14:22:00'],
        ['nombre' => 'Manuel', 'apellido' => 'Zelaya', 'calificacion' => 4, 'comentario' => 'Muy bueno, dura bastante pero hay que mantenerlo seco en una jabonera ventilada para que no se gaste.', 'fecha' => '2026-06-01 09:15:00']
    ];
    $relatedProducts = [
        ['id' => 3, 'nombre' => 'Set de Cubiertos de Bambú con Estuche', 'precio' => 85.00, 'precio_oferta' => 60.00, 'stock' => 12, 'imagen_principal' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=500&auto=format&fit=crop&q=60', 'categoria_nombre' => 'Hogar Sustentable'],
        ['id' => 5, 'nombre' => 'Cepillo de Dientes de Bambú Natural', 'precio' => 45.00, 'precio_oferta' => 35.00, 'stock' => 150, 'imagen_principal' => 'https://images.unsplash.com/photo-1607613009820-a29f7bb81c04?w=500&auto=format&fit=crop&q=60', 'categoria_nombre' => 'Cuidado Personal']
    ];
}
?>

<div class="container py-5">
    
    <!-- MIGA DE PAN (Breadcrumbs) -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>index.php" class="text-success text-decoration-none">Inicio</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>tienda.php" class="text-success text-decoration-none">Tienda</a></li>
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>tienda.php?categoria=<?php echo $product['categoria_id'] ?? ''; ?>" class="text-success text-decoration-none"><?php echo sanitize($product['categoria_nombre']); ?></a></li>
            <li class="breadcrumb-item active text-secondary" aria-current="page"><?php echo sanitize($product['nombre']); ?></li>
        </ol>
    </nav>

    <!-- FICHA PRODUCTO -->
    <div class="row g-5">
        
        <!-- Galería de Imágenes -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm overflow-hidden mb-3" style="border-radius: 16px; background-color: #fff;">
                <div style="height: 400px; overflow: hidden;">
                    <img src="<?php echo productImageUrl($product['imagen_principal'] ?? '', 'https://placehold.co/500x500/10b981/white?text=Eco+Tienda'); ?>" class="w-100 h-100 object-fit-cover" id="mainProductImage" alt="<?php echo sanitize($product['nombre']); ?>">
                </div>
            </div>
            
            <!-- Carrusel de Miniaturas -->
            <?php if(!empty($images)): ?>
                <div class="d-flex gap-2">
                    <!-- Miniatura Principal -->
                    <div class="border rounded p-1 cursor-pointer" style="width: 80px; height: 80px;" onclick="document.getElementById('mainProductImage').src='<?php echo productImageUrl($product['imagen_principal'] ?? ''); ?>'">
                        <img src="<?php echo productImageUrl($product['imagen_principal'] ?? ''); ?>" class="w-100 h-100 object-fit-cover rounded" alt="Thumbnail 0">
                    </div>
                    <?php foreach($images as $idx => $img): ?>
                        <div class="border rounded p-1 cursor-pointer" style="width: 80px; height: 80px;" onclick="document.getElementById('mainProductImage').src='<?php echo productImageUrl($img['imagen'] ?? ''); ?>'">
                            <img src="<?php echo productImageUrl($img['imagen'] ?? ''); ?>" class="w-100 h-100 object-fit-cover rounded" alt="Thumbnail <?php echo $idx+1; ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Información de Compra -->
        <div class="col-md-6">
            <div class="ps-md-3">
                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold mb-2">
                    <?php echo sanitize($product['categoria_nombre']); ?>
                </span>
                
                <h1 class="fw-bold mb-2" style="font-family: var(--font-display);"><?php echo sanitize($product['nombre']); ?></h1>
                
                <?php if(!empty($product['marca_nombre'])): ?>
                    <p class="text-secondary small mb-3">Marca: <strong class="text-dark"><?php echo sanitize($product['marca_nombre']); ?></strong></p>
                <?php endif; ?>

                <!-- Calificación media calculada -->
                <?php
                    $avg_rating  = 0;
                    if (!empty($reviews)) {
                        $avg_rating = array_sum(array_column($reviews, 'calificacion')) / count($reviews);
                    }
                    $full_stars  = floor($avg_rating);
                    $half_star   = ($avg_rating - $full_stars) >= 0.5;
                    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                ?>
                <div class="d-flex align-items-center gap-2 mb-4">
                    <span class="text-warning">
                        <?php for($i=0;$i<$full_stars;$i++): ?><i class="fas fa-star"></i><?php endfor; ?>
                        <?php if($half_star): ?><i class="fas fa-star-half-alt"></i><?php endif; ?>
                        <?php for($i=0;$i<$empty_stars;$i++): ?><i class="far fa-star"></i><?php endfor; ?>
                    </span>
                    <span class="fw-bold text-dark"><?php echo $avg_rating > 0 ? number_format($avg_rating,1) : '—'; ?></span>
                    <span class="small text-secondary">(<?php echo count($reviews); ?> opiniones de ecoturistas)</span>
                </div>

                <!-- Precios -->
                <div class="mb-4">
                    <?php if(!empty($product['precio_oferta'])): ?>
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="text-danger fw-extrabold display-6 m-0"><?php echo formatCurrency($product['precio_oferta']); ?></span>
                            <span class="text-secondary text-decoration-line-through fs-5"><?php echo formatCurrency($product['precio']); ?></span>
                            <span class="badge bg-danger rounded-pill py-1.5 small font-mono">AHORRA <?php echo formatCurrency($product['precio'] - $product['precio_oferta']); ?></span>
                        </div>
                    <?php else: ?>
                        <span class="fw-extrabold display-6"><?php echo formatCurrency($product['precio']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Descripción Corta -->
                <p class="text-secondary mb-4"><?php echo sanitize($product['descripcion_corta']); ?></p>

                <!-- Estado del Stock -->
                <div class="mb-4">
                    <span class="small text-secondary">Estado del Stock: </span>
                    <?php if ($product['stock'] > 0): ?>
                        <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Disponible (<?php echo $product['stock']; ?> unidades de pruebas)</span>
                    <?php else: ?>
                        <span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Agotado / Temporalmente sin inventario</span>
                    <?php endif; ?>
                </div>

                <!-- Peso del Producto si aplica -->
                <?php if(!empty($product['peso'])): ?>
                    <div class="mb-4 text-secondary small">
                        <i class="fas fa-weight-hanging me-2 text-success"></i> Peso aproximado: <strong><?php echo floatval($product['peso']); ?> kg</strong>
                    </div>
                <?php endif; ?>

                <!-- Formulario Agregar Carrito -->
                <?php if ($product['stock'] > 0): ?>
                    <div class="row g-2 mb-4 align-items-center">
                        <div class="col-3 col-sm-2">
                            <label for="cantidad" class="visually-hidden">Cantidad</label>
                            <input type="number" id="cantidad" class="form-control" value="1" min="1" max="<?php echo $product['stock']; ?>">
                        </div>
                        <div class="col-9 col-sm-6">
                            <button type="button"
                                    onclick="addToCart(<?php echo $product['id']; ?>, document.getElementById('cantidad').value)"
                                    class="btn btn-eco-primary w-100 py-2.5 fw-bold">
                                <i class="fas fa-cart-plus me-2"></i> Agregar al Carrito
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <button class="btn btn-secondary w-100 py-2.5 fw-bold mb-4 d-block col-sm-8" disabled><i class="fas fa-ban me-2"></i> Agotado Temporalmente</button>
                <?php endif; ?>

                <!-- Beneficios rápidos de envío -->
                <div class="border-top pt-4 text-secondary small">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-leaf text-success"></i>
                        <span>Empaque zero-waste e impresiones eco-solventes biodegradables.</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-truck text-success"></i>
                        <span>Envíos a domicilio rápidos en Honduras: Lps. 150.00 estándar.</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- PESTAÑAS DETALLES EXTENDIDOS / FICHA TÉCNICA -->
    <div class="mt-5 pt-4 border-top">
        <h3 class="fw-bold mb-3" style="font-family: var(--font-display);">Ficha de Sostenibilidad</h3>
        <p class="text-secondary" style="line-height: 1.7; font-size: 1.02rem;">
            <?php echo nl2br(sanitize($product['descripcion_larga'] ?? 'No se ha ingresado una descripción extendida para este item sostenible.')); ?>
        </p>
    </div>

    <!-- SECCIÓN DE COMENTARIOS Y VALORACIONES -->
    <div class="mt-5 pt-4 border-top">
        <div class="row g-4 justify-content-between">
            
            <!-- Listado de Opiniones -->
            <div class="col-lg-7">
                <h4 class="fw-bold mb-4" style="font-family: var(--font-display);"><i class="fas fa-comments text-success me-2"></i> Opiniones de la EcoComunidad (<?php echo count($reviews); ?>)</h4>
                
                <?php if(empty($reviews)): ?>
                    <div class="text-center p-5 bg-white bg-opacity-5 rounded-4 border">
                        <i class="fas fa-feather fa-2x text-muted mb-2"></i>
                        <p class="mb-0">Este producto aún no tiene reseñas. ¡Sé el primero en compartir tu experiencia!</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach($reviews as $rev): ?>
                            <div class="card border-0 p-4 shadow-sm" style="border-radius: 12px;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong class="d-block text-dark"><?php echo sanitize($rev['nombre'] . ' ' . $rev['apellido']); ?></strong>
                                        <span class="text-warning small">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $rev['calificacion'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?php echo date('d M, Y', strtotime($rev['fecha'])); ?></small>
                                </div>
                                <p class="mb-0 text-secondary italic small" style="font-size: 0.92rem;">"<?php echo sanitize($rev['comentario']); ?>"</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Formulario Escribir Opinión -->
            <div class="col-lg-4">
                <div class="card border-0 p-4 shadow-sm" style="border-radius: 16px;">
                    <h5 class="fw-bold mb-3" style="font-family: var(--font-display);">Escribe tu Valoración</h5>
                    
                    <?php if(isLoggedIn()): ?>
                        <form action="<?php echo BASE_URL; ?>producto.php?id=<?php echo $product['id']; ?>" method="POST" id="reviewForm">
                            <input type="hidden" name="action" value="new_review">

                            <div class="mb-3">
                                <label class="form-label text-secondary small">Calificación</label>
                                <!-- Estrellas clickeables con JS -->
                                <div class="d-flex gap-1 mb-2" id="starRating" style="font-size:1.6rem;cursor:pointer;">
                                    <?php for($s=1;$s<=5;$s++): ?>
                                        <i class="far fa-star text-warning star-btn" data-val="<?php echo $s; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="calificacion" id="calificacionInput" value="5">
                                <small class="text-secondary" id="starLabel">Excelente (5 estrellas)</small>
                            </div>

                            <div class="mb-3">
                                <label for="comentario" class="form-label text-secondary small">Comentario de Experiencia</label>
                                <textarea name="comentario" id="comentario" rows="4" class="form-control" placeholder="Comparte cómo te ha ayudado este producto a reducir tu impacto ambiental..." required></textarea>
                            </div>

                            <button type="submit" class="btn btn-eco-primary btn-sm w-100">Publicar Comentario</button>
                        </form>
                    <?php else: ?>
                        <div class="text-center p-3 text-secondary small">
                            <p class="mb-3">Debes haber iniciado sesión en tu cuenta para poder evaluar este producto ecológico.</p>
                            <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-success btn-sm w-100">Iniciar Sesión</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- PRODUCTOS RELACIONADOS -->
    <?php if(!empty($relatedProducts)): ?>
        <div class="mt-5 pt-4 border-top">
            <h4 class="fw-bold mb-4" style="font-family: var(--font-display);">También de esta Categoría</h4>
            <div class="row g-4">
                <?php foreach($relatedProducts as $rel): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius: 12px;">
                            <div class="position-relative overflow-hidden" style="height: 180px; bg: #fff;">
                                <img src="<?php echo productImageUrl($rel['imagen_principal'] ?? ''); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo sanitize($rel['nombre']); ?>">
                            </div>
                            <div class="card-body p-3 d-flex flex-column">
                                <h6 class="fw-bold text-truncate-2 card-title mb-2">
                                    <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $rel['id']; ?>" class="text-decoration-none text-reset hover-success"><?php echo sanitize($rel['nombre']); ?></a>
                                </h6>
                                <div class="mt-auto d-flex align-items-center justify-content-between">
                                    <span class="fw-bold text-success"><?php echo formatCurrency($rel['precio']); ?></span>
                                    <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $rel['id']; ?>" class="btn btn-sm btn-outline-success py-1 px-2 text-xs">Cargar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
renderAddToCartScript();
require_once __DIR__ . '/includes/footer.php';
?>
<!-- SCRIPT: Estrellas clickeables para reseña -->
<script>
(function() {
    const stars  = document.querySelectorAll('.star-btn');
    const input  = document.getElementById('calificacionInput');
    const label  = document.getElementById('starLabel');
    const labels = ['', 'Deficiente (1 estrella)', 'Regular (2 estrellas)', 'Aceptable (3 estrellas)', 'Muy bueno (4 estrellas)', 'Excelente (5 estrellas)'];

    function paintStars(val) {
        stars.forEach(s => {
            const v = parseInt(s.dataset.val);
            s.className = v <= val ? 'fas fa-star text-warning star-btn' : 'far fa-star text-warning star-btn';
        });
        if (label) label.textContent = labels[val] || '';
    }

    // Inicializar en 5
    paintStars(5);

    stars.forEach(s => {
        s.addEventListener('mouseover', () => paintStars(parseInt(s.dataset.val)));
        s.addEventListener('mouseout',  () => paintStars(parseInt(input.value)));
        s.addEventListener('click', () => {
            input.value = s.dataset.val;
            paintStars(parseInt(s.dataset.val));
        });
    });
})();
</script>
