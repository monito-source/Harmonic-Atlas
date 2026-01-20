<?php get_header(); ?>

<main class="site-main">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article class="entry">
                <?php the_content(); ?>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p>No hay contenido aún.</p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
