<?php get_header(); ?>

<main>
    <h1><?php the_title(); ?></h1>
    <div><?php the_content(); ?></div>

    <section>
        <h2>Sesiones del curso</h2>
        <ul>
        <?php
        $sesiones = get_posts([
            'post_type' => 'mlc_sesion',
            'post_parent' => get_the_ID(),
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_mlc_numero_sesion',
            'order' => 'ASC',
        ]);

        foreach ($sesiones as $sesion) {
            echo "<li><strong>{$sesion->post_title}</strong><br>";
            echo "Objetivo: " . get_post_meta($sesion->ID, '_mlc_objetivo_sesion', true) . "<br>";
            echo "Duración: " . get_post_meta($sesion->ID, '_mlc_duracion_horas', true) . "h ";
            echo get_post_meta($sesion->ID, '_mlc_duracion_minutos', true) . "min</li>";
        }
        ?>
        </ul>
    </section>
</main>

<?php get_footer(); ?>
