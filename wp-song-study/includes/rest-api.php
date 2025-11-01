<?php
/**
 * Rutas REST para exponer información de canciones.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'wpss_register_rest_routes' );

/**
 * Registra rutas personalizadas para el plugin.
 */
function wpss_register_rest_routes() {
    register_rest_route(
        'wpss/v1',
        '/cancion/(?P<id>\d+)/versos',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_cancion_versos',
            'permission_callback' => 'wpss_rest_public_permission',
            'args'                => [
                'id' => [
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]
    );
}

/**
 * Verifica permiso público de lectura.
 *
 * @return bool
 */
function wpss_rest_public_permission() {
    return true;
}

/**
 * Devuelve la lista de versos de una canción.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_get_cancion_versos( WP_REST_Request $request ) {
    $id = (int) $request->get_param( 'id' );

    if ( ! $id || 'cancion' !== get_post_type( $id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Canción no encontrada.', 'wp-song-study' ) ], 404 );
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

    $data = [];

    foreach ( $versos as $verso ) {
        $data[] = [
            'id'               => $verso->ID,
            'orden'            => (int) get_post_meta( $verso->ID, '_orden', true ),
            'texto'            => wp_kses_post( $verso->post_content ),
            'acorde_absoluto'  => sanitize_text_field( get_post_meta( $verso->ID, '_acorde_absoluto', true ) ),
            'funcion_relativa' => sanitize_text_field( get_post_meta( $verso->ID, '_funcion_relativa', true ) ),
            'notas'            => sanitize_text_field( get_post_meta( $verso->ID, '_notas_verso', true ) ),
        ];
    }

    return new WP_REST_Response( $data );
}
