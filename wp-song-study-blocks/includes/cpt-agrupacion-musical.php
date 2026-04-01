<?php
/**
 * Registro del CPT Agrupación Musical.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra el CPT interno para administrar agrupaciones musicales.
 *
 * @return void
 */
function wpss_register_cpt_agrupacion_musical() {
    $labels = [
        'name'               => _x( 'Agrupaciones musicales', 'post type general name', 'wp-song-study' ),
        'singular_name'      => _x( 'Agrupación musical', 'post type singular name', 'wp-song-study' ),
        'menu_name'          => _x( 'Agrupaciones', 'admin menu', 'wp-song-study' ),
        'name_admin_bar'     => _x( 'Agrupación musical', 'add new on admin bar', 'wp-song-study' ),
        'add_new'            => _x( 'Añadir nueva', 'agrupacion-musical', 'wp-song-study' ),
        'add_new_item'       => __( 'Añadir nueva agrupación musical', 'wp-song-study' ),
        'new_item'           => __( 'Nueva agrupación musical', 'wp-song-study' ),
        'edit_item'          => __( 'Editar agrupación musical', 'wp-song-study' ),
        'view_item'          => __( 'Ver agrupación musical', 'wp-song-study' ),
        'all_items'          => __( 'Todas las agrupaciones musicales', 'wp-song-study' ),
        'search_items'       => __( 'Buscar agrupaciones musicales', 'wp-song-study' ),
        'not_found'          => __( 'No se encontraron agrupaciones musicales.', 'wp-song-study' ),
        'not_found_in_trash' => __( 'No hay agrupaciones musicales en la papelera.', 'wp-song-study' ),
    ];

    register_post_type(
        'agrupacion_musical',
        [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'supports'            => [ 'title' ],
            'has_archive'         => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'rewrite'             => false,
        ]
    );
}
