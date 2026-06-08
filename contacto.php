<?php
/**
 * 🌱 ECOTIENDA HN - CANAL DE CONTACTO
 * Ruta: /contacto.php
 * Descripción: Formulario público para registrar mensajes en la tabla MySQL `contacto`, protegiendo de inyecciones SQL.
 */

$pageTitle = "Contáctanos";
require_once __DIR__ . '/includes/navbar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                // Limpiar campos de post
                $_POST = [];
            } else {
                $error = "Falla interna al enviar el mensaje de contacto.";
            }
        } catch (Exception $e) {
            $error = "Error al guardar el mensaje de contacto: " . $e->getMessage();
        }
    }
}
?>

<div class="container py-5">
    
    <div class="row g-5 align-items-center">
        <!-- Columna de Información -->
        <div class="col-lg-5 text-start">
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold mb-3">
                📍 EcoSoporte Honduras
            </span>
            <h1 class="fw-extrabold display-5 mb-4" style="font-family: var(--font-display);">Escríbenos</h1>
            <p class="text-secondary mb-4 leading-6" style="font-size: 1.05rem;">
                ¿Tienes dudas sobre los componentes biodegradables, envíos regionales corporativos o deseas distribuir tus artesanías sostenibles en nuestra plataforma? Llena el formulario y con gusto te atenderemos.
            </p>

            <ul class="list-unstyled d-flex flex-column gap-3 mb-4 text-secondary">
                <li class="d-flex align-items-center gap-3">
                    <span class="bg-success text-white p-2 rounded-circle" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-location-dot"></i></span>
                    <span>Tegucigalpa, Francisco Morazán. Honduras</span>
                </li>
                <li class="d-flex align-items-center gap-3">
                    <span class="bg-success text-white p-2 rounded-circle" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-phone"></i></span>
                    <span>+504 3192-3329</span>
                </li>
                <li class="d-flex align-items-center gap-3">
                    <span class="bg-success text-white p-2 rounded-circle" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-envelope"></i></span>
                    <span>soporte@ecotiendahn.com</span>
                </li>
            </ul>
        </div>

        <!-- Columna de Formulario -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-lg p-4 p-md-5" style="border-radius: 20px;">
                <h4 class="fw-bold mb-3" style="font-family: var(--font-display);">Registrar Mensaje</h4>

                <?php if(!empty($error)): ?>
                    <?php echo renderAlert($error, 'danger'); ?>
                <?php endif; ?>

                <?php if(!empty($success)): ?>
                    <?php echo renderAlert($success, 'success'); ?>
                <?php endif; ?>

                <form action="<?php echo BASE_URL; ?>contacto.php" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6 text-start">
                            <label for="nombre" class="form-label text-secondary small fw-bold">Nombre Completo *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ej: Diana Mendoza" required value="<?php echo isset($_POST['nombre']) ? sanitize($_POST['nombre']) : ''; ?>">
                        </div>

                        <div class="col-md-6 text-start">
                            <label for="correo" class="form-label text-secondary small fw-bold">Correo de Respuesta *</label>
                            <input type="email" name="correo" id="correo" class="form-control" placeholder="ejemplo@correo.com" required value="<?php echo isset($_POST['correo']) ? sanitize($_POST['correo']) : ''; ?>">
                        </div>

                        <div class="col-12 text-start">
                            <label for="asunto" class="form-label text-secondary small fw-bold">Asunto del Mensaje *</label>
                            <input type="text" name="asunto" id="asunto" class="form-control" placeholder="Ej: Consulta sobre ventas mayoristas" required value="<?php echo isset($_POST['asunto']) ? sanitize($_POST['asunto']) : ''; ?>">
                        </div>

                        <div class="col-12 text-start">
                            <label for="mensaje" class="form-label text-secondary small fw-bold">Mensaje / Detalle de la Solicitud *</label>
                            <textarea name="mensaje" id="mensaje" rows="5" class="form-control" placeholder="Escribe tu observación ecológica aquí..." required><?php echo isset($_POST['mensaje']) ? sanitize($_POST['mensaje']) : ''; ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-eco-primary w-100 py-2.5 mt-4 fw-bold rounded-3">
                        <i class="fas fa-paper-plane me-2"></i> Enviar Mensaje Seguro
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- MAPA LEAFLET - Ubicación EcoTienda HN -->
<div class="container pb-5">
    <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 20px;">
        <div class="card-header bg-success bg-opacity-10 border-0 px-4 py-3">
            <h5 class="fw-bold mb-0" style="font-family: var(--font-display);">
                <i class="fas fa-map-location-dot text-success me-2"></i> Nuestra Ubicación en Tegucigalpa
            </h5>
            <p class="text-secondary small mb-0">📦 Zona de despacho: Todo Honduras</p>
        </div>
        <div id="mapa-ecotienda" style="height: 420px; width: 100%;"></div>
    </div>
</div>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('mapa-ecotienda').setView([14.0818, -87.2068], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Ícono personalizado verde
    const ecoIcon = L.divIcon({
        html: '<div style="background:#10b981;width:36px;height:36px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);"></div>',
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -36],
        className: ''
    });

    L.marker([14.0818, -87.2068], { icon: ecoIcon })
        .addTo(map)
        .bindPopup(`
            <div style="font-family:'Segoe UI',sans-serif;min-width:180px;">
                <div style="font-weight:700;font-size:1rem;color:#1a5c2a;margin-bottom:4px;">🌱 EcoTienda HN</div>
                <div style="font-size:.82rem;color:#555;">Tegucigalpa, Francisco Morazán</div>
                <div style="font-size:.8rem;color:#888;margin-top:4px;">📞 +504 3192-3329</div>
                <div style="font-size:.8rem;color:#888;">✉️ soporte@ecotiendahn.com</div>
                <hr style="margin:6px 0;">
                <div style="font-size:.75rem;color:#10b981;font-weight:600;">📦 Envíos a todo Honduras</div>
            </div>
        `)
        .openPopup();

    // Círculo zona de cobertura
    L.circle([14.0818, -87.2068], {
        color: '#10b981',
        fillColor: '#10b981',
        fillOpacity: 0.08,
        radius: 5000
    }).addTo(map);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
