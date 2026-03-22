<?php
/**
 * Registro de la taxonomía Etiquetas de canción.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra la taxonomía de etiquetas para canciones.
 */
function wpss_register_cancion_tag_tax() {
    $labels = [
        'name'                       => _x( 'Etiquetas', 'taxonomy general name', 'wp-song-study' ),
        'singular_name'              => _x( 'Etiqueta', 'taxonomy singular name', 'wp-song-study' ),
        'search_items'               => __( 'Buscar etiquetas', 'wp-song-study' ),
        'popular_items'              => __( 'Etiquetas populares', 'wp-song-study' ),
        'all_items'                  => __( 'Todas las etiquetas', 'wp-song-study' ),
        'edit_item'                  => __( 'Editar etiqueta', 'wp-song-study' ),
        'view_item'                  => __( 'Ver etiqueta', 'wp-song-study' ),
        'update_item'                => __( 'Actualizar etiqueta', 'wp-song-study' ),
        'add_new_item'               => __( 'Añadir nueva etiqueta', 'wp-song-study' ),
        'new_item_name'              => __( 'Nombre de la nueva etiqueta', 'wp-song-study' ),
        'separate_items_with_commas' => __( 'Separa etiquetas con comas', 'wp-song-study' ),
        'add_or_remove_items'        => __( 'Añadir o quitar etiquetas', 'wp-song-study' ),
        'choose_from_most_used'      => __( 'Elegir entre las más usadas', 'wp-song-study' ),
        'menu_name'                  => __( 'Etiquetas', 'wp-song-study' ),
    ];

    register_taxonomy(
        'cancion_tag',
        [ 'cancion' ],
        [
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_in_rest'          => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'public'                => true,
            'rewrite'               => false,
        ]
    );
}
