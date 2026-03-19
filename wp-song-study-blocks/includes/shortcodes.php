<?php
/**
 * Compatibilidad heredada para shortcodes dentro del plugin de bloques.
 *
 * @package WP_Song_Study_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! shortcode_exists( 'wpss_public_reader' ) ) {
    add_shortcode( 'wpss_public_reader', 'wpssb_shortcode_public_reader' );
}

if ( ! shortcode_exists( 'songs_by_key' ) ) {
    add_shortcode( 'songs_by_key', 'wpssb_shortcode_songs_by_key' );
}

/**
 * Shortcode legacy del lector público.
 *
 * @return string
 */
function wpssb_shortcode_public_reader() {
    return wpssb_render_interface_markup();
}

/**
 * Shortcode legacy para lista por tonalidad.
 *
 * @param array $atts Atributos.
 * @return string
 */
function wpssb_shortcode_songs_by_key( $atts ) {
    $atts = shortcode_atts(
        [
            'key' => '',
        ],
        $atts,
        'songs_by_key'
    );

    return wpssb_render_song_list_markup(
        [
            'tonalidad'      => $atts['key'],
            'postsToShow'    => -1,
            'order'          => 'ASC',
            'orderBy'        => 'title',
            'showKey'        => true,
            'showCollection' => false,
        ]
    );
}
