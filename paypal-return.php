<?php
/**
 * 🌱 ECOTIENDA HN - PUENTE DE REGRESO DESDE PAYPAL (APP MÓVIL)
 * Ruta: /paypal-return.php  (ARCHIVO NUEVO, en la raíz del sitio)
 * Descripción: PayPal exige un return_url http(s) real; esta página solo
 *              reenvía al navegador móvil hacia el deep link de la app
 *              (ecotiendahn://paypal-return), pasando los mismos parámetros
 *              que PayPal agregó (token, PayerID). No toca la base de datos
 *              ni hace nada más que redirigir. (Fase 4 del plan EcoTienda HN)
 */

$query = $_SERVER['QUERY_STRING'] ?? '';
$deepLink = 'ecotiendahn://paypal-return' . ($query !== '' ? '?' . $query : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Volviendo a EcoTienda HN…</title>
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($deepLink, ENT_QUOTES) ?>">
</head>
<body>
    <p>Volviendo a la app EcoTienda HN…</p>
    <p>Si no pasa nada automáticamente, <a href="<?= htmlspecialchars($deepLink, ENT_QUOTES) ?>">toca aquí</a>.</p>
    <script>
        window.location.href = <?= json_encode($deepLink) ?>;
    </script>
</body>
</html>
