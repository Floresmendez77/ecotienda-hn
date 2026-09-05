</div><!-- /main-content -->

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- Fondo Ferrofluid (WebGL, port vanilla de React Bits) -->
<script src="<?php echo BASE_URL; ?>assets/js/ferrofluid-bg.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.EcoFerrofluid) {
        window.EcoFerrofluid.init('ferrofluidBg', {
            colors: ['#10B981', '#059669', '#10B981'],
            speed: 0.4,
            scale: 1.6,
            turbulence: 0.8,
            fluidity: 0.12,
            rimWidth: 0.18,
            sharpness: 2.6,
            shimmer: 1.2,
            glow: 1.6,
            flowDirection: 'down',
            opacity: 0.5,
            mouseInteraction: true,
            mouseStrength: 0.8,
            mouseRadius: 0.32
        });
    }
});
</script>
</body>
</html>
