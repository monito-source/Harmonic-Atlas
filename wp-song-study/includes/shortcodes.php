<?php
/**
 * Shortcodes públicos para explorar canciones.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'songs_by_key', 'wpss_shortcode_songs_by_key' );
add_shortcode( 'song', 'wpss_shortcode_song' );
add_shortcode( 'wpss_public_reader', 'wpss_shortcode_public_reader' );

add_action( 'wp_enqueue_scripts', 'wpss_register_public_reader_assets' );
add_filter( 'the_content', 'wpss_inject_public_reader_shortcode', 9 );

function wpss_is_hold_chord_token( $value ) {
    if ( ! is_string( $value ) || '' === $value ) {
        return false;
    }

    $token = strtolower( trim( $value ) );
    return in_array( $token, [ 'null', 'still' ], true );
}

function wpss_text_ends_with_joiner( $value ) {
    if ( ! is_string( $value ) || '' === $value ) {
        return false;
    }

    $trimmed = rtrim( $value );
    return '' !== $trimmed && '-' === substr( $trimmed, -1 );
}

/**
 * Renderiza un listado de canciones filtradas por tonalidad.
 *
 * @param array $atts Atributos.
 * @return string
 */
function wpss_shortcode_songs_by_key( $atts ) {
    $atts = shortcode_atts(
        [
            'key' => '',
        ],
        $atts,
        'songs_by_key'
    );

    if ( empty( $atts['key'] ) ) {
        return '<em>' . esc_html__( 'Falta el parámetro key.', 'wp-song-study' ) . '</em>';
    }

    $query = new WP_Query(
        [
            'post_type'      => 'cancion',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'tonalidad',
                    'field'    => 'slug',
                    'terms'    => sanitize_title( $atts['key'] ),
                ],
            ],
        ]
    );

    if ( ! $query->have_posts() ) {
        wp_reset_postdata();
        return '<p>' . esc_html__( 'Sin canciones en esta tonalidad.', 'wp-song-study' ) . '</p>';
    }

    $output = '<ul class="wpss-songs-by-key">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $output .= sprintf(
            '<li><a href="%1$s">%2$s</a></li>',
            esc_url( get_permalink() ),
            esc_html( get_the_title() )
        );
    }
    wp_reset_postdata();
    $output .= '</ul>';

    return $output;
}

/**
 * Renderiza una canción con sus versos y acordes.
 *
 * @param array $atts Atributos.
 * @return string
 */
