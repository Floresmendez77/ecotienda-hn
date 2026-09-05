<?php
/**
 * 🌱 ECOTIENDA HN - CATÁLOGO ECOLÓGICO (ORGANIC PREMIUM COMMERCE + GSAP MOTION)
 * Ruta: /tienda.php
 * Descripción: Catálogo ecológico con Búsqueda AJAX, Filtros Sticky, ScrollTrigger Batch Reveal,
 *              Tilt 3D con luz especular y Microinteracciones GSAP.
 */

$pageTitle = "La EcoTienda - Productos Sostenibles";
require_once __DIR__ . '/includes/navbar.php';

// Inicializar Filtros y Parámetros
$search = trim($_GET['buscar'] ?? '');
$catFilter = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$minPrice = isset($_GET['min_precio']) ? (float)$_GET['min_precio'] : 0;
$maxPrice = isset($_GET['max_precio']) ? (float)$_GET['max_precio'] : 0;
$stockFilter = $_GET['disponibilidad'] ?? ''; // 'disponible' o todo
$sort = $_GET['orden'] ?? 'recientes';

// Paginación
$page = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($page < 1) $page = 1;
$limit = 8; // Artículos por página
$offset = ($page - 1) * $limit;

$products = [];
$totalProducts = 0;
$totalPages = 1;
$categories = [];

try {
    $db = Database::getConnection();
    
    // 1. Obtener todas las categorías para la barra lateral de filtros
    $catStmt = $db->query("SELECT * FROM categorias WHERE estado = 'activo' ORDER BY nombre ASC");
    $categories = $catStmt->fetchAll();

    // 2. Construir Query de Productos con filtros dinámicos
    $whereClauses = ["p.estado != 'inactivo'"];
    $params = [];

    if (!empty($search)) {
        $whereClauses[] = "(p.nombre LIKE :search OR p.descripcion_corta LIKE :search OR p.descripcion_larga LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($catFilter > 0) {
        $whereClauses[] = "p.categoria_id = :categoria_id";
        $params[':categoria_id'] = $catFilter;
    }

    if ($minPrice > 0) {
        $whereClauses[] = "p.precio >= :min_precio";
        $params[':min_precio'] = $minPrice;
    }

    if ($maxPrice > 0) {
        $whereClauses[] = "p.precio <= :max_precio";
        $params[':max_precio'] = $maxPrice;
    }

    if ($stockFilter === 'disponible') {
        $whereClauses[] = "p.stock > 0 AND p.estado = 'activo'";
    }

    $whereSQL = implode(' AND ', $whereClauses);

    // 3. Conteo Total para Paginación
    $countSql = "SELECT COUNT(*) FROM productos p WHERE $whereSQL";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = (int)ceil($totalProducts / $limit);
    if ($totalPages < 1) $totalPages = 1;

    // 4. Agregar Regla de Ordenamiento de forma segura
    $allowedSorts = [
        'precio_asc' => 'precio_actual ASC',
        'precio_desc' => 'precio_actual DESC',
        'nombre_asc' => 'p.nombre ASC',
        'nombre_desc' => 'p.nombre DESC',
        'recientes' => 'p.fecha_creacion DESC'
    ];
    $orderBy = $allowedSorts[$sort] ?? 'p.fecha_creacion DESC';

    // Query principal con cálculo del precio real considerando ofertas si existen
    $sql = "SELECT p.*, c.nombre AS categoria_nombre, 
            COALESCE(p.precio_oferta, p.precio) AS precio_actual 
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE $whereSQL 
            ORDER BY $orderBy 
            LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error de base de datos en tienda.php: " . $e->getMessage());
}

// Fallback de Categorías
if (empty($categories)) {
    $categories = [
        ['id' => 1, 'nombre' => 'Cuidado Personal'],
        ['id' => 2, 'nombre' => 'Hogar Sustentable'],
        ['id' => 3, 'nombre' => 'Moda Ética']
    ];
}

