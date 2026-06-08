<?php
/**
 * 🌱 ECOTIENDA HN - RESTABLECIMIENTO DE CONTRASEÑA
 * Ruta: /reset_password.php
 * Descripción: Valida token de 64 chars desde auditoria, verifica expiración (1 hora),
 *              permite nueva contraseña, hashea con password_hash() e invalida el token.
 */

$pageTitle = "Nueva Contraseña";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    redirect('/tienda.php');
}

$token   = trim($_GET['token'] ?? '');
$success = '';
$error   = '';
$tokenOk = false;
$tokenData = null; // ['audit_id', 'usuario_id', 'expires']

// ── Validar token en auditoría ────────────────────────────────────────────
if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $error = 'El enlace de recuperación es inválido o está malformado.';
} else {
    try {
        $db = Database::getConnection();

        /*
         * El token se guardó en auditoria.accion con el formato:
         *   reset_token:<token>:expires:<YYYY-MM-DD HH:MM:SS>
         * Buscamos cualquier registro que contenga el token.
         */
        $likePattern = 'reset_token:' . $token . ':expires:%';
        $stmt = $db->prepare("SELECT id, usuario_id, accion FROM auditoria WHERE accion LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$likePattern]);
        $audit = $stmt->fetch();

        if (!$audit) {
            $error = 'El enlace de recuperación no existe o ya fue utilizado.';
        } else {
            // Extraer fecha de expiración del campo accion
            preg_match('/expires:(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $audit['accion'], $matches);
            $expires = $matches[1] ?? null;

            if (!$expires || strtotime($expires) < time()) {
                // Token expirado — invalidar y mostrar error
                $del = $db->prepare("DELETE FROM auditoria WHERE id = ?");
                $del->execute([$audit['id']]);
                $error = 'El enlace de recuperación ha expirado (válido 1 hora). Solicita uno nuevo.';
            } else {
                // Token válido
                $tokenOk = true;
                $tokenData = [
                    'audit_id'   => $audit['id'],
                    'usuario_id' => $audit['usuario_id'],
                ];
            }
        }
    } catch (Exception $e) {
        error_log('[EcoTienda ResetPwd] ' . $e->getMessage());
        $error = 'Error al validar el enlace. Por favor intenta de nuevo.';
    }
}

// ── Procesar formulario de nueva contraseña ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenOk && $tokenData) {
    $password        = $_POST['password']        ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error   = 'La contraseña debe tener al menos 8 caracteres.';
        $tokenOk = true; // mantener formulario visible
    } elseif ($password !== $password_confirm) {
        $error   = 'Las contraseñas no coinciden.';
        $tokenOk = true;
    } else {
        try {
            $db = Database::getConnection();

            // Hashear nueva contraseña
            $hash = password_hash($password, PASSWORD_BCRYPT);

            // Actualizar contraseña en la BD
            $upd = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $upd->execute([$hash, $tokenData['usuario_id']]);

            // Invalidar token (eliminar de auditoría)
            $del = $db->prepare("DELETE FROM auditoria WHERE id = ?");
            $del->execute([$tokenData['audit_id']]);

            // Auditar el cambio
            logAuditoria($tokenData['usuario_id'], 'Contraseña restablecida via token de recuperación', 'usuarios');

            // Notificar por email que la contraseña fue cambiada
            notify_cambio_password($db, $tokenData['usuario_id']);

            // Guardar mensaje de éxito en sesión y redirigir al login
            $_SESSION['flash_success'] = '✅ ¡Contraseña actualizada exitosamente! Ya puedes iniciar sesión con tu nueva contraseña.';
            redirect('/login.php');

        } catch (Exception $e) {
            error_log('[EcoTienda ResetPwd POST] ' . $e->getMessage());
            $error   = 'Error al actualizar la contraseña. Intenta de nuevo.';
            $tokenOk = true;
        }
    }
}

