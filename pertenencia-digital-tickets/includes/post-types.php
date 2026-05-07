<?php
/**
 * Tipos de contenido y taxonomías del sistema de tickets.
 *
 * @package PertenenciaDigitalTickets
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const PDT_TICKET_POST_TYPE  = 'pd_ticket';
const PDT_SERVICE_POST_TYPE = 'pd_service_product';
const PDT_AREA_TAXONOMY     = 'pd_ticket_area';

/**
 * Registra tickets y productos/servicios contratables.
 */
function pdt_register_post_types(): void {
    register_post_type(
        PDT_TICKET_POST_TYPE,
        [
            'labels'       => [
                'name'               => __( 'Tickets', 'pertenencia-digital-tickets' ),
                'singular_name'      => __( 'Ticket', 'pertenencia-digital-tickets' ),
                'add_new_item'       => __( 'Agregar ticket', 'pertenencia-digital-tickets' ),
                'edit_item'          => __( 'Editar ticket', 'pertenencia-digital-tickets' ),
                'new_item'           => __( 'Nuevo ticket', 'pertenencia-digital-tickets' ),
                'view_item'          => __( 'Ver ticket', 'pertenencia-digital-tickets' ),
                'search_items'       => __( 'Buscar tickets', 'pertenencia-digital-tickets' ),
                'not_found'          => __( 'No se encontraron tickets.', 'pertenencia-digital-tickets' ),
                'not_found_in_trash' => __( 'No hay tickets en la papelera.', 'pertenencia-digital-tickets' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'menu_icon'    => 'dashicons-sos',
            'supports'     => [ 'title', 'editor', 'author', 'comments' ],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'show_in_rest' => true,
        ]
    );

    register_post_type(
        PDT_SERVICE_POST_TYPE,
        [
            'labels'       => [
                'name'               => __( 'Servicios contratables', 'pertenencia-digital-tickets' ),
                'singular_name'      => __( 'Servicio contratable', 'pertenencia-digital-tickets' ),
                'add_new_item'       => __( 'Agregar servicio', 'pertenencia-digital-tickets' ),
                'edit_item'          => __( 'Editar servicio', 'pertenencia-digital-tickets' ),
                'new_item'           => __( 'Nuevo servicio', 'pertenencia-digital-tickets' ),
                'view_item'          => __( 'Ver servicio', 'pertenencia-digital-tickets' ),
                'search_items'       => __( 'Buscar servicios', 'pertenencia-digital-tickets' ),
                'not_found'          => __( 'No se encontraron servicios.', 'pertenencia-digital-tickets' ),
                'not_found_in_trash' => __( 'No hay servicios en la papelera.', 'pertenencia-digital-tickets' ),
            ],
            'public'       => true,
            'has_archive'  => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type=' . PDT_TICKET_POST_TYPE,
            'rewrite'      => [ 'slug' => 'servicios-contratables' ],
            'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ],
            'show_in_rest' => true,
        ]
    );
}

/**
 * Registra el área del ticket para poder separar Web, Tecnologías y otros frentes.
 */
function pdt_register_taxonomies(): void {
    register_taxonomy(
        PDT_AREA_TAXONOMY,
        [ PDT_TICKET_POST_TYPE, PDT_SERVICE_POST_TYPE ],
        [
            'labels'       => [
                'name'          => __( 'Áreas de solicitud', 'pertenencia-digital-tickets' ),
                'singular_name' => __( 'Área de solicitud', 'pertenencia-digital-tickets' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]
    );
}
