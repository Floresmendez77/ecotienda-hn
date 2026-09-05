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
//  URL ABSOLUTA DEL SITIO (usar SIEMPRE esta función, nunca concatenar a mano)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Devuelve una URL absoluta y correcta del sitio, sin importar si BASE_URL
 * (definida en config.php) es una ruta relativa ("/") o ya viene absoluta
 * desde una variable de entorno (como en AlwaysData: "https://mi-api-test.alwaysdata.net/").
 *
 * Antes, varios archivos (forgot_password.php, mailer.php) le pegaban manualmente
 * "https://" + HTTP_HOST por delante de BASE_URL, lo que duplicaba el dominio
 * cuando BASE_URL ya era absoluta. Esta función centraliza esa lógica.
 *
 * @param string $path Ruta relativa al sitio, ej: 'reset_password.php?token=abc'
 */
function site_url(string $path = ''): string {
    $path = ltrim($path, '/');
    $base = defined('BASE_URL') ? BASE_URL : '/';

    if (preg_match('#^https?://#i', $base)) {
        // BASE_URL ya es absoluta (ej: variable de entorno en AlwaysData)
        return rtrim($base, '/') . '/' . $path;
    }

    // BASE_URL es relativa: construir el absoluto a mano
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . rtrim($base, '/') . '/' . $path;
}

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
//  HISTORIAL DE PEDIDOS (Fase 9 — timeline en la app)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Inserta una fila en pedido_historial. Es el "paso" de timeline que la app
 * móvil (y a futuro el sitio web) muestra en "Mis pedidos". Se llama cada
 * vez que el estado de un pedido cambia: creación, pago aprobado/rechazado,
 * y los cambios de logística que hace el admin (procesando/enviado/etc).
 */
