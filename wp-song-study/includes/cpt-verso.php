<?php
/**
 * Registro de Custom Post Type Verso y sus metacampos asociados.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra el CPT de Verso junto con metacampos necesarios.
 */
function wpss_register_cpt_verso() {
    $labels = [
        'name'               => _x( 'Versos', 'post type general name', 'wp-song-study' ),
        'singular_name'      => _x( 'Verso', 'post type singular name', 'wp-song-study' ),
        'menu_name'          => _x( 'Versos', 'admin menu', 'wp-song-study' ),
        'name_admin_bar'     => _x( 'Verso', 'add new on admin bar', 'wp-song-study' ),
        'add_new'            => _x( 'Añadir nuevo', 'verso', 'wp-song-study' ),
        'add_new_item'       => __( 'Añadir nuevo verso', 'wp-song-study' ),
        'new_item'           => __( 'Nuevo verso', 'wp-song-study' ),
        'edit_item'          => __( 'Editar verso', 'wp-song-study' ),
        'view_item'          => __( 'Ver verso', 'wp-song-study' ),
        'all_items'          => __( 'Todos los versos', 'wp-song-study' ),
        'search_items'       => __( 'Buscar versos', 'wp-song-study' ),
        'parent_item_colon'  => __( 'Verso padre:', 'wp-song-study' ),
        'not_found'          => __( 'No se encontraron versos.', 'wp-song-study' ),
        'not_found_in_trash' => __( 'No hay versos en la papelera.', 'wp-song-study' ),
    ];

    $args = [
        'labels'        => $labels,
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => 'edit.php?post_type=cancion',
        'show_in_rest'  => true,
        'supports'      => [ 'editor' ],
        'map_meta_cap'  => true,
    ];

    register_post_type( 'verso', $args );

    $capability_cb = static function() {
        return current_user_can( 'edit_posts' );
    };

    register_post_meta(
        'verso',
        '_cancion_id',
        [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'absint',
        ]
    );

    register_post_meta(
        'verso',
        '_orden',
        [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]
    );

    $meta_text = [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $capability_cb,
        'sanitize_callback' => 'wp_kses_post',
    ];

    register_post_meta( 'verso', '_acorde_absoluto', $meta_text );
    register_post_meta( 'verso', '_funcion_relativa', $meta_text );
    register_post_meta( 'verso', '_notas_verso', $meta_text );
}
