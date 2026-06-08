<?php
/**
 * 🌱 ECOTIENDA HN - FUNCIONES DE UTILIDAD GENERAL
 * Ruta: /includes/functions.php
 * Descripción: Sanitización, formateo, manejo de logs, subida de archivos
 *              e interacciones comunes de negocio.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// ═══════════════════════════════════════════════════════════════════════════════
//  LOGGING DE ERRORES
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Registra un error/evento en el log del servidor con contexto estructurado.
 *
 * @param string $level   Nivel: 'ERROR' | 'WARNING' | 'INFO' | 'DEBUG'
 * @param string $message Descripción legible del problema
 * @param array  $context Datos adicionales (excepción, usuario, etc.)
 */
function logError(string $level, string $message, array $context = []): void
{
    $level    = strtoupper($level);
    $datetime = date('Y-m-d H:i:s');
    $file     = $context['file']      ?? '';
    $line     = $context['line']      ?? '';
    $userId   = $_SESSION['user_id']  ?? 'guest';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

    // Serializar contexto extra (omitir claves ya usadas)
    $extra = array_diff_key($context, array_flip(['file', 'line']));
    $extraStr = empty($extra) ? '' : ' | ctx=' . json_encode($extra, JSON_UNESCAPED_UNICODE);

    $logLine = sprintf(
        "[%s] [%s] [user:%s] [ip:%s] %s%s%s\n",
        $datetime,
        $level,
        $userId,
        $ip,
        $message,
        ($file ? " | file:{$file}:{$line}" : ''),
        $extraStr
    );

    // error_log escribe en el canal configurado en php.ini (apache/nginx log)
    error_log($logLine);

    // En modo desarrollo también mostramos WARNING/ERROR en HTML comment
    if (defined('APP_ENV') && APP_ENV === 'local' && in_array($level, ['ERROR', 'WARNING'])) {
        echo "<!-- [{$level}] " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . " -->\n";
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  PROTECCIÓN CSRF
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Genera (o reutiliza) el token CSRF de la sesión actual.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica que el token CSRF enviado coincida con el de la sesión.
 */
function verifyCsrfToken(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || $token === null || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Devuelve el campo hidden HTML con el token CSRF.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// ═══════════════════════════════════════════════════════════════════════════════
//  SANITIZACIÓN Y FORMATEO
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Sanitiza valores de entrada para evitar ataques XSS
 */
function sanitize($value): string {
    if ($value === null) return '';
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Formatea un valor monetario a Lempiras
 */
function formatCurrency($amount): string {
    return CURRENCY . number_format($amount, 2, '.', ',');
}

/**
 * Resuelve la URL pública de una imagen de producto.
 */
function productImageUrl(?string $path, string $placeholder = 'https://placehold.co/100x100/10b981/white?text=Eco'): string
{
    if (empty($path)) {
        return $placeholder;
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    return BASE_URL . ltrim($path, '/');
}

/**
 * Genera un slug optimizado para URLs amigables
 */
function generateSlug(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  AUDITORÍA
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Registra una acción en la tabla de auditoría de la base de datos
 */
function logAuditoria($usuario_id, string $accion, string $tabla_afectada): bool {
    try {
        $db = Database::getConnection();
        $sql = "INSERT INTO auditoria (usuario_id, accion, tabla_afectada)
                VALUES (:usuario_id, :accion, :tabla_afectada)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':usuario_id'     => $usuario_id,
            ':accion'         => $accion,
            ':tabla_afectada' => $tabla_afectada,
        ]);
    } catch (Exception $e) {
        logError('ERROR', 'Error al registrar auditoría: ' . $e->getMessage(), [
            'file' => $e->getFile(), 'line' => $e->getLine()
        ]);
        return false;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  UI HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Muestra alertas estilizadas de Bootstrap 5
 */
function renderAlert(string $msg, string $type = 'info'): string {
    if (empty($msg)) return '';
    $icon = ($type === 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle';
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show border-0 shadow-sm" role="alert">'
         . '<i class="fas ' . $icon . ' me-2"></i>' . $msg
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
         . '</div>';
}

/**
 * Redirige de forma segura considerando BASE_URL
 */
function redirect(string $path): void {
    $cleanPath = ltrim($path, '/');
    header('Location: ' . BASE_URL . $cleanPath);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  RATE LIMITING (LOGIN)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Verifica si el usuario ha excedido el límite de intentos de login.
 * Máximo 5 intentos cada 15 minutos.
 */
function isLoginRateLimited(): bool
{
    if (!isset($_SESSION['login_attempts'])) {
        return false;
    }

    $attempts = $_SESSION['login_attempts'];
    $window   = 15 * 60;
    $elapsed  = time() - ($attempts['first_attempt'] ?? 0);

    if ($elapsed > $window) {
        unset($_SESSION['login_attempts']);
        return false;
    }

    return ($attempts['count'] ?? 0) >= 5;
}

/**
 * Registra un intento fallido de login.
 */
function recordFailedLoginAttempt(): void
{
    $window = 15 * 60;
    $now    = time();

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = ['count' => 1, 'first_attempt' => $now];
        return;
    }

    $elapsed = $now - ($_SESSION['login_attempts']['first_attempt'] ?? $now);
    if ($elapsed > $window) {
        $_SESSION['login_attempts'] = ['count' => 1, 'first_attempt' => $now];
        return;
    }

    $_SESSION['login_attempts']['count'] = ($_SESSION['login_attempts']['count'] ?? 0) + 1;
}

/**
 * Limpia el contador de intentos tras un login exitoso.
 */
function clearLoginAttempts(): void
{
    unset($_SESSION['login_attempts']);
}

// ═══════════════════════════════════════════════════════════════════════════════
//  SUBIDA DE ARCHIVOS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Maneja la subida segura de imágenes de productos
 *
 * @param array  $file      Arreglo $_FILES['key']
 * @param string $targetDir Directorio de destino
 * @return string|false     Nombre del archivo subido o false en error
 */
function uploadImage(array $file, string $targetDir = '../assets/uploads/productos/'): string|false {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        logError('WARNING', 'uploadImage: archivo no recibido o error de subida', ['error_code' => $file['error'] ?? -1]);
        return false;
    }

    $maxSize = 3 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        logError('WARNING', 'uploadImage: archivo excede el límite de 3MB', ['size' => $file['size']]);
        return false;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedExt   = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);

    if (!in_array($mimeType, $allowedTypes, true)) {
        logError('WARNING', 'uploadImage: tipo MIME no permitido', ['mime' => $mimeType]);
        return false;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExt, true)) {
        logError('WARNING', 'uploadImage: extensión no permitida', ['ext' => $extension]);
        return false;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $newFileName = 'prod_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $destPath    = $targetDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        return $newFileName;
    }

    logError('ERROR', 'uploadImage: no se pudo mover el archivo temporal', ['dest' => $destPath]);
    return false;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  CARRITO — SCRIPT CLIENTE CENTRALIZADO
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Emite el bloque JavaScript centralizado para addToCart() y sessionStorage.
 */
function renderAddToCartScript(): void
{
    $baseUrl   = BASE_URL;
    $apiUrl    = BASE_URL . 'api/carrito.php';
    $loginUrl  = BASE_URL . 'login.php';
    $isLogged  = isLoggedIn() ? 'true' : 'false';
    ?>
<script>
(function () {
    const API_URL    = '<?php echo $apiUrl; ?>';
    const BASE_URL   = '<?php echo $baseUrl; ?>';
    const LOGIN_URL  = '<?php echo $loginUrl; ?>';
    const IS_LOGGED  = <?php echo $isLogged; ?>;
    const PENDING_KEY = 'eco_pending_cart';

    function getPendingCart() {
        try {
            return JSON.parse(sessionStorage.getItem(PENDING_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function savePendingCart(items) {
        sessionStorage.setItem(PENDING_KEY, JSON.stringify(items));
    }

    function showCartToast(msg, type) {
        if (typeof showToast === 'function') {
            showToast(msg, type || 'success');
            return;
        }
        let t = document.getElementById('ecoToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'ecoToast';
            t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#064e3b;color:#fff;padding:12px 20px;border-radius:10px;font-size:.9rem;box-shadow:0 4px 20px rgba(0,0,0,.3);transition:opacity .3s ease;';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.opacity = '1';
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.style.opacity = '0', 2800);
    }

    function updateNavbarBadge(count) {
        let badge = document.querySelector('.cart-badge');
        const cartLink = document.querySelector('a[title="Mi Carrito"]') || document.querySelector('a[href*="carrito.php"]');
        if (count > 0) {
            if (badge) {
                badge.textContent = count;
            } else if (cartLink) {
                cartLink.style.position = 'relative';
                cartLink.insertAdjacentHTML('beforeend',
                    `<span class="position-absolute translate-middle badge rounded-pill bg-danger cart-badge"
                           style="top:6px;left:calc(100% - 6px);font-size:.65rem;min-width:18px;">
                        ${count}
                    </span>`
                );
            }
        } else if (badge) {
            badge.remove();
        }
    }

    window.addToCart = async function (productoId, cantidad) {
        productoId = parseInt(productoId, 10);
        cantidad   = parseInt(cantidad, 10) || 1;

        if (!productoId || productoId <= 0) return;

        if (!IS_LOGGED) {
            const pending = getPendingCart();
            const idx = pending.findIndex(i => i.producto_id === productoId);
            if (idx >= 0) {
                pending[idx].cantidad += cantidad;
            } else {
                pending.push({ producto_id: productoId, cantidad: cantidad });
            }
            savePendingCart(pending);
            const totalPending = pending.reduce((s, i) => s + i.cantidad, 0);
            updateNavbarBadge(totalPending);
            showCartToast('Producto guardado. Inicia sesión para confirmar tu bolsa.', 'warning');
            return;
        }

        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ producto_id: productoId, cantidad: cantidad }),
            });
            const json = await res.json();

            if (json.success) {
                updateNavbarBadge(json.cart_count);
                showCartToast(json.message, 'success');
            } else {
                if (res.status === 401) {
                    window.location.href = LOGIN_URL;
                    return;
                }
                showCartToast(json.message || 'No se pudo agregar el producto.', 'warning');
            }
        } catch (err) {
            showCartToast('Error de conexión. Verifica tu internet.', 'error');
        }
    };

    async function mergePendingCart() {
        if (!IS_LOGGED) return;

        const pending = getPendingCart();
        if (!pending.length) return;

        let lastCount = 0;
        for (const item of pending) {
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        producto_id: item.producto_id,
                        cantidad: item.cantidad,
                    }),
                });
                const json = await res.json();
                if (json.success) {
                    lastCount = json.cart_count;
                }
            } catch (e) {
                break;
            }
        }

        sessionStorage.removeItem(PENDING_KEY);
        if (lastCount > 0) {
            updateNavbarBadge(lastCount);
            showCartToast('Tus productos pendientes se agregaron a tu bolsa.', 'success');
        }
    }

    document.addEventListener('DOMContentLoaded', mergePendingCart);
})();
</script>
    <?php
}
