<?php
/**
 * 🌱 ECOTIENDA HN - ADMINISTRACIÓN DE USUARIOS
 * Ruta: /admin/usuarios.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = "Gestión de Usuarios";
$pageSubtitle = "🌱 Habilita o restringe inicios de sesión de compradores en EcoTienda HN.";
$error = '';
$success = '';

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido o expirado. Recargá la página e intentá de nuevo.";
    } else {
    $usr_id = (int)$_POST['usuario_id'];
    $nuevo_estado = $_POST['estado'] === 'activo' ? 'inactivo' : 'activo';
    try {
        if ($usr_id === (int)$_SESSION['user_id']) {
            $error = "Acción prohibida: No puedes inhabilitar tu propia cuenta administrativa en uso.";
        } else {
            $db->prepare("UPDATE usuarios SET estado = :estado WHERE id = :id")
               ->execute([':estado' => $nuevo_estado, ':id' => $usr_id]);
            logAuditoria($_SESSION['user_id'], "Modificó estado de usuario ID: " . $usr_id . " a " . $nuevo_estado, "usuarios");
            $success = "El estatus del usuario ID #{$usr_id} se actualizó a '{$nuevo_estado}'.";
        }
    } catch (Exception $e) {
        $error = "Error al operar estado de usuario: " . $e->getMessage();
    }
    }
}

$usersList = [];
try {
    $usersList = $db->query("SELECT u.*, r.nombre AS rol_nombre 
                             FROM usuarios u 
                             INNER JOIN roles r ON u.rol_id = r.id 
                             ORDER BY u.fecha_registro DESC")->fetchAll();
} catch (Exception $e) {}
?>
<?php require_once __DIR__ . '/includes/admin_navbar.php'; ?>
    

    <?php if(!empty($error)): echo renderAlert($error, 'danger'); endif; ?>
    <?php if(!empty($success)): echo renderAlert($success, 'success'); endif; ?>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="tabla-usuarios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Comprador</th>
                        <th>Correo Registrado</th>
                        <th>Teléfono</th>
                        <th>Permisos</th>
                        <th style="text-align:center">Registro</th>
                        <th style="text-align:center">Acceso</th>
                        <th style="text-align:center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usersList as $usr): ?>
                        <tr>
                            <td style="color:#94a3b8;font-family:monospace;">#<?php echo $usr['id']; ?></td>
                            <td><strong style="color:#ffffff;font-weight:600;"><?php echo sanitize($usr['nombre'] . ' ' . $usr['apellido']); ?></strong></td>
                            <td style="color:#cbd5e1;font-size:.88rem;"><?php echo sanitize($usr['correo']); ?></td>
                            <td style="color:#94a3b8;font-family:monospace;font-size:.85rem;"><?php echo sanitize($usr['telefono'] ?? '—'); ?></td>
                            <td>
                                <?php $esAdmin = $usr['rol_nombre'] === 'admin'; ?>
                                <span style="background:<?php echo $esAdmin ? 'rgba(239,68,68,.15)' : 'rgba(59,130,246,.15)'; ?>;color:<?php echo $esAdmin ? '#f87171' : '#60a5fa'; ?>;padding:.25em .65em;border-radius:6px;font-size:.75rem;font-weight:700;font-family:monospace;">
                                    <?php echo strtoupper($usr['rol_nombre']); ?>
                                </span>
                            </td>
                            <td style="text-align:center;color:#94a3b8;font-size:.85rem;">
                                <?php echo date('Y-m-d', strtotime($usr['fecha_registro'])); ?>
                            </td>
                            <td style="text-align:center;color:#10b981;font-family:monospace;font-size:.85rem;">OK</td>
                            <td style="text-align:center;">
                                <form action="<?php echo BASE_URL; ?>admin/usuarios.php" method="POST" style="margin:0;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="usuario_id" value="<?php echo (int)$usr['id']; ?>">
                                    <input type="hidden" name="estado" value="<?php echo e($usr['estado']); ?>">
                                    <?php if($usr['estado'] === 'activo'): ?>
                                        <button type="submit" style="background:rgba(16,185,129,.2);color:#10b981;border:1px solid rgba(16,185,129,.3);padding:.3em .9em;border-radius:999px;font-size:.78rem;font-weight:700;cursor:pointer;">
                                            Activo
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" style="background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);padding:.3em .9em;border-radius:999px;font-size:.78rem;font-weight:700;cursor:pointer;">
                                            Suspendido
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>



<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
