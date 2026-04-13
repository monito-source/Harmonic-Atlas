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
 * Valores por defecto para la apariencia del lector blocks.
 *
 * @return array
 */
function wpssb_get_interface_style_defaults() {
    return [
        'panelBackgroundColor'     => '#ffffff',
        'panelBackgroundOpacity'   => 80,
        'textColor'                => '#4b5563',
        'headingColor'             => '#1f2937',
        'buttonColor'              => '#1e3a8a',
        'buttonTextColor'          => '#ffffff',
        'buttonEmphasisColor'      => '#e7ecf6',
        'buttonEmphasisTextColor'  => '#1e3a8a',
        'buttonDangerColor'        => '#7f1d1d',
        'buttonDangerTextColor'    => '#ffffff',
    ];
}

/**
 * Convierte un color hexadecimal a rgba.
 *
 * @param string $hex_color Color hexadecimal.
 * @param float  $opacity   Opacidad entre 0 y 1.
 * @return string
 */
function wpssb_hex_to_rgba( $hex_color, $opacity ) {
    $hex_color = sanitize_hex_color( (string) $hex_color );
    if ( empty( $hex_color ) ) {
        return '';
    }

    $hex = ltrim( $hex_color, '#' );
    if ( 3 === strlen( $hex ) ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if ( 6 !== strlen( $hex ) ) {
        return '';
    }

    $opacity = max( 0, min( 1, (float) $opacity ) );

    $red   = hexdec( substr( $hex, 0, 2 ) );
    $green = hexdec( substr( $hex, 2, 2 ) );
    $blue  = hexdec( substr( $hex, 4, 2 ) );

    return sprintf( 'rgba(%1$d, %2$d, %3$d, %4$s)', $red, $green, $blue, rtrim( rtrim( number_format( $opacity, 3, '.', '' ), '0' ), '.' ) );
}

/**
 * Construye el atributo style del contenedor del bloque.
 *
 * @param array $attributes Atributos del bloque.
 * @return string
 */
function wpssb_get_interface_style_attribute( $attributes = [] ) {
    $defaults = wpssb_get_interface_style_defaults();

    $panel_background_color = sanitize_hex_color( isset( $attributes['panelBackgroundColor'] ) ? (string) $attributes['panelBackgroundColor'] : '' );
    if ( empty( $panel_background_color ) ) {
        $panel_background_color = $defaults['panelBackgroundColor'];
    }

    $text_color = sanitize_hex_color( isset( $attributes['textColor'] ) ? (string) $attributes['textColor'] : '' );
    if ( empty( $text_color ) ) {
        $text_color = $defaults['textColor'];
    }

    $heading_color = sanitize_hex_color( isset( $attributes['headingColor'] ) ? (string) $attributes['headingColor'] : '' );
    if ( empty( $heading_color ) ) {
        $heading_color = $defaults['headingColor'];
    }

    $button_color = sanitize_hex_color( isset( $attributes['buttonColor'] ) ? (string) $attributes['buttonColor'] : '' );
    if ( empty( $button_color ) ) {
        $button_color = $defaults['buttonColor'];
    }

    $button_text_color = sanitize_hex_color( isset( $attributes['buttonTextColor'] ) ? (string) $attributes['buttonTextColor'] : '' );
    if ( empty( $button_text_color ) ) {
        $button_text_color = $defaults['buttonTextColor'];
    }

    $button_emphasis_color = sanitize_hex_color( isset( $attributes['buttonEmphasisColor'] ) ? (string) $attributes['buttonEmphasisColor'] : '' );
    if ( empty( $button_emphasis_color ) ) {
        $button_emphasis_color = $defaults['buttonEmphasisColor'];
    }

    $button_emphasis_text_color = sanitize_hex_color( isset( $attributes['buttonEmphasisTextColor'] ) ? (string) $attributes['buttonEmphasisTextColor'] : '' );
    if ( empty( $button_emphasis_text_color ) ) {
        $button_emphasis_text_color = $defaults['buttonEmphasisTextColor'];
    }

    $button_danger_color = sanitize_hex_color( isset( $attributes['buttonDangerColor'] ) ? (string) $attributes['buttonDangerColor'] : '' );
    if ( empty( $button_danger_color ) ) {
        $button_danger_color = $defaults['buttonDangerColor'];
    }

    $button_danger_text_color = sanitize_hex_color( isset( $attributes['buttonDangerTextColor'] ) ? (string) $attributes['buttonDangerTextColor'] : '' );
    if ( empty( $button_danger_text_color ) ) {
        $button_danger_text_color = $defaults['buttonDangerTextColor'];
    }

    $panel_background_opacity = isset( $attributes['panelBackgroundOpacity'] ) ? intval( $attributes['panelBackgroundOpacity'] ) : $defaults['panelBackgroundOpacity'];
    $panel_background_opacity = max( 0, min( 100, $panel_background_opacity ) );

    $style_rules = [
        '--wpssb-panel-bg'                => wpssb_hex_to_rgba( $panel_background_color, $panel_background_opacity / 100 ),
        '--wpssb-text-color'              => $text_color,
        '--wpssb-heading-color'           => $heading_color,
        '--wpssb-button-bg'               => $button_color,
        '--wpssb-button-text'             => $button_text_color,
        '--wpssb-button-emphasis-bg'      => $button_emphasis_color,
        '--wpssb-button-emphasis-text'    => $button_emphasis_text_color,
        '--wpssb-button-danger-bg'        => $button_danger_color,
        '--wpssb-button-danger-text'      => $button_danger_text_color,
    ];

    $style_fragments = [];
    foreach ( $style_rules as $property => $value ) {
        if ( '' !== $value ) {
            $style_fragments[] = $property . ': ' . $value;
        }
    }

    return implode( '; ', $style_fragments );
}

/**
 * Determina si el usuario actual puede ver el cancionero blocks.
 *
 * @return bool
 */
function wpssb_user_can_view_songbook() {
    if ( function_exists( 'wpss_user_can_view_songbook' ) ) {
        return wpss_user_can_view_songbook();
    }

    return function_exists( 'wpss_user_can_read_songbook' ) ? wpss_user_can_read_songbook() : false;
}

/**
 * Obtiene el payload JS necesario para la interfaz pública.
 * Reutiliza la función heredada si ya existe.
 *
 * @return array
 */
function wpssb_get_public_reader_data() {
    $can_manage = function_exists( 'wpss_user_can_manage_songbook' ) ? wpss_user_can_manage_songbook() : current_user_can( defined( 'WPSS_CAP_MANAGE' ) ? WPSS_CAP_MANAGE : 'edit_posts' );
    $is_admin   = current_user_can( 'manage_options' );
    $can_read   = wpssb_user_can_view_songbook();
    $campos_library = function_exists( 'wpss_get_campos_armonicos_library' ) ? array_values( wpss_get_campos_armonicos_library() ) : [];
    $campos_names = array_values(
        array_unique(
            array_filter(
                array_merge(
                    [],
                    array_reduce(
                        array_filter(
                            $campos_library,
                            static function( $campo ) {
                                return ! empty( $campo['activo'] );
                            }
                        ),
                        static function( $labels, $campo ) {
                            if ( ! is_array( $campo ) ) {
                                return $labels;
                            }
                            $item_labels = [];
                            if ( ! empty( $campo['nombre'] ) ) {
                                $item_labels[] = $campo['nombre'];
                            }
                            if ( ! empty( $campo['aliases'] ) && is_array( $campo['aliases'] ) ) {
                                foreach ( $campo['aliases'] as $alias ) {
                                    if ( ! empty( $alias ) ) {
                                        $item_labels[] = $alias;
                                    }
                                }
                            }
                            if ( empty( $item_labels ) && ! empty( $campo['slug'] ) ) {
                                $item_labels[] = $campo['slug'];
                            }
                            return array_merge( $labels, $item_labels );
                        },
                        []
                    )
                )
            )
        )
    );
    $acordes_library = function_exists( 'wpss_get_acordes_library' ) ? array_values( wpss_get_acordes_library() ) : [];
    $acordes_config = function_exists( 'wpss_get_acordes_config' ) ? wpss_get_acordes_config() : [ 'paradigms' => [], 'qualities' => [] ];

    if ( function_exists( 'wpss_get_public_localized_data' ) ) {
        $data = wpss_get_public_localized_data();
        if ( ! is_array( $data ) ) {
            $data = [];
        }

        $data['canManage']    = ! empty( $data['canManage'] ) || $can_manage;
        $data['canRead']      = $can_read;
        $data['isAdmin']      = ! empty( $data['isAdmin'] ) || $is_admin;
        $data['currentUserId'] = isset( $data['currentUserId'] ) ? (int) $data['currentUserId'] : get_current_user_id();
        $data['adminUrls'] = isset( $data['adminUrls'] ) && is_array( $data['adminUrls'] ) ? $data['adminUrls'] : [];
        $data['adminUrls'] = array_merge(
            [
                'drivePage'   => admin_url( 'admin.php?page=wpss-mi-drive' ),
                'profilePage' => admin_url( 'profile.php' ),
            ],
            $data['adminUrls']
        );
        $data['googleDriveStatus'] = function_exists( 'wpss_get_google_drive_status_payload' )
            ? wpss_get_google_drive_status_payload( get_current_user_id() )
            : [
                'configured' => false,
                'connected'  => false,
            ];
        $data['camposArmonicos'] = $campos_library;
        $data['camposArmonicosNombres'] = $campos_names;
        $data['chordsLibrary'] = $acordes_library;
        $data['chordsConfig'] = is_array( $acordes_config ) ? $acordes_config : [ 'paradigms' => [], 'qualities' => [] ];

        return $data;
    }

    return [
        'restUrl'       => esc_url_raw( rest_url( 'wpss/v1/' ) ),
        'publicRestUrl' => esc_url_raw( rest_url( 'wpss/v1/' ) ),
        'wpRestNonce'   => wp_create_nonce( 'wp_rest' ),
        'wpssNonce'     => wp_create_nonce( 'wpss' ),
        'canManage'     => $can_manage,
        'canRead'       => $can_read,
        'isAdmin'       => $is_admin,
        'isPublicReader' => true,
        'currentUserId' => get_current_user_id(),
        'googleDriveStatus' => function_exists( 'wpss_get_google_drive_status_payload' )
            ? wpss_get_google_drive_status_payload( get_current_user_id() )
            : [
                'configured' => false,
                'connected'  => false,
            ],
        'adminUrls'     => [
            'drivePage'   => admin_url( 'admin.php?page=wpss-mi-drive' ),
            'profilePage' => admin_url( 'profile.php' ),
        ],
        'tonicas'       => [ 'C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B' ],
        'camposArmonicos' => $campos_library,
        'camposArmonicosNombres' => $campos_names,
        'chordsLibrary' => $acordes_library,
        'chordsConfig'  => $acordes_config,
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

    $manifest_path = WPSSB_PATH . 'assets/admin-build/.vite/manifest.json';
    $manifest      = file_exists( $manifest_path ) ? json_decode( file_get_contents( $manifest_path ), true ) : [];
    $entry         = is_array( $manifest ) && isset( $manifest['index.html'] ) ? $manifest['index.html'] : [];
    $style_file    = ! empty( $entry['css'][0] ) ? $entry['css'][0] : 'assets/index-DFEdcGfY.css';
    $script_file   = ! empty( $entry['file'] ) ? $entry['file'] : 'assets/index-SvmnOcNt.js';

    wp_register_style(
        'wpssb-public-reader-vite-style',
        WPSSB_URL . 'assets/admin-build/' . ltrim( $style_file, '/' ),
        [ $style_handle ],
        WPSSB_VERSION
    );
    wp_enqueue_style( 'wpssb-public-reader-vite-style' );

    wp_register_script(
        $script_handle,
        WPSSB_URL . 'assets/admin-build/' . ltrim( $script_file, '/' ),
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
    if ( ! wpssb_user_can_view_songbook() ) {
        return '';
    }

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

    $style_attribute = wpssb_get_interface_style_attribute( is_array( $attributes ) ? $attributes : [] );

    return sprintf(
        '<div class="%1$s"%2$s><div id="wpss-cancion-app" class="wpss-cancion-app wpss-public-reader" data-view="public"></div></div>',
        esc_attr( $class_name ),
        '' !== $style_attribute ? ' style="' . esc_attr( $style_attribute ) . '"' : ''
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
