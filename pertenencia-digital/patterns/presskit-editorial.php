<?php
/**
 * Title: Press kit editorial
 * Slug: pertenencia-digital/presskit-editorial
 * Categories: pertenencia-digital, text
 * Inserter: true
 * Description: Estructura editorial para biografía, música, videos, prensa y descargas dentro de una página de press kit.
 */
?>
<!-- wp:group {"className":"pd-presskit__editorial","layout":{"type":"constrained"}} -->
<div class="wp-block-group pd-presskit__editorial">
  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Biografía</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>Cuenta aquí tu historia, contexto artístico, referencias y enfoque creativo. Esta sección puede ser breve o extensa según el material que quieras compartir con prensa, promotores o colaboradores.</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Música</h2>
    <!-- /wp:heading -->

    <!-- wp:embed {"url":"https://open.spotify.com","type":"rich","className":"pd-presskit__embed"} -->
    <figure class="wp-block-embed is-type-rich pd-presskit__embed"><div class="wp-block-embed__wrapper">
https://open.spotify.com
</div></figure>
    <!-- /wp:embed -->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Videos</h2>
    <!-- /wp:heading -->

    <!-- wp:embed {"url":"https://youtube.com","type":"video","className":"pd-presskit__embed"} -->
    <figure class="wp-block-embed is-type-video pd-presskit__embed"><div class="wp-block-embed__wrapper">
https://youtube.com
</div></figure>
    <!-- /wp:embed -->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Prensa y logros</h2>
    <!-- /wp:heading -->

    <!-- wp:list -->
    <ul class="wp-block-list">
      <!-- wp:list-item -->
      <li>Medio o revista con una cita destacada sobre tu proyecto.</li>
      <!-- /wp:list-item -->

      <!-- wp:list-item -->
      <li>Festival, foro o circuito donde hayas participado.</li>
      <!-- /wp:list-item -->

      <!-- wp:list-item -->
      <li>Premio, beca, residencia o reconocimiento relevante.</li>
      <!-- /wp:list-item -->
    </ul>
    <!-- /wp:list -->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Descargas</h2>
    <!-- /wp:heading -->

    <!-- wp:file {"className":"pd-presskit__download"} -->
    <div class="wp-block-file pd-presskit__download"><a href="#">Descargar press kit en PDF</a></div>
    <!-- /wp:file -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
