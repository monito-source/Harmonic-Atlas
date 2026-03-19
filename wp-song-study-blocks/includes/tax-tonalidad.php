<?php
/**
 * Registro de la taxonomía Tonalidad.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra la taxonomía de tonalidades.
 */
function wpss_register_tonalidad_tax() {
    $labels = [
        'name'              => _x( 'Tonalidades', 'taxonomy general name', 'wp-song-study' ),
        'singular_name'     => _x( 'Tonalidad', 'taxonomy singular name', 'wp-song-study' ),
        'search_items'      => __( 'Buscar tonalidades', 'wp-song-study' ),
        'all_items'         => __( 'Todas las tonalidades', 'wp-song-study' ),
        'parent_item'       => __( 'Tonalidad padre', 'wp-song-study' ),
        'parent_item_colon' => __( 'Tonalidad padre:', 'wp-song-study' ),
        'edit_item'         => __( 'Editar tonalidad', 'wp-song-study' ),
        'update_item'       => __( 'Actualizar tonalidad', 'wp-song-study' ),
        'add_new_item'      => __( 'Añadir nueva tonalidad', 'wp-song-study' ),
        'new_item_name'     => __( 'Nombre de nueva tonalidad', 'wp-song-study' ),
        'menu_name'         => __( 'Tonalidades', 'wp-song-study' ),
    ];

    $args = [
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'update_count_callback' => '_update_post_term_count',
        'query_var'         => true,
        'public'            => true,
        'show_in_rest'      => true,
        'rewrite'           => [ 'slug' => 'tonalidad' ],
    ];

    register_taxonomy( 'tonalidad', [ 'cancion' ], $args );
}
