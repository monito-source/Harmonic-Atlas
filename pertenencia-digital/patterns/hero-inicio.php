<?php
/**
 * Title: Hero de inicio
 * Slug: pertenencia-digital/hero-inicio
 * Categories: pertenencia-digital, featured
 * Inserter: true
 * Description: Hero editorial para portada o páginas de presentación.
 */
?>
<!-- wp:group {"className":"pd-home","layout":{"type":"constrained"}} -->
<div class="wp-block-group pd-home">
  <!-- wp:group {"align":"wide","className":"pd-home__hero","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center","justifyContent":"space-between"}} -->
  <div class="wp-block-group alignwide pd-home__hero">
    <!-- wp:group {"className":"pd-home__about","layout":{"type":"constrained"}} -->
    <div class="wp-block-group pd-home__about">
      <!-- wp:paragraph {"textColor":"azul-principal","fontSize":"x-small","className":"pd-eyebrow"} -->
      <p class="pd-eyebrow has-azul-principal-color has-text-color has-x-small-font-size">Acerca de nosotros</p>
      <!-- /wp:paragraph -->

      <!-- wp:heading {"level":1,"fontSize":"x-large"} -->
      <h1 class="wp-block-heading has-x-large-font-size">Pertenencia Digital</h1>
      <!-- /wp:heading -->

      <!-- wp:paragraph -->
      <p>Somos un espacio que conecta creatividad, aprendizaje y cultura digital. Compartimos contenidos para explorar la música, la tecnología y la web desde una mirada accesible, experimental y colaborativa.</p>
      <!-- /wp:paragraph -->

      <!-- wp:paragraph -->
      <p>Usa este bloque como arranque para una portada, una landing o una presentación institucional del proyecto.</p>
      <!-- /wp:paragraph -->

      <!-- wp:html -->
      <div class="pd-home__illustration" aria-hidden="true">
        <svg viewBox="0 0 640 420" role="img" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="pdPatternHeroGradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="var(--wp--preset--color--azul-principal)"/>
              <stop offset="100%" stop-color="var(--wp--preset--color--resalte-header)"/>
            </linearGradient>
          </defs>
          <rect width="640" height="420" rx="28" fill="url(#pdPatternHeroGradient)"/>
          <circle cx="142" cy="120" r="38" fill="var(--wp--preset--color--blanco)" fill-opacity="0.18"/>
          <circle cx="498" cy="94" r="22" fill="var(--wp--preset--color--blanco)" fill-opacity="0.2"/>
          <path d="M168 290c22-86 92-140 184-140 70 0 133 34 166 88" stroke="var(--wp--preset--color--blanco)" stroke-width="16" stroke-linecap="round" fill="none" fill-opacity="0.8"/>
          <rect x="120" y="250" width="400" height="18" rx="9" fill="var(--wp--preset--color--blanco)" fill-opacity="0.85"/>
          <rect x="120" y="288" width="280" height="18" rx="9" fill="var(--wp--preset--color--fondo)" fill-opacity="0.95"/>
          <rect x="120" y="326" width="340" height="18" rx="9" fill="var(--wp--preset--color--resalte-header)" fill-opacity="0.95"/>
          <path d="M430 148v92.5a34 34 0 1 1-18-30.1V174l92-24v58.5a34 34 0 1 1-18-30.1V124l-56 14.5Z" fill="var(--wp--preset--color--blanco)"/>
        </svg>
      </div>
      <!-- /wp:html -->
    </div>
    <!-- /wp:group -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
