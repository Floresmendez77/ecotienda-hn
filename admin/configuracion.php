<?php
/**
 * 🌱 ECOTIENDA HN - CONFIGURACIÓN DEL SISTEMA
 * Ruta: /admin/configuracion.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Configuración Planetaria";
$pageSubtitle = "🌱 Adapta variables clave de comercio, costo logístico y canales informativos.";
$error      = '';
$success    = '';
$errorPwd   = '';
$successPwd = '';

$db = Database::getConnection();

// ── Guardar parámetros globales ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido o expirado. Recargá la página e intentá de nuevo.";
    } else {
    $nombre_sitio          = filter_input(INPUT_POST, 'nombre_sitio',          FILTER_DEFAULT);
    $telefono_contacto     = filter_input(INPUT_POST, 'telefono_contacto',     FILTER_DEFAULT);
    $correo_notificaciones = filter_input(INPUT_POST, 'correo_notificaciones', FILTER_SANITIZE_EMAIL);
    $costo_envio           = (float)($_POST['costo_envio'] ?? 150.00);

    try {
        $db->beginTransaction();

        $params = [
            'nombre_sitio'          => $nombre_sitio,
            'telefono_contacto'     => $telefono_contacto,
            'correo_notificaciones' => $correo_notificaciones,
            'costo_envio'           => $costo_envio
        ];

        $updateStmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor) 
                                    ON DUPLICATE KEY UPDATE valor = :valor");
        foreach ($params as $clave => $valor) {
            $updateStmt->execute([':clave' => $clave, ':valor' => (string)$valor]);
        }

        logAuditoria($_SESSION['user_id'], "Actualizó configuración global del sitio", "configuracion");
        $db->commit();
        $success = "¡Fabuloso! Todos los parámetros globales de la EcoTienda HN se han actualizado correctamente.";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = "Error al guardar parámetros globales: " . $e->getMessage();
    }
    }
}

// ── Cambiar contraseña del admin ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorPwd = "Token de seguridad inválido o expirado. Recargá la página e intentá de nuevo.";
    } else {
    $pwd_actual    = $_POST['pwd_actual']    ?? '';
    $pwd_nueva     = $_POST['pwd_nueva']     ?? '';
    $pwd_confirmar = $_POST['pwd_confirmar'] ?? '';

    if (empty($pwd_actual) || empty($pwd_nueva) || empty($pwd_confirmar)) {
        $errorPwd = "Todos los campos de contraseña son obligatorios.";
    } elseif (strlen($pwd_nueva) < 8) {
        $errorPwd = "La nueva contraseña debe tener al menos 8 caracteres.";
    } elseif ($pwd_nueva !== $pwd_confirmar) {
        $errorPwd = "La nueva contraseña y su confirmación no coinciden.";
    } else {
        try {
            $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || !password_verify($pwd_actual, $usuario['password'])) {
                $errorPwd = "La contraseña actual es incorrecta.";
            } else {
                $hash = password_hash($pwd_nueva, PASSWORD_DEFAULT);
                $upd  = $db->prepare("UPDATE usuarios SET password = :hash WHERE id = :id");
                $upd->execute([':hash' => $hash, ':id' => $_SESSION['user_id']]);

                logAuditoria($_SESSION['user_id'], "Cambió su contraseña de acceso al panel admin", "configuracion");
                $successPwd = "Contraseña actualizada correctamente. Úsala en tu próximo inicio de sesión.";
            }
        } catch (Exception $e) {
            $errorPwd = "Error al cambiar contraseña: " . $e->getMessage();
        }
    }
    }
}

// ── Cargar variables existentes ───────────────────────────────────────────────
$settings = [
    'nombre_sitio'          => '🌱 EcoTienda HN',
    'telefono_contacto'     => '+504 3192-3329',
    'correo_notificaciones' => 'soporte@ecotiendahn.com',
    'costo_envio'           => 150.00
];

try {
    $rows = $db->query("SELECT clave, valor FROM configuracion")->fetchAll();
    foreach ($rows as $row) {
        if (array_key_exists($row['clave'], $settings)) {
            $settings[$row['clave']] = $row['valor'];
        }
    }
} catch (Exception $e) { /* sin tabla aún */ }
?>
<?php require_once __DIR__ . '/includes/admin_navbar.php'; ?>