// Fallback de Productos
if (empty($products) && empty($search) && $catFilter === 0) {
    $products = [
        [
            'id' => 1, 'nombre' => 'Champú Sólido de Romero y Árbol de Té', 'descripcion_corta' => 'Biodegradable, hecho en Honduras libre de sulfato.', 'precio' => 120.00, 'precio_oferta' => 95.00, 'stock' => 12, 'imagen_principal' => 'https://images.unsplash.com/photo-1608248597481-496100c80836?w=500&auto=format&fit=crop&q=60', 'categoria_nombre' => 'Cuidado Personal', 'categoria_id' => 1
        ],
        [
            'id' => 2, 'nombre' => 'Bolsas de Algodón Orgánico (Set de 3)', 'descripcion_corta' => 'Bolsas reutilizables biodegradables para vegetales.', 'precio' => 140.00, 'precio_oferta' => null, 'stock' => 8, 'imagen_principal' => 'https://images.unsplash.com/photo-1533750349088-cd871a92f311?w=500&auto=format&fit=crop&q=60', 'categoria_nombre' => 'Hogar Sustentable', 'categoria_id' => 2
        ],
        [
            'id' => 3, 'nombre' => 'Set de Cubiertos de Bambú con Estuche', 'descripcion_corta' => 'Cubiertos reutilizables biodegradables de bambú natural.', 'precio' => 85.00, 'precio_oferta' => 60.00, 'stock' => 25, 'imagen_principal' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=500&auto=format&fit=crop&q=60', 'categoria_nombre' => 'Hogar Sustentable', 'categoria_id' => 2
        ],
        [
            'id' => 4, 'nombre' => 'Termo Acero de Doble Capa Térmica (500ml)', 'descripcion_corta' => 'Mantiene frío por 24 horas y caliente por 12 horas.', 'precio' => 350.00, 'precio_oferta' => null, 'stock' => 5, 'imagen_principal' => 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=500&auto=format&fit=crop&q=60', 'categoria_nombre' => 'Hogar Sustentable', 'categoria_id' => 2
        ],
        [
            'id' => 5, 'nombre' => 'Cepillo de Dientes de Bambú Natural', 'descripcion_corta' => 'Cerdas infundidas con carbón activo biodegradable.', 'precio' => 45.00, 'precio_oferta' => 35.00, 'stock' => 150, 'imagen_principal' => 'https://images.unsplash.com/photo-1607613009820-a29f7bb81c04?w=500&auto=format&fit=crop&q=60', 'categoria_nombre' => 'Cuidado Personal', 'categoria_id' => 1
        ],
        [
            'id' => 6, 'nombre' => 'Jabón de Avena y Miel Orgánica de Lempira', 'descripcion_corta' => 'Exfoliante suave, nutritivo e hipoalergénico hecho a mano.', 'precio' => 60.00, 'precio_oferta' => null, 'stock' => 40, 'imagen_principal' => 'https://images.unsplash.com/photo-1607006342411-101c4e12c16c?w=500&auto=format&fit=crop&q=60', 'categoria_nombre' => 'Cuidado Personal', 'categoria_id' => 1
        ]
    ];
    $totalProducts = count($products);
    $totalPages = 1;
}
?>

