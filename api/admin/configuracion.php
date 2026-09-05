<?php
/**
 * 🌱 ECOTIENDA HN - CONFIGURACIÓN (API ADMIN)
 * Ruta: /api/admin/configuracion.php
 *
 * NOTA IMPORTANTE (Fase 0): el archivo /admin/configuracion.php del sitio
 * hace `INSERT INTO configuracion (clave, valor) ... ON DUPLICATE KEY
 * UPDATE`, pero la tabla real `configuracion` NO tiene columnas
 * `clave`/`valor`: es una fila única (id=1) con columnas fijas
 * (nombre_tienda, correo, telefono, direccion, facebook, instagram,
 * tiktok, logo, favicon). Ese código del sitio fallaría contra la base
 * real. Este endpoint usa el esquema REAL, confirmado contra el dump SQL.
 *
 * GET  (sin accion)              → configuración actual (fila única)
 * POST ?accion=guardar           → { nombre_tienda, correo, telefono,
 *                                     direccion, facebook, instagram, tiktok }
 * POST ?accion=cambiar_password  → { pwd_actual, pwd_nueva, pwd_confirmar }
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
$accion = $_GET['accion'] ?? ($metodo === 'GET' ? 'ver' : null);

// ────────────────────────────────────────────────────────────
// VER configuración actual (fila única)
// ────────────────────────────────────────────────────────────
if ($accion === 'ver' && $metodo === 'GET') {
    $config = $pdo->query(
        "SELECT id, nombre_tienda, correo, telefono, direccion, facebook, instagram, tiktok, logo, favicon
         FROM configuracion ORDER BY id ASC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        $config = [
            'id' => 1, 'nombre_tienda' => 'EcoTienda HN', 'correo' => null,
            'telefono' => null, 'direccion' => null, 'facebook' => null,
            'instagram' => null, 'tiktok' => null, 'logo' => null, 'favicon' => null,
        ];
    }

    responderApi(true, ['configuracion' => $config]);
}

// ────────────────────────────────────────────────────────────
// GUARDAR parámetros de la tienda (UPDATE de la fila única)
// ────────────────────────────────────────────────────────────
if ($accion === 'guardar' && $metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];

    $campos = ['nombre_tienda', 'correo', 'telefono', 'direccion', 'facebook', 'instagram', 'tiktok'];
    $valores = [];
    foreach ($campos as $campo) {
        if (array_key_exists($campo, $datos)) {
            $valores[$campo] = trim((string)$datos[$campo]);
        }
    }

    if (empty($valores)) {
        responderApi(false, ['error' => 'No se recibió ningún campo para actualizar.'], 400);
    }

    try {
        $existe = (int)$pdo->query("SELECT COUNT(*) FROM configuracion")->fetchColumn();
        if ($existe === 0) {
            $pdo->exec("INSERT INTO configuracion (id, nombre_tienda) VALUES (1, 'EcoTienda HN')");
        }

        $sets = [];
        foreach (array_keys($valores) as $campo) {
            $sets[] = "{$campo} = :{$campo}";
        }
        // Fila única: se actualiza siempre la de menor id, sin asumir que sea 1
        $sql = "UPDATE configuracion SET " . implode(', ', $sets) . " ORDER BY id ASC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($valores);

        logAuditoria($admin['id'], 'Actualizó configuración de la tienda (desde app móvil)', 'configuracion');

        responderApi(true, ['mensaje' => 'La configuración de la tienda se actualizó correctamente.']);
    } catch (Exception $e) {
        responderApi(false, ['error' => 'Error al guardar la configuración: ' . $e->getMessage()], 500);
    }
}

// ────────────────────────────────────────────────────────────
// CAMBIAR PASSWORD del admin autenticado
// ────────────────────────────────────────────────────────────
if ($accion === 'cambiar_password' && $metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];

    $pwd_actual    = $datos['pwd_actual']    ?? '';
    $pwd_nueva     = $datos['pwd_nueva']     ?? '';
    $pwd_confirmar = $datos['pwd_confirmar'] ?? '';

    if ($pwd_actual === '' || $pwd_nueva === '' || $pwd_confirmar === '') {
        responderApi(false, ['error' => 'Todos los campos de contraseña son obligatorios.'], 400);
    }
    if (strlen($pwd_nueva) < 8) {
        responderApi(false, ['error' => 'La nueva contraseña debe tener al menos 8 caracteres.'], 400);
    }
    if ($pwd_nueva !== $pwd_confirmar) {
        responderApi(false, ['error' => 'La nueva contraseña y su confirmación no coinciden.'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $admin['id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($pwd_actual, $usuario['password'])) {
            responderApi(false, ['error' => 'La contraseña actual es incorrecta.'], 401);
        }

        $hash = password_hash($pwd_nueva, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET password = :hash WHERE id = :id")
            ->execute([':hash' => $hash, ':id' => $admin['id']]);

        logAuditoria($admin['id'], 'Cambió su contraseña de acceso al panel admin (desde app móvil)', 'configuracion');

        responderApi(true, ['mensaje' => 'Contraseña actualizada correctamente. Úsala en tu próximo inicio de sesión.']);
    } catch (Exception $e) {
        responderApi(false, ['error' => 'Error al cambiar contraseña: ' . $e->getMessage()], 500);
    }
}

responderApi(false, ['error' => 'Acción no reconocida.'], 400);
