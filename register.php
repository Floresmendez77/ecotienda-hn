<?php
/**
 * 🌱 ECOTIENDA HN - REGISTRO DE NUEVOS CLIENTES
 * Ruta: /register.php
 * Descripción: Página independiente que permite a nuevos compradores registarse en Honduras. Valida duplicados, contraseñas y asigna el rol de cliente de la base de datos (id: 2).
 */

$pageTitle = "Regístrate";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// Si ya inició sesión, enviarlo a la tienda
if (isLoggedIn()) {
    redirect('/tienda.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = "La solicitud no es válida. Por favor, recarga la página e intenta de nuevo.";
    } else {
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_DEFAULT);
        $apellido = filter_input(INPUT_POST, 'apellido', FILTER_DEFAULT);
        $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
        $telefono = filter_input(INPUT_POST, 'telefono', FILTER_DEFAULT);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($nombre) || empty($apellido) || empty($correo) || empty($password)) {
            $error = "Por favor, completa todos los campos requeridos (*).";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato de correo electrónico no es válido.";
        } elseif (strlen($password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } elseif ($password !== $confirm_password) {
            $error = "Las contraseñas no coinciden.";
        } else {
            try {
                $db = Database::getConnection();

                $checkStmt = $db->prepare("SELECT id FROM usuarios WHERE correo = :correo LIMIT 1");
                $checkStmt->execute([':correo' => $correo]);

                if ($checkStmt->fetch()) {
                    $error = "Este correo electrónico ya está registrado. Intenta iniciar sesión.";
                } else {
                    $hashedPass = password_hash($password, PASSWORD_BCRYPT);

                    $sql = "INSERT INTO usuarios (rol_id, nombre, apellido, correo, telefono, password, estado) 
                            VALUES (2, :nombre, :apellido, :correo, :telefono, :password, 'activo')";

                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([
                        ':nombre' => $nombre,
                        ':apellido' => $apellido,
                        ':correo' => $correo,
                        ':telefono' => $telefono,
                        ':password' => $hashedPass
                    ]);

                    if ($result) {
                        $new_id = $db->lastInsertId();

                        $dirSql = "INSERT INTO direcciones (usuario_id, pais, departamento, municipio, colonia, direccion, referencia) 
                                   VALUES (:usuario_id, 'Honduras', '', '', '', '', '')";
                        $dirStmt = $db->prepare($dirSql);
                        $dirStmt->execute([':usuario_id' => $new_id]);
                        require_once __DIR__ . '/includes/mailer.php';
                        notify_bienvenida_eco($db, $new_id);

                        logAuditoria($new_id, "Registro exitoso de nuevo cliente", "usuarios");

                        $_SESSION['flash_success'] = "¡Registro exitoso! Ya puedes iniciar sesión en su cuenta.";
                        redirect('/login.php');
                    } else {
                        $error = "Ocurrió un error inesperado al procesar el registro.";
                    }
                }
            } catch (Exception $e) {
                logError('ERROR', 'Error en register.php: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $error = "Ocurrió un error al procesar tu registro. Intenta de nuevo más tarde.";
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section class="py-5 flex-grow-1 d-flex align-items-center" style="background: radial-gradient(circle at center, rgba(16, 185, 129, 0.08), transparent 70%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-9 col-sm-12">
                <div class="card border-0 shadow-lg p-4 p-md-5" style="border-radius: 20px;">
                    
                    <div class="text-center mb-4">
                        <span class="text-success display-5"><i class="fas fa-user-plus"></i></span>
                        <h2 class="fw-bold mt-2 mb-1" style="font-family: var(--font-display);">Crea Tu Cuenta</h2>
                        <p class="text-secondary small">Únete hoy y sé parte del comercio sustentable de Honduras</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <?php echo renderAlert($error, 'danger'); ?>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>register.php" method="POST">
                        <?php echo csrfField(); ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label text-secondary small fw-bold">Nombre *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Tu nombre" required value="<?php echo isset($_POST['nombre']) ? sanitize($_POST['nombre']) : ''; ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="apellido" class="form-label text-secondary small fw-bold">Apellido *</label>
                                <input type="text" name="apellido" id="apellido" class="form-control" placeholder="Tu apellido" required value="<?php echo isset($_POST['apellido']) ? sanitize($_POST['apellido']) : ''; ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="correo" class="form-label text-secondary small fw-bold">Correo Electrónico *</label>
                                <input type="email" name="correo" id="correo" class="form-control" placeholder="ejemplo@correo.com" required value="<?php echo isset($_POST['correo']) ? sanitize($_POST['correo']) : ''; ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="telefono" class="form-label text-secondary small fw-bold">Número de Teléfono</label>
                                <input type="tel" name="telefono" id="telefono" class="form-control" placeholder="+504 9999-9999" value="<?php echo isset($_POST['telefono']) ? sanitize($_POST['telefono']) : ''; ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label text-secondary small fw-bold">Contraseña *</label>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
                            </div>

                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label text-secondary small fw-bold">Confirmar Contraseña *</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Escribe de nuevo" required>
                            </div>
                        </div>

                        <div class="form-check my-4 text-start">
                            <input type="checkbox" id="terms" class="form-check-input" required>
                            <label for="terms" class="form-check-label text-secondary small">Acepto los términos, condiciones y la política de tratamiento verde de EcoTienda HN.</label>
                        </div>

                        <button type="submit" class="btn btn-eco-primary w-100 py-3 fw-bold rounded-3 mb-3">
                            <i class="fas fa-file-signature me-2"></i> Crear Cuenta de Cliente
                        </button>
                    </form>

                    <div class="text-center">
                        <span class="text-secondary small">¿Ya tienes cuenta? </span>
                        <a href="<?php echo BASE_URL; ?>login.php" class="text-success fw-semibold text-decoration-none small">Inicia sesión</a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
