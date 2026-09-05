<?php
/**
 * 🌱 ECOTIENDA HN - PREGUNTAS FRECUENTES (ORGANIC PREMIUM COMMERCE + STARBORDER + SEARCH)
 * Ruta: /faq.php
 * Descripción: Centro interactivo de respuestas con filtrado dinámico en tiempo real y acordeón StarBorder.
 */

$pageTitle = "Preguntas Frecuentes - EcoSoporte";
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5 text-start">
    
    <!-- ENCABEZADO Y BUSCADOR FRECUENTE -->
    <div class="text-center mb-5">
        <span class="impact-badge-pill mb-3">
            📚 CENTRO DE RESPUESTAS DIRECTAS
        </span>
        <h1 class="display-4 font-display fw-extrabold text-white mb-3">Preguntas Frecuentes</h1>
        <p class="text-slate-400 col-md-6 mx-auto mb-4" style="color: #94a3b8; font-size: 1.05rem;">
            Resuelve tus dudas sobre los empaques ecológicos, envíos a cabeceras de Honduras y transferencias bancarias.
        </p>

        <!-- Buscador Interactivo de FAQ -->
        <div class="row justify-content-center mb-4">
            <div class="col-lg-6">
                <div class="position-relative">
                    <span class="position-absolute start-0 top-50 translate-middle-y ps-4 text-slate-400" style="color: #94a3b8;">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" id="faqSearchInput" class="form-control form-control-lg ps-5 rounded-pill" placeholder="Escribe tu consulta (ej. envíos, empaque, pago)..." style="background: rgba(15,23,42,0.85) !important; border: 1px solid rgba(255,255,255,0.12) !important; backdrop-filter: blur(20px);">
                </div>
            </div>
        </div>

        <!-- Pills de Filtro Rápido -->
        <div class="d-flex flex-wrap justify-content-center gap-2" id="faqFilterPills">
            <button class="btn btn-sm btn-eco-primary rounded-pill px-3 faq-pill active" data-category="all">Todas</button>
            <button class="btn btn-sm btn-pill-glass rounded-pill px-3 faq-pill" data-category="envios">📦 Envíos</button>
            <button class="btn btn-sm btn-pill-glass rounded-pill px-3 faq-pill" data-category="empaques">🌱 Empaques</button>
            <button class="btn btn-sm btn-pill-glass rounded-pill px-3 faq-pill" data-category="pagos">💳 Pagos</button>
            <button class="btn btn-sm btn-pill-glass rounded-pill px-3 faq-pill" data-category="garantia">🛡️ Garantía</button>
        </div>
    </div>

    <!-- LISTADO DE ACORDEÓN STARBORDER -->
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex flex-column gap-3" id="faqContainer">
                
                <!-- FAQ 1: Envíos -->
                <div class="faq-card-wrap" data-category="envios">
                    <div class="star-border-container w-100" style="--star-color: #10b981; --star-speed: 6s;">
                        <div class="border-gradient-bottom"></div>
                        <div class="border-gradient-top"></div>
                        <div class="inner-content p-4">
                            <div class="d-flex justify-content-between align-items-center cursor-pointer faq-trigger" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true">
                                <h5 class="fw-bold mb-0 text-white font-display d-flex align-items-center gap-2">
                                    <i class="fas fa-truck-fast text-success"></i> ¿A qué partes de Honduras hacen envíos y cuánto tarda?
                                </h5>
                                <i class="fas fa-chevron-down text-slate-400 faq-icon"></i>
                            </div>
                            <div id="faq1" class="collapse show mt-3 text-slate-300 pt-3 border-top border-secondary border-opacity-25" style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.6;">
                                Hacemos envíos a todos los 18 departamentos de Honduras (Francisco Morazán, Cortés, Atlántida, etc.) a través de mensajería express y logística nacional. En San Pedro Sula y Tegucigalpa el tiempo estimado de entrega es de 24 a 48 horas hábiles. Para otras cabeceras departamentales, el envío suele tardar de 3 a 5 días hábiles.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 2: Empaques -->
                <div class="faq-card-wrap" data-category="empaques">
                    <div class="star-border-container w-100" style="--star-color: #06b6d4; --star-speed: 5s;">
                        <div class="border-gradient-bottom"></div>
                        <div class="border-gradient-top"></div>
                        <div class="inner-content p-4">
                            <div class="d-flex justify-content-between align-items-center cursor-pointer faq-trigger collapsed" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false">
                                <h5 class="fw-bold mb-0 text-white font-display d-flex align-items-center gap-2">
                                    <i class="fas fa-leaf text-info"></i> ¿Qué materiales utilizan para empacar los pedidos?
                                </h5>
                                <i class="fas fa-chevron-down text-slate-400 faq-icon"></i>
                            </div>
                            <div id="faq2" class="collapse mt-3 text-slate-300 pt-3 border-top border-secondary border-opacity-25" style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.6;">
                                Estamos 100% comprometidos con la filosofía Zero-Waste. No utilizamos bolsas de plástico de un solo uso, celofán ni cintas adhesivas convencionales. Todos tus productos se embalan en cajas de cartón reciclado, virutas de madera protectoras, papel kraft biodegradable y se sellan con cinta de papel activada por agua con adhesivo vegetal.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 3: Pagos -->
                <div class="faq-card-wrap" data-category="pagos">
                    <div class="star-border-container w-100" style="--star-color: #f59e0b; --star-speed: 6.5s;">
                        <div class="border-gradient-bottom"></div>
                        <div class="border-gradient-top"></div>
                        <div class="inner-content p-4">
                            <div class="d-flex justify-content-between align-items-center cursor-pointer faq-trigger collapsed" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false">
                                <h5 class="fw-bold mb-0 text-white font-display d-flex align-items-center gap-2">
                                    <i class="fas fa-file-invoice-dollar text-warning"></i> ¿Cómo confirmo mi pago de transferencia bancaria?
                                </h5>
                                <i class="fas fa-chevron-down text-slate-400 faq-icon"></i>
                            </div>
                            <div id="faq3" class="collapse mt-3 text-slate-300 pt-3 border-top border-secondary border-opacity-25" style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.6;">
                                Una vez que finalices tu compra en el checkout seleccionando el método "Transferencia", registrarás el código o número de referencia de la transferencia directamente en el formulario. Nuestro equipo administrativo verificará el depósito en las cuentas nacionales y actualizará el estado de tu pedido inmediatamente.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 4: Garantía -->
                <div class="faq-card-wrap" data-category="garantia">
                    <div class="star-border-container w-100" style="--star-color: #ef4444; --star-speed: 5.5s;">
                        <div class="border-gradient-bottom"></div>
                        <div class="border-gradient-top"></div>
                        <div class="inner-content p-4">
                            <div class="d-flex justify-content-between align-items-center cursor-pointer faq-trigger collapsed" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false">
                                <h5 class="fw-bold mb-0 text-white font-display d-flex align-items-center gap-2">
                                    <i class="fas fa-shield-halved text-danger"></i> ¿Ofrecen garantía de devolución o reemplazo?
                                </h5>
                                <i class="fas fa-chevron-down text-slate-400 faq-icon"></i>
                            </div>
                            <div id="faq4" class="collapse mt-3 text-slate-300 pt-3 border-top border-secondary border-opacity-25" style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.6;">
                                Sí. Por la naturaleza de los productos de higiene personal, aceptamos devoluciones si el empaque original del fabricante se encuentra sellado y sin usar dentro de los primeros 7 días hábiles de la compra. Si recibiste un producto dañado durante la ruta de entrega, realizamos el reemplazo de inmediato sin ningún costo adicional.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- Mensaje sin resultados de búsqueda -->
            <div id="noFaqResults" class="text-center py-5" style="display: none;">
                <i class="fas fa-search-minus fa-3x text-emerald-400 mb-3" style="color: #10b981;"></i>
                <h5 class="fw-bold text-white font-display">No encontramos respuestas para esa consulta</h5>
                <p class="text-slate-400 small" style="color: #94a3b8;">¿Tienes una duda específica? Escríbenos directamente en nuestro canal de contacto.</p>
                <a href="<?php echo BASE_URL; ?>contacto.php" class="btn btn-eco-primary btn-sm mt-2">Ir a Contacto</a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- SCRIPT BUSCADOR Y FILTROS FAQ -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById('faqSearchInput');
    const cards = document.querySelectorAll('.faq-card-wrap');
    const pills = document.querySelectorAll('.faq-pill');
    const noResults = document.getElementById('noFaqResults');

    let activeCategory = 'all';

    function filterFaqs() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        let visibleCount = 0;

        cards.forEach(card => {
            const cat = card.dataset.category;
            const text = card.textContent.toLowerCase();

            const matchesCategory = (activeCategory === 'all' || cat === activeCategory);
            const matchesQuery = (query === '' || text.includes(query));

            if (matchesCategory && matchesQuery) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterFaqs);
    }

    pills.forEach(pill => {
        pill.addEventListener('click', function () {
            pills.forEach(p => {
                p.classList.remove('btn-eco-primary', 'active');
                p.classList.add('btn-pill-glass');
            });
            this.classList.remove('btn-pill-glass');
            this.classList.add('btn-eco-primary', 'active');

            activeCategory = this.dataset.category;
            filterFaqs();
        });
    });
});
</script>