require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5" style="min-height:70vh;">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">

            <div class="card border-0 shadow-sm p-4 p-md-5" style="border-radius:20px;">

                <!-- Encabezado -->
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                         style="width:72px;height:72px;background:linear-gradient(135deg,#1a5c2a,#2d8a45);">
                        <i class="fas fa-shield-alt text-white fa-xl"></i>
                    </div>
                    <h2 class="fw-bold mb-1" style="font-family:var(--font-display);font-size:1.5rem;">
                        Nueva Contraseña
                    </h2>
                    <p class="text-secondary small mb-0">
                        Elige una contraseña segura para tu cuenta en EcoTienda HN.
                    </p>
                </div>

                <!-- Alerta de error -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-2" role="alert" style="border-radius:12px;">
                        <i class="fas fa-exclamation-triangle mt-1 flex-shrink-0"></i>
                        <span style="font-size:.88rem;"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($tokenOk): ?>
                <!-- Formulario de nueva contraseña -->
                <form method="POST" action="<?php echo BASE_URL; ?>reset_password.php?token=<?php echo urlencode($token); ?>" novalidate id="reset-form">

                    <!-- Nueva contraseña -->
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold small text-secondary">
                            <i class="fas fa-lock me-1"></i> Nueva contraseña
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   class="form-control form-control-lg"
                                   id="password"
                                   name="password"
                                   placeholder="Mínimo 8 caracteres"
                                   minlength="8"
                                   required
                                   autofocus
                                   style="border-radius:10px 0 0 10px;">
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePwd('password', this)"
                                    style="border-radius:0 10px 10px 0;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <!-- Indicador de fortaleza -->
                        <div class="progress mt-2" style="height:4px;border-radius:4px;">
                            <div class="progress-bar" id="pwd-strength-bar" role="progressbar" style="width:0%;transition:width .3s,background .3s;"></div>
                        </div>
                        <small class="text-secondary" id="pwd-strength-label"></small>
                    </div>

                    <!-- Confirmar contraseña -->
                    <div class="mb-4">
                        <label for="password_confirm" class="form-label fw-semibold small text-secondary">
                            <i class="fas fa-lock me-1"></i> Confirmar contraseña
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   class="form-control form-control-lg"
                                   id="password_confirm"
                                   name="password_confirm"
                                   placeholder="Repite tu contraseña"
                                   minlength="8"
                                   required
                                   style="border-radius:10px 0 0 10px;">
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePwd('password_confirm', this)"
                                    style="border-radius:0 10px 10px 0;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-danger d-none" id="match-error">Las contraseñas no coinciden.</small>
                    </div>

                    <button type="submit" id="submit-btn"
                            class="btn btn-eco-primary w-100 py-3 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-check-circle"></i> Actualizar Contraseña
                    </button>
                </form>

                <?php elseif (empty($error)): ?>
                    <!-- Estado de carga inesperado -->
                    <p class="text-center text-secondary">Procesando tu solicitud...</p>

                <?php else: ?>
                    <!-- Token inválido / expirado — mostrar opción de nuevo enlace -->
                    <div class="text-center mt-2">
                        <a href="<?php echo BASE_URL; ?>forgot_password.php"
                           class="btn btn-eco-primary rounded-pill px-4">
                            <i class="fas fa-redo me-2"></i> Solicitar nuevo enlace
                        </a>
                    </div>
                <?php endif; ?>

                <hr class="my-4 opacity-25">
                <div class="text-center small text-secondary">
                    <a href="<?php echo BASE_URL; ?>login.php" class="text-success fw-semibold text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Volver al inicio de sesión
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// Mostrar/ocultar contraseña
function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.innerHTML = isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}

// Indicador de fortaleza de contraseña
const pwdInput = document.getElementById('password');
const bar      = document.getElementById('pwd-strength-bar');
const label    = document.getElementById('pwd-strength-label');

if (pwdInput) {
    pwdInput.addEventListener('input', () => {
        const val = pwdInput.value;
        let score = 0;
        if (val.length >= 8)  score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct: '0%',   color: '',          text: '' },
            { pct: '25%',  color: '#ef4444',   text: '⚠️ Muy débil' },
            { pct: '50%',  color: '#f59e0b',   text: '🔶 Regular' },
            { pct: '75%',  color: '#3b82f6',   text: '🔷 Buena' },
            { pct: '100%', color: '#10b981',   text: '✅ Muy fuerte' },
        ];
        const lvl = levels[score] || levels[0];
        bar.style.width      = lvl.pct;
        bar.style.background = lvl.color;
        label.textContent    = lvl.text;
    });
}

// Validación de coincidencia en tiempo real
const confInput  = document.getElementById('password_confirm');
const matchError = document.getElementById('match-error');
const submitBtn  = document.getElementById('submit-btn');

if (confInput) {
    confInput.addEventListener('input', checkMatch);
    pwdInput?.addEventListener('input', checkMatch);
}

function checkMatch() {
    if (!confInput || !pwdInput) return;
    const noMatch = confInput.value && pwdInput.value !== confInput.value;
    matchError?.classList.toggle('d-none', !noMatch);
    if (submitBtn) submitBtn.disabled = noMatch;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
