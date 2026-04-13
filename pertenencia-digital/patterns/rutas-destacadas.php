<?php
/**
 * Title: Rutas destacadas
 * Slug: pertenencia-digital/rutas-destacadas
 * Categories: pertenencia-digital, buttons
 * Inserter: true
 * Description: Panel de llamados a acción para las rutas principales del sitio.
 */
?>
<!-- wp:group {"className":"pd-home__cta-panel","layout":{"type":"constrained"}} -->
<div class="wp-block-group pd-home__cta-panel">
  <!-- wp:paragraph {"textColor":"azul-principal","fontSize":"x-small","className":"pd-eyebrow"} -->
  <p class="pd-eyebrow has-azul-principal-color has-text-color has-x-small-font-size">Explora</p>
  <!-- /wp:paragraph -->

  <!-- wp:heading {"level":2,"fontSize":"large"} -->
  <h2 class="wp-block-heading has-large-font-size">Explora nuestros contenidos</h2>
  <!-- /wp:heading -->

  <!-- wp:paragraph {"textColor":"oscuro"} -->
  <p class="has-oscuro-color has-text-color">Elige una de las dos secciones destacadas para comenzar tu recorrido.</p>
  <!-- /wp:paragraph -->

  <!-- wp:buttons {"layout":{"type":"flex","orientation":"vertical"}} -->
  <div class="wp-block-buttons">
    <!-- wp:button {"className":"pd-home__cta pd-home__cta--primary"} -->
    <div class="wp-block-button pd-home__cta pd-home__cta--primary"><a class="wp-block-button__link wp-element-button" href="/musica">Ir a Música</a></div>
    <!-- /wp:button -->

    <!-- wp:button {"className":"is-style-outline pd-home__cta pd-home__cta--secondary","textColor":"azul-principal"} -->
    <div class="wp-block-button is-style-outline pd-home__cta pd-home__cta--secondary"><a class="wp-block-button__link has-azul-principal-color has-text-color wp-element-button" href="/tecnologias-web">Ir a Tecnologías y web</a></div>
    <!-- /wp:button -->
  </div>
  <!-- /wp:buttons -->
</div>
<!-- /wp:group -->
