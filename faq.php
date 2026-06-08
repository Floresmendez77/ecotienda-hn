<?php
/**
 * 🌱 ECOTIENDA HN - PREGUNTAS FRECUENTES (FAQ)
 * Ruta: /faq.php
 * Descripción: Muestra las preguntas recurrentes organizadas en categorías de forma amigable e interactiva.
 */

$pageTitle = "Preguntas Frecuentes";
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5 text-start">
    <div class="text-center mb-5">
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold mb-3">
            📚 Centro de Respuestas
        </span>
        <h1 class="fw-extrabold display-5 mb-2" style="font-family: var(--font-display);">Preguntas Frecuentes</h1>
        <p class="text-secondary col-md-6 mx-auto">Resuelve tus dudas sobre los empaques, envíos a cabeceras de Honduras y transferencias.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="accordion accordion-flush shadow-sm rounded-4 overflow-hidden" id="faqAccordion">
                
                <!-- FAQ 1 -->
                <div class="accordion-item border-bottom">
                    <h2 class="accordion-header" id="faqHeadingOne">
                        <button class="accordion-button fw-bold py-3.5 text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseOne" aria-expanded="true" aria-controls="faqCollapseOne">
                            ¿A qué partes de Honduras hacen envíos y cuánto tarda?
                        </button>
                    </h2>
                    <div id="faqCollapseOne" class="accordion-collapse collapse show" aria-labelledby="faqHeadingOne" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary leading-6 small">
                            Hacemos envíos a todos los departamentos de Honduras (Francisco Morazán, Cortés, Atlántida, etc.) a través de mensajería express y logística nacional. En San Pedro Sula y Tegucigalpa el tiempo estimado de entrega es de 24 a 48 horas hábiles. Para otras cabeceras departamentales, el envío suele tardar de 3 a 5 días hábiles.
                        </div>
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="accordion-item border-bottom">
                    <h2 class="accordion-header" id="faqHeadingTwo">
                        <button class="accordion-button collapsed fw-bold py-3.5 text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseTwo" aria-expanded="false" aria-controls="faqCollapseTwo">
                            ¿Qué materiales utilizan para empacar los pedidos?
                        </button>
                    </h2>
                    <div id="faqCollapseTwo" class="accordion-collapse collapse" aria-labelledby="faqHeadingTwo" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary leading-6 small">
                            Estamos comprometidos con el ambiente (Zero-Waste). No utilizamos bolsas de plástico de un solo uso, celofán ni cintas adhesivas convencionales. Todos tus productos se embalan en cajas de cartón reciclado, virutas de madera protectoras, papel kraft biodegradable y se sellan con cinta de papel activada por agua con adhesivo vegetal ecológico.
                        </div>
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="accordion-item border-bottom">
                    <h2 class="accordion-header" id="faqHeadingThree">
                        <button class="accordion-button collapsed fw-bold py-3.5 text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseThree" aria-expanded="false" aria-controls="faqCollapseThree">
                            ¿Cómo confirmo mi pago de transferencia?
                        </button>
                    </h2>
                    <div id="faqCollapseThree" class="accordion-collapse collapse" aria-labelledby="faqHeadingThree" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary leading-6 small">
                            Una vez que finalices tu compra en el checkout con el método "Transferencia", registrarás el código o referencia de la transferencia directamente. El administrador verificará tu pago en las cuentas correspondientes y el estado cambiará de "Pendiente" a "Abonado/Aprobado" de forma inmediata, habilitando el empaque de tus productos.
                        </div>
                    </div>
                </div>

                <!-- FAQ 4 -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqHeadingFour">
                        <button class="accordion-button collapsed fw-bold py-3.5 text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseFour" aria-expanded="false" aria-controls="faqCollapseFour">
                            ¿Ofrecen garantía de devolución?
                        </button>
                    </h2>
                    <div id="faqCollapseFour" class="accordion-collapse collapse" aria-labelledby="faqHeadingFour" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary leading-6 small">
                            Sí, por la naturaleza de los productos sostenibles (especialmente higiene) aceptamos devoluciones si el empaque original del fabricante se encuentra sellado y sin usar dentro de los primeros 7 días hábiles de la compra. Si recibiste un producto dañado en la ruta, con mucho gusto realizamos el reemplazo sin ningún cargo adicional.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
