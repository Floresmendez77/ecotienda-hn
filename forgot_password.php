<?php
/**
 * 🌱 ECOTIENDA HN - RECUPERACIÓN DE CONTRASEÑA
 * Ruta: /forgot_password.php
 * Descripción: Genera token seguro de 64 chars, lo guarda en auditoría con
 *              expiración de 1 hora y envía el enlace de restablecimiento por email.
 */

$pageTitle = "Recuperar Contraseña";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    redirect('/tienda.php');
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);

    if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor ingresa un correo electrónico válido.';
    } else {
        try {
            $db = Database::getConnection();

            // Verificar si el correo existe y la cuenta está activa
            $stmt = $db->prepare("SELECT id, nombre, apellido FROM usuarios WHERE correo = ? AND estado = 'activo' LIMIT 1");
            $stmt->execute([$correo]);
            $user = $stmt->fetch();

            /*
             * Por seguridad mostramos el mismo mensaje haya o no cuenta,
             * para no revelar si un correo está registrado.
             */
            if ($user) {
                // Generar token seguro de 64 caracteres
                $token     = bin2hex(random_bytes(32)); // 32 bytes = 64 chars hex
                $expires   = date('Y-m-d H:i:s', time() + 3600); // 1 hora
                $accion    = "reset_token:{$token}:expires:{$expires}";

                // Guardar token en tabla auditoria
                $ins = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada) VALUES (?, ?, 'usuarios')");
                $ins->execute([$user['id'], $accion]);

                // Construir enlace de restablecimiento
                $resetLink = rtrim('http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . BASE_URL . 'reset_password.php?token=' . urlencode($token);

                // Enviar email con PHPMailer
                $nombre  = htmlspecialchars($user['nombre']);
                $expires_hr = date('d/m/Y \a \l\a\s H:i', time() + 3600);

                $body_html = <<<HTML
<h2 style="color:#1a5c2a;font-size:1.3rem;margin:0 0 8px;">🔑 Recuperación de Contraseña</h2>
<p style="color:#555;font-size:.9rem;margin:0 0 20px;">
    Hola <b>{$nombre}</b>, recibimos una solicitud para restablecer la contraseña de tu cuenta en <b>EcoTienda HN</b>.
</p>

<div style="background:#f0faf2;border:1px solid #b2dfb8;border-radius:10px;padding:16px;margin-bottom:20px;">
    <p style="margin:0 0 6px;font-size:.83rem;color:#555;">
        Haz clic en el botón de abajo para crear una nueva contraseña. Este enlace es válido por <b>1 hora</b> (hasta el {$expires_hr}).
    </p>
</div>

<p style="text-align:center;margin:28px 0;">
    <a href="{$resetLink}"
       style="background:linear-gradient(135deg,#1a5c2a,#2d8a45);color:#fff;text-decoration:none;
              padding:14px 36px;border-radius:10px;font-weight:700;font-size:.95rem;display:inline-block;letter-spacing:.5px;">
        🔒 RESTABLECER CONTRASEÑA
    </a>
</p>

<div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:14px;margin-bottom:20px;">
    <p style="margin:0;font-size:.8rem;color:#7c5800;">
        ⚠️ Si no solicitaste este cambio, ignora este correo. Tu contraseña actual seguirá siendo la misma.<br>
        <span style="font-size:.75rem;color:#9e7a00;margin-top:4px;display:block;">
            Por seguridad, nunca compartas este enlace con nadie.
        </span>
    </p>
</div>

<p style="font-size:.78rem;color:#aaa;text-align:center;margin-top:20px;">
    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
    <a href="{$resetLink}" style="color:#2d8a45;font-size:.75rem;word-break:break-all;">{$resetLink}</a>
</p>
HTML;

                $html  = ecotienda_email_template('Recuperar Contraseña', $body_html);
                $plain = "Hola {$nombre}, usa este enlace para restablecer tu contraseña (válido 1 hora): {$resetLink}";

                ecotienda_mail(
                    $correo,
                    $user['nombre'] . ' ' . $user['apellido'],
                    '🔑 Recuperación de contraseña — EcoTienda HN',
                    $html,
                    $plain
                );
            }

            // Mensaje genérico (no revelar si el correo existe)
            $success = "Si ese correo está registrado en EcoTienda HN, recibirás un enlace de recuperación en los próximos minutos. Revisa también tu carpeta de spam.";

        } catch (Exception $e) {
            error_log('[EcoTienda ForgotPwd] ' . $e->getMessage());
            $error = 'Ocurrió un error al procesar tu solicitud. Por favor intenta de nuevo.';
        }
    }
}

require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5" style="min-height:70vh;">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">

            <div class="card border-0 shadow-sm p-4 p-md-5" style="border-radius:20px;">

                <!-- Icono + Título -->
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                         style="width:72px;height:72px;background:linear-gradient(135deg,#1a5c2a,#2d8a45);">
                        <i class="fas fa-lock-open text-white fa-xl"></i>
                    </div>
                    <h2 class="fw-bold mb-1" style="font-family:var(--font-display);font-size:1.5rem;">
                        ¿Olvidaste tu contraseña?
                    </h2>
                    <p class="text-secondary small mb-0">
                        Ingresa tu correo y te enviaremos un enlace para restablecerla.
                    </p>
                </div>

                <!-- Alertas -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-start gap-2" role="alert" style="border-radius:12px;">
                        <i class="fas fa-check-circle mt-1 text-success"></i>
                        <span style="font-size:.88rem;"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-2" role="alert" style="border-radius:12px;">
                        <i class="fas fa-exclamation-triangle mt-1"></i>
                        <span style="font-size:.88rem;"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (empty($success)): ?>
                <!-- Formulario -->
                <form method="POST" action="" novalidate>
                    <div class="mb-4">
                        <label for="correo" class="form-label fw-semibold small text-secondary">
                            <i class="fas fa-envelope me-1"></i> Correo electrónico
                        </label>
                        <input type="email"
                               class="form-control form-control-lg"
                               id="correo"
                               name="correo"
                               placeholder="tu@correo.com"
                               value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>"
                               required
                               autofocus
                               style="border-radius:10px;">
                    </div>

                    <button type="submit"
                            class="btn btn-eco-primary w-100 py-3 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-paper-plane"></i> Enviar enlace de recuperación
                    </button>
                </form>
                <?php else: ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo BASE_URL; ?>forgot_password.php" class="btn btn-outline-success btn-sm rounded-pill">
                            <i class="fas fa-redo me-1"></i> Enviar de nuevo
                        </a>
                    </div>
                <?php endif; ?>

                <hr class="my-4 opacity-25">

                <div class="text-center small text-secondary">
                    ¿Recordaste tu contraseña?
                    <a href="<?php echo BASE_URL; ?>login.php" class="text-success fw-semibold text-decoration-none ms-1">
                        Iniciar sesión
                    </a>
                </div>

            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
