<?php
/**
 * 🌱 ECOTIENDA HN - GENERADOR DE PDF DE RECIBO
 * Ruta: /includes/recibo_pdf.php  (ARCHIVO NUEVO)
 * Descripción: Genera el PDF del recibo de un pedido con TCPDF, a partir
 *              de datos reales de pedidos + detalle_pedido + pagos +
 *              productos. Reutilizado por www/recibo-pedido.php (descarga
 *              directa) y por api/checkout-capturar-pago.php (adjunto en
 *              el correo de confirmación).
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php';

/**
 * Genera el PDF del recibo de un pedido y lo devuelve como string binario
 * (sin escribir nada a disco - se usa tanto para servir la descarga como
 * para adjuntar en el correo vía PHPMailer::addStringAttachment()).
 *
 * @return string|null El contenido binario del PDF, o null si el pedido no existe.
 */
function generarReciboPdfPedido(int $pedidoId): ?string
{
    $db = Database::getConnection();

    $stmt = $db->prepare("SELECT * FROM pedidos WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $pedidoId]);
    $pedido = $stmt->fetch();
    if (!$pedido) {
        return null;
    }

    $stmtDetalle = $db->prepare(
        "SELECT dp.cantidad, dp.precio, dp.subtotal, pr.nombre
         FROM detalle_pedido dp
         JOIN productos pr ON pr.id = dp.producto_id
         WHERE dp.pedido_id = :id
         ORDER BY dp.id ASC"
    );
    $stmtDetalle->execute(['id' => $pedidoId]);
    $items = $stmtDetalle->fetchAll();

    $stmtPago = $db->prepare(
        "SELECT * FROM pagos WHERE pedido_id = :id ORDER BY id DESC LIMIT 1"
    );
    $stmtPago->execute(['id' => $pedidoId]);
    $pago = $stmtPago->fetch();

    $fecha        = date('d/m/Y H:i', strtotime($pedido['fecha']));
    $subtotalFmt  = number_format((float)$pedido['subtotal'], 2);
    $descuentoFmt = number_format((float)$pedido['descuento'], 2);
    $envioFmt     = number_format((float)$pedido['envio'], 2);
    $totalFmt     = number_format((float)$pedido['total'], 2);

    $filasProductos = '';
    foreach ($items as $item) {
        $nombre    = htmlspecialchars($item['nombre']);
        $precioFmt = number_format((float)$item['precio'], 2);
        $subFmt    = number_format((float)$item['subtotal'], 2);
        $filasProductos .= "
            <tr>
                <td>{$nombre}</td>
                <td align='center'>{$item['cantidad']}</td>
                <td align='right'>L. {$precioFmt}</td>
                <td align='right'>L. {$subFmt}</td>
            </tr>";
    }

    $bloqueDescuento = '';
    if ((float)$pedido['descuento'] > 0) {
        $bloqueDescuento = "<tr><td align='right' colspan='3'>Descuento:</td><td align='right'>-L. {$descuentoFmt}</td></tr>";
    }

    $bloquePago = '';
    if ($pago) {
        $referencia   = htmlspecialchars($pago['referencia_paypal'] ?? ($pago['referencia'] ?? '-'));
        $estadoPago   = htmlspecialchars(ucfirst($pago['estado'] ?? ''));
        $bloquePago = "
            <p style='margin-top:14px;font-size:9pt;color:#444;'>
                <b>Método de pago:</b> PayPal Sandbox<br>
                <b>Referencia:</b> {$referencia}<br>
                <b>Estado del pago:</b> {$estadoPago}
            </p>";
    }

    $html = "
        <table cellpadding='0' cellspacing='0' style='width:100%;'>
            <tr>
                <td style='width:60%;'>
                    <h1 style='color:#1a5c2a;font-size:18pt;margin:0;'>EcoTienda HN</h1>
                    <p style='color:#666;font-size:9pt;margin:2px 0 0;'>Ecológico, Sostenible y Hondureño</p>
                </td>
                <td style='width:40%;text-align:right;'>
                    <h2 style='color:#333;font-size:13pt;margin:0;'>Recibo de pedido #{$pedidoId}</h2>
                    <p style='color:#666;font-size:9pt;margin:2px 0 0;'>Fecha: {$fecha}</p>
                    <p style='color:#666;font-size:9pt;margin:2px 0 0;'>Estado: " . htmlspecialchars(ucfirst($pedido['estado'])) . "</p>
                </td>
            </tr>
        </table>

        <br>

        <table border='1' cellpadding='6' style='width:100%;border-collapse:collapse;'>
            <thead>
                <tr style='background-color:#e8f5e9;font-size:9pt;'>
                    <th align='left'>Producto</th>
                    <th align='center'>Cant.</th>
                    <th align='right'>Precio unit.</th>
                    <th align='right'>Subtotal</th>
                </tr>
            </thead>
            <tbody style='font-size:9pt;'>
                {$filasProductos}
            </tbody>
        </table>

        <br>

        <table cellpadding='4' style='width:100%;font-size:9pt;'>
            <tr><td align='right' colspan='3'>Subtotal de productos:</td><td align='right' width='90'>L. {$subtotalFmt}</td></tr>
            {$bloqueDescuento}
            <tr><td align='right' colspan='3'>Envío:</td><td align='right'>L. {$envioFmt}</td></tr>
            <tr style='background-color:#e8f5e9;'>
                <td align='right' colspan='3'><b>TOTAL PAGADO:</b></td>
                <td align='right'><b>L. {$totalFmt}</b></td>
            </tr>
        </table>

        {$bloquePago}

        <p style='margin-top:20px;font-size:8pt;color:#999;text-align:center;'>
            Gracias por comprar eco-responsable. Este recibo fue generado automáticamente por EcoTienda HN.
        </p>
    ";

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('EcoTienda HN');
    $pdf->SetAuthor('EcoTienda HN');
    $pdf->SetTitle("Recibo de pedido #{$pedidoId}");
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');

    // 'S' = devolver como string, sin tocar el disco.
    return $pdf->Output("recibo-pedido-{$pedidoId}.pdf", 'S');
}
