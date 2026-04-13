<?php
/**
 * Plugin Name: WP Song Study Blocks
 * Plugin URI:  https://example.com/wp-song-study-blocks
 * Description: Versión basada en bloques de WP Song Study con bloques dinámicos SSR para listado y lector de canciones.
 * Version:     1.3.0
 * Author:      Sergio Mendoza
 * Text Domain: wp-song-study-blocks
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WPSSB_PATH' ) ) {
    define( 'WPSSB_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPSSB_URL' ) ) {
    define( 'WPSSB_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPSSB_VERSION' ) ) {
    define( 'WPSSB_VERSION', '1.3.0' );
}

// Compatibilidad con funciones heredadas que todavía esperan las constantes antiguas.
if ( ! defined( 'WPSS_PATH' ) ) {
    define( 'WPSS_PATH', WPSSB_PATH );
}

if ( ! defined( 'WPSS_URL' ) ) {
    define( 'WPSS_URL', WPSSB_URL );
}

if ( ! defined( 'WPSS_VERSION' ) ) {
    define( 'WPSS_VERSION', WPSSB_VERSION );
}

require_once WPSSB_PATH . 'includes/songbook-access.php';

/**
 * Carga traducciones del plugin de bloques.
 */
function wpssb_load_textdomain() {
    load_plugin_textdomain( 'wp-song-study-blocks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wpssb_load_textdomain' );

/**
 * Carga la capa backend heredada del plugin clásico sólo si no existe ya.
 * Esto evita colisiones fatales si ambos plugins están activos al mismo tiempo.
 */
function wpssb_load_legacy_backend() {
    if ( function_exists( 'wpss_register_cpt_cancion' ) ) {
        return;
    }

    require_once WPSSB_PATH . 'includes/tax-tonalidad.php';
    require_once WPSSB_PATH . 'includes/tax-coleccion.php';
    require_once WPSSB_PATH . 'includes/tax-etiqueta-cancion.php';
    require_once WPSSB_PATH . 'includes/cpt-cancion.php';
    require_once WPSSB_PATH . 'includes/cpt-agrupacion-musical.php';
    require_once WPSSB_PATH . 'includes/cpt-verso.php';
    require_once WPSSB_PATH . 'includes/admin-columns.php';
    require_once WPSSB_PATH . 'includes/admin-pages.php';
    require_once WPSSB_PATH . 'includes/rest-api.php';
    require_once WPSSB_PATH . 'includes/media-drive-groups.php';
    require_once WPSSB_PATH . 'includes/song-import-export.php';
    require_once WPSSB_PATH . 'includes/midi-settings.php';
    require_once WPSSB_PATH . 'includes/shortcodes.php';
}

wpssb_load_legacy_backend();

require_once WPSSB_PATH . 'includes/projects.php';
require_once WPSSB_PATH . 'includes/shared-render.php';
require_once WPSSB_PATH . 'includes/blocks.php';

/**
 * Registra CPT y taxonomías cuando el plugin clásico no está activo.
 */
function wpssb_register_content_types() {
    if ( function_exists( 'wpss_register_tonalidad_tax' ) ) {
        wpss_register_tonalidad_tax();
    }
    if ( function_exists( 'wpss_register_coleccion_tax' ) ) {
        wpss_register_coleccion_tax();
    }
    if ( function_exists( 'wpss_register_cancion_tag_tax' ) ) {
        wpss_register_cancion_tag_tax();
    }
    if ( function_exists( 'wpss_register_cpt_cancion' ) ) {
        wpss_register_cpt_cancion();
    }
    if ( function_exists( 'wpss_register_cpt_agrupacion_musical' ) ) {
        wpss_register_cpt_agrupacion_musical();
    }
    if ( function_exists( 'wpss_register_cpt_verso' ) ) {
        wpss_register_cpt_verso();
    }
}
add_action( 'init', 'wpssb_register_content_types', 5 );

/**
 * Activa backend legado si aplica y asegura que bloques/CPT queden listos.
 */
function wpssb_activate_plugin() {
    wpssb_register_content_types();
    wpssb_register_collaborator_role();
    wpssb_register_project_post_type();
    wpssb_register_project_area_taxonomy();
    wpss_register_songbook_access();
    wpssb_sync_presskit_page_template_assignment();

    if ( function_exists( 'wpss_register_colega_role' ) ) {
        wpss_register_colega_role();
    }

    if ( function_exists( 'wpss_register_invitado_role' ) ) {
        wpss_register_invitado_role();
    }

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpssb_activate_plugin' );

/**
 * Limpieza en desactivación.
 */
function wpssb_deactivate_plugin() {
    if ( function_exists( 'wpss_cleanup_temp_meta' ) ) {
        wpss_cleanup_temp_meta();
    }

    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wpssb_deactivate_plugin' );
