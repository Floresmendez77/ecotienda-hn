<?php
/**
 * 🌱 ECOTIENDA HN - INTEGRACIÓN PAYPAL SANDBOX
 * Ruta: /includes/paypal.php  (ARCHIVO NUEVO)
 * Descripción: Funciones auxiliares para hablar con la REST API de PayPal
 *              (obtener token OAuth, crear orden, capturar pago). El
 *              Client Secret NUNCA sale de este archivo ni del servidor;
 *              la app solo recibe de vuelta el ID de la orden de PayPal.
 *              (Fase 4 del plan EcoTienda HN)
 */

/**
 * Pide un access token OAuth2 a PayPal usando Client ID + Secret
 * (Client Credentials grant). Se pide uno nuevo en cada operación por
 * simplicidad (no se cachea; el volumen de este proyecto no lo justifica).
 *
 * @throws Exception si PayPal no responde con un token válido
 */
function paypalObtenerAccessToken(): string
{
    $ch = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $respuesta = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $datos = json_decode($respuesta, true);
    if ($httpCode !== 200 || empty($datos['access_token'])) {
        logError('ERROR', 'paypalObtenerAccessToken: fallo al obtener token', [
            'http_code' => $httpCode, 'respuesta' => $respuesta,
        ]);
        throw new Exception('No se pudo autenticar con PayPal.');
    }

    return $datos['access_token'];
}

/**
 * Crea una orden de PayPal por el monto total del pedido (convertido a
 * USD, ya que PayPal no admite Lempiras hondureñas - HNL - como moneda).
 * La tasa de conversión es configurable vía PAYPAL_EXCHANGE_RATE en el .env.
 *
 * @param float $totalLempiras Total del pedido en Lempiras (L.)
 * @return array{paypal_order_id: string, monto_usd: float}
 * @throws Exception si PayPal rechaza la creación de la orden
 */
function paypalCrearOrden(float $totalLempiras): array
{
    $accessToken = paypalObtenerAccessToken();
    $montoUsd = round($totalLempiras / PAYPAL_EXCHANGE_RATE, 2);

    $body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => 'USD',
                'value'         => number_format($montoUsd, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'return_url'          => 'https://mi-api-test.alwaysdata.net/paypal-return.php',
            'cancel_url'          => 'https://mi-api-test.alwaysdata.net/paypal-cancel.php',
            'shipping_preference' => 'NO_SHIPPING',
        ],
    ];

    $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $respuesta = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $datos = json_decode($respuesta, true);
    if ($httpCode !== 201 || empty($datos['id'])) {
        logError('ERROR', 'paypalCrearOrden: PayPal rechazó la orden', [
            'http_code' => $httpCode, 'respuesta' => $respuesta,
        ]);
        throw new Exception('PayPal no pudo crear la orden de pago.');
    }

    $approveUrl = null;
    foreach (($datos['links'] ?? []) as $link) {
        if (($link['rel'] ?? '') === 'approve') {
            $approveUrl = $link['href'];
            break;
        }
    }

    return [
        'paypal_order_id' => $datos['id'],
        'monto_usd'       => $montoUsd,
        'approve_url'     => $approveUrl,
    ];
}

/**
 * Captura (cobra, dentro del entorno Sandbox) una orden de PayPal
 * previamente aprobada por el usuario en su checkout.
 *
 * @return array{capturado: bool, capture_id: ?string, estado: string}
 */
function paypalCapturarOrden(string $paypalOrderId): array
{
    $accessToken = paypalObtenerAccessToken();

    $ch = curl_init(PAYPAL_API_BASE . "/v2/checkout/orders/{$paypalOrderId}/capture");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $respuesta = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $datos = json_decode($respuesta, true);
    $estado = $datos['status'] ?? 'DESCONOCIDO';

    if ($httpCode !== 201 || $estado !== 'COMPLETED') {
        logError('WARNING', 'paypalCapturarOrden: captura no completada', [
            'http_code' => $httpCode, 'estado' => $estado, 'respuesta' => $respuesta,
        ]);
        return ['capturado' => false, 'capture_id' => null, 'estado' => $estado];
    }

    $captureId = $datos['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

    return ['capturado' => true, 'capture_id' => $captureId, 'estado' => $estado];
}