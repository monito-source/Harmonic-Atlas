<?php
/**
 * Title: Proyecto editorial
 * Slug: pertenencia-digital/proyecto-editorial
 * Categories: pertenencia-digital, text
 * Inserter: true
 * Description: Base editorial abierta para proyectos con narrativa, colaboradores, galería y contacto.
 */
?>
<!-- wp:group {"className":"pd-presskit__editorial","layout":{"type":"constrained"}} -->
<div class="wp-block-group pd-presskit__editorial">
  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Narrativa del proyecto</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>Usa esta apertura para contar el contexto, la intención, la etapa actual del proyecto y el tipo de experiencia que propone.</p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph -->
    <p>A partir de aquí puedes insertar textos, embeds, columnas, imágenes, citas o cualquier otro bloque que te ayude a construir un presskit más libre.</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Integrantes y colaboradores</h2>
    <!-- /wp:heading -->

    <!-- wp:wp-song-study/project-collaborators /-->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Material visual</h2>
    <!-- /wp:heading -->

    <!-- wp:gallery {"linkTo":"none"} -->
    <figure class="wp-block-gallery has-nested-images columns-default is-cropped"></figure>
    <!-- /wp:gallery -->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"pd-presskit__editorial-section","layout":{"type":"constrained"}} -->
  <div class="wp-block-group pd-presskit__editorial-section">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Booking y contacto</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>Añade aquí tu correo, agencia, management, formulario o botones de contacto usando bloques nativos.</p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons -->
    <div class="wp-block-buttons">
      <!-- wp:button {"className":"is-style-outline"} -->
      <div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">Contactar</a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