<div class="text-start">
    

    <div class="row g-4">

        <!-- ── Columna izquierda: parámetros del sitio ── -->
        <div class="col-lg-8">

            <!-- Parámetros globales -->
            <?php if(!empty($error)):   echo renderAlert($error,   'danger');  endif; ?>
            <?php if(!empty($success)): echo renderAlert($success, 'success'); endif; ?>

            <div class="admin-card mb-4">
                <h5 class="fw-bold mb-4" style="font-family: var(--font-display);">
                    <i class="fas fa-sliders text-success me-2"></i> Configuración del Sitio
                </h5>
                <form action="<?php echo BASE_URL; ?>admin/configuracion.php" method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="save_settings">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nombre de la Ecoplataforma *</label>
                            <input type="text" name="nombre_sitio" class="form-control" value="<?php echo sanitize($settings['nombre_sitio']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Costo Envío Estándar (Lps.) *</label>
                            <input type="number" step="0.01" name="costo_envio" class="form-control" value="<?php echo e($settings['costo_envio']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Correo de Notificaciones *</label>
                            <input type="email" name="correo_notificaciones" class="form-control" value="<?php echo sanitize($settings['correo_notificaciones']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">WhatsApp de Ayuda *</label>
                            <input type="text" name="telefono_contacto" class="form-control" value="<?php echo sanitize($settings['telefono_contacto']); ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-eco-primary mt-4">Guardar Parámetros</button>
                </form>
            </div>

            <!-- Cambiar contraseña -->
            <?php if(!empty($errorPwd)):   echo renderAlert($errorPwd,   'danger');  endif; ?>
            <?php if(!empty($successPwd)): echo renderAlert($successPwd, 'success'); endif; ?>

            <div class="admin-card">
                <h5 class="fw-bold mb-1" style="font-family: var(--font-display);">
                    <i class="fas fa-lock text-warning me-2"></i> Cambiar Contraseña de Acceso
                </h5>
                <p class="text-secondary small mb-4">Actualiza la contraseña de tu cuenta admin. Mínimo 8 caracteres.</p>

                <form action="<?php echo BASE_URL; ?>admin/configuracion.php" method="POST" autocomplete="off">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="row g-3">

                        <!-- Contraseña actual -->
                        <div class="col-12">
                            <label class="form-label small fw-bold">Contraseña Actual *</label>
                            <div class="pwd-wrap">
                                <input type="password" name="pwd_actual" id="pwd_actual" class="form-control" placeholder="Tu contraseña actual" required autocomplete="current-password">
                                <button type="button" class="pwd-toggle" onclick="togglePwd('pwd_actual', this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>

                        <!-- Nueva contraseña -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nueva Contraseña *</label>
                            <div class="pwd-wrap">
                                <input type="password" name="pwd_nueva" id="pwd_nueva" class="form-control" placeholder="Mínimo 8 caracteres" required autocomplete="new-password" oninput="checkStrength(this.value)">
                                <button type="button" class="pwd-toggle" onclick="togglePwd('pwd_nueva', this)"><i class="fas fa-eye"></i></button>
                            </div>
                            <!-- Barra de fuerza -->
                            <div style="background:rgba(255,255,255,0.06);border-radius:2px;height:4px;margin-top:6px;">
                                <div id="strengthBar" class="pwd-strength-bar"></div>
                            </div>
                            <div id="strengthLabel" class="mt-1" style="font-size:.75rem;color:#64748b;"></div>
                        </div>

                        <!-- Confirmar nueva contraseña -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Confirmar Nueva Contraseña *</label>
                            <div class="pwd-wrap">
                                <input type="password" name="pwd_confirmar" id="pwd_confirmar" class="form-control" placeholder="Repite la nueva contraseña" required autocomplete="new-password">
                                <button type="button" class="pwd-toggle" onclick="togglePwd('pwd_confirmar', this)"><i class="fas fa-eye"></i></button>
                            </div>
                            <div id="matchLabel" class="mt-1" style="font-size:.75rem;"></div>
                        </div>

                    </div>
                    <button type="submit" class="btn btn-eco-primary mt-4">
                        <i class="fas fa-key me-1"></i> Actualizar Contraseña
                    </button>
                </form>
            </div>

        </div><!-- /col-lg-8 -->

        <!-- ── Columna derecha: info de sesión ── -->
        <div class="col-lg-4">
            <div class="admin-card">
                <h6 class="fw-bold mb-3" style="font-family: var(--font-display);">
                    <i class="fas fa-circle-info text-secondary me-2"></i> Sesión Activa
                </h6>
                <p class="small text-secondary mb-1">Usuario ID</p>
                <p class="fw-bold mb-3">#<?php echo (int)$_SESSION['user_id']; ?></p>
                <p class="small text-secondary mb-1">Rol</p>
                <p class="fw-bold mb-3">
                    <span style="background:rgba(16,185,129,.15);color:#10b981;padding:3px 10px;border-radius:50px;font-size:.8rem;">Administrador</span>
                </p>
                <p class="small text-secondary mb-1">Servidor</p>
                <p class="fw-bold mb-0" style="font-size:.85rem;word-break:break-all;"><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost'); ?></p>
            </div>
        </div>

    </div><!-- /row -->

</div>
<!-- /main-content -->

<script>
// ── Mostrar/ocultar contraseña ────────────────────────────────────────────────
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ── Medidor de fuerza de contraseña ──────────────────────────────────────────
function checkStrength(val) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8)                        score++;
    if (val.length >= 12)                       score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val))                      score++;
    if (/[^A-Za-z0-9]/.test(val))              score++;

    const levels = [
        { w:'0%',   color:'transparent', txt:'' },
        { w:'25%',  color:'#ef4444',     txt:'Muy débil' },
        { w:'50%',  color:'#f97316',     txt:'Débil' },
        { w:'70%',  color:'#eab308',     txt:'Aceptable' },
        { w:'88%',  color:'#10b981',     txt:'Fuerte' },
        { w:'100%', color:'#10b981',     txt:'Muy fuerte ✓' },
    ];
    const l = levels[Math.min(score, 5)];
    bar.style.width      = val.length ? l.w     : '0%';
    bar.style.background = val.length ? l.color : 'transparent';
    label.textContent    = val.length ? l.txt   : '';
    label.style.color    = l.color;
}

// ── Validar que las contraseñas coincidan en tiempo real ──────────────────────
document.getElementById('pwd_confirmar').addEventListener('input', function() {
    const nueva    = document.getElementById('pwd_nueva').value;
    const lbl      = document.getElementById('matchLabel');
    if (!this.value) { lbl.textContent = ''; return; }
    if (this.value === nueva) {
        lbl.textContent = '✓ Las contraseñas coinciden';
        lbl.style.color = '#10b981';
    } else {
        lbl.textContent = '✗ No coinciden';
        lbl.style.color = '#f87171';
    }
});
</script>


<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
