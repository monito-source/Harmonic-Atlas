<?php
/**
 * Render compartido entre shortcodes legacy y bloques Gutenberg.
 *
 * @package WP_Song_Study_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtiene el payload JS necesario para la interfaz pública.
 * Reutiliza la función heredada si ya existe.
 *
 * @return array
 */
function wpssb_get_public_reader_data() {
    if ( function_exists( 'wpss_get_public_localized_data' ) ) {
        return wpss_get_public_localized_data();
    }

    return [
        'restUrl'       => esc_url_raw( rest_url( 'wpss/v1/' ) ),
        'publicRestUrl' => esc_url_raw( rest_url( 'wpss/v1/' ) ),
        'wpRestNonce'   => wp_create_nonce( 'wp_rest' ),
        'wpssNonce'     => wp_create_nonce( 'wpss' ),
        'canManage'     => current_user_can( defined( 'WPSS_CAP_MANAGE' ) ? WPSS_CAP_MANAGE : 'edit_posts' ),
        'isAdmin'       => current_user_can( 'manage_options' ),
        'isPublicReader' => true,
        'currentUserId' => get_current_user_id(),
        'tonicas'       => [ 'C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B' ],
        'camposArmonicos' => [],
        'camposArmonicosNombres' => [],
        'chordsLibrary' => [],
        'chordsConfig'  => [ 'paradigms' => [], 'qualities' => [] ],
        'strings'       => [
            'filtersTitle' => __( 'Canciones disponibles', 'wp-song-study-blocks' ),
        ],
    ];
}

/**
 * Registra y encola assets del frontend React reutilizado.
 */
function wpssb_enqueue_interface_assets() {
    $style_handle = 'wpssb-public-reader-style';
    $script_handle = 'wpssb-public-reader-script';

    wp_register_style(
        $style_handle,
        WPSSB_URL . 'assets/cancion-dashboard.css',
        [],
        WPSSB_VERSION
    );

    wp_enqueue_style( $style_handle );

    wp_register_style(
        'wpssb-public-reader-vite-style',
        WPSSB_URL . 'assets/admin-build/assets/index-DyABIkx9.css',
        [ $style_handle ],
        WPSSB_VERSION
    );
    wp_enqueue_style( 'wpssb-public-reader-vite-style' );

    wp_register_script(
        $script_handle,
        WPSSB_URL . 'assets/admin-build/assets/index-BgR-wIHi.js',
        [],
        WPSSB_VERSION,
        true
    );

    wp_enqueue_script( $script_handle );
    wp_script_add_data( $script_handle, 'type', 'module' );
    wp_localize_script( $script_handle, 'WPSS', wpssb_get_public_reader_data() );

    return $script_handle;
}

/**
 * Render SSR del bloque principal con punto de montaje React.
 *
 * @param array  $attributes Atributos del bloque.
 * @param string $content    Contenido interno.
 * @return string
 */
function wpssb_render_interface_markup( $attributes = [], $content = '' ) {
    wpssb_enqueue_interface_assets();

    $class_name = 'wpssb-interface';
    if ( ! empty( $attributes['className'] ) ) {
        $extra_classes = preg_split( '/\s+/', sanitize_text_field( $attributes['className'] ) );
        if ( is_array( $extra_classes ) ) {
            foreach ( $extra_classes as $extra_class ) {
                $extra_class = sanitize_html_class( $extra_class );
                if ( '' !== $extra_class ) {
                    $class_name .= ' ' . $extra_class;
                }
            }
        }
    }

    return sprintf(
        '<div class="%1$s"><div id="wpss-cancion-app" class="wpss-cancion-app wpss-public-reader" data-view="public"></div></div>',
        esc_attr( $class_name )
    );
}

/**
 * Query compartida para listados de canciones.
 *
 * @param array $attributes Atributos del bloque/listado.
 * @return WP_Query
 */
function wpssb_get_song_list_query( $attributes = [] ) {
    $posts_per_page = isset( $attributes['postsToShow'] ) ? intval( $attributes['postsToShow'] ) : -1;
    if ( 0 === $posts_per_page ) {
        $posts_per_page = 10;
    }
    if ( $posts_per_page < -1 ) {
        $posts_per_page = -1;
    }
    $order = ! empty( $attributes['order'] ) && in_array( strtoupper( $attributes['order'] ), [ 'ASC', 'DESC' ], true )
        ? strtoupper( $attributes['order'] )
        : 'ASC';
    $orderby = ! empty( $attributes['orderBy'] ) ? sanitize_key( $attributes['orderBy'] ) : 'title';

    $query_args = [
        'post_type'      => 'cancion',
        'posts_per_page' => $posts_per_page,
        'orderby'        => $orderby,
        'order'          => $order,
    ];

    if ( ! empty( $attributes['tonalidad'] ) ) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => 'tonalidad',
                'field'    => 'slug',
                'terms'    => sanitize_title( $attributes['tonalidad'] ),
            ],
        ];
    }

    if ( ! empty( $attributes['coleccion'] ) ) {
        $query_args['tax_query'][] = [
            'taxonomy' => 'coleccion',
            'field'    => 'slug',
            'terms'    => sanitize_title( $attributes['coleccion'] ),
        ];
    }

    return new WP_Query( $query_args );
}

/**
 * Render SSR del bloque Song List.
 *
 * @param array $attributes Atributos del bloque.
 * @return string
 */
function wpssb_render_song_list_markup( $attributes = [] ) {
    $query = wpssb_get_song_list_query( $attributes );

    if ( ! $query->have_posts() ) {
        wp_reset_postdata();
        return '<div class="wpssb-song-list is-empty"><p>' . esc_html__( 'No hay canciones disponibles.', 'wp-song-study-blocks' ) . '</p></div>';
    }

    $show_key = ! empty( $attributes['showKey'] );
    $show_collection = ! empty( $attributes['showCollection'] );

    $html = '<div class="wpssb-song-list"><ul class="wpss-songs-by-key">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id = get_the_ID();
        $html   .= '<li class="wpssb-song-list__item">';
        $html   .= sprintf(
            '<a class="wpssb-song-list__link" href="%1$s">%2$s</a>',
            esc_url( get_permalink( $post_id ) ),
            esc_html( get_the_title( $post_id ) )
        );

        if ( $show_key ) {
            $keys = wp_get_post_terms( $post_id, 'tonalidad', [ 'fields' => 'names' ] );
            if ( ! is_wp_error( $keys ) && ! empty( $keys ) ) {
                $html .= '<div class="wpssb-song-list__meta wpssb-song-list__meta--key">' . esc_html( implode( ', ', $keys ) ) . '</div>';
            }
        }

        if ( $show_collection ) {
            $collections = wp_get_post_terms( $post_id, 'coleccion', [ 'fields' => 'names' ] );
            if ( ! is_wp_error( $collections ) && ! empty( $collections ) ) {
                $html .= '<div class="wpssb-song-list__meta wpssb-song-list__meta--collection">' . esc_html( implode( ', ', $collections ) ) . '</div>';
            }
        }

        $html .= '</li>';
    }

    wp_reset_postdata();

    $html .= '</ul></div>';

    return $html;
}
