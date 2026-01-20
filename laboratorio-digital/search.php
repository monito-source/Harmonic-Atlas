<?php get_header(); ?>

<main class="site-main">
    <header class="entry">
        <h2><?php printf( esc_html__( 'Resultados para: %s', 'laboratorio-digital' ), get_search_query() ); ?></h2>
    </header>

    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article class="entry">
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <div><?php the_excerpt(); ?></div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p><?php esc_html_e( 'No se encontraron resultados.', 'laboratorio-digital' ); ?></p>
        <?php get_search_form(); ?>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