function registrarHistorialPedido(PDO $db, int $pedidoId, string $estado, ?string $nota = null, ?int $usuarioId = null): bool {
    try {
        $sql = "INSERT INTO pedido_historial (pedido_id, estado, nota, usuario_id)
                VALUES (:pedido_id, :estado, :nota, :usuario_id)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':pedido_id'  => $pedidoId,
            ':estado'     => $estado,
            ':nota'       => $nota,
            ':usuario_id' => $usuarioId,
        ]);
    } catch (Exception $e) {
        logError('ERROR', 'Error al registrar historial de pedido: ' . $e->getMessage(), [
            'pedido_id' => $pedidoId, 'estado' => $estado,
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
//  STOCK DE PEDIDOS (Fase 7 — fix: checkout-crear-orden.php y
//  checkout-capturar-pago.php de la app no descontaban stock)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Excepción específica para "ya no queda stock suficiente al momento de
 * confirmar". El caller decide si esto debe abortar la operación (pago
 * manual, todavía no se cobró nada) o solo quedar registrado como aviso
 * (captura de PayPal, el dinero ya se cobró y no tiene sentido fallar).
 */
class StockInsuficienteException extends Exception {}

/**
 * Descuenta el stock de todos los productos de un pedido ya creado,
 * usando su `detalle_pedido`, con el mismo patrón atómico que checkout.php:
 * UPDATE ... WHERE stock >= cantidad, para que dos confirmaciones
 * simultáneas nunca dejen el stock en negativo sin necesidad de locks
 * explícitos.
 *
 * IMPORTANTE: debe llamarse DENTRO de una transacción ya abierta
 * (`$db->beginTransaction()`) por quien la invoca. Esta función no hace
 * commit ni rollback — eso le corresponde al caller.
 *
 * @throws StockInsuficienteException si algún producto ya no tiene stock
 *         suficiente. La transacción queda intacta; el caller decide si
 *         hace rollback o no.
 */
function descontarStockPedido(PDO $db, int $pedidoId, string $motivoInventario): void
{
    $detalleStmt = $db->prepare(
        "SELECT dp.producto_id, dp.cantidad, p.nombre
         FROM detalle_pedido dp
         INNER JOIN productos p ON p.id = dp.producto_id
         WHERE dp.pedido_id = :pedido_id"
    );
    $detalleStmt->execute([':pedido_id' => $pedidoId]);
    $lineas = $detalleStmt->fetchAll();

    $stockStmt = $db->prepare(
        "UPDATE productos SET stock = stock - :cantidad WHERE id = :producto_id AND stock >= :cantidad2"
    );
    $movStmt = $db->prepare(
        "INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion)
         VALUES (:producto_id, 'salida', :cantidad, :descripcion)"
    );

    foreach ($lineas as $linea) {
        $stockStmt->execute([
            ':cantidad'    => $linea['cantidad'],
            ':cantidad2'   => $linea['cantidad'],
            ':producto_id' => $linea['producto_id'],
        ]);

        if ($stockStmt->rowCount() === 0) {
            throw new StockInsuficienteException(
                "Stock insuficiente de '{$linea['nombre']}' para el pedido #{$pedidoId}."
            );
        }

        $movStmt->execute([
            ':producto_id' => $linea['producto_id'],
            ':cantidad'    => $linea['cantidad'],
            ':descripcion' => $motivoInventario . " (Pedido #{$pedidoId})",
        ]);
    }
}

/**
 * Devuelve al inventario el stock de un pedido cuyo pago fue rechazado
 * (o cancelado) después de haber sido descontado. Mismo criterio de
 * transacción abierta por el caller que descontarStockPedido().
 */
function restaurarStockPedido(PDO $db, int $pedidoId, string $motivoInventario): void
{
    $detalleStmt = $db->prepare(
        "SELECT producto_id, cantidad FROM detalle_pedido WHERE pedido_id = :pedido_id"
    );
    $detalleStmt->execute([':pedido_id' => $pedidoId]);
    $lineas = $detalleStmt->fetchAll();

    $stockStmt = $db->prepare(
        "UPDATE productos SET stock = stock + :cantidad WHERE id = :producto_id"
    );
    $movStmt = $db->prepare(
        "INSERT INTO inventario (producto_id, tipo_movimiento, cantidad, descripcion)
         VALUES (:producto_id, 'entrada', :cantidad, :descripcion)"
    );

    foreach ($lineas as $linea) {
        $stockStmt->execute([
            ':cantidad'    => $linea['cantidad'],
            ':producto_id' => $linea['producto_id'],
        ]);

        $movStmt->execute([
            ':producto_id' => $linea['producto_id'],
            ':cantidad'    => $linea['cantidad'],
            ':descripcion' => $motivoInventario . " (Pedido #{$pedidoId})",
        ]);
    }
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
    $csrfToken = generateCsrfToken();
    ?>
<script>
(function () {
    const API_URL    = '<?php echo $apiUrl; ?>';
    const BASE_URL   = '<?php echo $baseUrl; ?>';
    const LOGIN_URL  = '<?php echo $loginUrl; ?>';
    const IS_LOGGED  = <?php echo $isLogged; ?>;
    const CSRF_TOKEN = '<?php echo addslashes($csrfToken); ?>';
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

        /* ── GSAP FLY TO CART MICRO-INTERACTION ────────────────────────── */
        try {
            if (typeof gsap !== 'undefined' && window.innerWidth >= 768 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                let btn = (window.event && window.event.currentTarget) ? window.event.currentTarget : null;
                let sourceImg = null;
                if (btn) {
                    let card = btn.closest('.card, .carousel-item-card, [data-product-card]');
                    if (card) {
                        sourceImg = card.querySelector('img');
                    }
                }
                if (!sourceImg) {
                    sourceImg = document.querySelector(`a[href*="producto.php?id=${productoId}"]`)?.closest('.card')?.querySelector('img') || document.querySelector('.card img');
                }

                const cartTarget = document.querySelector('.cart-icon-wrapper') || document.querySelector('a[href*="carrito.php"]');
                if (sourceImg && cartTarget) {
                    const sRect = sourceImg.getBoundingClientRect();
                    const tRect = cartTarget.getBoundingClientRect();

                    const clone = sourceImg.cloneNode(true);
                    clone.style.cssText = `
                        position: fixed;
                        top: ${sRect.top}px;
                        left: ${sRect.left}px;
                        width: ${sRect.width}px;
                        height: ${sRect.height}px;
                        z-index: 99999;
                        pointer-events: none;
                        border-radius: 16px;
                        box-shadow: 0 12px 30px rgba(16, 185, 129, 0.4);
                        object-fit: cover;
                    `;
                    document.body.appendChild(clone);

                    gsap.to(clone, {
                        top: tRect.top + tRect.height / 2 - 20,
                        left: tRect.left + tRect.width / 2 - 20,
                        width: 40,
                        height: 40,
                        opacity: 0.15,
                        scale: 0.4,
                        duration: 0.75,
                        ease: 'power3.inOut',
                        onComplete: function () {
                            clone.remove();
                            gsap.fromTo(cartTarget, { scale: 1 }, { scale: 1.25, duration: 0.25, yoyo: true, repeat: 1, ease: 'back.out(2)' });
                        }
                    });
                }
            }
        } catch (e) {
            // Silencioso
        }

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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
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