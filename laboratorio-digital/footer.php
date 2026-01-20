<footer>
    <p>© <?php echo esc_html( date('Y') ); ?> <?php esc_html_e( 'Laboratorio Digital. Con amor por Sergio.', 'laboratorio-digital' ); ?></p>
</footer>

<?php wp_footer(); ?>

<script>
  (function () {
    const body = document.body;
    let ticking = false;
    let isScrolled = body.classList.contains('scrolled');

    // Histeresis (anti-parpadeo):
    // - Entra a modo reducido después de cierto punto
    // - Sale a modo expandido bastante antes (para que el cambio de altura no lo haga “rebotar”)
    const ENTER_AT = 140; // px
    const EXIT_AT  = 40;  // px

    function update() {
      ticking = false;
      const y = window.scrollY || document.documentElement.scrollTop || 0;

      if (!isScrolled && y > ENTER_AT) {
        body.classList.add('scrolled');
        isScrolled = true;
      } else if (isScrolled && y < EXIT_AT) {
        body.classList.remove('scrolled');
        isScrolled = false;
      }
    }

    function onScroll() {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', update);

    // Estado inicial (por si recargas a media página)
    update();
  })();
</script>

</body>
</html>
