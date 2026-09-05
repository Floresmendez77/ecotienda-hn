<?php
/**
 * 🌱 ECOTIENDA HN - PORTADA PRINCIPAL (ORGANIC PREMIUM COMMERCE + REACT BITS 3D DEPTH CAROUSEL & STARBORDER)
 * Ruta: /index.php
 * Descripción: Portada principal con Composición Editorial Monumental, Hero 3D Flotante,
 *              3D Depth Carousel (React Bits style con rotateY perspective), StarBorder Glowing Accents y Pop Elástico.
 */

$pageTitle = "Inicio - Sostenible y Ecológico";
require_once __DIR__ . '/includes/navbar.php';

// Cargar Categorías y Productos destacados / Ofertas de la Base de Datos
$featuredProducts = [];
$ofertasProducts = [];
$categories = [];

try {
    $db = Database::getConnection();
    
    // Obtener categorías activas
    $catStmt = $db->prepare("SELECT * FROM categorias WHERE estado = 'activo' LIMIT 6");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();
    
    // Obtener productos activos recientes
    $prodStmt = $db->prepare("SELECT p.*, c.nombre AS categoria_nombre FROM productos p 
                              LEFT JOIN categorias c ON p.categoria_id = c.id 
                              WHERE p.estado = 'activo' ORDER BY p.fecha_creacion DESC LIMIT 8");
    $prodStmt->execute();
    $featuredProducts = $prodStmt->fetchAll();

    // Obtener ofertas activas con precio_oferta
    $ofertasStmt = $db->prepare("SELECT p.*, c.nombre AS categoria_nombre FROM productos p 
                                LEFT JOIN categorias c ON p.categoria_id = c.id 
                                WHERE p.estado = 'activo' AND p.precio_oferta IS NOT NULL AND p.precio_oferta > 0 ORDER BY p.id DESC LIMIT 8");
    $ofertasStmt->execute();
    $ofertasProducts = $ofertasStmt->fetchAll();

} catch (Exception $e) {
    error_log("Error al cargar productos en index: " . $e->getMessage());
}

// Fallback de Categorías
if (empty($categories)) {
    $categories = [
        ['id' => 1, 'nombre' => 'Cuidado Personal', 'descripcion' => 'Kits biodegradables, cepillos de madera y champús sólidos.', 'imagen' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=500&auto=format&fit=crop&q=60'],
        ['id' => 2, 'nombre' => 'Hogar Sustentable', 'descripcion' => 'Limpiadores naturales, bolsas de tela de Honduras y filtros de agua.', 'imagen' => 'https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?w=500&auto=format&fit=crop&q=60'],
        ['id' => 3, 'nombre' => 'Moda Ética', 'descripcion' => 'Textiles orgánicos y accesorios reciclados duraderos.', 'imagen' => 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=500&auto=format&fit=crop&q=60']
    ];
}

// Fallback de Productos Destacados
if (empty($featuredProducts)) {
    $featuredProducts = [
        [
            'id' => 1,
            'nombre' => 'Champú Sólido de Romero y Árbol de Té',
            'descripcion_corta' => 'Fórmula biodegradable hecha en Honduras libre de sulfatos.',
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
            'nombre' => 'Set de Cubiertos de Bambú con Estuche',
            'descripcion_corta' => 'Cubiertos reutilizables ultra livianos y termo-resistentes.',
            'precio' => 85.00,
            'precio_oferta' => 60.00,
            'stock' => 25,
            'imagen_principal' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=500&auto=format&fit=crop&q=60',
            'categoria_nombre' => 'Hogar Sustentable'
        ],
        [
            'id' => 4,
            'nombre' => 'Termo Acero Doble Capa Térmica (500ml)',
            'descripcion_corta' => 'Mantén tus bebidas frías por 24 horas o calientes por 12 horas.',
            'precio' => 350.00,
            'precio_oferta' => null,
            'stock' => 5,
            'imagen_principal' => 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=500&auto=format&fit=crop&q=60',
            'categoria_nombre' => 'Hogar Sustentable'
        ]
    ];
}

// Fallback de Ofertas
if (empty($ofertasProducts)) {
    $ofertasProducts = array_filter($featuredProducts, function($p) {
        return !empty($p['precio_oferta']);
    });
    if (empty($ofertasProducts)) {
        $ofertasProducts = [
            [
                'id' => 5,
                'nombre' => 'Aceite Esencial de Lavanda Hondureña 30ml',
                'descripcion_corta' => 'Destilado puro al vapor cultivado en Siguatepeque.',
                'precio' => 225.00,
                'precio_oferta' => 180.00,
                'stock' => 10,
                'imagen_principal' => 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?w=500&auto=format&fit=crop&q=60',
                'categoria_nombre' => 'Bienestar'
            ],
            [
                'id' => 6,
                'nombre' => 'Filtro de Agua Cerámico Artesanal',
                'descripcion_corta' => 'Filtro ecológico de barro cocido hondureño de 20 litros.',
                'precio' => 450.00,
                'precio_oferta' => 399.00,
                'stock' => 4,
                'imagen_principal' => 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=500&auto=format&fit=crop&q=60',
                'categoria_nombre' => 'Hogar Eco'
            ]
        ];
    }
}
?>

<!-- HERO SECTION EDITORIAL MONUMENTAL -->
<section class="position-relative overflow-hidden py-5 d-flex align-items-center" style="min-height: 86vh;">
    <div class="container text-center text-lg-start my-auto position-relative z-1">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <!-- Insignia de Impacto Social -->
                <div class="impact-badge-pill hero-reveal-badge mb-4">
                    <span class="badge rounded-pill bg-success me-1 px-2.5 py-1" style="font-size: 0.72rem; background: var(--eco-primary) !important;">HN</span> 
                    <span>100% COMERCIO LOCAL · IMPACTO SUSTENTABLE</span>
                </div>
                
                <!-- Titular Monumental Editorial -->
                <h1 class="display-2 font-display fw-extrabold hero-reveal-title mb-4" style="line-height: 1.08; color: #fff;">
                    Productos que <br>
                    <span style="background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">cuidan lo que importa.</span>
                </h1>
                
                <p class="lead text-slate-300 hero-reveal-text mb-5" style="font-size: 1.18rem; max-width: 620px; color: #cbd5e1; line-height: 1.6;">
                    EcoTienda HN une el consumo inteligente con la preservación natural. Alternativas biodegradables, libres de plástico y elaboradas por manos hondureñas.
                </p>

                <!-- Botones CTA con StarBorder -->
                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3.5 hero-reveal-cta">
                    <div class="star-border-container" style="--star-color: #10b981; --star-speed: 4s;">
                        <div class="border-gradient-bottom"></div>
                        <div class="border-gradient-top"></div>
                        <div class="inner-content" style="background: transparent; border: none;">
                            <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-eco-primary btn-lg px-4">
                                <i class="fas fa-shopping-bag me-1"></i> Explorar Catálogo
                            </a>
                        </div>
                    </div>

                    <a href="<?php echo BASE_URL; ?>sobre_nosotros.php" class="btn btn-pill-glass btn-lg px-4">
                        Conoce Nuestro Impacto <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            
            <!-- Composición Visual 3D Flotante con Glass Blur & Partículas -->
            <div class="col-lg-5 text-center position-relative hero-reveal-visual">
                <!-- Contenedor de Partículas Orgánicas Sutiles -->
                <div id="heroParticleContainer" style="position: absolute; inset: 0; pointer-events: none; z-index: 0;">
                    <div class="hero-particle" style="position: absolute; top: 15%; left: 10%; width: 6px; height: 6px; background: rgba(16, 185, 129, 0.6); border-radius: 50%; filter: blur(1px);"></div>
                    <div class="hero-particle" style="position: absolute; top: 75%; left: 5%; width: 8px; height: 8px; background: rgba(6, 182, 212, 0.5); border-radius: 50%; filter: blur(1px);"></div>
                    <div class="hero-particle" style="position: absolute; top: 35%; right: 10%; width: 5px; height: 5px; background: rgba(16, 185, 129, 0.7); border-radius: 50%; filter: blur(1px);"></div>
                    <div class="hero-particle" style="position: absolute; top: 85%; right: 20%; width: 7px; height: 7px; background: rgba(52, 211, 153, 0.6); border-radius: 50%; filter: blur(1px);"></div>
                    <div class="hero-particle" style="position: absolute; top: 10%; right: 25%; width: 4px; height: 4px; background: rgba(6, 182, 212, 0.6); border-radius: 50%; filter: blur(1px);"></div>
                    <div class="hero-particle" style="position: absolute; top: 55%; left: 45%; width: 6px; height: 6px; background: rgba(16, 185, 129, 0.5); border-radius: 50%; filter: blur(1px);"></div>
                </div>

                <div class="mx-auto position-relative overflow-hidden shadow-2xl z-1" style="width: 350px; height: 380px; border-radius: 32px; background: url('https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=600&auto=format&fit=crop&q=80') center/cover; box-shadow: 0 30px 70px rgba(0,0,0,0.8), 0 0 40px rgba(16,185,129,0.2); border: 1px solid rgba(255,255,255,0.15);">
                    <div style="position: absolute; inset: 0; background: linear-gradient(180deg, transparent 40%, rgba(7, 10, 15, 0.88) 100%);"></div>
                </div>

                <!-- Insignia Flotante Interactiva -->
                <div class="position-absolute bottom-0 start-0 p-3.5 rounded-4 d-flex align-items-center gap-3 border shadow-lg m-3 z-2" style="max-width: 260px; background: rgba(15, 23, 42, 0.88); backdrop-filter: blur(20px); border-color: rgba(255,255,255,0.12) !important;">
                    <div class="flex-shrink-0 text-white p-2.5 rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 15px rgba(16,185,129,0.4);">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                    <div class="text-start">
                        <span class="d-block fw-bold text-white" style="font-size: 0.88rem;">Envíos Sostenibles</span>
                        <small class="text-slate-400" style="font-size: 0.76rem; color: #94a3b8;">A toda Honduras en 24-48 hrs</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- BENEFICIOS Y VALORES ORGÁNICOS -->
<section class="py-5">
    <div class="container">
        <div class="row g-4 text-center">
            <!-- Beneficio 1 con StarBorder -->
            <div class="col-md-4">
                <div class="star-border-container w-100 h-100" style="--star-color: #10b981; --star-speed: 5s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-4 text-start">
                        <div class="mb-3.5 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);">
                            <i class="fas fa-shield-heart fa-xl"></i>
                        </div>
                        <h5 class="fw-bold mb-2 text-white font-display">Ingredientes Seguros</h5>
                        <p class="text-slate-400 mb-0" style="font-size: 0.92rem; color: #94a3b8; line-height: 1.6;">
                            Fórmulas 100% libres de microplásticos, parabenos, fragancias sintéticas o conservantes dañinos.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Beneficio 2 con StarBorder -->
            <div class="col-md-4">
                <div class="star-border-container w-100 h-100" style="--star-color: #06b6d4; --star-speed: 6s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-4 text-start">
                        <div class="mb-3.5 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 50%; background: rgba(6, 182, 212, 0.15); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.3);">
                            <i class="fas fa-leaf fa-xl"></i>
                        </div>
                        <h5 class="fw-bold mb-2 text-white font-display">Respeto a la Biodiversidad</h5>
                        <p class="text-slate-400 mb-0" style="font-size: 0.92rem; color: #94a3b8; line-height: 1.6;">
                            Productos éticos y libres de crueldad animal, protegiendo los ecosistemas locales de Honduras.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Beneficio 3 con StarBorder -->
            <div class="col-md-4">
                <div class="star-border-container w-100 h-100" style="--star-color: #f59e0b; --star-speed: 5.5s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-4 text-start">
                        <div class="mb-3.5 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 50%; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);">
                            <i class="fas fa-hands-holding-circle fa-xl"></i>
                        </div>
                        <h5 class="fw-bold mb-2 text-white font-display">Comercio Justo Nacional</h5>
                        <p class="text-slate-400 mb-0" style="font-size: 0.92rem; color: #94a3b8; line-height: 1.6;">
                            Apoyamos directamente a familias de productores y empacadores artesanales rurales en Honduras.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 🔥 3D DEPTH CAROUSEL (REACT BITS STYLE): PRODUCTOS DESTACADOS -->
<section class="py-5 overflow-hidden">
    <div class="container">
        <div class="d-flex align-items-end justify-content-between mb-4">
            <div>
                <span class="impact-badge-pill mb-2 px-3 py-1" style="font-size: 0.78rem;">🔥 REACT BITS 3D EXPERIENCE</span>
                <h2 class="fw-bold display-6 mb-0 text-white font-display">Productos Destacados</h2>
            </div>
        </div>

        <!-- 3D Depth Carousel Container -->
        <div class="depth-carousel-wrapper" id="depthFeaturedWrapper">
            <div class="depth-carousel-track">
                <?php foreach($featuredProducts as $index => $prod): ?>
                    <div class="depth-carousel-item" data-index="<?php echo $index; ?>">
                        <div class="star-border-container w-100" style="--star-color: #10b981; --star-speed: 6s;">
                            <div class="border-gradient-bottom"></div>
                            <div class="border-gradient-top"></div>
                            <div class="inner-content overflow-hidden">
                                <!-- Badges -->
                                <?php if(!empty($prod['precio_oferta'])): ?>
                                    <span class="badge position-absolute top-0 start-0 m-3 z-3 px-3 py-1.5 rounded-pill" style="background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.5);">
                                        ¡Oferta!
                                    </span>
                                <?php endif; ?>
                                
                                <!-- Imagen 1:1 -->
                                <div class="position-relative overflow-hidden" style="height: 220px; background-color: #0f172a;">
                                    <img src="<?php echo !empty($prod['imagen_principal']) ? BASE_URL . ltrim($prod['imagen_principal'], '/') : 'https://placehold.co/500x500/10b981/white?text=EcoTienda'; ?>" 
                                         class="w-100 h-100 object-fit-cover img-zoom-hover" 
                                         alt="<?php echo sanitize($prod['nombre']); ?>">
                                </div>

                                <!-- Info -->
                                <div class="p-4 d-flex flex-column" style="min-height: 210px;">
                                    <span class="text-success small fw-bold mb-1" style="color: #34d399 !important; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo sanitize($prod['categoria_nombre'] ?? 'Ecológico'); ?></span>
                                    <h5 class="fw-bold card-title text-truncate-2 mb-2" style="font-size: 1rem; line-height: 1.4;">
                                        <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $prod['id']; ?>" class="text-decoration-none text-white hover-emerald"><?php echo sanitize($prod['nombre']); ?></a>
                                    </h5>
                                    <p class="text-slate-400 small text-truncate-2 mb-4" style="font-size: 0.86rem; color: #94a3b8; flex-grow: 1;">
                                        <?php echo sanitize($prod['descripcion_corta']); ?>
                                    </p>
                                    
                                    <!-- Precio y Botones -->
                                    <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary border-opacity-25 mt-auto">
                                        <div>
                                            <?php if(!empty($prod['precio_oferta'])): ?>
                                                <span class="text-danger fw-bold m-0" style="font-size: 1.2rem; color: #f87171 !important;"><?php echo formatCurrency($prod['precio_oferta']); ?></span>
                                                <span class="text-slate-500 text-decoration-line-through small d-block" style="font-size: 0.8rem; color: #64748b;"><?php echo formatCurrency($prod['precio']); ?></span>
                                            <?php else: ?>
                                                <span class="fw-bold text-white" style="font-size: 1.2rem;"><?php echo formatCurrency($prod['precio']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-outline-danger btn-sm rounded-circle p-2 fav-btn" data-id="<?php echo $prod['id']; ?>" title="Favorito" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-color: rgba(239, 68, 68, 0.3);">
                                                <i class="far fa-heart"></i>
                                            </button>
                                            <button type="button" onclick="addToCart(<?php echo $prod['id']; ?>, 1)" class="btn btn-eco-primary btn-sm rounded-circle p-2" title="Agregar" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Dot Indicators -->
            <div class="depth-carousel-indicators"></div>
        </div>
    </div>
</section>

<!-- 🏷️ CARRUSEL 2: OFERTAS DE LA SEMANA -->
<section class="py-5" style="background: rgba(15, 23, 42, 0.4); border-y: 1px solid rgba(255,255,255,0.05);">
    <div class="container">
        <div class="d-flex align-items-end justify-content-between mb-4">
            <div>
                <span class="badge rounded-pill mb-2 px-3 py-1.5" style="background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); font-size: 0.78rem;">🏷️ OPORTUNIDAD LIMITADA</span>
                <h2 class="fw-bold display-6 mb-0 text-white font-display">Ofertas de la Semana</h2>
            </div>
            
            <!-- Flechas de Control Glassmorphism -->
            <div class="d-flex gap-2">
                <button type="button" onclick="scrollCarousel('dealsCarousel', 'left')" class="carousel-arrow-btn" aria-label="Anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" onclick="scrollCarousel('dealsCarousel', 'right')" class="carousel-arrow-btn" aria-label="Siguiente">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Contenedor Horizontal Scroll -->
        <div class="carousel-scroll-container" id="dealsCarousel">
            <?php foreach($ofertasProducts as $prod): ?>
                <div class="carousel-item-card">
                    <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius: 24px; border: 1px solid rgba(239, 68, 68, 0.25) !important;">
                        <!-- Badge Descuento -->
                        <span class="badge position-absolute top-0 start-0 m-3 z-3 px-3 py-1.5 rounded-pill" style="background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.5);">
                            ¡Ahorra en Eco!
                        </span>
                        
                        <!-- Imagen con zoom suave -->
                        <div class="position-relative overflow-hidden" style="height: 230px; background-color: #0f172a;">
                            <img src="<?php echo !empty($prod['imagen_principal']) ? BASE_URL . ltrim($prod['imagen_principal'], '/') : 'https://placehold.co/500x500/ef4444/white?text=Oferta+Eco'; ?>" 
                                 class="w-100 h-100 object-fit-cover img-zoom-hover" 
                                 alt="<?php echo sanitize($prod['nombre']); ?>">
                        </div>

                        <!-- Info -->
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="text-danger small fw-bold mb-1" style="color: #f87171 !important; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo sanitize($prod['categoria_nombre'] ?? 'Descuento'); ?></span>
                            <h5 class="fw-bold card-title text-truncate-2 mb-2" style="font-size: 1rem; line-height: 1.4;">
                                <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $prod['id']; ?>" class="text-decoration-none text-white hover-emerald"><?php echo sanitize($prod['nombre']); ?></a>
                            </h5>
                            <p class="text-slate-400 small text-truncate-2 mb-4" style="font-size: 0.86rem; color: #94a3b8; flex-grow: 1;">
                                <?php echo sanitize($prod['descripcion_corta']); ?>
                            </p>
                            
                            <!-- Precio y Botones -->
                            <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary border-opacity-25 mt-auto">
                                <div>
                                    <span class="text-danger fw-bold m-0" style="font-size: 1.2rem; color: #f87171 !important;"><?php echo formatCurrency($prod['precio_oferta'] ?? $prod['precio']); ?></span>
                                    <?php if(!empty($prod['precio_oferta'])): ?>
                                        <span class="text-slate-500 text-decoration-line-through small d-block" style="font-size: 0.8rem; color: #64748b;"><?php echo formatCurrency($prod['precio']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-danger btn-sm rounded-circle p-2 fav-btn" data-id="<?php echo $prod['id']; ?>" title="Favorito" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-color: rgba(239, 68, 68, 0.3);">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $prod['id']; ?>" class="btn btn-pill-glass btn-sm rounded-circle p-2" title="Detalles" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-eye"></i>
                                    </a>
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

<!-- CATEGORÍAS EDITORIALES -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold display-6 text-white font-display">Explora por Categoría</h2>
            <p class="text-slate-400" style="color: #94a3b8;">Alternativas sostenibles diseñadas para cada momento de tu día</p>
        </div>
        
        <div class="row g-4">
            <?php foreach($categories as $cat): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 overflow-hidden shadow-sm position-relative group" style="border-radius: 28px; min-height: 270px;">
                        <div class="position-absolute top-0 start-0 w-100 h-100 img-zoom-hover" style="background: linear-gradient(0deg, rgba(7, 10, 15, 0.92) 20%, rgba(7, 10, 15, 0.35) 100%), url('<?php echo $cat['imagen']; ?>') center/cover;">
                        </div>
                        
                        <div class="card-body d-flex flex-column justify-content-end p-4 position-relative text-white mt-auto z-1">
                            <h4 class="fw-bold text-white mb-2 font-display"><?php echo sanitize($cat['nombre']); ?></h4>
                            <p class="small text-slate-300 opacity-90 mb-3" style="color: #cbd5e1;"><?php echo sanitize($cat['descripcion']); ?></p>
                            <a href="<?php echo BASE_URL; ?>tienda.php?categoria=<?php echo $cat['id']; ?>" class="btn btn-pill-glass btn-sm w-fit align-self-start">
                                Explorar <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TESTIMONIOS REALES DE HONDURAS -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold display-6 text-white font-display">Comunidad Consciente</h2>
            <p class="text-slate-400" style="color: #94a3b8;">Experiencias reales de compradores en Tegucigalpa, San Pedro Sula y todo el país</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 p-4 shadow-sm h-100" style="border-radius: 24px;">
                    <div class="text-warning mb-3" style="color: #fbbf24 !important;">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="fst-italic text-slate-300 mb-4" style="font-size: 0.94rem; color: #cbd5e1; line-height: 1.6;">"Los champús sólidos huelen delicioso y dejan el cabello espectacular. Me encanta la entrega sin empaques plásticos innecesarios."</p>
                    <div class="d-flex align-items-center gap-3 mt-auto">
                        <div style="width: 46px; height: 46px; border-radius: 50%; background: url('https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&auto=format&fit=crop&q=60') center/cover; border: 2px solid var(--eco-primary);"></div>
                        <div>
                            <span class="d-block fw-bold text-white" style="font-size: 0.9rem;">María Fernanda Castro</span>
                            <small class="text-slate-400" style="font-size: 0.78rem; color: #94a3b8;">San Pedro Sula</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 p-4 shadow-sm h-100" style="border-radius: 24px;">
                    <div class="text-warning mb-3" style="color: #fbbf24 !important;">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="fst-italic text-slate-300 mb-4" style="font-size: 0.94rem; color: #cbd5e1; line-height: 1.6;">"Los cubiertos de Bambú son de excelente calidad. Los ando en mi mochila de trabajo todos los días. ¡Gran iniciativa en Honduras!"</p>
                    <div class="d-flex align-items-center gap-3 mt-auto">
                        <div style="width: 46px; height: 46px; border-radius: 50%; background: url('https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=60') center/cover; border: 2px solid var(--eco-primary);"></div>
                        <div>
                            <span class="d-block fw-bold text-white" style="font-size: 0.9rem;">Carlos Alvarado</span>
                            <small class="text-slate-400" style="font-size: 0.78rem; color: #94a3b8;">Tegucigalpa</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 p-4 shadow-sm h-100" style="border-radius: 24px;">
                    <div class="text-warning mb-3" style="color: #fbbf24 !important;">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="fst-italic text-slate-300 mb-4" style="font-size: 0.94rem; color: #cbd5e1; line-height: 1.6;">"Es un placer tener una tienda online ecológica oficial en Honduras. La atención al cliente fue impecable y el envío súper rápido."</p>
                    <div class="d-flex align-items-center gap-3 mt-auto">
                        <div style="width: 46px; height: 46px; border-radius: 50%; background: url('https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&auto=format&fit=crop&q=60') center/cover; border: 2px solid var(--eco-primary);"></div>
                        <div>
                            <span class="d-block fw-bold text-white" style="font-size: 0.9rem;">Gabriela Meza</span>
                            <small class="text-slate-400" style="font-size: 0.78rem; color: #94a3b8;">La Ceiba</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- BOLETÍN ECO / NEWSLETTER GLASS -->
<section class="py-5 my-4 position-relative">
    <div class="container">
        <div class="card border-0 p-5 text-center text-white overflow-hidden position-relative" style="border-radius: 32px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.22), rgba(6, 182, 212, 0.18)) !important; border: 1px solid rgba(16, 185, 129, 0.35) !important;">
            <h3 class="fw-bold mb-2 display-6 font-display">Recibe Eco-Consejos y Descuentos</h3>
            <p class="text-slate-300 mb-4 opacity-90" style="max-width: 600px; margin: 0 auto; color: #cbd5e1;">Únete a nuestra comunidad consciente y obtén un 10% de descuento en tu primera compra con el código <strong class="text-success" style="color: #34d399 !important;">ECOHN10</strong></p>
            
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-5">
                    <form action="#" method="POST" class="d-flex gap-2">
                        <?php echo csrfField(); ?>
                        <input type="email" class="form-control rounded-pill px-4" style="min-height: 50px;" placeholder="Tu correo electrónico..." required>
                        <button type="submit" class="btn btn-eco-primary px-4">Suscribirme</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
renderAddToCartScript();
require_once __DIR__ . '/includes/footer.php';
?>

<!-- SCRIPT GSAP & REACT BITS 3D DEPTH CAROUSEL -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const isMobile = window.innerWidth < 768 || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ── 1. HERO GSAP TIMELINE REVEAL ────────────────────────────────────── */
    if (typeof gsap !== 'undefined') {
        const heroTl = gsap.timeline({ defaults: { ease: 'power4.out', duration: 0.9 } });
        heroTl.fromTo('.hero-reveal-badge', { opacity: 0, y: 35 }, { opacity: 1, y: 0, duration: 0.7 })
              .fromTo('.hero-reveal-title', { opacity: 0, y: 45 }, { opacity: 1, y: 0, duration: 0.95 }, "-=0.45")
              .fromTo('.hero-reveal-text',  { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 0.75 }, "-=0.6")
              .fromTo('.hero-reveal-cta',   { opacity: 0, y: 25 }, { opacity: 1, y: 0, duration: 0.65, stagger: 0.1 }, "-=0.5")
              .fromTo('.hero-reveal-visual', { opacity: 0, scale: 0.9, y: 35 }, { opacity: 1, scale: 1, y: 0, duration: 1.05, ease: 'power3.out' }, "-=0.85");

        /* ── 2. HERO ORGANIC PARTICLES ───────────────────────────────────── */
        if (!isMobile) {
            document.querySelectorAll('.hero-particle').forEach(p => {
                gsap.to(p, {
                    x: gsap.utils.random(-25, 25),
                    y: gsap.utils.random(-25, 25),
                    opacity: gsap.utils.random(0.2, 0.8),
                    duration: gsap.utils.random(3, 6),
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut',
                    delay: gsap.utils.random(0, 2)
                });
            });
        }

        /* ── 3. SCROLLTRIGGER BATCH REVEALS ───────────────────────────────── */
        if (typeof ScrollTrigger !== 'undefined') {
            ScrollTrigger.batch('.card, .carousel-item-card, .depth-carousel-item', {
                interval: 0.08,
                batchMax: 6,
                onEnter: batch => gsap.fromTo(batch, { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.7, stagger: 0.08, ease: 'power3.out', overwrite: 'auto' }),
                start: 'top 88%'
            });
        }
    }

    /* ── 4. REACT BITS 3D DEPTH CAROUSEL IMPLEMENTATION ───────────────────── */
    initDepthCarousel('depthFeaturedWrapper');

    /* ── 5. TILT 3D + LUZ ESPECULAR CON quickTo ────────────────────────────── */
    if (!isMobile && typeof gsap !== 'undefined') {
        document.querySelectorAll('.card').forEach(card => {
            const qRotateX = gsap.quickTo(card, "rotateX", { duration: 0.35, ease: "power2.out" });
            const qRotateY = gsap.quickTo(card, "rotateY", { duration: 0.35, ease: "power2.out" });

            card.addEventListener('mousemove', function (e) {
                const rect = card.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width;
                const y = (e.clientY - rect.top) / rect.height;

                card.style.setProperty('--mx', `${(x * 100).toFixed(1)}%`);
                card.style.setProperty('--my', `${(y * 100).toFixed(1)}%`);

                const rotX = -((y - 0.5) * 12); // Max ±6 deg
                const rotY = (x - 0.5) * 12;

                qRotateX(rotX);
                qRotateY(rotY);
            });

            card.addEventListener('mouseleave', function () {
                gsap.to(card, { rotateX: 0, rotateY: 0, duration: 0.5, ease: "power3.out" });
            });
        });
    }

    /* ── 6. BOTÓN DE FAVORITO CON POP ELÁSTICO ───────────────────────────── */
    bindFavButtons();
});

function initDepthCarousel(wrapperId) {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;

    const track = wrapper.querySelector('.depth-carousel-track');
    const items = track ? Array.from(track.querySelectorAll('.depth-carousel-item')) : [];
    const indicatorsContainer = wrapper.querySelector('.depth-carousel-indicators');

    if (!track || items.length === 0) return;

    let position = 0;
    const baseWidth = window.innerWidth < 768 ? 310 : 330;
    const gap = 20;
    const offsetStep = baseWidth + gap;

    if (indicatorsContainer) {
        indicatorsContainer.innerHTML = '';
        items.forEach((_, idx) => {
            const btn = document.createElement('button');
            btn.className = `depth-carousel-indicator ${idx === 0 ? 'active' : ''}`;
            btn.type = 'button';
            btn.setAttribute('aria-label', `Go to slide ${idx + 1}`);
            btn.addEventListener('click', () => updatePosition(idx));
            indicatorsContainer.appendChild(btn);
        });
    }

    function updatePosition(newPos) {
        position = Math.max(0, Math.min(newPos, items.length - 1));
        const translateX = -position * offsetStep;

        if (typeof gsap !== 'undefined') {
            gsap.to(track, {
                x: translateX,
                duration: 0.65,
                ease: 'power3.out'
            });

            items.forEach((item, index) => {
                const distance = index - position;
                let rotateY = 0;
                let scale = 1;
                let opacity = 1;

                if (distance < 0) {
                    rotateY = Math.min(45, Math.abs(distance) * 25);
                    scale = Math.max(0.84, 1 - Math.abs(distance) * 0.08);
                    opacity = Math.max(0.45, 1 - Math.abs(distance) * 0.25);
                } else if (distance > 0) {
                    rotateY = Math.max(-45, -distance * 25);
                    scale = Math.max(0.84, 1 - distance * 0.08);
                    opacity = Math.max(0.45, 1 - distance * 0.25);
                }

                gsap.to(item, {
                    rotateY: rotateY,
                    scale: scale,
                    opacity: opacity,
                    duration: 0.65,
                    ease: 'power3.out'
                });
            });
        }

        if (indicatorsContainer) {
            const dots = indicatorsContainer.querySelectorAll('.depth-carousel-indicator');
            dots.forEach((dot, idx) => {
                dot.classList.toggle('active', idx === position);
            });
        }
    }

    if (typeof Draggable !== 'undefined' && window.innerWidth >= 768) {
        Draggable.create(track, {
            type: 'x',
            edgeResistance: 0.8,
            inertia: true,
            onDragEnd: function () {
                const dragOffset = this.x;
                const closestIdx = Math.round(-dragOffset / offsetStep);
                updatePosition(closestIdx);
            }
        });
    }

    updatePosition(0);
}

function scrollCarousel(containerId, direction) {
    const el = document.getElementById(containerId);
    if (!el) return;
    const step = 330;
    const target = el.scrollLeft + (direction === 'left' ? -step : step);

    if (typeof gsap !== 'undefined') {
        el.style.scrollBehavior = 'auto';
        gsap.to(el, {
            scrollLeft: target,
            duration: 0.6,
            ease: 'power3.out',
            onComplete: function () {
                el.style.scrollBehavior = 'smooth';
            }
        });
    } else {
        el.scrollBy({ left: direction === 'left' ? -step : step, behavior: 'smooth' });
    }
}

function bindFavButtons() {
    document.querySelectorAll('.fav-btn').forEach(btn => {
        if (btn._favBound) return;
        btn._favBound = true;

        btn.addEventListener('click', function () {
            const icon = this.querySelector('i');

            if (typeof gsap !== 'undefined' && icon) {
                gsap.fromTo(icon, 
                    { scale: 0.6 }, 
                    { scale: 1.45, duration: 0.45, ease: 'elastic.out(1.2, 0.4)', onComplete: () => gsap.to(icon, { scale: 1, duration: 0.2 }) }
                );
            }

            if (typeof IS_LOGGED !== 'undefined' && !IS_LOGGED) {
                if (typeof BASE_URL !== 'undefined') window.location.href = BASE_URL + 'login.php';
                return;
            }

            const pid = this.dataset.id;
            if (!pid) return;

            if (typeof CSRF_TOKEN !== 'undefined' && typeof BASE_URL !== 'undefined') {
                fetch(BASE_URL + 'api/favoritos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ producto_id: parseInt(pid) })
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        if (data.redirect) window.location.href = data.redirect;
                        return;
                    }
                    if (data.favorito) {
                        icon.classList.replace('far', 'fas');
                        btn.style.background = '#ef4444';
                        btn.style.color = '#fff';
                    } else {
                        icon.classList.replace('fas', 'far');
                        btn.style.background = 'transparent';
                        btn.style.color = '#ef4444';
                    }
                    if (typeof showToast === 'function') showToast(data.message);
                });
            }
        });
    });
}
</script>