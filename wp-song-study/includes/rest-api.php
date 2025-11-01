<?php
/**
 * Rutas REST para gestionar canciones, préstamos, modulaciones y versos.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'wpss_register_rest_routes' );

/**
 * Registra las rutas personalizadas del plugin.
 */
function wpss_register_rest_routes() {
    register_rest_route(
        'wpss/v1',
        '/canciones',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_canciones',
            'permission_callback' => 'wpss_rest_verify_permissions',
            'args'                => [
                'tonalidad' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'con_prestamos' => [
                    'sanitize_callback' => 'wpss_sanitize_bool_request',
                ],
                'con_modulaciones' => [
                    'sanitize_callback' => 'wpss_sanitize_bool_request',
                ],
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/cancion/(?P<id>\d+)',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_cancion',
            'permission_callback' => 'wpss_rest_verify_permissions',
            'args'                => [
                'id' => [
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/cancion',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'wpss_rest_save_cancion',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );
}

/**
 * Valida permisos y nonce específico del módulo.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return bool|WP_Error
 */
function wpss_rest_verify_permissions( WP_REST_Request $request ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error( 'wpss_rest_forbidden', __( 'No tienes permisos suficientes para esta acción.', 'wp-song-study' ), [ 'status' => 403 ] );
    }

    $nonce = $request->get_header( 'x-wpss-nonce' );

    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wpss_rest' ) ) {
        return new WP_Error( 'wpss_rest_invalid_nonce', __( 'Nonce inválido o ausente.', 'wp-song-study' ), [ 'status' => 403 ] );
    }

    return true;
}

/**
 * Sanitiza parámetros booleanos recibidos en la consulta.
 *
 * @param mixed $value Valor entrante.
 * @return int|null
 */
function wpss_sanitize_bool_request( $value ) {
    if ( '' === $value || null === $value ) {
        return null;
    }

    return (int) (bool) $value;
}

/**
 * Devuelve canciones con filtros y paginación.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_get_canciones( WP_REST_Request $request ) {
    $page     = max( 1, (int) $request->get_param( 'page' ) );
    $per_page = (int) $request->get_param( 'per_page' );
    $per_page = min( max( 1, $per_page ), 100 );

    $args = [
        'post_type'      => 'cancion',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ];

    $tonalidad = (string) $request->get_param( 'tonalidad' );
    if ( '' !== $tonalidad ) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'tonalidad',
                'field'    => 'slug',
                'terms'    => $tonalidad,
            ],
        ];
    }

    $meta_query = [];

    $con_prestamos = $request->get_param( 'con_prestamos' );
    if ( null !== $con_prestamos ) {
        $meta_query[] = [
            'key'   => '_tiene_prestamos',
            'value' => (int) $con_prestamos,
        ];
    }

    $con_modulaciones = $request->get_param( 'con_modulaciones' );
    if ( null !== $con_modulaciones ) {
        $meta_query[] = [
            'key'   => '_tiene_modulaciones',
            'value' => (int) $con_modulaciones,
        ];
    }

    if ( ! empty( $meta_query ) ) {
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query( $args );

    $items = [];

    foreach ( $query->posts as $post_id ) {
        $tonalidad_names = wp_get_post_terms( $post_id, 'tonalidad', [ 'fields' => 'names' ] );
        $items[]         = [
            'id'                => (int) $post_id,
            'titulo'            => get_the_title( $post_id ),
            'tonalidad'         => ! empty( $tonalidad_names ) ? $tonalidad_names[0] : '',
            'tiene_prestamos'   => (bool) get_post_meta( $post_id, '_tiene_prestamos', true ),
            'tiene_modulaciones'=> (bool) get_post_meta( $post_id, '_tiene_modulaciones', true ),
            'conteo_versos'     => (int) get_post_meta( $post_id, '_conteo_versos', true ),
        ];
    }

    $response = rest_ensure_response( $items );
    $response->header( 'X-WP-Total', (int) $query->found_posts );
    $response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

    return $response;
}

/**
 * Devuelve una canción con toda su información asociada.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_get_cancion( WP_REST_Request $request ) {
    $post_id = (int) $request->get_param( 'id' );
    $post    = get_post( $post_id );

    if ( ! $post || 'cancion' !== $post->post_type ) {
        return new WP_REST_Response( [ 'message' => __( 'Canción no encontrada.', 'wp-song-study' ) ], 404 );
    }

    $tonalidad_terms = wp_get_post_terms( $post_id, 'tonalidad', [ 'fields' => 'names' ] );

    $data = [
        'id'                => (int) $post_id,
        'titulo'            => get_the_title( $post_id ),
        'tonalidad'         => ! empty( $tonalidad_terms ) ? $tonalidad_terms[0] : '',
        'campo_armonico'    => sanitize_textarea_field( get_post_meta( $post_id, '_campo_armonico_predominante', true ) ),
        'prestamos'         => wpss_decode_json_meta( get_post_meta( $post_id, '_prestamos_tonales_json', true ) ),
        'modulaciones'      => wpss_decode_json_meta( get_post_meta( $post_id, '_modulaciones_json', true ) ),
        'versos'            => wpss_get_cancion_versos( $post_id ),
        'tiene_prestamos'   => (bool) get_post_meta( $post_id, '_tiene_prestamos', true ),
        'tiene_modulaciones'=> (bool) get_post_meta( $post_id, '_tiene_modulaciones', true ),
    ];

    return rest_ensure_response( $data );
}

/**
 * Guarda una canción y sus datos relacionados de forma atómica.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_save_cancion( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }

    $id             = isset( $params['id'] ) ? absint( $params['id'] ) : 0;
    $titulo         = isset( $params['titulo'] ) ? sanitize_text_field( $params['titulo'] ) : '';
    $tonalidad      = isset( $params['tonalidad'] ) ? sanitize_text_field( $params['tonalidad'] ) : '';
    $campo_armonico = isset( $params['campo_armonico'] ) ? sanitize_textarea_field( $params['campo_armonico'] ) : '';

    if ( '' === $titulo ) {
        return new WP_REST_Response( [ 'message' => __( 'El título es obligatorio.', 'wp-song-study' ) ], 400 );
    }

    if ( '' === $tonalidad ) {
        return new WP_REST_Response( [ 'message' => __( 'La tonalidad es obligatoria.', 'wp-song-study' ) ], 400 );
    }

    $prestamos    = wpss_sanitize_prestamos_array( isset( $params['prestamos'] ) ? (array) $params['prestamos'] : [] );
    $modulaciones = wpss_sanitize_modulaciones_array( isset( $params['modulaciones'] ) ? (array) $params['modulaciones'] : [] );
    $versos       = wpss_sanitize_versos_array( isset( $params['versos'] ) ? (array) $params['versos'] : [] );
    $versos       = wpss_normalize_versos_order( $versos );

    $postarr = [
        'post_type'   => 'cancion',
        'post_status' => 'publish',
        'post_title'  => $titulo,
    ];

    if ( $id ) {
        $postarr['ID'] = $id;
    }

    $post_id = wp_insert_post( wp_slash( $postarr ), true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No fue posible guardar la canción.', 'wp-song-study' ) ], 500 );
    }

    wpss_assign_tonalidad_term( $post_id, $tonalidad );

    update_post_meta( $post_id, '_campo_armonico_predominante', $campo_armonico );
    update_post_meta( $post_id, '_prestamos_tonales_json', wp_json_encode( $prestamos ) );
    update_post_meta( $post_id, '_modulaciones_json', wp_json_encode( $modulaciones ) );

    $versos_result = wpss_replace_cancion_versos( $post_id, $versos );
    if ( is_wp_error( $versos_result ) ) {
        return new WP_REST_Response( [ 'message' => $versos_result->get_error_message() ], 500 );
    }

    $tiene_prestamos    = ! empty( $prestamos );
    $tiene_modulaciones = ! empty( $modulaciones );

    update_post_meta( $post_id, '_tiene_prestamos', $tiene_prestamos ? 1 : 0 );
    update_post_meta( $post_id, '_tiene_modulaciones', $tiene_modulaciones ? 1 : 0 );
    update_post_meta( $post_id, '_conteo_versos', count( $versos ) );

    $response = [
        'ok'                 => true,
        'id'                 => (int) $post_id,
        'tiene_prestamos'    => $tiene_prestamos,
        'tiene_modulaciones' => $tiene_modulaciones,
    ];

    return rest_ensure_response( $response );
}

/**
 * Decodifica un metacampo JSON.
 *
 * @param string $value Valor almacenado.
 * @return array
 */
function wpss_decode_json_meta( $value ) {
    $decoded = json_decode( (string) $value, true );

    return is_array( $decoded ) ? $decoded : [];
}

/**
 * Devuelve la lista de versos de una canción ordenados.
 *
 * @param int $post_id ID de la canción.
 * @return array
 */
function wpss_get_cancion_versos( $post_id ) {
    $versos = get_posts(
        [
            'post_type'        => 'verso',
            'numberposts'      => -1,
            'orderby'          => 'meta_value_num',
            'order'            => 'ASC',
            'meta_key'         => '_orden',
            'meta_query'       => [
                [
                    'key'   => '_cancion_id',
                    'value' => $post_id,
                ],
            ],
            'suppress_filters' => true,
        ]
    );

    $data = [];

    foreach ( $versos as $verso ) {
        $data[] = [
            'id'        => (int) $verso->ID,
            'orden'     => (int) get_post_meta( $verso->ID, '_orden', true ),
            'texto'     => sanitize_textarea_field( $verso->post_content ),
            'acorde'    => sanitize_text_field( get_post_meta( $verso->ID, '_acorde_absoluto', true ) ),
            'comentario'=> sanitize_text_field( get_post_meta( $verso->ID, '_funcion_relativa', true ) ),
        ];
    }

    return $data;
}

/**
 * Asigna la tonalidad principal a la canción, creando el término si no existe.
 *
 * @param int    $post_id   ID de la canción.
 * @param string $tonalidad Nombre/slug de la tonalidad.
 */
function wpss_assign_tonalidad_term( $post_id, $tonalidad ) {
    if ( '' === $tonalidad ) {
        wp_set_post_terms( $post_id, [], 'tonalidad' );
        return;
    }

    $term       = term_exists( $tonalidad, 'tonalidad' );
    $tonalidad_slug = sanitize_title( $tonalidad );

    if ( ! $term ) {
        $term = term_exists( $tonalidad_slug, 'tonalidad' );
    }

    if ( ! $term ) {
        $term = wp_insert_term(
            $tonalidad,
            'tonalidad',
            [
                'slug' => $tonalidad_slug,
            ]
        );
    }

    if ( is_wp_error( $term ) ) {
        return;
    }

    $term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
    wp_set_post_terms( $post_id, [ $term_id ], 'tonalidad', false );
}

/**
 * Limpia y filtra la estructura de préstamos tonales.
 *
 * @param array $prestamos Datos recibidos.
 * @return array
 */
function wpss_sanitize_prestamos_array( array $prestamos ) {
    $limpios = [];

    foreach ( $prestamos as $prestamo ) {
        $origen      = isset( $prestamo['origen'] ) ? sanitize_text_field( $prestamo['origen'] ) : '';
        $descripcion = isset( $prestamo['descripcion'] ) ? sanitize_textarea_field( $prestamo['descripcion'] ) : '';
        $notas       = isset( $prestamo['notas'] ) ? sanitize_textarea_field( $prestamo['notas'] ) : '';

        if ( '' === $origen && '' === $descripcion && '' === $notas ) {
            continue;
        }

        $limpios[] = [
            'origen'      => $origen,
            'descripcion' => $descripcion,
            'notas'       => $notas,
        ];
    }

    return $limpios;
}

/**
 * Limpia y filtra la estructura de modulaciones.
 *
 * @param array $modulaciones Datos recibidos.
 * @return array
 */
function wpss_sanitize_modulaciones_array( array $modulaciones ) {
    $limpias = [];

    foreach ( $modulaciones as $modulacion ) {
        $seccion = isset( $modulacion['seccion'] ) ? sanitize_text_field( $modulacion['seccion'] ) : '';
        $destino = isset( $modulacion['destino'] ) ? sanitize_text_field( $modulacion['destino'] ) : '';

        if ( '' === $seccion && '' === $destino ) {
            continue;
        }

        $limpias[] = [
            'seccion' => $seccion,
            'destino' => $destino,
        ];
    }

    return $limpias;
}

/**
 * Limpia y filtra la estructura de versos.
 *
 * @param array $versos Datos recibidos.
 * @return array
 */
function wpss_sanitize_versos_array( array $versos ) {
    $limpios = [];

    foreach ( $versos as $verso ) {
        $texto      = isset( $verso['texto'] ) ? sanitize_textarea_field( $verso['texto'] ) : '';
        $acorde     = isset( $verso['acorde'] ) ? sanitize_text_field( $verso['acorde'] ) : '';
        $comentario = isset( $verso['comentario'] ) ? sanitize_text_field( $verso['comentario'] ) : '';
        $orden      = isset( $verso['orden'] ) ? absint( $verso['orden'] ) : 0;

        if ( '' === $texto && '' === $acorde && '' === $comentario ) {
            continue;
        }

        $limpios[] = [
            'orden'      => $orden,
            'texto'      => $texto,
            'acorde'     => $acorde,
            'comentario' => $comentario,
        ];
    }

    return $limpios;
}

/**
 * Normaliza el orden de los versos para garantizar enteros consecutivos.
 *
 * @param array $versos Versos sanitizados.
 * @return array
 */
function wpss_normalize_versos_order( array $versos ) {
    if ( empty( $versos ) ) {
        return $versos;
    }

    usort(
        $versos,
        static function( $a, $b ) {
            return $a['orden'] <=> $b['orden'];
        }
    );

    $index = 1;
    foreach ( $versos as &$verso ) {
        $verso['orden'] = $index++;
    }

    return $versos;
}

/**
 * Reemplaza los versos asociados a una canción manteniendo consistencia.
 *
 * @param int   $post_id ID de la canción.
 * @param array $versos  Versos normalizados.
 * @return true|WP_Error
 */
function wpss_replace_cancion_versos( $post_id, array $versos ) {
    $existing = get_posts(
        [
            'post_type'        => 'verso',
            'numberposts'      => -1,
            'orderby'          => 'meta_value_num',
            'order'            => 'ASC',
            'meta_key'         => '_orden',
            'meta_query'       => [
                [
                    'key'   => '_cancion_id',
                    'value' => $post_id,
                ],
            ],
            'suppress_filters' => true,
        ]
    );

    $existing_ids = wp_list_pluck( $existing, 'ID' );

    foreach ( $versos as $index => $verso ) {
        $verso_post = [
            'post_type'   => 'verso',
            'post_status' => 'publish',
            'post_title'  => sprintf( __( 'Verso %1$d', 'wp-song-study' ), $index + 1 ),
            'post_content'=> $verso['texto'],
        ];

        if ( isset( $existing_ids[ $index ] ) ) {
            $verso_post['ID'] = $existing_ids[ $index ];
            $result           = wp_update_post( wp_slash( $verso_post ), true );
            unset( $existing_ids[ $index ] );
        } else {
            $result = wp_insert_post( wp_slash( $verso_post ), true );
        }

        if ( is_wp_error( $result ) || ! $result ) {
            return new WP_Error( 'wpss_rest_save_versos', __( 'No fue posible guardar los versos.', 'wp-song-study' ) );
        }

        $verso_id = is_int( $result ) ? $result : (int) $verso_post['ID'];

        update_post_meta( $verso_id, '_cancion_id', $post_id );
        update_post_meta( $verso_id, '_orden', $verso['orden'] );
        update_post_meta( $verso_id, '_acorde_absoluto', $verso['acorde'] );
        update_post_meta( $verso_id, '_funcion_relativa', $verso['comentario'] );
        update_post_meta( $verso_id, '_notas_verso', '' );

    }

    // Elimina versos sobrantes.
    if ( ! empty( $existing_ids ) ) {
        foreach ( $existing_ids as $remaining_id ) {
            wp_delete_post( $remaining_id, true );
        }
    }

    return true;
}
