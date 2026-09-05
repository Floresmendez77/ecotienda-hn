<?php
/**
 * 🌱 ECOTIENDA HN - SOBRE NOSOTROS (ORGANIC PREMIUM COMMERCE + GSAP MOTION + STARBORDER)
 * Ruta: /sobre_nosotros.php
 * Descripción: Presentación institucional, visión ambiental, métricas de impacto animadas y pilares éticos.
 */

$pageTitle = "Sobre Nosotros - Impacto Sostenible";
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5 text-start">
    
    <!-- HERO EDITORIAL INSTITUCIONAL -->
    <div class="row g-5 align-items-center mb-5 pb-4">
        <div class="col-lg-6">
            <div class="impact-badge-pill mb-3">
                <i class="fas fa-seedling me-1"></i> NUESTRA MISIÓN VERDE HONDUREÑA
            </div>
            <h1 class="display-3 font-display fw-extrabold text-white mb-4" style="line-height: 1.1;">
                Sembrando conciencia <br>
                <span style="background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">ecológica en Honduras.</span>
            </h1>
            <p class="text-slate-300 mb-4" style="font-size: 1.1rem; color: #cbd5e1; line-height: 1.7;">
                EcoTienda HN nació en Tegucigalpa, Francisco Morazán, con el ferviente propósito de democratizar el consumo sustentable. Creemos que cada pequeña acción cuenta: sustituir implementos cotidianos por alternativas biodegradables genera un impacto monumental en nuestros ríos, bosques y comunidades.
            </p>
            <p class="text-slate-400 mb-5" style="color: #94a3b8; line-height: 1.6;">
                Colaboramos activamente con cooperativas agrícolas y recolectores de Lempira, Copán e Intibucá, promoviendo el comercio justo y llevando sus productos artesanales a todo el país.
            </p>

            <div class="star-border-container" style="--star-color: #10b981; --star-speed: 4s;">
                <div class="border-gradient-bottom"></div>
                <div class="border-gradient-top"></div>
                <div class="inner-content" style="background: transparent; border: none;">
                    <a href="<?php echo BASE_URL; ?>tienda.php" class="btn btn-eco-primary btn-lg px-4">
                        <i class="fas fa-store me-2"></i> Ver Productos Sustentables
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="position-relative">
                <div class="card border-0 shadow-2xl overflow-hidden" style="border-radius: 32px; height: 420px; border: 1px solid rgba(255,255,255,0.12) !important;">
                    <img src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&auto=format&fit=crop&q=80" class="w-100 h-100 object-fit-cover img-zoom-hover" alt="Honduras Sostenible">
                    <div class="position-absolute bottom-0 start-0 w-100 p-4 text-white" style="background: linear-gradient(0deg, rgba(7,10,15,0.95) 0%, transparent 100%);">
                        <span class="badge bg-success mb-2 px-3 py-1 rounded-pill" style="background: var(--eco-primary) !important;">Honduras Verde</span>
                        <h4 class="fw-bold mb-0 font-display">Preservando nuestros ecosistemas</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MÉTRICAS DE IMPACTO CON GSAP COUNT-UP -->
    <div class="row g-4 my-5 text-center">
        <div class="col-6 col-md-3">
            <div class="card border-0 p-4 shadow-sm h-100" style="border-radius: 24px;">
                <h2 class="display-4 fw-extrabold text-success font-display mb-1 counter-num" data-target="15000" style="color: #10b981 !important;">0</h2>
                <span class="text-slate-400 small fw-semibold" style="color: #94a3b8;">Plásticos Ahorrados</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 p-4 shadow-sm h-100" style="border-radius: 24px;">
                <h2 class="display-4 fw-extrabold text-info font-display mb-1 counter-num" data-target="100" style="color: #06b6d4 !important;">0</h2>
                <span class="text-slate-400 small fw-semibold" style="color: #94a3b8;">% Manos Hondureñas</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 p-4 shadow-sm h-100" style="border-radius: 24px;">
                <h2 class="display-4 fw-extrabold text-warning font-display mb-1 counter-num" data-target="18" style="color: #f59e0b !important;">0</h2>
                <span class="text-slate-400 small fw-semibold" style="color: #94a3b8;">Departamentos Cubiertos</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 p-4 shadow-sm h-100" style="border-radius: 24px;">
                <h2 class="display-4 fw-extrabold text-danger font-display mb-1 counter-num" data-target="0" style="color: #ef4444 !important;">0</h2>
                <span class="text-slate-400 small fw-semibold" style="color: #94a3b8;">Químicos Tóxicos</span>
            </div>
        </div>
    </div>

    <!-- NUESTROS ECO-PILARES CON STARBORDER -->
    <div class="my-5">
        <div class="text-center mb-5">
            <span class="impact-badge-pill mb-2 px-3 py-1" style="font-size: 0.78rem;">🌿 VALORES IRROMPIBLES</span>
            <h2 class="display-5 fw-bold text-white font-display">Nuestros Eco-Pilares</h2>
            <p class="text-slate-400" style="color: #94a3b8;">Los cimientos sobre los cuales construimos comercio justo y sustentable</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="star-border-container w-100 h-100" style="--star-color: #10b981; --star-speed: 5s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-4 text-start">
                        <span class="text-success mb-3 d-inline-block" style="font-size: 2.2rem; color: #10b981 !important;"><i class="fas fa-tree"></i></span>
                        <h4 class="fw-bold mb-3 text-white font-display">Compromiso Carbono Cero</h4>
                        <p class="text-slate-400 mb-0" style="font-size: 0.94rem; color: #94a3b8; line-height: 1.6;">
                            Suministramos y empacamos de manera de compensar las emisiones de transporte, utilizando exclusivamente papel, cartón de descarte y adhesivos naturales biodegradables.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="star-border-container w-100 h-100" style="--star-color: #06b6d4; --star-speed: 6s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-4 text-start">
                        <span class="text-info mb-3 d-inline-block" style="font-size: 2.2rem; color: #06b6d4 !important;"><i class="fas fa-handshake"></i></span>
                        <h4 class="fw-bold mb-3 text-white font-display">Comercio 100% Justo</h4>
                        <p class="text-slate-400 mb-0" style="font-size: 0.94rem; color: #94a3b8; line-height: 1.6;">
                            Eliminamos las cadenas de intermediarios abusivas comprando de manera directa a pequeños productores hondureños para asegurar salarios dignos y sustentables en el agro.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="star-border-container w-100 h-100" style="--star-color: #f59e0b; --star-speed: 5.5s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-4 text-start">
                        <span class="text-warning mb-3 d-inline-block" style="font-size: 2.2rem; color: #f59e0b !important;"><i class="fas fa-box-open"></i></span>
                        <h4 class="fw-bold mb-3 text-white font-display">Innovación en Envases</h4>
                        <p class="text-slate-400 mb-0" style="font-size: 0.94rem; color: #94a3b8; line-height: 1.6;">
                            Fomentamos activamente el formato sólido y libre de envase (champú, acondicionador, pastas), ahorrando miles de toneladas de plásticos contaminantes al año.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CRONOLOGÍA / MILESTONES HISTORIA -->
    <div class="my-5 py-4">
        <div class="text-center mb-5">
            <h2 class="display-6 fw-bold text-white font-display">Nuestra Trayectoria</h2>
            <p class="text-slate-400" style="color: #94a3b8;">El camino recorrido llevando sustentabilidad a Honduras</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="position-relative border-start border-secondary border-opacity-25 ps-4 ms-3">
                    <!-- Hito 1 -->
                    <div class="mb-5 position-relative">
                        <div class="position-absolute top-0 start-0 translate-middle-x rounded-circle bg-success" style="width: 16px; height: 16px; left: -25px; box-shadow: 0 0 10px #10b981;"></div>
                        <span class="text-success fw-bold small" style="color: #34d399 !important;">2022</span>
                        <h5 class="fw-bold text-white font-display mb-2">Fundación en Tegucigalpa</h5>
                        <p class="text-slate-400 small mb-0" style="color: #94a3b8;">Iniciamos con una selección de 5 productos biodegradables de cuidado personal elaborados en casa.</p>
                    </div>

                    <!-- Hito 2 -->
                    <div class="mb-5 position-relative">
                        <div class="position-absolute top-0 start-0 translate-middle-x rounded-circle bg-info" style="width: 16px; height: 16px; left: -25px; box-shadow: 0 0 10px #06b6d4;"></div>
                        <span class="text-info fw-bold small" style="color: #38bdf8 !important;">2023</span>
                        <h5 class="fw-bold text-white font-display mb-2">Red de Cooperativas Rurales</h5>
                        <p class="text-slate-400 small mb-0" style="color: #94a3b8;">Firmamos alianzas directas con artesanos de Lempira, Copán e Intibucá para distribución justa.</p>
                    </div>

                    <!-- Hito 3 -->
                    <div class="position-relative">
                        <div class="position-absolute top-0 start-0 translate-middle-x rounded-circle bg-warning" style="width: 16px; height: 16px; left: -25px; box-shadow: 0 0 10px #f59e0b;"></div>
                        <span class="text-warning fw-bold small" style="color: #fbbf24 !important;">2024 - Presente</span>
                        <h5 class="fw-bold text-white font-display mb-2">Ecosistema E-Commerce Nacional</h5>
                        <p class="text-slate-400 small mb-0" style="color: #94a3b8;">Lanzamiento de la plataforma digital oficial enviando a los 18 departamentos de Honduras.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- GSAP ANIMATION SCRIPT FOR ABOUT US -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof gsap !== 'undefined') {
        // Animación de contadores numéricos al hacer scroll
        document.querySelectorAll('.counter-num').forEach(el => {
            const target = parseInt(el.dataset.target, 10);
            if (isNaN(target)) return;

            if (typeof ScrollTrigger !== 'undefined') {
                ScrollTrigger.create({
                    trigger: el,
                    start: 'top 85%',
                    onEnter: () => {
                        gsap.to(el, {
                            innerText: target,
                            duration: 2,
                            snap: { innerText: 1 },
                            ease: 'power2.out'
                        });
                    }
                });
            } else {
                gsap.to(el, {
                    innerText: target,
                    duration: 2,
                    snap: { innerText: 1 },
                    ease: 'power2.out'
                });
            }
        });
    }
});
</script>
