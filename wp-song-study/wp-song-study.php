<?php

/**
 * Plugin Name: WP Song Study
 * Plugin URI:  https://example.com/wp-song-study
 * Description: Registro y análisis armónico de canciones con versos y acordes por verso.
 * Version:     0.1.0
 * Author:      Sergio Mendoza
 * Text Domain: wp-song-study
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Asegura referencia inequívoca al archivo principal del plugin.
// Respeta definiciones previas para no romper entornos que ya lo definan.
if ( ! defined( 'WPSS_PLUGIN_FILE' ) ) {
    define( 'WPSS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WPSS_PATH' ) ) {
    define( 'WPSS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPSS_URL' ) ) {
    define( 'WPSS_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPSS_VERSION' ) ) {
    define( 'WPSS_VERSION', '0.1.0' );
}

/**
 * Carga el archivo de traducciones.
 */
function wpss_load_textdomain() {
    load_plugin_textdomain( 'wp-song-study', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wpss_load_textdomain' );

require_once WPSS_PATH . 'includes/tax-tonalidad.php';
require_once WPSS_PATH . 'includes/tax-coleccion.php';
require_once WPSS_PATH . 'includes/cpt-cancion.php';
require_once WPSS_PATH . 'includes/cpt-verso.php';
require_once WPSS_PATH . 'includes/admin-columns.php';
require_once WPSS_PATH . 'includes/shortcodes.php';
require_once WPSS_PATH . 'includes/admin-pages.php';
require_once WPSS_PATH . 'includes/rest-api.php';

/**
 * Registro de taxonomía y CPT.
 */
function wpss_register_content_types() {
    wpss_register_tonalidad_tax();
    wpss_register_coleccion_tax();
    wpss_register_cpt_cancion();
    wpss_register_cpt_verso();
}
add_action( 'init', 'wpss_register_content_types' );

/**
 * Ejecuta tareas de activación.
 */
function wpss_activate_plugin() {
    wpss_register_content_types();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpss_activate_plugin' );

/**
 * Ejecuta tareas de desactivación.
 */
function wpss_deactivate_plugin() {
    if ( function_exists( 'wpss_cleanup_temp_meta' ) ) {
        wpss_cleanup_temp_meta();
    }
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wpss_deactivate_plugin' );
