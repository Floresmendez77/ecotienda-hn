<?php
/**
 * 🌱 ECOTIENDA HN - REGISTRO DE API (APP MÓVIL)
 * Ruta: /api/registro.php
 * Descripción: Crea una cuenta de cliente (rol_id = 2) para la app
 *              NativeScript, con la misma validación que register.php
 *              (versión web). Además vincula automáticamente cualquier
 *              pedido anterior hecho como invitado con el mismo correo
 *              (pedidos.correo_invitado) a la cuenta recién creada, y
 *              deja al usuario logueado devolviendo un token igual que
 *              api/auth.php (así no hace falta loguearse otra vez justo
 *              después de registrarse).
 *
 * Petición esperada (POST, Content-Type: application/json):
 *   {
 *     "nombre": "...", "apellido": "...", "correo": "...",
 *     "telefono": "...",            // opcional
 *     "password": "...", "confirm_password": "..."  // opcional, si se manda debe coincidir
 *   }
 *
 * Respuesta exitosa (201):
 *   {
 *     "success": true, "token": "...", "usuario": {...},
 *     "pedidos_vinculados": 0
 *   }
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function responderError(int $httpCode, string $mensaje): void
{
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError(405, 'Método no permitido. Usa POST.');
}

$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
    responderError(400, 'Cuerpo de la petición inválido, se esperaba JSON.');
}

$nombre            = trim($datos['nombre']   ?? '');
$apellido          = trim($datos['apellido'] ?? '');
$correo            = trim($datos['correo']   ?? '');
$telefono          = trim($datos['telefono'] ?? '');
$password          = (string)($datos['password']         ?? '');
$confirm_password  = (string)($datos['confirm_password']  ?? $password);
$dispositivo       = trim($datos['dispositivo'] ?? '');

if ($nombre === '' || $apellido === '' || $correo === '' || $password === '') {
    responderError(400, 'Nombre, apellido, correo y contraseña son obligatorios.');
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    responderError(400, 'El formato de correo electrónico no es válido.');
}
if (strlen($password) < 6) {
    responderError(400, 'La contraseña debe tener al menos 6 caracteres.');
}
if ($password !== $confirm_password) {
    responderError(400, 'Las contraseñas no coinciden.');
}

try {
    $pdo = Database::getConnection();

    $checkStmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = :correo LIMIT 1");
    $checkStmt->execute([':correo' => $correo]);
    if ($checkStmt->fetch()) {
        responderError(409, 'Este correo electrónico ya está registrado. Inicia sesión en su lugar.');
    }

    $pdo->beginTransaction();

    $hashedPass = password_hash($password, PASSWORD_BCRYPT);

    $insertUser = $pdo->prepare(
        "INSERT INTO usuarios (rol_id, nombre, apellido, correo, telefono, password, estado)
         VALUES (2, :nombre, :apellido, :correo, :telefono, :password, 'activo')"
    );
    $insertUser->execute([
        ':nombre'   => $nombre,
        ':apellido' => $apellido,
        ':correo'   => $correo,
        ':telefono' => $telefono !== '' ? $telefono : null,
        ':password' => $hashedPass,
    ]);
    $nuevoId = (int)$pdo->lastInsertId();

    // Dirección vacía inicial, igual que register.php (versión web)
    $pdo->prepare(
        "INSERT INTO direcciones (usuario_id, pais, departamento, municipio, colonia, direccion, referencia)
         VALUES (:usuario_id, 'Honduras', '', '', '', '', '')"
    )->execute([':usuario_id' => $nuevoId]);

    // ── Vincular pedidos hechos antes como invitado con el mismo correo ──
    $vincularStmt = $pdo->prepare(
        "UPDATE pedidos SET usuario_id = :usuario_id
         WHERE usuario_id IS NULL AND correo_invitado = :correo"
    );
    $vincularStmt->execute([':usuario_id' => $nuevoId, ':correo' => $correo]);
    $pedidosVinculados = $vincularStmt->rowCount();

    // ── Dejar al usuario logueado: mismo mecanismo que api/auth.php ──
    $token    = bin2hex(random_bytes(32));
    $expiraEn = date('Y-m-d H:i:s', strtotime('+30 days'));
    $pdo->prepare(
        "INSERT INTO api_tokens (usuario_id, token, dispositivo, expira_en)
         VALUES (:usuario_id, :token, :dispositivo, :expira_en)"
    )->execute([
        ':usuario_id'  => $nuevoId,
        ':token'       => $token,
        ':dispositivo' => $dispositivo !== '' ? $dispositivo : null,
        ':expira_en'   => $expiraEn,
    ]);

    $pdo->commit();

    logAuditoria($nuevoId, "Registro exitoso desde la app móvil" . ($pedidosVinculados > 0 ? " ({$pedidosVinculados} pedido(s) de invitado vinculado(s))" : ''), "usuarios");

    // El correo de bienvenida va fuera de cualquier posible fallo bloqueante:
    // si el mailer falla, la cuenta ya quedó creada y no debe perderse.
    try {
        require_once __DIR__ . '/../includes/mailer.php';
        notify_bienvenida_eco($pdo, $nuevoId);
    } catch (Exception $mailEx) {
        logError('WARNING', 'api/registro.php – email de bienvenida falló (cuenta creada OK): ' . $mailEx->getMessage(), [
            'usuario_id' => $nuevoId,
        ]);
    }

    logError('INFO', 'Registro exitoso en la API', ['usuario_id' => $nuevoId, 'pedidos_vinculados' => $pedidosVinculados]);

    http_response_code(201);
    echo json_encode([
        'success'             => true,
        'token'               => $token,
        'pedidos_vinculados'  => $pedidosVinculados,
        'usuario'             => [
            'id'       => $nuevoId,
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'correo'   => $correo,
            'rol_id'   => 2,
            'es_admin' => false,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('ERROR', 'api/registro.php: ' . $e->getMessage(), [
        'file' => $e->getFile(), 'line' => $e->getLine(),
    ]);
    responderError(500, 'Ocurrió un error al procesar tu registro. Intenta de nuevo más tarde.');
}
