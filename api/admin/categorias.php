<?php
/**
 * 🌱 ECOTIENDA HN - CATEGORÍAS (API ADMIN)
 * Ruta: /api/admin/categorias.php
 * Descripción: Lista y crea categorías, usado por la app móvil para
 *              poblar el selector de categorías en el formulario de
 *              productos (Fase 2 del plan).
 *
 * GET                        → lista todas las categorías activas
 * POST { "nombre": "..." }   → crea una nueva categoría (requiere admin)
 */

require_once __DIR__ . '/../../includes/api_auth.php';

header('Content-Type: application/json; charset=utf-8');

function responderCategorias(bool $success, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Listar solo requiere estar autenticado (no necesariamente admin),
    // ya que se usa también para mostrar filtros en el catálogo normal.
    requireApiAuth();

    $stmt = $pdo->query(
        "SELECT id, nombre, descripcion, imagen
         FROM categorias
         WHERE estado = 'activo'
         ORDER BY nombre ASC"
    );
    responderCategorias(true, ['categorias' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Crear una categoría sí requiere admin
    $admin = requireApiAdmin();

    $datos = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($datos['nombre'] ?? '');

    if ($nombre === '') {
        responderCategorias(false, ['error' => 'El nombre de la categoría es obligatorio.'], 400);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)"
    );
    $stmt->execute([
        'nombre'      => $nombre,
        'descripcion' => trim($datos['descripcion'] ?? ''),
    ]);

    logAuditoria($admin['id'], "Creó categoría: $nombre (desde app móvil)", 'categorias');

    responderCategorias(true, [
        'mensaje' => 'Categoría creada correctamente.',
        'id'      => (int)$pdo->lastInsertId(),
    ]);
}

responderCategorias(false, ['error' => 'Método no permitido.'], 405);
