<?php get_header(); ?>

<main class="site-main">
    <article class="entry">
        <h2><?php esc_html_e( 'Página no encontrada', 'laboratorio-digital' ); ?></h2>
        <p><?php esc_html_e( 'No encontramos lo que buscabas. Puedes intentar con otra búsqueda.', 'laboratorio-digital' ); ?></p>
        <?php get_search_form(); ?>
    </article>
</main>

<?php get_footer(); ?>
