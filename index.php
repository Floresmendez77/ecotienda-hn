<?php
/**
 * 🌱 ECOTIENDA HN - DEPARTAMENTO DE INDEX (LANDING PAGE)
 * Ruta: /index.php
 * Descripción: No es login, no es carrito. Es la portada principal de EcoTienda HN, con Hero, Beneficios de sostenibilidad, Categorías destacadas y Productos ecológicos de la base de datos de Honduras.
 */

$pageTitle = "Inicio - Sostenible y Ecológico";
require_once __DIR__ . '/includes/navbar.php';

// Cargar Categorías y Productos destacados de la Base de Datos
$featuredProducts = [];
$categories = [];

try {
    $db = Database::getConnection();
    
    // Obtener categorías activas
    $catStmt = $db->prepare("SELECT * FROM categorias WHERE estado = 'activo' LIMIT 4");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();
    
    // Obtener productos activos con stock
    $prodStmt = $db->prepare("SELECT p.*, c.nombre AS categoria_nombre FROM productos p 
                              LEFT JOIN categorias c ON p.categoria_id = c.id 
                              WHERE p.estado = 'activo' ORDER BY p.fecha_creacion DESC LIMIT 4");
    $prodStmt->execute();
    $featuredProducts = $prodStmt->fetchAll();
} catch (Exception $e) {
    // Si la base de datos está vacía o el puerto tira error, cargaremos placeholders de primera clase para que sea un sitio espectacular al instante
    error_log("Error al cargar productos en index: " . $e->getMessage());
}

// Fallback de Categorías por si la DB está virgen
if (empty($categories)) {
    $categories = [
        ['id' => 1, 'nombre' => 'Cuidado Personal', 'descripcion' => 'Kits biodegradables, cepillos de madera y champús sólidos.', 'imagen' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=500&auto=format&fit=crop&q=60'],
        ['id' => 2, 'nombre' => 'Hogar Sustentable', 'descripcion' => 'Limpiadores naturales, bolsas de tela de Honduras y filtros de agua.', 'imagen' => 'https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?w=500&auto=format&fit=crop&q=60'],
        ['id' => 3, 'nombre' => 'Moda Ética', 'descripcion' => 'Textiles orgánicos y accesorios reciclados duraderos.', 'imagen' => 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=500&auto=format&fit=crop&q=60']
    ];
}

// Fallback de Productos por si la DB está virgen
if (empty($featuredProducts)) {
    $featuredProducts = [
        [
            'id' => 1,
            'nombre' => 'Champú Sólido de Romero y Árbol de Té',
            'descripcion_corta' => 'Fórmula biodegradable hecha en Honduras libre de sulfatos y conservantes artificiales.',
            'precio' => 120.00,
            'precio_oferta' => 95.00,
            'stock' => 15,
            'imagen_principal' => 'https://images.unsplash.com/photo-1608248597481-496100c80836?w=500&auto=format&fit=crop&q=60',
            'categoria_nombre' => 'Cuidado Personal'
        ],
        [
            'id' => 2,
            'nombre' => 'Bolsas de Algodón Orgánico (Set de 3)',
            'descripcion_corta' => 'Bolsas reutilizables biodegradables para verduras con asa reforzada.',
            'precio' => 140.00,
            'precio_oferta' => null,
            'stock' => 8,
            'imagen_principal' => 'https://images.unsplash.com/photo-1533750349088-cd871a92f311?w=500&auto=format&fit=crop&q=60',
            'categoria_nombre' => 'Hogar Sustentable'
        ],
        [
            'id' => 3,
            'nombre' => 'Set de Cubiertos de Bambú con Estuche de Manta',
            'descripcion_corta' => 'Cubiertos reutilizables ultra livianos y termo-resistentes, ideales para llevar.',
            'precio' => 85.00,
            'precio_oferta' => 60.00,
            'stock' => 25,
            'imagen_principal' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=500&auto=format&fit=crop&q=60',
            'categoria_nombre' => 'Hogar Sustentable'
        ],
        [
            'id' => 4,
            'nombre' => 'Termo Acero de Doble Capa Térmica (500ml)',
            'descripcion_corta' => 'Mantén tus bebidas frías por 24 horas o calientes por 12 horas.',
            'precio' => 350.00,
            'precio_oferta' => null,
            'stock' => 5,
            'imagen_principal' => 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=500&auto=format&fit=crop&q=60',
            'categoria_nombre' => 'Hogar Sustentable'
        ]
    ];
}
?>

<!-- HÉROE PREMIUM (Estilo Apple / Glassmorphism) -->
<section class="position-relative overflow-hidden py-5 d-flex align-items-center" style="min-height: 85vh; background: radial-gradient(circle at top right, rgba(16, 185, 129, 0.15), transparent 50%), radial-gradient(circle at bottom left, rgba(6, 78, 59, 0.25), transparent 40%);">
    <div class="container text-center text-lg-start my-auto">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold mb-3">
                    🌱 100% Ecológico y Sustentable
                </span>
                <h1 class="display-3 fw-bold tracking-tight mb-4" style="font-family: var(--font-display); line-height: 1.15;">
                    Únete al cambio global <br>
                    <span class="text-success">EcoTienda HN</span>
                </h1>
                <p class="lead text-secondary mb-5" style="font-size: 1.15rem; max-width: 600px;">
                    Te ofrecemos alternativas seguras al plástico y químicos industriales en Honduras. Productos biodegradables, éticos y locales para un estilo de vida consciente y equilibrado.
                </p>
                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3">
                    <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-eco-primary btn-lg px-4 d-flex align-items-center gap-2">
                        <i class="fas fa-shopping-bag"></i> Ver Tienda
                    </a>
                    <a href="<?php echo BASE_URL; ?>sobre_nosotros.php" class="btn btn-outline-success btn-lg px-4">
                        Conoce más
                    </a>
                </div>
            </div>
            
            <!-- Ilustración Floating con Vidrio Opaco -->
            <div class="col-lg-5 text-center position-relative">
                <div class="mx-auto position-relative" style="width: 320px; height: 320px; border-radius: 24px; background: url('https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=500&auto=format&fit=crop&q=60') center/cover; box-shadow: 0 20px 50px rgba(16,185,129,0.3); border: 2px solid var(--eco-glass-border)">
                </div>
                <!-- Mini Card flotante Glassmorphic -->
                <div class="position-absolute bottom-0 start-0 p-3 bg-white bg-opacity-20 backdrop-blur rounded-4 d-flex align-items-center gap-3 border shadow-lg m-3 translate-middle-y" style="max-width: 250px; backdrop-filter: blur(8px);">
                    <div class="flex-shrink-0 bg-success text-white p-2 rounded-3">
                        <i class="fas fa-shipping-fast" style="font-size: 1.2rem;"></i>
                    </div>
                    <div class="text-start">
                        <span class="d-block fw-bold text-dark" style="font-size: 0.9rem;">Envíos Rápidos</span>
                        <small class="text-secondary" style="font-size: 0.75rem;">A toda Honduras</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- BENEFICIOS / CARACTERÍSTICAS -->
<section class="py-5 bg-opacity-50">
    <div class="container">
        <div class="row g-4 text-center">
            
            <!-- Beneficio 1 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 p-4 shadow-sm" style="border-radius: 16px;">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-shield-heart fa-lg"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Ingredientes Seguros</h5>
                    <p class="text-secondary mb-0" style="font-size: 0.95rem;">
                        Certificados libres de parabenos, plástico, fragancias sintéticas pesadas o colorantes artificiales dañinos.
                    </p>
                </div>
            </div>

            <!-- Beneficio 2 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 p-4 shadow-sm" style="border-radius: 16px;">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-leaf fa-lg"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Respeto Animal</h5>
                    <p class="text-secondary mb-0" style="font-size: 0.95rem;">
                        Productos 100% libres de crueldad animal y de fuentes éticas, fomentando la flora y fauna sostenible.
                    </p>
                </div>
            </div>

            <!-- Beneficio 3 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 p-4 shadow-sm" style="border-radius: 16px;">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-hands-holding-circle fa-lg"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Economía Local de HN</h5>
                    <p class="text-secondary mb-0" style="font-size: 0.95rem;">
                        Apoyamos a artesanos y empacadores rurales en Honduras, fortaleciendo el comercio justo nacional.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- CATEGORÍAS DESTACADAS -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold display-6" style="font-family: var(--font-display);">Categorías Destacadas</h2>
            <p class="text-secondary">Encuentra alternativas ecológicas perfectas para tu rutina</p>
        </div>
        
        <div class="row g-4">
            <?php foreach($categories as $cat): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 overflow-hidden shadow-sm position-relative group" style="border-radius: 16px; min-height: 250px;">
                        <!-- Imagen de fondo -->
                        <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(0deg, rgba(15, 23, 42, 0.85) 25%, rgba(15, 23, 42, 0.4) 100%), url('<?php echo $cat['imagen']; ?>') center/cover; transition: transform 0.5s ease;">
                        </div>
                        
                        <div class="card-body d-flex flex-column justify-content-end p-4 position-relative text-white mt-auto z-1">
                            <h4 class="fw-bold"><?php echo sanitize($cat['nombre']); ?></h4>
                            <p class="small text-light opacity-75 mb-3"><?php echo sanitize($cat['descripcion']); ?></p>
                            <a href="<?php echo BASE_URL; ?>tienda.php?categoria=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline-success text-white w-fit align-self-start border-white border-opacity-50 hover-text-dark">
                                Explorar <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- PRODUCTOS DESTACADOS -->
<section class="py-5" style="background-color: rgba(30, 41, 59, 0.25);">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-5">
            <div>
                <h2 class="fw-bold display-6" style="font-family: var(--font-display);">Productos Recientes</h2>
                <p class="text-secondary mb-0">Lo último en sustentabilidad directo a tu hogar hondo</p>
            </div>
            <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-outline-success fw-bold">Ver Todo el Catálogo <i class="fas fa-arrow-right ms-2"></i></a>
        </div>

        <div class="row g-4">
            <?php foreach($featuredProducts as $prod): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius: 16px; transition: transform 0.2s ease, box-shadow 0.2s ease;">
                        <!-- Insignia Descuento -->
                        <?php if(!empty($prod['precio_oferta'])): ?>
                            <span class="badge bg-danger position-absolute top-0 start-0 m-3 z-3">
                                ¡Oferta!
                            </span>
                        <?php endif; ?>
                        
                        <!-- Imagen del Producto -->
                        <div class="position-relative overflow-hidden" style="height: 230px; background-color: #fff;">
                            <img src="<?php echo !empty($prod['imagen_principal']) ? BASE_URL . ltrim($prod['imagen_principal'], '/') : 'https://placehold.co/500x500/emerald/white?text=No+Img'; ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo sanitize($prod['nombre']); ?>">
                        </div>

                        <!-- Detalles -->
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="text-success small fw-bold mb-1-5"><?php echo sanitize($prod['categoria_nombre'] ?? 'Ecológico'); ?></span>
                            <h5 class="fw-bold card-title text-truncate-2" style="font-size: 1rem; line-height: 1.4;">
                                <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $prod['id']; ?>" class="text-decoration-none text-reset hover-success"><?php echo sanitize($prod['nombre']); ?></a>
                            </h5>
                            <p class="text-secondary small text-truncate-2 mt-2 mb-4" style="font-size: 0.85rem; flex-grow: 1;">
                                <?php echo sanitize($prod['descripcion_corta']); ?>
                            </p>
                            
                            <!-- Precio y Botones -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary border-opacity-10 mt-auto">
                                <div>
                                    <?php if(!empty($prod['precio_oferta'])): ?>
                                        <span class="text-danger fw-bold m-0" style="font-size: 1.1rem;"><?php echo formatCurrency($prod['precio_oferta']); ?></span>
                                        <span class="text-secondary text-decoration-line-through small d-block" style="font-size: 0.8rem;"><?php echo formatCurrency($prod['precio']); ?></span>
                                    <?php else: ?>
                                        <span class="fw-bold" style="font-size: 1.1rem;"><?php echo formatCurrency($prod['precio']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $prod['id']; ?>" class="btn btn-outline-secondary btn-sm rounded-circle p-2" title="Detalles" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <!-- Formulario para Añadir al Carrito rápidamente -->
                                    <button type="button" onclick="addToCart(<?php echo $prod['id']; ?>, 1)" class="btn btn-eco-primary btn-sm rounded-circle p-2" title="Agregar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TESTIMONIOS -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold display-6" style="font-family: var(--font-display);">Clientes Felices</h2>
            <p class="text-secondary">Comentarios reales de personas cuidando el planeta en Honduras</p>
        </div>

        <div class="row g-4">
            <!-- Testimonio 1 -->
            <div class="col-md-4">
                <div class="card border-0 p-4 shadow-sm" style="border-radius: 16px;">
                    <span class="text-warning mb-3"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></span>
                    <p class="fst-italic text-secondary mb-3">"Los champús sólidos huelen delicioso y dejan el cabello espectacular. Me encanta que la entrega en San Pedro Sula fue súper rápida y sin envoltorios plásticos innecesarios."</p>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 50%; bg-secondary; background: url('https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&auto=format&fit=crop&q=60') center/cover;"></div>
                        <div>
                            <span class="d-block fw-bold" style="font-size: 0.9rem;">María Fernanda Castro</span>
                            <small class="text-muted" style="font-size: 0.75rem;">Sampedrana Consciente</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Testimonio 2 -->
            <div class="col-md-4">
                <div class="card border-0 p-4 shadow-sm" style="border-radius: 16px;">
                    <span class="text-warning mb-3"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></span>
                    <p class="fst-italic text-secondary mb-3">"Los cubiertos de Bambú son de excelente calidad. Los ando en mi mochila de trabajo todos los días y así rechazo siempre los cubiertos plásticos descartables. ¡Gran iniciativa!"</p>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 50%; bg-secondary; background: url('https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=60') center/cover;"></div>
                        <div>
                            <span class="d-block fw-bold" style="font-size: 0.9rem;">Carlos Alvarado</span>
                            <small class="text-muted" style="font-size: 0.75rem;">Comprador del Distrito Central</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Testimonio 3 -->
            <div class="col-md-4">
                <div class="card border-0 p-4 shadow-sm" style="border-radius: 16px;">
                    <span class="text-warning mb-3"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></span>
                    <p class="fst-italic text-secondary mb-3">"Es un placer tener una tienda online ecológica oficial en Honduras. La atención al cliente por WhatsApp y la seguridad del pago por transferencia bancaria fue impecable. Recomiendo."</p>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 44px; height: 44px; border-radius: 50%; bg-secondary; background: url('https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&auto=format&fit=crop&q=60') center/cover;"></div>
                        <div>
                            <span class="d-block fw-bold" style="font-size: 0.9rem;">Gabriela Meza</span>
                            <small class="text-muted" style="font-size: 0.75rem;">Fundadora de EcoComunidad</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- BOLETÍN ECO / NEWSLETTER -->
<section class="py-5 bg-success text-white">
    <div class="container text-center py-4">
        <h3 class="fw-bold" style="font-family: var(--font-display);">Recibe Eco-Consejos y Descuentos</h3>
        <p class="opacity-75 mb-4">Inscríbete a nuestro boletín y obtén un 10% de descuento en tu primera compra con el código ECOHN10</p>
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <form action="#" method="POST" class="d-flex gap-2">
                    <input type="email" class="form-control border-0 py-2.5 px-3 rounded-3" style="min-height: 48px;" placeholder="Tu correo electrónico" required>
                    <button type="submit" class="btn btn-dark fw-bold px-4 rounded-3 d-flex align-items-center">Suscribirme</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>