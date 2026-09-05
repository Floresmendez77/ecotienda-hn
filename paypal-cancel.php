<?php
/**
 * 🌱 ECOTIENDA HN - PUENTE DE CANCELACIÓN DESDE PAYPAL (APP MÓVIL)
 * Ruta: /paypal-cancel.php  (ARCHIVO NUEVO, en la raíz del sitio)
 * Descripción: Igual que paypal-return.php pero para cuando el comprador
 *              cancela el pago en PayPal. Reenvía al deep link de cancelación
 *              de la app (ecotiendahn://paypal-cancel). (Fase 4)
 */

$query = $_SERVER['QUERY_STRING'] ?? '';
$deepLink = 'ecotiendahn://paypal-cancel' . ($query !== '' ? '?' . $query : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Volviendo a EcoTienda HN…</title>
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($deepLink, ENT_QUOTES) ?>">
</head>
<body>
    <p>Pago cancelado. Volviendo a la app EcoTienda HN…</p>
    <p>Si no pasa nada automáticamente, <a href="<?= htmlspecialchars($deepLink, ENT_QUOTES) ?>">toca aquí</a>.</p>
    <script>
        window.location.href = <?= json_encode($deepLink) ?>;
    </script>
</body>
</html>
