<?php
/**
 * Registro de Custom Post Type Canción y sus metadatos.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sanitiza cadenas de JSON almacenadas como texto.
 *
 * @param string $value Valor enviado.
 * @return string
 */
function wpss_sanitize_json_string( $value ) {
    if ( $value instanceof Traversable ) {
        $value = iterator_to_array( $value );
    } elseif ( is_object( $value ) ) {
        $value = get_object_vars( $value );
    }

    if ( is_array( $value ) ) {
        if ( function_exists( 'wpss_safe_json_encode' ) ) {
            $encoded = wpss_safe_json_encode( $value );
        } else {
            $encoded = wp_json_encode( $value );
        }

        return '' === $encoded ? '' : wp_slash( $encoded );
    }

    if ( '' === $value ) {
        return '';
    }

    $raw = (string) $value;
    $clean = wp_check_invalid_utf8( $raw, true );
    json_decode( $clean );
    if ( JSON_ERROR_NONE === json_last_error() ) {
        return wp_slash( $clean );
    }

    $unslashed = wp_unslash( $raw );
    $clean_unslashed = wp_check_invalid_utf8( $unslashed, true );
    json_decode( $clean_unslashed );
    if ( JSON_ERROR_NONE === json_last_error() ) {
        return wp_slash( $clean_unslashed );
    }

    $sample = substr( $clean_unslashed, 0, 200 );
    error_log(
        sprintf(
            'wpss: invalid json meta: %s len=%d sample=%s',
            json_last_error_msg(),
            strlen( $clean_unslashed ),
            $sample
        )
    );

    return '';
}

/**
 * Registra el CPT de Canción junto con sus metacampos.
 */
function wpss_register_cpt_cancion() {
    $labels = [
        'name'               => _x( 'Canciones', 'post type general name', 'wp-song-study' ),
        'singular_name'      => _x( 'Canción', 'post type singular name', 'wp-song-study' ),
        'menu_name'          => _x( 'Canciones', 'admin menu', 'wp-song-study' ),
        'name_admin_bar'     => _x( 'Canción', 'add new on admin bar', 'wp-song-study' ),
        'add_new'            => _x( 'Añadir nueva', 'cancion', 'wp-song-study' ),
        'add_new_item'       => __( 'Añadir nueva canción', 'wp-song-study' ),
        'new_item'           => __( 'Nueva canción', 'wp-song-study' ),
        'edit_item'          => __( 'Editar canción', 'wp-song-study' ),
        'view_item'          => __( 'Ver canción', 'wp-song-study' ),
        'all_items'          => __( 'Todas las canciones', 'wp-song-study' ),
        'search_items'       => __( 'Buscar canciones', 'wp-song-study' ),
        'parent_item_colon'  => __( 'Canción padre:', 'wp-song-study' ),
        'not_found'          => __( 'No se encontraron canciones.', 'wp-song-study' ),
        'not_found_in_trash' => __( 'No hay canciones en la papelera.', 'wp-song-study' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'show_in_rest'       => true,
        'supports'           => [ 'title' ],
        'has_archive'        => false,
        'menu_icon'          => 'dashicons-album',
        'taxonomies'         => [ 'tonalidad', 'cancion_tag' ],
        'rewrite'            => [ 'slug' => 'cancion' ],
    ];

    register_post_type( 'cancion', $args );

    $capability_cb = static function() {
        return function_exists( 'wpss_user_can_manage_songbook' )
            ? wpss_user_can_manage_songbook()
            : current_user_can( defined( 'WPSS_CAP_MANAGE' ) ? WPSS_CAP_MANAGE : 'edit_posts' );
    };

    $meta_single_text = [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'auth_callback'     => $capability_cb,
        'sanitize_callback' => 'wp_kses_post',
    ];

    register_post_meta(
        'cancion',
        '_tonica',
        [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'string',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );

    register_post_meta(
        'cancion',
        '_campo_armonico',
        [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'string',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );

    register_post_meta( 'cancion', '_campo_armonico_predominante', $meta_single_text );
    register_post_meta( 'cancion', '_notas_generales', $meta_single_text );

    $meta_json = [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'string',
        'auth_callback'     => $capability_cb,
        'sanitize_callback' => 'wpss_sanitize_json_string',
    ];

    register_post_meta( 'cancion', '_prestamos_tonales_json', $meta_json );
    register_post_meta( 'cancion', '_modulaciones_json', $meta_json );
    register_post_meta( 'cancion', '_repertorio_asignaciones_json', $meta_json );

    register_post_meta(
        'cancion',
        '_reversion_origen_id',
        [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'integer',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]
    );
    register_post_meta(
        'cancion',
        '_reversion_raiz_id',
        [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'integer',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]
    );
    register_post_meta(
        'cancion',
        '_reversion_autor_origen_id',
        [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'integer',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]
    );
    register_post_meta( 'cancion', '_reversion_origen_titulo', $meta_single_text );
    register_post_meta( 'cancion', '_reversion_raiz_titulo', $meta_single_text );
    register_post_meta( 'cancion', '_reversion_autor_origen_nombre', $meta_single_text );
    register_post_meta( 'cancion', '_estado_transcripcion', $meta_single_text );

    $meta_bool = [
        'show_in_rest'      => true,
        'single'            => true,
        'type'              => 'boolean',
        'auth_callback'     => $capability_cb,
        'sanitize_callback' => 'wpss_sanitize_bool_meta',
        'default'           => false,
    ];

    register_post_meta( 'cancion', '_tiene_prestamos', $meta_bool );
    register_post_meta( 'cancion', '_tiene_modulaciones', $meta_bool );

    register_post_meta(
        'cancion',
        '_conteo_versos',
        [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'integer',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]
    );

    register_post_meta(
        'cancion',
        '_wpss_visibility_mode',
        [
            'show_in_rest'      => true,
            'single'            => true,
            'type'              => 'string',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'sanitize_key',
            'default'           => 'private',
        ]
    );

    register_post_meta(
        'cancion',
        '_wpss_visibility_project_ids',
        [
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
            'single'            => true,
            'type'              => 'array',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'wpssb_sanitize_id_list',
            'default'           => [],
        ]
    );

    register_post_meta(
        'cancion',
        '_wpss_visibility_group_ids',
        [
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
            'single'            => true,
            'type'              => 'array',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => function_exists( 'wpss_sanitize_media_group_ids' ) ? 'wpss_sanitize_media_group_ids' : 'wpssb_sanitize_id_list',
            'default'           => [],
        ]
    );

    register_post_meta(
        'cancion',
        '_wpss_visibility_user_ids',
        [
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
            'single'            => true,
            'type'              => 'array',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => function_exists( 'wpss_sanitize_media_explicit_user_ids' ) ? 'wpss_sanitize_media_explicit_user_ids' : 'wpssb_sanitize_id_list',
            'default'           => [],
        ]
    );

    register_post_meta(
        'cancion',
        '_wpss_rehearsal_project_ids',
        [
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
            'single'            => true,
            'type'              => 'array',
            'auth_callback'     => $capability_cb,
            'sanitize_callback' => 'wpssb_sanitize_id_list',
            'default'           => [],
        ]
    );
}

/**
 * Sanitiza metacampos booleanos asegurando un entero 0/1.
 *
 * @param mixed $value Valor recibido.
 * @return int
 */
function wpss_sanitize_bool_meta( $value ) {
    return (int) (bool) $value;
}
