<?php get_header(); ?>

<main>
  <?php
  if ( have_posts() ) :
      while ( have_posts() ) : the_post(); ?>
          <article class="entry">
              <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
              <div><?php the_excerpt(); ?></div>
          </article>
      <?php endwhile;
  else :
      echo '<p>' . esc_html__( 'No hay contenido aún.', 'laboratorio-digital' ) . '</p>';
  endif;
  ?>
</main>

<?php get_footer(); ?>
