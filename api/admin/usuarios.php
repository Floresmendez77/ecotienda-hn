<?php
/**
 * 🌱 ECOTIENDA HN - USUARIOS (API ADMIN)
 * Ruta: /api/admin/usuarios.php
 * Descripción: Espejo de /admin/usuarios.php para la app móvil.
 *
 * GET  (sin accion)             → lista todos los usuarios con su rol
 * POST ?accion=toggle_status    → { usuario_id } activa/desactiva
 */

require_once __DIR__ . '/../../includes/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

function responderApi(bool $success, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$admin = requireApiAdmin();
$pdo = Database::getConnection();

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? ($metodo === 'GET' ? 'listar' : null);

// ────────────────────────────────────────────────────────────
// LISTAR
// ────────────────────────────────────────────────────────────
if ($accion === 'listar' && $metodo === 'GET') {
    $usuarios = $pdo->query("
        SELECT u.id, u.nombre, u.apellido, u.correo, u.telefono, u.estado,
               u.fecha_registro, u.rol_id, r.nombre AS rol_nombre
        FROM usuarios u
        INNER JOIN roles r ON u.rol_id = r.id
        ORDER BY u.fecha_registro DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    responderApi(true, ['usuarios' => $usuarios]);
}

// ────────────────────────────────────────────────────────────
// ACTIVAR / DESACTIVAR
// ────────────────────────────────────────────────────────────
if ($accion === 'toggle_status' && $metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    $usuario_id = (int)($datos['usuario_id'] ?? 0);

    if ($usuario_id <= 0) {
        responderApi(false, ['error' => 'usuario_id inválido.'], 400);
    }

    if ($usuario_id === (int)$admin['id']) {
        responderApi(false, ['error' => 'No puedes inhabilitar tu propia cuenta administrativa en uso.'], 403);
    }

    try {
        $stmt = $pdo->prepare("SELECT estado FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $usuario_id]);
        $estadoActual = $stmt->fetchColumn();

        if ($estadoActual === false) {
            responderApi(false, ['error' => 'Usuario no encontrado.'], 404);
        }

        $nuevoEstado = $estadoActual === 'activo' ? 'inactivo' : 'activo';

        $pdo->prepare("UPDATE usuarios SET estado = :estado WHERE id = :id")
            ->execute([':estado' => $nuevoEstado, ':id' => $usuario_id]);

        logAuditoria($admin['id'], "Modificó estado de usuario ID: {$usuario_id} a {$nuevoEstado} (desde app móvil)", 'usuarios');

        responderApi(true, [
            'mensaje' => "El estatus del usuario ID #{$usuario_id} se actualizó a '{$nuevoEstado}'.",
            'estado'  => $nuevoEstado,
        ]);
    } catch (Exception $e) {
        responderApi(false, ['error' => 'Error al operar estado de usuario: ' . $e->getMessage()], 500);
    }
}

responderApi(false, ['error' => 'Acción no reconocida.'], 400);
