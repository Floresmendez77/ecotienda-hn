<?php
/**
 * 🌱 ECOTIENDA HN - MI PERFIL DE CLIENTE (RESTRINGIDO)
 * Ruta: /perfil.php
 * Descripción: Permite a los clientes actualizar sus detalles de contacto, cambiar de contraseñas de forma cifrada y guardar su información de envío en Honduras.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// Control de acceso
requireLogin();

$pageTitle = "Mi Perfil Sostenible";
$error = '';
$success = '';

try {
    $db = Database::getConnection();

    // 1. Obtener datos actuales del usuario
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    // 2. Obtener dirección actual
    $dirStmt = $db->prepare("SELECT * FROM direcciones WHERE usuario_id = :user_id LIMIT 1");
    $dirStmt->execute([':user_id' => $_SESSION['user_id']]);
    $address = $dirStmt->fetch();

} catch (Exception $e) {
    $error = "Falla de servidor al cargar tu perfil: " . $e->getMessage();
}

// Procesar actualización de Información General (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_info') {
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_DEFAULT);
    $apellido = filter_input(INPUT_POST, 'apellido', FILTER_DEFAULT);
    $telefono = filter_input(INPUT_POST, 'telefono', FILTER_DEFAULT);

    if (empty($nombre) || empty($apellido)) {
        $error = "Nombre y Apellido son obligatorios.";
    } else {
        try {
            $updateSql = "UPDATE usuarios SET nombre = :nombre, apellido = :apellido, telefono = :telefono WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $result = $updateStmt->execute([
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':telefono' => $telefono,
                ':id' => $_SESSION['user_id']
            ]);

            if ($result) {
                // Actualizar nombres en sesión
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_lastname'] = $apellido;
                
                logAuditoria($_SESSION['user_id'], "Actualizó información general del perfil", "usuarios");
                $_SESSION['flash_success'] = "¡Información guardada! Tus datos personales se actualizaron correctamente.";
                redirect('/perfil.php');
            }
        } catch (Exception $e) {
            $error = "Error al guardar cambios: " . $e->getMessage();
        }
    }
}

// Procesar actualización de Contraseña (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pass') {
    $current_pass = $_POST['current_pass'] ?? '';
    $new_pass = $_POST['new_pass'] ?? '';
    $confirm_pass = $_POST['confirm_pass'] ?? '';

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error = "Por favor, completa todas las credenciales de contraseña.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "La nueva contraseña y su confirmación no coinciden.";
    } elseif (strlen($new_pass) < 6) {
        $error = "La nueva contraseña debe constar de al menos 6 caracteres.";
    } else {
        try {
            // Validar contraseña actual
            if (password_verify($current_pass, $user['password'])) {
                $newHash = password_hash($new_pass, PASSWORD_BCRYPT);
                $passStmt = $db->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
                $passStmt->execute([
                    ':password' => $newHash,
                    ':id' => $_SESSION['user_id']
                ]);

                logAuditoria($_SESSION['user_id'], "Cambió contraseña de cuenta", "usuarios");
                $_SESSION['flash_success'] = "¡Éxito! Tu contraseña ha sido cambiada de forma segura.";
                redirect('/perfil.php');
            } else {
                $error = "La contraseña actual es incorrecta.";
            }
        } catch (Exception $e) {
            $error = "Error al actualizar contraseña: " . $e->getMessage();
        }
    }
}
?>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-5">
    
    <div class="text-start mb-5">
        <h1 class="fw-bold fs-2" style="font-family: var(--font-display);"><i class="fas fa-user-cog text-success me-2"></i> Mi Perfil Verde</h1>
        <p class="text-secondary">Administra los accesos de tu cuenta y los datos de envío en Honduras.</p>
    </div>

    <?php if(!empty($error)): ?>
        <?php echo renderAlert($error, 'danger'); ?>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- Menú de la izquierda (Opciones rápidas) -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm p-4 text-center mb-4" style="border-radius: 16px;">
                <div class="bg-success text-white p-3 rounded-circle mx-auto mb-3" style="width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                    <i class="fas fa-heart-pulse"></i>
                </div>
                <h5 class="fw-bold m-0"><?php echo sanitize($user['nombre'] . ' ' . $user['apellido']); ?></h5>
                <small class="text-success font-mono d-block mt-1"><?php echo $_SESSION['user_role_name'] === 'admin' ? 'Administrador Superior' : 'Comprador Ecológico'; ?></small>
                <p class="text-muted small mt-2 mb-0">Miembro desde: <?php echo date('d M, Y', strtotime($user['fecha_registro'])); ?></p>
            </div>

            <!-- Listado Lateral -->
            <div class="list-group list-group-flush shadow-sm rounded-3 border-0 overflow-hidden" style="border-radius: 12px;">
                <a href="<?php echo BASE_URL; ?>perfil.php" class="list-group-item list-group-item-action py-3 active bg-success border-0"><i class="fas fa-sliders-h me-2"></i> Ajustes Generales</a>
                <a href="<?php echo BASE_URL; ?>mis_pedidos.php" class="list-group-item list-group-item-action py-3 text-secondary"><i class="fas fa-shopping-bag me-2"></i> Mis Pedidos</a>
                <a href="<?php echo BASE_URL; ?>carrito.php" class="list-group-item list-group-item-action py-3 text-secondary"><i class="fas fa-shopping-basket me-2"></i> Mi Bolsa de Compras</a>
                <a href="<?php echo BASE_URL; ?>logout.php" class="list-group-item list-group-item-action py-3 text-danger"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a>
            </div>
        </div>

        <!-- Ajustes de Cuenta Form -->
        <div class="col-lg-9">
            
            <!-- Bloque 1: Datos Personales -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 16px;">
                <h5 class="fw-bold mb-4" style="font-family: var(--font-display);"><i class="fas fa-id-card text-success me-2"></i> Datos Personales</h5>
                
                <form action="<?php echo BASE_URL; ?>perfil.php" method="POST">
                    <input type="hidden" name="action" value="update_info">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label text-secondary small fw-bold">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" value="<?php echo sanitize($user['nombre']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="apellido" class="form-label text-secondary small fw-bold">Apellido *</label>
                            <input type="text" name="apellido" id="apellido" class="form-control" value="<?php echo sanitize($user['apellido']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="correo" class="form-label text-secondary small fw-bold">Correo Electrónico (No modificable)</label>
                            <input type="email" id="correo" class="form-control bg-light" value="<?php echo sanitize($user['correo']); ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label for="telefono" class="form-label text-secondary small fw-bold">Número de Teléfono</label>
                            <input type="tel" name="telefono" id="telefono" class="form-control" value="<?php echo sanitize($user['telefono']); ?>" placeholder="+504 9999-9999">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-eco-primary btn-sm mt-4 px-4 py-2">Guardar Datos Generales</button>
                </form>
            </div>

            <!-- Bloque 2: Actualizar Contraseña -->
            <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
                <h5 class="fw-bold mb-4" style="font-family: var(--font-display);"><i class="fas fa-shield-halved text-success me-2"></i> Cambiar Contraseña</h5>
                
                <form action="<?php echo BASE_URL; ?>perfil.php" method="POST">
                    <input type="hidden" name="action" value="update_pass">

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="current_pass" class="form-label text-secondary small fw-bold">Contraseña Actual *</label>
                            <input type="password" name="current_pass" id="current_pass" class="form-control" placeholder="Escribe tu contraseña en uso" required>
                        </div>

                        <div class="col-md-6">
                            <label for="new_pass" class="form-label text-secondary small fw-bold">Nueva Contraseña *</label>
                            <input type="password" name="new_pass" id="new_pass" class="form-control" placeholder="Mínimo 6 caracteres" required>
                        </div>

                        <div class="col-md-6">
                            <label for="confirm_pass" class="form-label text-secondary small fw-bold">Confirmar Nueva Contraseña *</label>
                            <input type="password" name="confirm_pass" id="confirm_pass" class="form-control" placeholder="Escribe de nuevo" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-success btn-sm mt-4 px-4 py-2">Actualizar Contraseña</button>
                </form>
            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