<div class="container py-5">
    
    <!-- ENCABEZADO EDITORIAL & BUSCADOR PROMINENTE (React Bits Style) -->
    <div class="row mb-5 align-items-center justify-content-between g-4">
        <div class="col-lg-6">
            <div class="impact-badge-pill mb-2 px-3 py-1" style="font-size: 0.76rem;">🌱 CATÁLOGO SOSTENIBLE · HONDURAS</div>
            <h1 class="display-5 font-display fw-extrabold text-white mb-2" style="letter-spacing: -0.03em;">Explora EcoTienda</h1>
            <p class="text-slate-400 mb-0" style="color: #94a3b8; font-size: 1.05rem;">Productos biodegradables y de origen ético para tu día a día</p>
        </div>

        <div class="col-lg-5">
            <div class="position-relative">
                <span class="position-absolute start-0 top-50 translate-middle-y ps-4 text-slate-400" style="color: #94a3b8; font-size: 1.1rem;">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="ajaxSearch" name="buscar"
                       class="form-control form-control-lg ps-5 pe-5 rounded-pill"
                       placeholder="Buscar por nombre, categoría o beneficio..."
                       value="<?php echo sanitize($search); ?>"
                       style="min-height: 52px; background: rgba(15, 23, 42, 0.75) !important; border: 1px solid rgba(255, 255, 255, 0.12) !important; backdrop-filter: blur(20px);"
                       autocomplete="off">
                <span class="position-absolute end-0 top-50 translate-middle-y pe-4 text-success" id="searchSpinner" style="display:none;">
                    <i class="fas fa-spinner fa-spin fa-lg"></i>
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- BARRA LATERAL DE FILTROS STICKY (Glass Panel) -->
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm p-4 sticky-top" style="top: 100px; border-radius: 24px; z-index: 10; background: rgba(15, 23, 42, 0.82) !important; backdrop-filter: blur(20px);">
                <h5 class="fw-bold mb-4 text-white d-flex align-items-center gap-2 font-display">
                    <i class="fas fa-sliders-h text-success"></i> Filtros
                </h5>
                
                <form action="<?php echo BASE_URL; ?>tienda.php" method="GET" id="filterForm">
                    <!-- Preservar Búsqueda si existe -->
                    <?php if(!empty($search)): ?>
                        <input type="hidden" name="buscar" value="<?php echo sanitize($search); ?>">
                    <?php endif; ?>

                    <!-- Categorías -->
                    <div class="mb-4">
                        <label class="form-label text-slate-400 small fw-bold mb-2" style="color: #94a3b8;">Categoría</label>
                        <select name="categoria" class="form-select border-0" onchange="document.getElementById('filterForm').submit()">
                            <option value="0">Todas las Categorías</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $catFilter === (int)$cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ordenamiento -->
                    <div class="mb-4">
                        <label class="form-label text-slate-400 small fw-bold mb-2" style="color: #94a3b8;">Ordenar por</label>
                        <select name="orden" class="form-select border-0" onchange="document.getElementById('filterForm').submit()">
                            <option value="recientes" <?php echo $sort === 'recientes' ? 'selected' : ''; ?>>Más Recientes</option>
                            <option value="precio_asc" <?php echo $sort === 'precio_asc' ? 'selected' : ''; ?>>Precio: Menor a Mayor</option>
                            <option value="precio_desc" <?php echo $sort === 'precio_desc' ? 'selected' : ''; ?>>Precio: Mayor a Menor</option>
                            <option value="nombre_asc" <?php echo $sort === 'nombre_asc' ? 'selected' : ''; ?>>Nombre: A - Z</option>
                        </select>
                    </div>

                    <!-- Rango de Precios -->
                    <div class="mb-4">
                        <label class="form-label text-slate-400 small fw-bold mb-2" style="color: #94a3b8;">Rango de Precio (L.)</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="number" name="min_precio" class="form-control text-center p-2 small" placeholder="Mín" value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>">
                            <span class="text-slate-500">-</span>
                            <input type="number" name="max_precio" class="form-control text-center p-2 small" placeholder="Máx" value="<?php echo $maxPrice > 0 ? $maxPrice : ''; ?>">
                        </div>
                    </div>

                    <!-- Disponibilidad -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="disponibilidad" value="disponible" id="disponibilidad" class="form-check-input" <?php echo $stockFilter === 'disponible' ? 'checked' : ''; ?> onchange="document.getElementById('filterForm').submit()">
                            <label for="disponibilidad" class="form-check-label text-slate-300 small ms-1" style="color: #cbd5e1;">Solo en Stock</label>
                        </div>
                    </div>

                    <!-- Botones Aplicar / Limpiar -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-eco-primary btn-sm flex-grow-1">Aplicar</button>
                        <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-pill-glass btn-sm" title="Limpiar"><i class="fas fa-undo"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- PRODUCT GRID -->
        <div class="col-lg-9 col-md-8">
            <!-- Contador de Resultados -->
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="text-slate-400 small" id="resultsCount" style="color: #94a3b8;">
                    Mostrando <strong class="text-white"><?php echo count($products); ?></strong> de <strong class="text-white"><?php echo $totalProducts > 0 ? $totalProducts : count($products); ?></strong> ecoproductos
                </div>
            </div>

            <!-- Catálogo vacío message -->
            <?php if (empty($products)): ?>
                <div class="text-center py-5" id="productGrid">
                    <div class="bg-success bg-opacity-10 text-success p-4 rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; border: 1px solid rgba(16,185,129,0.3);">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h4 class="fw-bold text-white font-display">No se encontraron productos</h4>
                    <p class="text-slate-400" style="color: #94a3b8;">Intenta remover o ajustar tus filtros de búsqueda</p>
                    <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-eco-primary mt-2">Limpiar Filtros</a>
                </div>
            <?php else: ?>
                <!-- Grid de productos -->
                <div class="row g-4" id="productGrid">
                    <?php foreach($products as $prod): ?>
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius: 24px;">
                                <!-- Insignia Oferta / Agotado -->
                                <?php if(!empty($prod['precio_oferta'])): ?>
                                    <span class="badge position-absolute top-0 start-0 m-3 z-3 px-3 py-1.5 rounded-pill" style="background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.5);">¡Oferta!</span>
                                <?php endif; ?>

                                <?php if($prod['stock'] <= 0): ?>
                                    <span class="badge bg-secondary position-absolute top-0 end-0 m-3 z-3 px-3 py-1.5 rounded-pill" style="background: rgba(100, 116, 139, 0.8);">Agotado</span>
                                <?php endif; ?>

                                <div class="position-relative overflow-hidden" style="height: 230px; background-color: #0f172a;">
                                    <img src="<?php echo !empty($prod['imagen_principal']) ? BASE_URL . ltrim($prod['imagen_principal'], '/') : 'https://placehold.co/500x500/10b981/white?text=EcoTienda'; ?>" 
                                         class="w-100 h-100 object-fit-cover img-zoom-hover" 
                                         alt="<?php echo sanitize($prod['nombre']); ?>">
                                </div>

                                <div class="card-body p-4 d-flex flex-column">
                                    <span class="text-success small fw-bold mb-1" style="color: #34d399 !important; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo sanitize($prod['categoria_nombre'] ?? 'Hogar Verde'); ?></span>
                                    <h5 class="fw-bold card-title text-truncate-2 mb-2" style="font-size: 1rem; line-height: 1.4;">
                                        <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $prod['id']; ?>" class="text-decoration-none text-white hover-emerald"><?php echo sanitize($prod['nombre']); ?></a>
                                    </h5>
                                    <p class="text-slate-400 small text-truncate-2 mb-4" style="font-size: 0.86rem; color: #94a3b8; flex-grow: 1;">
                                        <?php echo sanitize($prod['descripcion_corta']); ?>
                                    </p>

                                    <!-- Precios y Botones -->
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
                                            <!-- Corazón de favorito -->
                                            <button
                                                class="btn btn-outline-danger btn-sm rounded-circle p-2 fav-btn"
                                                data-id="<?php echo $prod['id']; ?>"
                                                title="Favorito"
                                                style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-color:rgba(239,68,68,0.3);">
                                                <i class="far fa-heart"></i>
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>producto.php?id=<?php echo $prod['id']; ?>" class="btn btn-pill-glass btn-sm rounded-circle p-2" title="Detalles" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if($prod['stock'] > 0): ?>
                                                <button type="button"
                                                        onclick="addToCart(<?php echo $prod['id']; ?>, 1)"
                                                        class="btn btn-eco-primary btn-sm rounded-circle p-2"
                                                        title="Añadir al carrito"
                                                        style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-cart-plus"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-pill-glass btn-sm rounded-circle p-2" disabled style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; opacity: 0.5;">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINACIÓN GLASSMORPHIC -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-5">
                        <ul class="pagination justify-content-center gap-2">
                            <!-- Anterior -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link border-0 text-white rounded-pill px-3" style="background: rgba(255,255,255,0.06);" href="<?php echo BASE_URL; ?>tienda.php?pagina=<?php echo $page-1; ?>&categoria=<?php echo $catFilter; ?>&orden=<?php echo $sort; ?>&buscar=<?php echo sanitize($search); ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>

                            <!-- Números -->
                            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link border-0 rounded-pill px-3.5 <?php echo $page === $i ? 'btn-eco-primary text-white' : 'text-white'; ?>" style="<?php echo $page !== $i ? 'background: rgba(255,255,255,0.06);' : ''; ?>" href="<?php echo BASE_URL; ?>tienda.php?pagina=<?php echo $i; ?>&categoria=<?php echo $catFilter; ?>&orden=<?php echo $sort; ?>&buscar=<?php echo sanitize($search); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Siguiente -->
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link border-0 text-white rounded-pill px-3" style="background: rgba(255,255,255,0.06);" href="<?php echo BASE_URL; ?>tienda.php?pagina=<?php echo $page+1; ?>&categoria=<?php echo $catFilter; ?>&orden=<?php echo $sort; ?>&buscar=<?php echo sanitize($search); ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
renderAddToCartScript();
require_once __DIR__ . '/includes/footer.php';
?>

