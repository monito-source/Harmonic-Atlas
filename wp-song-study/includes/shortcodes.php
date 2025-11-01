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
        $acorde = get_post_meta( $verso->ID, '_acorde_absoluto', true );
        $func   = get_post_meta( $verso->ID, '_funcion_relativa', true );
        $nota   = get_post_meta( $verso->ID, '_notas_verso', true );

        $html .= '<li class="wpss-verse">';
        $html .= '<div class="wpss-verse-text">' . wp_kses_post( $verso->post_content ) . '</div>';
        $html .= '<div class="wpss-verse-chord"><strong>' . esc_html__( 'Acorde:', 'wp-song-study' ) . '</strong> ' . esc_html( $acorde ) . '</div>';

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