function wpss_shortcode_song( $atts ) {
    $atts = shortcode_atts(
        [
            'id' => 0,
        ],
        $atts,
        'song'
    );

    $id = intval( $atts['id'] ? $atts['id'] : get_the_ID() );
    if ( ! $id || 'cancion' !== get_post_type( $id ) ) {
        return '<em>' . esc_html__( 'Canción no encontrada.', 'wp-song-study' ) . '</em>';
    }

    $versos = get_posts(
        [
            'post_type'        => 'verso',
            'numberposts'      => -1,
            'meta_key'         => '_orden',
            'orderby'          => 'meta_value_num',
            'order'            => 'ASC',
            'meta_query'       => [
                [
                    'key'   => '_cancion_id',
                    'value' => $id,
                ],
            ],
            'suppress_filters' => true,
        ]
    );

    $html  = '<div class="wpss-song">';
    $html .= '<h3>' . esc_html( get_the_title( $id ) ) . '</h3>';

    $tonalidades = wp_get_post_terms( $id, 'tonalidad', [ 'fields' => 'names' ] );
    if ( ! is_wp_error( $tonalidades ) && ! empty( $tonalidades ) ) {
        $html .= '<p class="wpss-song-key"><strong>' . esc_html__( 'Tonalidad principal:', 'wp-song-study' ) . '</strong> ' . esc_html( implode( ', ', $tonalidades ) ) . '</p>';
    }

    $html .= '<ol class="wpss-verses">';
    foreach ( $versos as $verso ) {
        $func   = get_post_meta( $verso->ID, '_funcion_relativa', true );
        $nota   = get_post_meta( $verso->ID, '_notas_verso', true );
        $evento = json_decode( (string) get_post_meta( $verso->ID, '_evento_armonico_json', true ), true );
        if ( ! is_array( $evento ) || empty( $evento['tipo'] ) ) {
            $evento = null;
        }

        $segment_index_raw = null;
        if ( $evento && isset( $evento['segment_index'] ) ) {
            $segment_index_raw = (int) $evento['segment_index'];
        }

        $segmentos_meta = json_decode( (string) get_post_meta( $verso->ID, '_segmentos_json', true ), true );
        $segmentos      = [];

        if ( is_array( $segmentos_meta ) ) {
            foreach ( $segmentos_meta as $segmento ) {
                $texto  = isset( $segmento['texto'] ) ? sanitize_textarea_field( $segmento['texto'] ) : '';
                $acorde = isset( $segmento['acorde'] ) ? sanitize_text_field( $segmento['acorde'] ) : '';

                if ( wpss_is_hold_chord_token( $acorde ) ) {
                    $acorde = '';
                }

                if ( '' === $texto && '' === $acorde ) {
                    continue;
                }

                $segmentos[] = [
                    'texto'  => $texto,
                    'acorde' => $acorde,
                ];
            }
        }

        if ( empty( $segmentos ) ) {
            $texto  = sanitize_textarea_field( $verso->post_content );
            $acorde = sanitize_text_field( get_post_meta( $verso->ID, '_acorde_absoluto', true ) );
            if ( wpss_is_hold_chord_token( $acorde ) ) {
                $acorde = '';
            }
            $segmentos[] = [
                'texto'  => $texto,
                'acorde' => $acorde,
            ];
        }

        $html .= '<li class="wpss-verse">';
        $segment_index = null;
        if ( null !== $segment_index_raw && $segment_index_raw >= 0 && $segment_index_raw < count( $segmentos ) ) {
            $segment_index = $segment_index_raw;
        }

        $html .= '<div class="wpss-verse-text">';
        foreach ( $segmentos as $index => $segmento ) {
            $classes = [ 'wpss-song-segment' ];
            if ( null !== $segment_index && $segment_index === $index ) {
                $classes[] = 'is-event-target';
            }

            $html .= '<span class="' . esc_attr( implode( ' ', $classes ) ) . '">';
            if ( ! empty( $segmento['acorde'] ) ) {
                $html .= '<span class="wpss-song-chord">[' . esc_html( $segmento['acorde'] ) . ']</span> ';
            }
            $html .= esc_html( $segmento['texto'] );
            $html .= '</span>';
            $has_joiner = wpss_text_ends_with_joiner( $segmento['texto'] );
            if ( ! $has_joiner && $index < count( $segmentos ) - 1 ) {
                $html .= ' ';
            }
        }
        $html .= '</div>';

        if ( $evento ) {
            $badge = '';
            if ( null !== $segment_index ) {
                $badge_text = sprintf( __( 'Segmento %d', 'wp-song-study' ), $segment_index + 1 );
                $badge      = ' <span class="wpss-song-event-badge">' . esc_html( $badge_text ) . '</span>';
            }

            if ( 'modulacion' === $evento['tipo'] ) {
                $destino = trim( ( $evento['tonica_destino'] ?? '' ) . ' ' . ( $evento['campo_armonico_destino'] ?? '' ) );
                $html   .= '<div class="wpss-song-event">' . sprintf( esc_html__( 'Modulación → %s', 'wp-song-study' ), $destino ? esc_html( $destino ) : '—' ) . $badge . '</div>';
            } elseif ( 'prestamo' === $evento['tipo'] ) {
                $origen = trim( ( $evento['tonica_origen'] ?? '' ) . ' ' . ( $evento['campo_armonico_origen'] ?? '' ) );
                $html  .= '<div class="wpss-song-event">' . sprintf( esc_html__( 'Préstamo ← %s', 'wp-song-study' ), $origen ? esc_html( $origen ) : '—' ) . $badge . '</div>';
            }
        }

        if ( ! empty( $func ) ) {
            $html .= '<div class="wpss-verse-function"><em>' . esc_html( $func ) . '</em></div>';
        }

        if ( ! empty( $nota ) ) {
            $html .= '<div class="wpss-verse-note">' . esc_html( $nota ) . '</div>';
        }

        $html .= '</li>';
    }
    $html .= '</ol>';
    $html .= '</div>';

    return $html;
}

/**
 * Registro diferido de assets para el lector público.
 */
function wpss_register_public_reader_assets() {
    if ( ! is_singular() ) {
        return;
    }

    $post_id = 0;
    $has_shortcode = false;

    global $post;
    if ( $post ) {
        $post_id = (int) $post->ID;
        if ( false !== strpos( (string) $post->post_content, '[wpss_public_reader' ) ) {
            $has_shortcode = true;
        }
    }

    $page_id = (int) get_option( 'wpss_public_reader_page_id' );
    if ( $page_id && is_page( $page_id ) ) {
        $has_shortcode = true;
    } elseif ( is_page( 'canciones' ) ) {
        $has_shortcode = true;
    }

    if ( ! $has_shortcode ) {
        return;
    }

    $localized_data = wpss_get_public_localized_data();
    $react_handle   = wpss_enqueue_react_assets();

    if ( $react_handle ) {
        wp_localize_script( $react_handle, 'WPSS', $localized_data );
    }
}

/**
 * Renderiza el contenedor del lector público.
 *
 * @return string
 */
function wpss_shortcode_public_reader() {
    return '<div id="wpss-cancion-app" class="wpss-cancion-app wpss-public-reader" data-view="public"></div>';
}