<script>
(function () {
    const BASE = '<?php echo BASE_URL; ?>';
    const IS_LOGGED = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
    const CSRF_TOKEN = '<?php echo addslashes(generateCsrfToken()); ?>';
    const currentCat = <?php echo $catFilter; ?>;
    const currentSort = '<?php echo sanitize($sort); ?>';
    const isMobile = window.innerWidth < 768 || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ── GSAP MOTION INIT PARA TIENDA ─────────────────────────────────────── */
    function initMotionFeatures() {
        if (typeof gsap === 'undefined') return;

        // 1. ScrollTrigger Batch Reveal
        if (typeof ScrollTrigger !== 'undefined') {
            ScrollTrigger.batch('#productGrid .card', {
                interval: 0.08,
                batchMax: 8,
                onEnter: batch => gsap.fromTo(batch, { opacity: 0, y: 35 }, { opacity: 1, y: 0, duration: 0.65, stagger: 0.08, ease: 'power3.out', overwrite: 'auto' }),
                start: 'top 90%'
            });
        }

        // 2. Tilt 3D + Luz Especular con quickTo
        if (!isMobile) {
            document.querySelectorAll('#productGrid .card').forEach(card => {
                if (card._tiltBound) return;
                card._tiltBound = true;

                const qRotateX = gsap.quickTo(card, "rotateX", { duration: 0.35, ease: "power2.out" });
                const qRotateY = gsap.quickTo(card, "rotateY", { duration: 0.35, ease: "power2.out" });

                card.addEventListener('mousemove', function (e) {
                    const rect = card.getBoundingClientRect();
                    const x = (e.clientX - rect.left) / rect.width;
                    const y = (e.clientY - rect.top) / rect.height;

                    card.style.setProperty('--mx', `${(x * 100).toFixed(1)}%`);
                    card.style.setProperty('--my', `${(y * 100).toFixed(1)}%`);

                    const rotX = -((y - 0.5) * 12);
                    const rotY = (x - 0.5) * 12;

                    qRotateX(rotX);
                    qRotateY(rotY);
                });

                card.addEventListener('mouseleave', function () {
                    gsap.to(card, { rotateX: 0, rotateY: 0, duration: 0.5, ease: "power3.out" });
                });
            });
        }

        bindFavButtons();
    }

    /* ── AJAX SEARCH ─────────────────────────────────────────────────────── */
    const searchInput = document.getElementById('ajaxSearch');
    const spinner     = document.getElementById('searchSpinner');
    const gridWrap    = document.getElementById('productGrid');
    const countEl     = document.getElementById('resultsCount');

    let searchTimer = null;

    function renderProductCard(p) {
        const ofertaBadge = p.en_oferta ? `<span class="badge position-absolute top-0 start-0 m-3 z-3 px-3 py-1.5 rounded-pill" style="background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.5);">¡Oferta!</span>` : '';
        const agotadoBadge = p.stock <= 0 ? `<span class="badge bg-secondary position-absolute top-0 end-0 m-3 z-3 px-3 py-1.5 rounded-pill" style="background: rgba(100, 116, 139, 0.8);">Agotado</span>` : '';
        const precioHtml = p.en_oferta
            ? `<span class="text-danger fw-bold" style="font-size:1.2rem; color:#f87171 !important;">${p.precio_fmt}</span>
               <span class="text-slate-500 text-decoration-line-through small d-block" style="font-size:.8rem; color:#64748b;">${p.precio_orig_fmt}</span>`
            : `<span class="fw-bold text-white" style="font-size:1.2rem;">${p.precio_fmt}</span>`;

        const addBtn = p.stock > 0
            ? `<button type="button" onclick="addToCart(${p.id}, 1)" class="btn btn-eco-primary btn-sm rounded-circle p-2" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;" title="Añadir al carrito">
                    <i class="fas fa-cart-plus"></i>
               </button>`
            : `<button class="btn btn-pill-glass btn-sm rounded-circle p-2" disabled style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;opacity:0.5;"><i class="fas fa-ban"></i></button>`;

        return `<div class="col-xl-4 col-lg-6 col-md-6">
                    <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius:24px;">
                        ${ofertaBadge}${agotadoBadge}
                        <div class="position-relative overflow-hidden" style="height:230px;background:#0f172a;">
                            <img src="${p.imagen_principal}" class="w-100 h-100 object-fit-cover img-zoom-hover" alt="${p.nombre}">
                        </div>
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="text-success small fw-bold mb-1" style="color:#34d399 !important; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em;">${p.categoria_nombre || 'Ecológico'}</span>
                            <h5 class="fw-bold card-title text-truncate-2 mb-2" style="font-size:1rem;line-height:1.4;">
                                <a href="${p.url}" class="text-decoration-none text-white hover-emerald">${p.nombre}</a>
                            </h5>
                            <p class="text-slate-400 small text-truncate-2 mb-4" style="font-size:.86rem;color:#94a3b8;flex-grow:1;">${p.descripcion_corta}</p>
                            <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary border-opacity-25 mt-auto">
                                <div>${precioHtml}</div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-danger btn-sm rounded-circle p-2 fav-btn" data-id="${p.id}" title="Favorito" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-color:rgba(239,68,68,0.3);">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <a href="${p.url}" class="btn btn-pill-glass btn-sm rounded-circle p-2" title="Detalles" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    ${addBtn}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
    }

    function fetchProducts(q) {
        if (!gridWrap) return;
        spinner && (spinner.style.display = 'inline');
        const url = `${BASE}api/productos.php?q=${encodeURIComponent(q)}&categoria=${currentCat}&orden=${currentSort}`;
        fetch(url)
            .then(r => r.json())
            .then(data => {
                spinner && (spinner.style.display = 'none');
                if (!data.success) return;
                const prods = data.productos;
                if (prods.length === 0) {
                    gridWrap.innerHTML = `<div class="col-12 text-center py-5">
                        <i class="fas fa-search fa-3x text-emerald-400 mb-3" style="color:#10b981;"></i>
                        <h5 class="fw-bold text-white font-display">Sin resultados para "${q}"</h5>
                        <p class="text-slate-400" style="color:#94a3b8;">Prueba con otro término de búsqueda o limpia los filtros.</p>
                    </div>`;
                } else {
                    gridWrap.innerHTML = prods.map(renderProductCard).join('');
                    initMotionFeatures();
                }
                countEl && (countEl.innerHTML = `Mostrando <strong class="text-white">${prods.length}</strong> ecoproductos`);
            })
            .catch(() => { spinner && (spinner.style.display = 'none'); });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            searchTimer = setTimeout(() => fetchProducts(q), 350);
        });
    }

    /* ── FAVORITOS CON POP ELÁSTICO ───────────────────────────────────────── */
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

                if (!IS_LOGGED) {
                    window.location.href = BASE + 'login.php';
                    return;
                }
                const pid = this.dataset.id;
                if (!pid) return;

                fetch(BASE + 'api/favoritos.php', {
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
                    showToast(data.message);
                });
            });
        });
    }

    function showToast(msg) {
        let t = document.getElementById('ecoToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'ecoToast';
            t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:rgba(15,23,42,0.95);backdrop-filter:blur(20px);color:#fff;border:1px solid rgba(16,185,129,0.4);padding:14px 24px;border-radius:9999px;font-size:.9rem;box-shadow:0 10px 35px rgba(0,0,0,.7);transition:opacity .3s ease;';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.opacity = '1';
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.style.opacity = '0', 2800);
    }

    document.addEventListener('DOMContentLoaded', initMotionFeatures);
})();
</script>