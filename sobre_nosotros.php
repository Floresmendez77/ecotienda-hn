<?php
/**
 * 🌱 ECOTIENDA HN - SOBRE NOSOTRAS
 * Ruta: /sobre_nosotros.php
 * Descripción: Presentación institucional, visión ambiental y bases éticas de la empresa de Honduras.
 */

$pageTitle = "Sobre Nosotros";
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5 text-start">
    
    <!-- Hero corporativo -->
    <div class="row g-5 align-items-center mb-5">
        <div class="col-lg-6">
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold mb-3">
                🏡 Nuestra Misión Verde
            </span>
            <h1 class="fw-extrabold display-5 mb-4" style="font-family: var(--font-display);">Sembrando conciencia ecológicas en Honduras</h1>
            <p class="text-secondary leading-7 mb-4" style="font-size: 1.05rem;">
                EcoTienda HN nació en Tegucigalpa, Honduras con el ferviente propósito de democratizar el consumo sustentable. Creemos que cada pequeña acción cuenta, y sustituir implementos cotidianos de plástico por alternativas de bambú, fibras naturales y formulaciones orgánicas biodegradables genera un impacto monumental a largo plazo en nuestros ríos, bosques y comunidades.
            </p>
            <p class="text-secondary leading-7">
                Colaboramos activamente con cooperativas agrícolas y recolectores de Lempira, Copán e Intibucá, pagando precios justos y ofreciendo sus productos de forma digital a todo el país.
            </p>
        </div>
        <div class="col-lg-6">
            <div class="rounded-4 overflow-hidden shadow-lg border-2 border-white border-opacity-10" style="height: 380px;">
                <img src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=700&auto=format&fit=crop&q=60" class="w-100 h-100 object-fit-cover" alt="Honduras Sostenible">
            </div>
        </div>
    </div>

    <!-- Valores corporativos -->
    <div class="row g-4 mt-5">
        <div class="text-center mb-4">
            <h2 class="fw-bold" style="font-family: var(--font-display);">Nuestros Eco-Pilares</h2>
            <p class="text-secondary">Los valores irrompibles sobre los cuales construimos comercio justo</p>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 p-4 shadow-sm" style="border-radius: 16px;">
                <span class="text-success mb-3" style="font-size: 1.8rem;"><i class="fas fa-tree"></i></span>
                <h5 class="fw-bold mb-2">Compromiso Carbono Cero</h5>
                <p class="text-secondary mb-0 small leading-6">Suministramos y empacamos de manera de compensar las emisiones de transporte, utilizando exclusivamente papel, cartón de descarte y adhesivos naturales biodegradables.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 p-4 shadow-sm" style="border-radius: 16px;">
                <span class="text-success mb-3" style="font-size: 1.8rem;"><i class="fas fa-handshake"></i></span>
                <h5 class="fw-bold mb-2">Comercio 100% Justo</h5>
                <p class="text-secondary mb-0 small leading-6">Eliminamos las cadenas de intermediarios abusivas comprando de manera directa a pequeños productores hondureños para asegurar salarios dignos y sustentables en el agro.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 p-4 shadow-sm" style="border-radius: 16px;">
                <span class="text-success mb-3" style="font-size: 1.8rem;"><i class="fas fa-microchip"></i></span>
                <h5 class="fw-bold mb-2">Innovación en Envases</h5>
                <p class="text-secondary mb-0 small leading-6">Fomentamos activamente el formato sólido y libre de envase (champú, acondicionador, pastas), ahorrando miles de toneladas de plásticos contaminantes al año.</p>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
