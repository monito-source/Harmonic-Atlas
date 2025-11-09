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
            $html .= '</span> ';
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
