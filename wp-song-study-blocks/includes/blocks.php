<?php
/**
 * Registro de bloques Gutenberg dinámicos.
 *
 * @package WP_Song_Study_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra los bloques SSR del plugin.
 */
function wpssb_register_blocks() {
    register_block_type(
        WPSSB_PATH . 'build/interface',
        [
            'render_callback' => 'wpssb_render_block_interface',
        ]
    );

    register_block_type(
        WPSSB_PATH . 'build/song-list',
        [
            'render_callback' => 'wpssb_render_block_song_list',
        ]
    );
}
add_action( 'init', 'wpssb_register_blocks' );

/**
 * Render callback del bloque principal.
 */
function wpssb_render_block_interface( $attributes, $content, $block ) {
    return wpssb_render_interface_markup( is_array( $attributes ) ? $attributes : [], $content );
}

/**
 * Render callback del bloque listado.
 */
function wpssb_render_block_song_list( $attributes ) {
    return wpssb_render_song_list_markup( is_array( $attributes ) ? $attributes : [] );
}
