<?php
/**
 * 🌱 ECOTIENDA HN - CANAL DE CONTACTO (ORGANIC PREMIUM COMMERCE + STARBORDER + LEAFLET)
 * Ruta: /contacto.php
 * Descripción: Formulario público para registrar mensajes en la tabla MySQL `contacto`, protegiendo contra CSRF e inyecciones SQL.
 */

$pageTitle = "Contáctanos - EcoSoporte";
require_once __DIR__ . '/includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido o expirado. Recargá la página e intentá de nuevo.";
    } else {
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_DEFAULT);
        $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
        $asunto = filter_input(INPUT_POST, 'asunto', FILTER_DEFAULT);
        $mensaje = filter_input(INPUT_POST, 'mensaje', FILTER_DEFAULT);

        if (empty($nombre) || empty($correo) || empty($asunto) || empty($mensaje)) {
            $error = "Todos los campos de contacto son obligatorios.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "Formato de correo electrónico no válido.";
        } else {
            try {
                $db = Database::getConnection();
                $sql = "INSERT INTO contacto (nombre, correo, asunto, mensaje) VALUES (:nombre, :correo, :asunto, :mensaje)";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    ':nombre' => $nombre,
                    ':correo' => $correo,
                    ':asunto' => $asunto,
                    ':mensaje' => $mensaje
                ]);

                if ($result) {
                    logAuditoria(isLoggedIn() ? $_SESSION['user_id'] : null, "Envió mensaje de contacto: " . $asunto, "contacto");
                    $success = "¡Muchas gracias! Tu mensaje ha sido registrado. El equipo de EcoTienda HN te responderá al correo ingresado pronto.";
                    $_POST = [];
                } else {
                    $error = "Falla interna al enviar el mensaje de contacto.";
                }
            } catch (Exception $e) {
                $error = "Error al guardar el mensaje de contacto: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="container py-5 text-start">
    
    <div class="row g-5 align-items-center mb-5">
        <!-- COLUMNA DE INFORMACIÓN DE CANALES DIRECTOS -->
        <div class="col-lg-5">
            <div class="impact-badge-pill mb-3">
                📍 ECOSOPORTE DIRECTO HONDURAS
            </div>
            <h1 class="display-4 font-display fw-extrabold text-white mb-3" style="line-height: 1.1;">
                Estamos listos para <br>
                <span style="background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">escuchar tus ideas.</span>
            </h1>
            <p class="text-slate-300 mb-4" style="font-size: 1.05rem; color: #cbd5e1; line-height: 1.6;">
                ¿Tienes dudas sobre los componentes biodegradables, envíos regionales corporativos o deseas distribuir tus artesanías sostenibles en nuestra plataforma? Llena el formulario y con gusto te atenderemos.
            </p>

            <!-- Tarjetas de Canales Directos con StarBorder -->
            <div class="d-flex flex-column gap-3 mb-4">
                <!-- Canal 1: Dirección -->
                <div class="star-border-container w-100" style="--star-color: #10b981; --star-speed: 6s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-3.5 d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.4);">
                            <i class="fas fa-location-dot"></i>
                        </div>
                        <div>
                            <span class="d-block fw-bold text-white small">Ubicación Central</span>
                            <small class="text-slate-400" style="color: #94a3b8;">Tegucigalpa, Francisco Morazán. Honduras</small>
                        </div>
                    </div>
                </div>

                <!-- Canal 2: Teléfono -->
                <div class="star-border-container w-100" style="--star-color: #06b6d4; --star-speed: 5.5s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-3.5 d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(6, 182, 212, 0.2); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.4);">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <span class="d-block fw-bold text-white small">Atención WhatsApp / Llamadas</span>
                            <small class="text-slate-400" style="color: #94a3b8;">+504 3192-3329 (Lun - Sáb: 8am - 6pm)</small>
                        </div>
                    </div>
                </div>

                <!-- Canal 3: Correo -->
                <div class="star-border-container w-100" style="--star-color: #f59e0b; --star-speed: 5s;">
                    <div class="border-gradient-bottom"></div>
                    <div class="border-gradient-top"></div>
                    <div class="inner-content p-3.5 d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4);">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <span class="d-block fw-bold text-white small">Correo Institucional</span>
                            <small class="text-slate-400" style="color: #94a3b8;">soporte@ecotiendahn.com</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DE FORMULARIO DE CONTACTO EN GLASS CARD -->
        <div class="col-lg-7">
            <div class="star-border-container w-100" style="--star-color: #10b981; --star-speed: 6.5s;">
                <div class="border-gradient-bottom"></div>
                <div class="border-gradient-top"></div>
                <div class="inner-content p-4 p-md-5">
                    <h3 class="fw-bold mb-3 text-white font-display">Registrar Mensaje</h3>

                    <?php if(!empty($error)): ?>
                        <?php echo renderAlert($error, 'danger'); ?>
                    <?php endif; ?>

                    <?php if(!empty($success)): ?>
                        <?php echo renderAlert($success, 'success'); ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>contacto.php" method="POST">
                        <?php echo csrfField(); ?>
                        <div class="row g-3">
                            <div class="col-md-6 text-start">
                                <label for="nombre" class="form-label text-slate-400 small fw-bold" style="color: #94a3b8;">Nombre Completo *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ej: Diana Mendoza" required value="<?php echo isset($_POST['nombre']) ? sanitize($_POST['nombre']) : ''; ?>">
                            </div>

                            <div class="col-md-6 text-start">
                                <label for="correo" class="form-label text-slate-400 small fw-bold" style="color: #94a3b8;">Correo de Respuesta *</label>
                                <input type="email" name="correo" id="correo" class="form-control" placeholder="ejemplo@correo.com" required value="<?php echo isset($_POST['correo']) ? sanitize($_POST['correo']) : ''; ?>">
                            </div>

                            <div class="col-12 text-start">
                                <label for="asunto" class="form-label text-slate-400 small fw-bold" style="color: #94a3b8;">Asunto del Mensaje *</label>
                                <input type="text" name="asunto" id="asunto" class="form-control" placeholder="Ej: Consulta sobre ventas mayoristas" required value="<?php echo isset($_POST['asunto']) ? sanitize($_POST['asunto']) : ''; ?>">
                            </div>

                            <div class="col-12 text-start">
                                <label for="mensaje" class="form-label text-slate-400 small fw-bold" style="color: #94a3b8;">Mensaje / Detalle de la Solicitud *</label>
                                <textarea name="mensaje" id="mensaje" rows="5" class="form-control" placeholder="Escribe tu observación ecológica aquí..." required><?php echo isset($_POST['mensaje']) ? sanitize($_POST['mensaje']) : ''; ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-eco-primary w-100 py-3 mt-4 fw-bold rounded-pill">
                            <i class="fas fa-paper-plane me-2"></i> Enviar Mensaje Seguro
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MAPA LEAFLET INTERACTIVO MODERNO -->
<div class="container pb-5">
    <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 28px; background: rgba(15, 23, 42, 0.85) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important;">
        <div class="card-header border-0 px-4 py-3 text-white d-flex align-items-center justify-content-between" style="background: rgba(16, 185, 129, 0.12); border-bottom: 1px solid rgba(16, 185, 129, 0.2) !important;">
            <h5 class="fw-bold mb-0 font-display d-flex align-items-center gap-2">
                <i class="fas fa-map-location-dot text-success"></i> Centro de Despacho en Tegucigalpa
            </h5>
            <span class="badge rounded-pill bg-success px-3 py-1.5" style="background: var(--eco-primary) !important;">📦 Cobertura 18 Departamentos</span>
        </div>
        <div id="mapa-ecotienda" style="height: 420px; width: 100%;"></div>
    </div>
</div>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H" crossorigin="anonymous"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH" crossorigin="anonymous"></script>
<script>
    const map = L.map('mapa-ecotienda').setView([14.0818, -87.2068], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const ecoIcon = L.divIcon({
        html: '<div style="background:#10b981;width:38px;height:38px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 0 15px rgba(16,185,129,0.8);"></div>',
        iconSize: [38, 38],
        iconAnchor: [19, 38],
        popupAnchor: [0, -38],
        className: ''
    });

    L.marker([14.0818, -87.2068], { icon: ecoIcon })
        .addTo(map)
        .bindPopup(`
            <div style="font-family:'Plus Jakarta Sans',sans-serif;min-width:190px;padding:4px;">
                <div style="font-weight:700;font-size:1.05rem;color:#10b981;margin-bottom:4px;">🌱 EcoTienda HN</div>
                <div style="font-size:.85rem;color:#334155;">Tegucigalpa, Francisco Morazán</div>
                <div style="font-size:.8rem;color:#64748b;margin-top:4px;">📞 +504 3192-3329</div>
                <div style="font-size:.8rem;color:#64748b;">✉️ soporte@ecotiendahn.com</div>
                <hr style="margin:8px 0;border-color:#cbd5e1;">
                <div style="font-size:.78rem;color:#10b981;font-weight:600;">📦 Envíos rápidos a todo Honduras</div>
            </div>
        `)
        .openPopup();

    L.circle([14.0818, -87.2068], {
        color: '#10b981',
        fillColor: '#10b981',
        fillOpacity: 0.12,
        radius: 5000
    }).addTo(map);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
