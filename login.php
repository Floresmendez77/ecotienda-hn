<?php
/**
 * 🌱 ECOTIENDA HN - ENTRADA DE SESIÓN (LOGIN)
 * Ruta: /login.php
 * Descripción: Formulario premium para validar acceso de clientes y administradores con protección contra accesos maliciosos y control de roles dinámicos.
 */

$pageTitle = "Iniciar Sesión";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// Si el usuario ya está logueado, redirigir
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('/admin/index.php');
    } else {
        redirect('/tienda.php');
    }
}

$error = '';
$success = '';

if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Procesar formulario de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = "La solicitud no es válida. Por favor, recarga la página e intenta de nuevo.";
    } elseif (isLoginRateLimited()) {
        $error = "Demasiados intentos fallidos. Espera 15 minutos antes de volver a intentar.";
    } else {
        $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $recordarme = isset($_POST['recordarme']);

        if (empty($correo) || empty($password)) {
            $error = "Por favor, completa todos los campos requeridos.";
        } else {
            try {
                $db = Database::getConnection();

                $sql = "SELECT u.*, r.nombre AS rol_nombre 
                        FROM usuarios u 
                        INNER JOIN roles r ON u.rol_id = r.id 
                        WHERE u.correo = :correo 
                        LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute([':correo' => $correo]);
                $user = $stmt->fetch();

                if ($user) {
                    if ($user['estado'] === 'bloqueado') {
                        $error = "Esta cuenta ha sido bloqueada temporalmente. Contacta al soporte.";
                    } elseif ($user['estado'] === 'inactivo') {
                        $error = "Tu cuenta se encuentra inactiva. Comunícate con nosotros.";
                    } elseif (password_verify($password, $user['password'])) {

                        clearLoginAttempts();

                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['nombre'];
                        $_SESSION['user_lastname'] = $user['apellido'];
                        $_SESSION['user_email'] = $user['correo'];
                        $_SESSION['user_role_id'] = $user['rol_id'];
                        $_SESSION['user_role_name'] = $user['rol_nombre'];

                        logAuditoria($user['id'], "Inicio de sesión correcto", "usuarios");

                        if (isset($_SESSION['redirect_after_login'])) {
                            $redirect = $_SESSION['redirect_after_login'];
                            unset($_SESSION['redirect_after_login']);
                            header("Location: " . $redirect);
                            exit;
                        }

                        if ($user['rol_nombre'] === 'admin') {
                            redirect('/admin/index.php');
                        } else {
                            redirect('/tienda.php');
                        }
                    } else {
                        recordFailedLoginAttempt();
                        $error = "La contraseña ingresada es incorrecta.";
                        logAuditoria(null, "Intento fallido de login - Password inválido para " . $correo, "usuarios");
                    }
                } else {
                    recordFailedLoginAttempt();
                    $error = "No existe ninguna cuenta asociada a este correo electrónico.";
                    logAuditoria(null, "Intento fallido de login - Correo no registrado: " . $correo, "usuarios");
                }
            } catch (Exception $e) {
                logError('ERROR', 'Error en login.php: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $error = "Ocurrió un error al procesar tu solicitud. Intenta de nuevo más tarde.";
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section class="py-5 flex-grow-1 d-flex align-items-center" style="background: radial-gradient(circle at center, rgba(16, 185, 129, 0.08), transparent 70%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-8 col-sm-10">
                <div class="card border-0 shadow-lg p-4 p-md-5" style="border-radius: 20px;">
                    
                    <div class="text-center mb-4">
                        <span class="text-success display-5"><i class="fas fa-leaf"></i></span>
                        <h2 class="fw-bold mt-2 mb-1" style="font-family: var(--font-display);">Bienvenido</h2>
                        <p class="text-secondary small">Llena tus datos para ingresar a EcoTienda HN</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <?php echo renderAlert($error, 'danger'); ?>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <?php echo renderAlert($success, 'success'); ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>login.php" method="POST">
                        <?php echo csrfField(); ?>

                        <div class="mb-3">
                            <label for="correo" class="form-label text-secondary small fw-bold">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="correo" id="correo" class="form-control border-start-0" placeholder="ejemplo@correo.com" required value="<?php echo isset($_POST['correo']) ? sanitize($_POST['correo']) : ''; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label for="password" class="form-label text-secondary small fw-bold">Contraseña</label>
                                <a href="<?php echo BASE_URL; ?>forgot_password.php" class="text-success text-decoration-none small">¿Olvidaste tu contraseña?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control border-start-0" placeholder="••••••••" required>
                            </div>
                        </div>

                        <div class="mb-4 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" name="recordarme" id="recordarme" class="form-check-input">
                                <label for="recordarme" class="form-check-label text-secondary small">Recordar mi sesión</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-eco-primary w-100 py-2.5 fw-bold rounded-3 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                        </button>
                    </form>

                    <div class="text-center">
                        <span class="text-secondary small">¿No tienes cuenta? </span>
                        <a href="<?php echo BASE_URL; ?>register.php" class="text-success fw-semibold text-decoration-none small">Regístrate gratis</a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<?php
renderAddToCartScript();
require_once __DIR__ . '/includes/footer.php';
?>
