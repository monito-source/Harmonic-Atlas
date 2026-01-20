<?php get_header(); ?>

<main class="site-main">
    <?php if ( have_posts() ) : ?>
        <?php if ( is_archive() ) : ?>
            <header class="entry">
                <h2><?php the_archive_title(); ?></h2>
                <?php the_archive_description( '<div>', '</div>' ); ?>
            </header>
        <?php endif; ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article class="entry">
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <div><?php the_excerpt(); ?></div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p><?php esc_html_e( 'No hay contenido aún.', 'laboratorio-digital' ); ?></p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
