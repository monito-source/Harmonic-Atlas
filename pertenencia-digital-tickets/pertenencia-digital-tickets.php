<?php
/**
 * Plugin Name: Pertenencia Digital Tickets
 * Description: Gestiona tickets, solicitudes de servicio y productos contratables para el área de Tecnologías y Web.
 * Version: 0.1.0
 * Author: Pertenencia Digital
 * Text Domain: pertenencia-digital-tickets
 *
 * @package PertenenciaDigitalTickets
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PDT_VERSION', '0.1.0' );
define( 'PDT_PLUGIN_FILE', __FILE__ );
define( 'PDT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PDT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PDT_PLUGIN_DIR . 'includes/post-types.php';
require_once PDT_PLUGIN_DIR . 'includes/ticket-actions.php';
require_once PDT_PLUGIN_DIR . 'includes/shortcodes.php';
require_once PDT_PLUGIN_DIR . 'includes/admin.php';

/**
 * Activa el plugin y deja los tipos de contenido disponibles para reescribir reglas.
 */
function pdt_activate(): void {
    pdt_register_post_types();
    pdt_register_taxonomies();
    pdt_seed_service_products();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pdt_activate' );

/**
 * Limpia reglas al desactivar.
 */
function pdt_deactivate(): void {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pdt_deactivate' );

add_action( 'init', 'pdt_register_post_types' );
add_action( 'init', 'pdt_register_taxonomies' );
add_action( 'wp_enqueue_scripts', 'pdt_enqueue_frontend_assets' );

/**
 * Crea productos/servicios iniciales para que el sistema no arranque vacío.
 */
function pdt_seed_service_products(): void {
    $services = [
        [
            'title'      => 'Espacio digital compartido',
            'slug'       => 'presencia-basica-colaboracion-ligera',
            'excerpt'    => 'Carta de presentación digital dentro de una página compartida.',
            'area'       => 'web',
            'menu_order' => 10,
        ],
        [
            'title'      => 'Presencia mínima propia',
            'slug'       => 'presencia-basica-sitio-propio',
            'excerpt'    => 'Sitio propio simple con dominio anual, hosting mensual y WordPress administrable.',
            'area'       => 'web',
            'menu_order' => 20,
        ],
        [
            'title'      => 'Alojamiento y mantenimiento base',
            'slug'       => 'alojamiento-mantenimiento-base',
            'excerpt'    => 'Base operativa mensual para hosting, mantenimiento y soporte cuando aplica.',
            'area'       => 'web',
            'menu_order' => 30,
        ],
        [
            'title'      => 'Sitio profesional',
            'slug'       => 'sitio-profesional',
            'excerpt'    => 'Implementación web con herramientas de administración, comercio y flujos profesionales.',
            'area'       => 'web',
            'menu_order' => 40,
        ],
        [
            'title'      => 'Enterprise · centro de operaciones digital',
            'slug'       => 'enterprise-centro-operaciones-digital',
            'excerpt'    => 'Soluciones web o sistemas para operaciones consolidadas, cotizadas por alcance.',
            'area'       => 'web',
            'menu_order' => 45,
        ],
        [
            'title'      => 'Contenido multimedia por comisión',
            'slug'       => 'contenido-multimedia-por-comision',
            'excerpt'    => 'Video, foto, audio, música o piezas narrativas por encargo.',
            'area'       => 'multimedia',
            'menu_order' => 50,
        ],
        [
            'title'      => 'Servicio técnico digital',
            'slug'       => 'servicio-tecnico-digital',
            'excerpt'    => 'Diagnóstico y apoyo para herramientas, cuentas, archivos o flujos digitales.',
            'area'       => 'tecnologias-digitales',
            'menu_order' => 60,
        ],
        [
            'title'      => 'Consultoría de tecnologías digitales',
            'slug'       => 'consultoria-tecnologias-digitales',
            'excerpt'    => 'Acompañamiento para elegir herramientas, ordenar requerimientos y definir alcance.',
            'area'       => 'tecnologias-digitales',
            'menu_order' => 70,
        ],
    ];

    foreach ( $services as $service ) {
        $existing = get_page_by_path( $service['slug'], OBJECT, PDT_SERVICE_POST_TYPE );

        if ( $existing instanceof WP_Post ) {
            continue;
        }

        $service_id = wp_insert_post(
            [
                'post_type'    => PDT_SERVICE_POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => $service['title'],
                'post_name'    => $service['slug'],
                'post_excerpt' => $service['excerpt'],
                'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $service['excerpt'] ) . '</p><!-- /wp:paragraph -->',
                'menu_order'   => $service['menu_order'],
            ]
        );

        if ( $service_id > 0 && taxonomy_exists( PDT_AREA_TAXONOMY ) ) {
            wp_set_object_terms( $service_id, $service['area'], PDT_AREA_TAXONOMY, false );
        }
    }
}

/**
 * Carga estilos públicos solo cuando la página contiene shortcodes del plugin.
 */
function pdt_enqueue_frontend_assets(): void {
    if ( ! is_singular() ) {
        return;
    }

    $post = get_post();

    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $ticket_template_pages = [
        'tickets',
        'tecnologias-digitales',
        'servicio-tecnico-digital',
        'consultoria-tecnologias-digitales',
        'necesito-trabajo-multimedia-por-comision',
    ];

    if ( ! in_array( $post->post_name, $ticket_template_pages, true ) && ! has_shortcode( $post->post_content, 'pd_technology_ticket_form' ) && ! has_shortcode( $post->post_content, 'pd_technology_ticket_portal' ) && ! has_shortcode( $post->post_content, 'pd_technology_services' ) ) {
        return;
    }

    wp_enqueue_style(
        'pertenencia-digital-tickets',
        PDT_PLUGIN_URL . 'assets/css/tickets.css',
        [],
        PDT_VERSION
    );
}