/**
 * Inyecta el shortcode en la pagina publica si el contenido esta vacio.
 *
 * @param string $content Contenido original.
 * @return string
 */
function wpss_inject_public_reader_shortcode( $content ) {
    if ( ! is_singular( 'page' ) ) {
        return $content;
    }

    $page_id = (int) get_option( 'wpss_public_reader_page_id' );

    if ( $page_id && is_page( $page_id ) ) {
        return wpss_shortcode_public_reader();
    }

    if ( is_page( 'canciones' ) ) {
        return wpss_shortcode_public_reader();
    }

    return $content;
}

/**
 * Crea la página pública del lector si no existe.
 */
function wpss_ensure_public_reader_page() {
    $option_key = 'wpss_public_reader_page_id';
    $page_id    = (int) get_option( $option_key );

    if ( $page_id ) {
        $page = get_post( $page_id );
        if ( $page && 'page' === $page->post_type ) {
            if ( false === strpos( (string) $page->post_content, '[wpss_public_reader' ) ) {
                wp_update_post(
                    [
                        'ID'           => $page_id,
                        'post_content' => trim( (string) $page->post_content . "\n\n[wpss_public_reader]" ),
                    ]
                );
            }
            return $page_id;
        }
    }

    $existing = get_page_by_path( 'canciones' );
    if ( $existing && 'page' === $existing->post_type ) {
        $page_id = (int) $existing->ID;
        if ( false === strpos( (string) $existing->post_content, '[wpss_public_reader' ) ) {
            wp_update_post(
                [
                    'ID'           => $page_id,
                    'post_content' => trim( (string) $existing->post_content . "\n\n[wpss_public_reader]" ),
                ]
            );
        }
        update_option( $option_key, $page_id );
        return $page_id;
    }

    $page_id = wp_insert_post(
        [
            'post_title'   => __( 'Canciones', 'wp-song-study' ),
            'post_name'    => sanitize_title( __( 'canciones', 'wp-song-study' ) ),
            'post_content' => '[wpss_public_reader]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]
    );

    if ( $page_id && ! is_wp_error( $page_id ) ) {
        update_option( $option_key, (int) $page_id );
        return (int) $page_id;
    }

    return 0;
}

/**
 * Datos localizables para el lector público.
 *
 * @return array
 */
function wpss_get_public_localized_data() {
    $tonicas = [
        'C',
        'C#',
        'Db',
        'D',
        'D#',
        'Eb',
        'E',
        'F',
        'F#',
        'Gb',
        'G',
        'G#',
        'Ab',
        'A',
        'A#',
        'Bb',
        'B',
    ];

    $can_manage = defined( 'WPSS_CAP_MANAGE' ) ? current_user_can( WPSS_CAP_MANAGE ) : current_user_can( 'edit_posts' );
    $is_admin = current_user_can( 'manage_options' );

    return [
        'restUrl'      => esc_url_raw( rest_url( 'wpss/v1/' ) ),
        'publicRestUrl' => esc_url_raw( rest_url( 'wpss/v1/' ) ),
        'wpRestNonce'  => wp_create_nonce( 'wp_rest' ),
        'wpssNonce'    => wp_create_nonce( 'wpss' ),
        'canManage'    => $can_manage,
        'isAdmin'      => $is_admin,
        'isPublicReader' => true,
        'currentUserId' => get_current_user_id(),
        'midiRanges'   => wpss_get_midi_range_presets(),
        'midiRangeDefault' => wpss_get_midi_range_default(),
        'tonicas'      => $tonicas,
        'strings'      => [
            'filtersTitle'     => __( 'Canciones disponibles', 'wp-song-study' ),
            'filtersLabel'     => __( 'Filtrar canciones', 'wp-song-study' ),
            'filtersClear'     => __( 'Limpiar filtros', 'wp-song-study' ),
            'filtersApply'     => __( 'Aplicar', 'wp-song-study' ),
            'filtersCollection' => __( 'Colección', 'wp-song-study' ),
            'filtersTonica'    => __( 'Tónica', 'wp-song-study' ),
            'filtersLoans'     => __( 'Préstamos', 'wp-song-study' ),
            'filtersMods'      => __( 'Modulaciones', 'wp-song-study' ),
            'readingView'      => __( 'Vista de lectura', 'wp-song-study' ),
            'readingEmpty'     => __( 'Sin contenido para mostrar.', 'wp-song-study' ),
            'readingModeInline' => __( 'Acordes inline', 'wp-song-study' ),
            'readingModeStacked' => __( 'Acordes arriba', 'wp-song-study' ),
            'readingFollowStructure' => __( 'Seguir estructura', 'wp-song-study' ),
            'readingFollowSections' => __( 'Ordenar por secciones', 'wp-song-study' ),
            'readingExit'      => __( 'Volver a la lista', 'wp-song-study' ),
        ],
    ];
}
