<?php
/**
 * 🌱 ECOTIENDA HN - SISTEMA DE CORREOS
 * Ruta: /includes/mailer.php
 * Descripción: Funciones de envío de correos con PHPMailer para EcoTienda HN
 */

function ecotienda_mail(string $to_email, string $to_name, string $subject, string $html, string $plain): bool {
    if (!MAIL_ENABLED) return true;

    $vendor = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendor)) {
        error_log('[EcoTienda Mail] vendor/autoload.php no encontrado');
        return false;
    }
    require_once $vendor;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $plain;
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('[EcoTienda Mail] Error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ── PLANTILLA BASE ────────────────────────────────────────────────────────────
function ecotienda_email_template(string $title, string $body_html): string {
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f0;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f0;padding:30px 0;">
  <tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.10);">

      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#1a5c2a,#2d8a45);padding:28px 40px;text-align:center;border-bottom:4px solid #4CAF50;">
          <div style="font-size:1.8rem;font-weight:900;color:#ffffff;letter-spacing:1px;">
            🌱 EcoTienda <span style="color:#a8f0b0;">HN</span>
          </div>
          <div style="font-size:.75rem;color:rgba(255,255,255,.65);margin-top:4px;">Ecológico, Sostenible y Hondureño</div>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:35px 40px;color:#2c3e2d;">
          {$body_html}
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#f7faf7;padding:20px 40px;text-align:center;border-top:1px solid #e0ece0;">
          <p style="margin:0;font-size:.72rem;color:#888;">&copy; {$year} EcoTienda HN · Tegucigalpa, Honduras</p>
          <p style="margin:6px 0 0;font-size:.7rem;color:#aaa;">Este es un mensaje automático. Por favor no respondas este correo.</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── 1. BIENVENIDA (registro nuevo usuario) ────────────────────────────────────
function notify_bienvenida_eco(PDO $pdo, int $uid): void {
    $stmt = $pdo->prepare("SELECT nombre, apellido, correo FROM usuarios WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if (!$u || empty($u['correo'])) return;

    $nombre  = htmlspecialchars($u['nombre']);
    $siteUrl = 'http://localhost:8080/ecotienda-hn';

    $body = <<<HTML
    <h2 style="color:#1a5c2a;font-size:1.4rem;margin:0 0 8px;">🎉 ¡Bienvenido a EcoTienda HN, {$nombre}!</h2>
    <p style="color:#555;font-size:.92rem;margin:0 0 20px;">Tu cuenta fue creada exitosamente. Ahora puedes explorar nuestros productos ecológicos y hacer tu primer pedido.</p>

    <div style="background:#f0faf2;border:1px solid #b2dfb8;border-radius:10px;padding:18px;margin-bottom:24px;">
      <div style="font-size:.8rem;font-weight:700;color:#1a5c2a;margin-bottom:10px;">🌿 ¿Qué puedes hacer en EcoTienda HN?</div>
      <ul style="margin:0;padding-left:18px;color:#444;font-size:.85rem;line-height:1.8;">
        <li>Explorar productos 100% ecológicos y sostenibles</li>
        <li>Agregar productos a tu carrito y favoritos</li>
        <li>Realizar pedidos con entrega en Honduras</li>
        <li>Seguir el estado de tus pedidos en tiempo real</li>
      </ul>
    </div>

    <p style="text-align:center;margin:24px 0;">
      <a href="{$siteUrl}" style="background:linear-gradient(135deg,#1a5c2a,#2d8a45);color:#fff;text-decoration:none;padding:13px 32px;border-radius:9px;font-weight:700;font-size:.9rem;display:inline-block;">
        🌱 IR A LA TIENDA
      </a>
    </p>
    <p style="color:#888;font-size:.8rem;text-align:center;">¡Gracias por unirte a nuestra comunidad eco-responsable! 🌎</p>
HTML;

    $html  = ecotienda_email_template('¡Bienvenido a EcoTienda HN!', $body);
    $plain = "Bienvenido {$nombre}! Tu cuenta en EcoTienda HN fue creada. Visita: {$siteUrl}";
    ecotienda_mail($u['correo'], $nombre, "🌱 ¡Bienvenido a EcoTienda HN, {$nombre}!", $html, $plain);
}

// ── 2. CONFIRMACIÓN DE PEDIDO ─────────────────────────────────────────────────
function notify_pedido_confirmado(PDO $pdo, int $pedido_id): void {
    // Obtener datos del pedido y del usuario
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre, u.apellido, u.correo
        FROM pedidos p
        JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $p = $stmt->fetch();
    if (!$p || empty($p['correo'])) return;

    // Obtener detalle de productos del pedido
    $dstmt = $pdo->prepare("
        SELECT dp.cantidad, dp.precio, dp.subtotal, pr.nombre AS producto_nombre,
               pr.imagen_principal, pr.precio_oferta
        FROM detalle_pedido dp
        JOIN productos pr ON pr.id = dp.producto_id
        WHERE dp.pedido_id = ?
        ORDER BY dp.id ASC
    ");
    $dstmt->execute([$pedido_id]);
    $items = $dstmt->fetchAll();

    // Datos formateados
    $nombre        = htmlspecialchars($p['nombre']);
    $apellido      = htmlspecialchars($p['apellido']);
    $total_fmt     = number_format((float)($p['total']    ?? 0), 2);
    $envio_fmt     = number_format((float)($p['envio']    ?? 0), 2);
    $subtotal_fmt  = number_format((float)($p['subtotal'] ?? 0), 2);
    $fecha         = date('d/m/Y H:i', strtotime($p['fecha']));
    $metodo_pago   = strtolower(trim($p['metodo_pago'] ?? ''));

    // URL dinámica: usa BASE_URL si está disponible, sino fallback a localhost
    $baseHref = defined('BASE_URL')
        ? rtrim('http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . BASE_URL
        : 'http://localhost:8080/ecotienda-hn/';

    $pedidosUrl = rtrim($baseHref, '/') . '/mis_pedidos.php';

    // ── Filas de la tabla de productos ───────────────────────────────────
    $filas = '';
    foreach ($items as $item) {
        $prod_nombre = htmlspecialchars($item['producto_nombre']);
        $precio_fmt  = number_format((float)$item['precio'],    2);
        $sub_fmt     = number_format((float)$item['subtotal'],  2);

        // Imagen del producto
        $img_path   = $item['imagen_principal'] ?? '';
        $img_url    = !empty($img_path)
            ? rtrim($baseHref, '/') . '/' . ltrim($img_path, '/')
            : 'https://placehold.co/60x60/10b981/white?text=Eco';

        // Badge de oferta
        $oferta_badge = !empty($item['precio_oferta'])
            ? "<span style='display:inline-block;background:#e53935;color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:20px;margin-left:6px;vertical-align:middle;'>🏷️ Oferta</span>"
            : '';

        $filas .= "
          <tr>
            <td style='padding:11px 14px;border-bottom:1px solid #e8f5e9;'>
              <table cellpadding='0' cellspacing='0' border='0'>
                <tr>
                  <td style='width:60px;vertical-align:middle;padding-right:12px;'>
                    <img src='{$img_url}' width='60' height='60' alt='{$prod_nombre}'
                         style='border-radius:8px;object-fit:cover;border:1px solid #e0f2f1;display:block;'>
                  </td>
                  <td style='vertical-align:middle;'>
                    <span style='font-size:.84rem;color:#333;font-weight:600;line-height:1.4;'>{$prod_nombre}</span>
                    {$oferta_badge}
                  </td>
                </tr>
              </table>
            </td>
            <td style='padding:11px 14px;border-bottom:1px solid #e8f5e9;font-size:.84rem;text-align:center;color:#555;font-weight:600;'>{$item['cantidad']}</td>
            <td style='padding:11px 14px;border-bottom:1px solid #e8f5e9;font-size:.84rem;text-align:right;color:#444;font-family:monospace;'>L.&nbsp;{$precio_fmt}</td>
            <td style='padding:11px 14px;border-bottom:1px solid #e8f5e9;font-size:.84rem;text-align:right;font-weight:700;color:#1a5c2a;font-family:monospace;'>L.&nbsp;{$sub_fmt}</td>
          </tr>";
    }

    // ── Bloque de instrucciones de pago (solo para transferencia) ────────
    $bloque_pago = '';
    $es_transferencia = in_array($metodo_pago, [
        'transferencia', 'transferencia_bancaria', 'deposito', 'deposito_bancario', 'banco'
    ]);

    if ($es_transferencia) {
        $bloque_pago = <<<PAGO
    <div style="background:#fffde7;border:2px solid #f9a825;border-radius:12px;padding:20px;margin:22px 0;">
      <div style="font-size:.95rem;font-weight:800;color:#e65100;margin-bottom:14px;">
        🏦 Instrucciones de Pago — Transferencia Bancaria
      </div>
      <p style="font-size:.83rem;color:#555;margin:0 0 14px;">
        Para confirmar tu pedido <b>#{$pedido_id}</b>, realiza la transferencia por el monto de
        <b style="color:#1a5c2a;">L.&nbsp;{$total_fmt}</b> a cualquiera de las siguientes cuentas:
      </p>

      <!-- Banco Atlántida -->
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#fff;border:1px solid #ffe082;border-radius:8px;margin-bottom:10px;overflow:hidden;">
        <tr>
          <td style="background:#1a5c2a;padding:8px 14px;">
            <span style="color:#fff;font-size:.8rem;font-weight:700;">🏦 Banco Atlántida</span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 14px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="font-size:.78rem;color:#888;width:40%;">N° de Cuenta:</td>
                <td style="font-size:.85rem;font-weight:700;color:#1a5c2a;font-family:monospace;">1234-5678-9012</td>
              </tr>
              <tr>
                <td style="font-size:.78rem;color:#888;padding-top:4px;">Titular:</td>
                <td style="font-size:.83rem;color:#333;padding-top:4px;">EcoTienda HN S. de R.L.</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- BAC Honduras -->
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#fff;border:1px solid #ffe082;border-radius:8px;margin-bottom:14px;overflow:hidden;">
        <tr>
          <td style="background:#1a5c2a;padding:8px 14px;">
            <span style="color:#fff;font-size:.8rem;font-weight:700;">🏦 BAC Honduras</span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 14px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="font-size:.78rem;color:#888;width:40%;">N° de Cuenta:</td>
                <td style="font-size:.85rem;font-weight:700;color:#1a5c2a;font-family:monospace;">0987-6543-2100</td>
              </tr>
              <tr>
                <td style="font-size:.78rem;color:#888;padding-top:4px;">Titular:</td>
                <td style="font-size:.83rem;color:#333;padding-top:4px;">EcoTienda HN S. de R.L.</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <div style="background:#fff3e0;border-radius:8px;padding:10px 14px;font-size:.79rem;color:#7c4700;">
        ⚠️ <b>Importante:</b> Una vez realizada la transferencia, envía el comprobante (captura de pantalla o PDF) 
        al correo <a href="mailto:pagos@ecotiendahn.com" style="color:#1a5c2a;">pagos@ecotiendahn.com</a> 
        indicando tu N° de pedido <b>#{$pedido_id}</b>. Tu pedido será procesado dentro de las siguientes 24 horas hábiles.
      </div>
    </div>
PAGO;
    }

    // ── Cuerpo del email ──────────────────────────────────────────────────
    $body = <<<HTML
    <h2 style="color:#1a5c2a;font-size:1.3rem;margin:0 0 6px;">✅ ¡Tu pedido #{$pedido_id} fue recibido — EcoTienda HN!</h2>
    <p style="color:#555;font-size:.9rem;margin:0 0 18px;">
        Hola <b>{$nombre} {$apellido}</b>, confirmamos que recibimos tu pedido correctamente.
        A continuación encontrarás el resumen de tu compra:
    </p>

    <!-- Cabecera del pedido -->
    <div style="background:#f0faf2;border:1px solid #b2dfb8;border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;">
      <div>
        <span style="font-size:.75rem;color:#888;display:block;">N° de Pedido</span>
        <span style="font-size:1.05rem;font-weight:800;color:#1a5c2a;">#{$pedido_id}</span>
      </div>
      <div>
        <span style="font-size:.75rem;color:#888;display:block;">Fecha</span>
        <span style="font-size:.88rem;color:#444;">{$fecha}</span>
      </div>
      <div>
        <span style="font-size:.75rem;color:#888;display:block;">Método de pago</span>
        <span style="font-size:.88rem;color:#444;text-transform:capitalize;">{$metodo_pago}</span>
      </div>
    </div>

    <!-- Tabla de productos del pedido -->
    <table width="100%" cellpadding="0" cellspacing="0"
           style="border-radius:10px;overflow:hidden;margin-bottom:20px;border:1px solid #dcedc8;">
      <thead>
        <tr style="background:linear-gradient(135deg,#1a5c2a,#2d8a45);">
          <th style="padding:10px 14px;color:#fff;font-size:.78rem;text-align:left;font-weight:700;">Producto</th>
          <th style="padding:10px 14px;color:#fff;font-size:.78rem;text-align:center;font-weight:700;">Cant.</th>
          <th style="padding:10px 14px;color:#fff;font-size:.78rem;text-align:right;font-weight:700;">Precio unit.</th>
          <th style="padding:10px 14px;color:#fff;font-size:.78rem;text-align:right;font-weight:700;">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        {$filas}
        <!-- Subtotal -->
        <tr style="background:#f0faf2;">
          <td colspan="3" style="padding:10px 14px;font-size:.84rem;color:#555;text-align:right;">Subtotal de productos:</td>
          <td style="padding:10px 14px;font-size:.84rem;text-align:right;color:#333;font-family:monospace;">L.&nbsp;{$subtotal_fmt}</td>
        </tr>
        <!-- Envío -->
        <tr style="background:#f7fdf8;">
          <td colspan="3" style="padding:10px 14px;font-size:.84rem;color:#555;text-align:right;">Envío estándar (Correos HN):</td>
          <td style="padding:10px 14px;font-size:.84rem;text-align:right;color:#333;font-family:monospace;">L.&nbsp;{$envio_fmt}</td>
        </tr>
        <!-- Total -->
        <tr style="background:#e8f5e9;">
          <td colspan="3" style="padding:13px 14px;font-size:.92rem;font-weight:700;color:#1a5c2a;text-align:right;">
            TOTAL A PAGAR:
          </td>
          <td style="padding:13px 14px;font-size:1.1rem;font-weight:900;text-align:right;color:#1a5c2a;font-family:monospace;">
            L.&nbsp;{$total_fmt}
          </td>
        </tr>
      </tbody>
    </table>

    {$bloque_pago}

    <!-- Botón Ver Mis Pedidos -->
    <p style="text-align:center;margin:26px 0 16px;">
      <a href="{$pedidosUrl}"
         style="background:linear-gradient(135deg,#1a5c2a,#2d8a45);color:#fff;text-decoration:none;
                padding:14px 34px;border-radius:10px;font-weight:700;font-size:.92rem;display:inline-block;
                letter-spacing:.4px;">
        📦 VER MIS PEDIDOS
      </a>
    </p>

    <p style="color:#888;font-size:.8rem;text-align:center;margin:0;">
        Te notificaremos cuando tu pedido sea enviado. ¡Gracias por comprar eco-responsable! 🌱🇭🇳
    </p>
HTML;

    $html  = ecotienda_email_template("Tu pedido #{$pedido_id} fue recibido — EcoTienda HN", $body);
    $plain = "¡Pedido #{$pedido_id} confirmado! Total: L.{$total_fmt}. Fecha: {$fecha}. Ver pedidos: {$pedidosUrl}";
    ecotienda_mail(
        $p['correo'],
        $nombre . ' ' . $apellido,
        "Tu pedido #{$pedido_id} fue recibido — EcoTienda HN",
        $html,
        $plain
    );
}

// ── 3. CAMBIO DE ESTADO DEL PEDIDO ───────────────────────────────────────────
function notify_estado_pedido(PDO $pdo, int $pedido_id, string $nuevo_estado): void {
    $stmt = $pdo->prepare("
        SELECT p.total, p.fecha, u.nombre, u.correo
        FROM pedidos p
        JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $p = $stmt->fetch();
    if (!$p || empty($p['correo'])) return;

    $nombre  = htmlspecialchars($p['nombre']);
    $total   = number_format($p['total'], 2);
    $siteUrl = 'http://localhost:8080/ecotienda-hn';

    $estados = [
        'pagado'      => ['emoji' => '💳', 'color' => '#1565C0', 'texto' => 'Pago Confirmado',    'desc' => 'Tu pago fue verificado. Estamos preparando tu pedido.'],
        'procesando'  => ['emoji' => '⚙️',  'color' => '#E65100', 'texto' => 'En Procesamiento',   'desc' => 'Estamos preparando y empacando tus productos.'],
        'enviado'     => ['emoji' => '🚚', 'color' => '#6A1B9A', 'texto' => 'Pedido Enviado',      'desc' => 'Tu pedido está en camino. Pronto llegará a tu dirección.'],
        'entregado'   => ['emoji' => '🎉', 'color' => '#1a5c2a', 'texto' => 'Pedido Entregado',    'desc' => '¡Tu pedido fue entregado! Esperamos que disfrutes tus productos eco.'],
        'cancelado'   => ['emoji' => '❌', 'color' => '#b71c1c', 'texto' => 'Pedido Cancelado',    'desc' => 'Tu pedido fue cancelado. Contáctanos si tienes dudas.'],
    ];

    $info = $estados[$nuevo_estado] ?? ['emoji' => '📦', 'color' => '#1a5c2a', 'texto' => ucfirst($nuevo_estado), 'desc' => 'El estado de tu pedido fue actualizado.'];

    $body = <<<HTML
    <div style="text-align:center;margin-bottom:24px;">
      <div style="font-size:3.5rem;margin-bottom:8px;">{$info['emoji']}</div>
      <h2 style="color:{$info['color']};font-size:1.4rem;margin:0 0 6px;">{$info['texto']}</h2>
      <p style="color:#555;font-size:.9rem;margin:0;">{$info['desc']}</p>
    </div>

    <div style="background:#f0faf2;border:1px solid #b2dfb8;border-radius:10px;padding:16px;margin-bottom:20px;text-align:center;">
      <div style="font-size:.78rem;color:#888;margin-bottom:4px;">N° de Pedido</div>
      <div style="font-size:1.4rem;font-weight:900;color:#1a5c2a;">#{$pedido_id}</div>
      <div style="font-size:.85rem;color:#555;margin-top:4px;">Total: <b>L. {$total}</b></div>
    </div>

    <p style="text-align:center;margin:24px 0;">
      <a href="{$siteUrl}/mis-pedidos.php" style="background:linear-gradient(135deg,#1a5c2a,#2d8a45);color:#fff;text-decoration:none;padding:13px 32px;border-radius:9px;font-weight:700;font-size:.9rem;display:inline-block;">
        📦 VER MIS PEDIDOS
      </a>
    </p>
    <p style="color:#888;font-size:.8rem;text-align:center;">Hola {$nombre}, gracias por confiar en EcoTienda HN 🌱</p>
HTML;

    $html  = ecotienda_email_template("Pedido #{$pedido_id} — {$info['texto']}", $body);
    $plain = "Hola {$nombre}, tu pedido #{$pedido_id} cambió a: {$info['texto']}. {$info['desc']}";
    ecotienda_mail($p['correo'], $nombre, "{$info['emoji']} Pedido #{$pedido_id}: {$info['texto']} – EcoTienda HN", $html, $plain);
}

// ── 4. RECUPERACIÓN / CAMBIO DE CONTRASEÑA ───────────────────────────────────
function notify_cambio_password(PDO $pdo, int $uid): void {
    $stmt = $pdo->prepare("SELECT nombre, correo FROM usuarios WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if (!$u || empty($u['correo'])) return;

    $nombre  = htmlspecialchars($u['nombre']);
    $fecha   = date('d/m/Y H:i');
    $siteUrl = 'http://localhost:8080/ecotienda-hn';

    $body = <<<HTML
    <h2 style="color:#1a5c2a;font-size:1.3rem;margin:0 0 8px;">🔒 Contraseña Actualizada</h2>
    <p style="color:#555;font-size:.9rem;margin:0 0 20px;">Hola <b>{$nombre}</b>, te informamos que la contraseña de tu cuenta fue cambiada exitosamente.</p>

    <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:16px;margin-bottom:20px;">
      <div style="font-size:.85rem;color:#555;">📅 Fecha y hora del cambio:</div>
      <div style="font-size:1rem;font-weight:700;color:#333;margin-top:4px;">{$fecha}</div>
    </div>

    <div style="background:#ffebee;border:1px solid #ef9a9a;border-radius:10px;padding:14px;margin-bottom:24px;">
      <div style="font-size:.85rem;color:#b71c1c;font-weight:700;">⚠️ ¿No fuiste tú?</div>
      <div style="font-size:.82rem;color:#555;margin-top:6px;">Si no realizaste este cambio, contáctanos de inmediato respondiendo este correo o escríbenos a <a href="mailto:admin@ecotiendahn.com" style="color:#1a5c2a;">admin@ecotiendahn.com</a>.</div>
    </div>

    <p style="text-align:center;margin:20px 0;">
      <a href="{$siteUrl}/login.php" style="background:linear-gradient(135deg,#1a5c2a,#2d8a45);color:#fff;text-decoration:none;padding:13px 32px;border-radius:9px;font-weight:700;font-size:.9rem;display:inline-block;">
        🔑 INICIAR SESIÓN
      </a>
    </p>
HTML;

    $html  = ecotienda_email_template('Contraseña Actualizada', $body);
    $plain = "Hola {$nombre}, tu contraseña de EcoTienda HN fue cambiada el {$fecha}. Si no fuiste tú, contáctanos.";
    ecotienda_mail($u['correo'], $nombre, "🔒 Contraseña actualizada – EcoTienda HN", $html, $plain);
}