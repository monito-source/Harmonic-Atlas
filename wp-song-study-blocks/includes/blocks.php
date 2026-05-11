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
    $dynamic_blocks = [
        'interface'               => 'wpssb_render_block_interface',
        'song-list'               => 'wpssb_render_block_song_list',
        'project-collaborators'   => 'wpssb_render_block_project_collaborators',
        'project-presskit'        => 'wpssb_render_block_project_presskit',
        'project-gallery'         => 'wpssb_render_block_project_gallery',
        'project-contact'         => 'wpssb_render_block_project_contact',
        'project-directory'       => 'wpssb_render_block_project_directory',
        'collaborator-presskit'   => 'wpssb_render_block_collaborator_presskit',
        'collaborator-gallery'    => 'wpssb_render_block_collaborator_gallery',
        'collaborator-contact'    => 'wpssb_render_block_collaborator_contact',
        'current-membership'      => 'wpssb_render_block_current_membership',
        'current-rehearsals'      => 'wpssb_render_block_current_rehearsals',
        'collaborator-projects'   => 'wpssb_render_block_collaborator_projects',
    ];

    foreach ( $dynamic_blocks as $block_directory => $render_callback ) {
        register_block_type(
            WPSSB_PATH . 'build/' . $block_directory,
            [
                'render_callback' => $render_callback,
            ]
        );
    }
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
