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
add_filter( 'rest_authentication_errors', 'wpss_allow_public_rest', 0 );
add_filter( 'rest_authentication_errors', 'wpss_allow_public_rest_late', 999 );

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
                'tonica' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
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
                'coleccion' => [
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'wpss_validate_coleccion_exists',
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
                    'description'       => __( 'ID de la canción.', 'wp-song-study' ),
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'wpss_validate_positive_id',
                ],
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/public/canciones',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_canciones',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/public/cancion/(?P<id>\d+)',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_cancion_public',
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'description'       => __( 'ID de la canción.', 'wp-song-study' ),
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'wpss_validate_positive_id',
                ],
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/public/colecciones',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_colecciones',
            'permission_callback' => '__return_true',
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

    register_rest_route(
        'wpss/v1',
        '/campos-armonicos',
        [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'wpss_rest_get_campos_armonicos',
                'permission_callback' => 'wpss_rest_verify_permissions',
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'wpss_rest_save_campos_armonicos',
                'permission_callback' => 'wpss_rest_verify_permissions',
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/colecciones',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_colecciones',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/coleccion/(?P<id>\\d+)',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_coleccion',
            'permission_callback' => 'wpss_rest_verify_permissions',
            'args'                => [
                'id' => [
                    'description'       => __( 'ID de la colección.', 'wp-song-study' ),
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'wpss_validate_coleccion_exists',
                ],
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/coleccion',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'wpss_rest_save_coleccion',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/coleccion/(?P<id>\\d+)',
        [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'wpss_rest_delete_coleccion',
            'permission_callback' => 'wpss_rest_verify_permissions',
            'args'                => [
                'id' => [
                    'description'       => __( 'ID de la colección.', 'wp-song-study' ),
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'wpss_validate_coleccion_exists',
                ],
            ],
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
    $wp_nonce   = $request->get_header( 'x-wp-nonce' );
    $wpss_nonce = $request->get_header( 'x-wpss-nonce' );

    $wp_nonce_valid   = $wp_nonce && wp_verify_nonce( $wp_nonce, 'wp_rest' );
    $wpss_nonce_valid = $wpss_nonce && wp_verify_nonce( $wpss_nonce, 'wpss' );

    if ( ! $wp_nonce_valid && ! $wpss_nonce_valid ) {
        return new WP_Error( 'wpss_rest_invalid_nonce', __( 'Nonce inválido o ausente.', 'wp-song-study' ), [ 'status' => 403 ] );
    }

    $method = strtoupper( $request->get_method() );
    $capability = in_array( $method, [ 'GET', 'HEAD' ], true ) ? 'read' : 'edit_posts';

    if ( ! current_user_can( $capability ) ) {
        return new WP_Error( 'wpss_rest_forbidden', __( 'No tienes permisos suficientes para esta acción.', 'wp-song-study' ), [ 'status' => 403 ] );
    }

    return true;
}

/**
 * Permite acceso publico a rutas wpss/v1/public incluso si otros filtros bloquean REST.
 *
 * @param WP_Error|mixed $result Resultado previo de autenticacion.
 * @return WP_Error|mixed|null
 */
function wpss_allow_public_rest( $result ) {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ( false !== strpos( $uri, '/wp-json/wpss/v1/public/' ) ) {
        return null;
    }

    return $result;
}

/**
 * Reintenta abrir rutas publicas aunque otro filtro falle mas tarde.
 *
 * @param WP_Error|mixed $result Resultado previo de autenticacion.
 * @return WP_Error|mixed|null
 */
function wpss_allow_public_rest_late( $result ) {
    return wpss_allow_public_rest( $result );
}

/**
 * Valida que el parámetro ID sea un entero positivo.
 *
 * @param mixed           $value   Valor proporcionado para el parámetro.
 * @param WP_REST_Request $request Solicitud REST actual.
 * @param string          $param   Nombre del parámetro.
 * @return true|WP_Error
 */
function wpss_validate_positive_id( $value, WP_REST_Request $request, $param ) {
    if ( is_numeric( $value ) && (int) $value > 0 ) {
        return true;
    }

    return new WP_Error(
        'wpss_invalid_id',
        __( 'El parámetro id debe ser un entero positivo.', 'wp-song-study' ),
        [ 'status' => 400 ]
    );
}

/**
 * Valida que exista la colección solicitada.
 *
 * @param mixed           $value   Valor recibido.
 * @param WP_REST_Request $request Solicitud REST actual.
 * @param string          $param   Nombre del parámetro.
 * @return true|WP_Error
 */
function wpss_validate_coleccion_exists( $value, WP_REST_Request $request, $param ) {
    $term_id = absint( $value );

    if ( 0 === $term_id ) {
        return true;
    }

    $term = get_term( $term_id, 'coleccion' );

    if ( $term && ! is_wp_error( $term ) ) {
        return true;
    }

    return new WP_Error(
        'wpss_invalid_coleccion',
        __( 'La colección indicada no existe.', 'wp-song-study' ),
        [ 'status' => 404 ]
    );
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

    $meta_query = [];
    $tax_query  = [];

    $tonica = (string) $request->get_param( 'tonica' );
    if ( '' === $tonica ) {
        $tonica = (string) $request->get_param( 'tonalidad' );
    }

    if ( '' !== $tonica ) {
        $meta_query[] = [
            'key'   => '_tonica',
            'value' => $tonica,
        ];
    }

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

    $coleccion_id = absint( $request->get_param( 'coleccion' ) );

    $items       = [];
    $total_items = 0;
    $total_pages = 0;

    if ( $coleccion_id ) {
        $tax_query[] = [
            'taxonomy' => 'coleccion',
            'field'    => 'term_id',
            'terms'    => [ $coleccion_id ],
        ];

        $ordered_ids = wpss_get_coleccion_sorted_song_ids( $coleccion_id );

        if ( ! empty( $ordered_ids ) ) {
            $filter_args = [
                'post_type'      => 'cancion',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'post__in'       => $ordered_ids,
                'orderby'        => 'post__in',
                'posts_per_page' => count( $ordered_ids ),
            ];

            if ( ! empty( $meta_query ) ) {
                $filter_args['meta_query'] = $meta_query;
            }

            if ( ! empty( $tax_query ) ) {
                $filter_args['tax_query'] = $tax_query;
            }

            $filter_query = new WP_Query( $filter_args );
            $filtered_ids = array_map( 'intval', $filter_query->posts );
            wp_reset_postdata();

            if ( ! empty( $filtered_ids ) ) {
                $order_map = array_flip( $ordered_ids );
                usort(
                    $filtered_ids,
                    static function( $a, $b ) use ( $order_map ) {
                        $a_index = isset( $order_map[ $a ] ) ? $order_map[ $a ] : PHP_INT_MAX;
                        $b_index = isset( $order_map[ $b ] ) ? $order_map[ $b ] : PHP_INT_MAX;

                        if ( $a_index === $b_index ) {
                            return 0;
                        }

                        return ( $a_index < $b_index ) ? -1 : 1;
                    }
                );
            }
        } else {
            $filtered_ids = [];
        }

        $total_items = count( $filtered_ids );
        $total_pages = $per_page > 0 ? (int) ceil( $total_items / $per_page ) : 0;

        $offset   = ( $page - 1 ) * $per_page;
        $page_ids = array_slice( $filtered_ids, $offset, $per_page );

        foreach ( $page_ids as $post_id ) {
            $items[] = wpss_prepare_cancion_list_item( $post_id );
        }
    } else {
        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query( $args );

        foreach ( $query->posts as $post_id ) {
            $items[] = wpss_prepare_cancion_list_item( $post_id );
        }

        $total_items = (int) $query->found_posts;
        $total_pages = (int) $query->max_num_pages;

        wp_reset_postdata();
    }

    $response = rest_ensure_response( $items );
    $response->header( 'X-WP-Total', $total_items );
    $response->header( 'X-WP-TotalPages', $total_pages );

    return $response;
}

/**
 * Prepara la información base de una canción para listados REST.
 *
 * @param int $post_id ID de la canción.
 * @return array
 */
function wpss_prepare_cancion_list_item( $post_id ) {
    $post_id = (int) $post_id;

    $tonica_value         = sanitize_text_field( get_post_meta( $post_id, '_tonica', true ) );
    $campo_armonico_value = sanitize_text_field( get_post_meta( $post_id, '_campo_armonico', true ) );

    return [
        'id'                 => $post_id,
        'titulo'             => get_the_title( $post_id ),
        'tonica'             => $tonica_value,
        'tonalidad'          => $tonica_value,
        'campo_armonico'     => $campo_armonico_value,
        'tiene_prestamos'    => (bool) get_post_meta( $post_id, '_tiene_prestamos', true ),
        'tiene_modulaciones' => (bool) get_post_meta( $post_id, '_tiene_modulaciones', true ),
        'conteo_versos'      => (int) get_post_meta( $post_id, '_conteo_versos', true ),
        'colecciones'        => wpss_get_song_colecciones( $post_id ),
    ];
}

/**
 * Obtiene las colecciones asociadas a una canción.
 *
 * @param int $post_id ID de la canción.
 * @return array
 */
function wpss_get_song_colecciones( $post_id ) {
    $terms = wp_get_post_terms( $post_id, 'coleccion' );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return [];
    }

    $result = [];

    foreach ( $terms as $term ) {
        if ( ! $term instanceof WP_Term ) {
            continue;
        }

        $result[] = [
            'id'      => (int) $term->term_id,
            'nombre'  => $term->name,
            'descripcion' => $term->description,
        ];
    }

    return $result;
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
        return new WP_Error(
            'wpss_not_found',
            __( 'Canción no encontrada.', 'wp-song-study' ),
            [ 'status' => 404 ]
        );
    }

    $tonica                 = sanitize_text_field( get_post_meta( $post_id, '_tonica', true ) );
    $campo_armonico         = sanitize_text_field( get_post_meta( $post_id, '_campo_armonico', true ) );
    $campo_predominante     = sanitize_textarea_field( get_post_meta( $post_id, '_campo_armonico_predominante', true ) );
    $prestamos_cancion      = wpss_decode_json_meta( get_post_meta( $post_id, '_prestamos_tonales_json', true ) );
    $modulaciones_cancion   = wpss_decode_json_meta( get_post_meta( $post_id, '_modulaciones_json', true ) );

    $versos = wpss_get_cancion_versos( $post_id );
    list( $secciones, $versos ) = wpss_prepare_sections_for_output( $post_id, $versos );

    $estructura_meta         = get_post_meta( $post_id, '_estructura_json', true );
    $estructura               = wpss_get_default_estructura( $secciones );
    $estructura_personalizada = false;

    if ( ! empty( $estructura_meta ) ) {
        $estructura_raw = is_array( $estructura_meta ) ? $estructura_meta : wpss_decode_json_meta( $estructura_meta );
        $had_orphans    = false;
        $estructura_tmp = wpss_sanitize_estructura_array( $estructura_raw, $secciones, true, 'filter', $had_orphans );

        if ( $had_orphans ) {
            error_log( 'wpss: estructura con refs huérfanas en cancion ' . $post_id );
        }

        if ( is_array( $estructura_tmp ) && ! empty( $estructura_tmp ) ) {
            $es_default  = wpss_is_default_structure( $estructura_tmp, $secciones );
            $tiene_notas = wpss_estructura_has_annotations( $estructura_tmp );

            if ( $es_default && ! $tiene_notas ) {
                $estructura               = wpss_get_default_estructura( $secciones );
                $estructura_personalizada = false;
            } else {
                $estructura               = $estructura_tmp;
                $estructura_personalizada = true;
            }
        }
    }

    $data = [
        'id'                         => (int) $post_id,
        'titulo'                     => get_the_title( $post_id ),
        'tonica'                     => $tonica,
        'tonalidad'                  => $tonica,
        'campo_armonico'             => $campo_armonico,
        'campo_armonico_predominante'=> $campo_predominante,
        'prestamos_cancion'          => $prestamos_cancion,
        'modulaciones_cancion'       => $modulaciones_cancion,
        'prestamos'                  => $prestamos_cancion,
        'modulaciones'               => $modulaciones_cancion,
        'secciones'                  => $secciones,
        'versos'                     => $versos,
        'tiene_prestamos'            => (bool) get_post_meta( $post_id, '_tiene_prestamos', true ),
        'tiene_modulaciones'         => (bool) get_post_meta( $post_id, '_tiene_modulaciones', true ),
        'colecciones'                => wpss_get_song_colecciones( $post_id ),
        'estructura'                 => $estructura,
        'estructura_personalizada'   => $estructura_personalizada,
    ];

    return rest_ensure_response( $data );
}

/**
 * Devuelve una canción publicada para consumo público.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response|WP_Error
 */
function wpss_rest_get_cancion_public( WP_REST_Request $request ) {
    $post_id = (int) $request->get_param( 'id' );
    $post    = get_post( $post_id );

    if ( ! $post || 'cancion' !== $post->post_type || 'publish' !== $post->post_status ) {
        return new WP_Error(
            'wpss_not_found',
            __( 'Canción no encontrada.', 'wp-song-study' ),
            [ 'status' => 404 ]
        );
    }

    return wpss_rest_get_cancion( $request );
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

    $id     = isset( $params['id'] ) ? absint( $params['id'] ) : 0;
    $titulo = isset( $params['titulo'] ) ? sanitize_text_field( $params['titulo'] ) : '';

    $tonica = '';
    if ( isset( $params['tonica'] ) ) {
        $tonica = sanitize_text_field( $params['tonica'] );
    } elseif ( isset( $params['tonalidad'] ) ) {
        $tonica = sanitize_text_field( $params['tonalidad'] );
    }

    $campo_armonico = isset( $params['campo_armonico'] ) ? sanitize_text_field( $params['campo_armonico'] ) : '';
    $campo_armonico_predominante = isset( $params['campo_armonico_predominante'] )
        ? sanitize_textarea_field( $params['campo_armonico_predominante'] )
        : ( isset( $params['campo_armonico'] ) ? sanitize_textarea_field( $params['campo_armonico'] ) : '' );

    if ( '' === $titulo ) {
        return new WP_REST_Response( [ 'message' => __( 'El título es obligatorio.', 'wp-song-study' ) ], 400 );
    }

    if ( '' === $tonica ) {
        return new WP_REST_Response( [ 'message' => __( 'La tónica es obligatoria.', 'wp-song-study' ) ], 400 );
    }

    $prestamos_raw = [];
    if ( isset( $params['prestamos_cancion'] ) ) {
        $prestamos_raw = (array) $params['prestamos_cancion'];
    } elseif ( isset( $params['prestamos'] ) ) {
        $prestamos_raw = (array) $params['prestamos'];
    }

    $modulaciones_raw = [];
    if ( isset( $params['modulaciones_cancion'] ) ) {
        $modulaciones_raw = (array) $params['modulaciones_cancion'];
    } elseif ( isset( $params['modulaciones'] ) ) {
        $modulaciones_raw = (array) $params['modulaciones'];
    }

    $prestamos    = wpss_sanitize_prestamos_array( $prestamos_raw );
    $modulaciones = wpss_sanitize_modulaciones_array( $modulaciones_raw );

    $secciones_input = isset( $params['secciones'] ) ? (array) $params['secciones'] : [];
    $secciones       = wpss_sanitize_secciones_array( $secciones_input );

    $input_name_map = [];
    foreach ( $secciones_input as $seccion_input ) {
        if ( $seccion_input instanceof Traversable ) {
            $seccion_input = iterator_to_array( $seccion_input );
        } elseif ( is_object( $seccion_input ) ) {
            $seccion_input = get_object_vars( $seccion_input );
        }

        if ( ! is_array( $seccion_input ) ) {
            continue;
        }

        $input_id = isset( $seccion_input['id'] ) ? sanitize_key( $seccion_input['id'] ) : '';
        if ( '' === $input_id ) {
            continue;
        }

        $input_name = isset( $seccion_input['nombre'] ) ? trim( sanitize_text_field( $seccion_input['nombre'] ) ) : '';
        $input_name_map[ $input_id ] = $input_name;
    }

    if ( $id ) {
        $prev_sections = wpss_sanitize_secciones_array(
            wpss_decode_json_meta( get_post_meta( $id, '_secciones_json', true ) )
        );
        $prev_name_map = [];
        foreach ( $prev_sections as $prev ) {
            if ( ! empty( $prev['id'] ) && ! empty( $prev['nombre'] ) ) {
                $prev_name_map[ $prev['id'] ] = $prev['nombre'];
            }
        }

        if ( $prev_name_map ) {
            foreach ( $secciones as &$seccion ) {
                if ( empty( $seccion['id'] ) ) {
                    continue;
                }

                $incoming_name = isset( $input_name_map[ $seccion['id'] ] ) ? $input_name_map[ $seccion['id'] ] : '';
                if ( '' === $incoming_name && isset( $prev_name_map[ $seccion['id'] ] ) ) {
                    $seccion['nombre'] = $prev_name_map[ $seccion['id'] ];
                }
            }
            unset( $seccion );
        }
    }
    $section_ids     = wp_list_pluck( $secciones, 'id' );

    $estructura_input = [];
    if ( array_key_exists( 'estructura', $params ) && is_array( $params['estructura'] ) ) {
        $estructura_input = $params['estructura'];
    }

    $estructura_personalizada_flag = false;
    if ( array_key_exists( 'estructura_personalizada', $params ) ) {
        $estructura_personalizada_flag = (bool) $params['estructura_personalizada'];
    } elseif ( array_key_exists( 'estructuraPersonalizada', $params ) ) {
        $estructura_personalizada_flag = (bool) $params['estructuraPersonalizada'];
    } elseif ( array_key_exists( 'estructura', $params ) ) {
        $estructura_personalizada_flag = true;
    }

    $versos = wpss_sanitize_versos_array(
        isset( $params['versos'] ) ? (array) $params['versos'] : [],
        $section_ids
    );

    if ( is_wp_error( $versos ) ) {
        return new WP_REST_Response( [ 'message' => $versos->get_error_message() ], 400 );
    }

    list( $secciones, $versos ) = wpss_ensure_sections_for_versos( $secciones, $versos );

    $estructura_sanitizada = wpss_sanitize_estructura_array( $estructura_input, $secciones, $estructura_personalizada_flag );
    if ( is_wp_error( $estructura_sanitizada ) ) {
        return new WP_REST_Response( [ 'message' => $estructura_sanitizada->get_error_message() ], 400 );
    }

    $estructura_default = wpss_get_default_estructura( $secciones );
    $estructura_es_default = wpss_is_default_structure( $estructura_sanitizada, $secciones );
    $estructura_tiene_notas = wpss_estructura_has_annotations( $estructura_sanitizada );

    if ( ! $estructura_personalizada_flag || ( $estructura_es_default && ! $estructura_tiene_notas ) ) {
        $estructura_sanitizada        = $estructura_default;
        $estructura_personalizada_flag = false;
    } else {
        $estructura_personalizada_flag = true;
    }

    $versos = wpss_apply_legacy_stanza_markers( $versos, $secciones );

    $versos = wpss_normalize_versos_order( $versos );

    if ( $id ) {
        $existing_post = get_post( $id );
        if ( ! $existing_post || 'cancion' !== $existing_post->post_type ) {
            return new WP_REST_Response( [ 'message' => __( 'Canción no encontrada.', 'wp-song-study' ) ], 404 );
        }

        if ( ! current_user_can( 'edit_post', $id ) ) {
            return new WP_REST_Response( [ 'message' => __( 'No tienes permisos para editar esta canción.', 'wp-song-study' ) ], 403 );
        }
    }

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

    wpss_assign_tonalidad_term( $post_id, $tonica );

    update_post_meta( $post_id, '_tonica', $tonica );
    update_post_meta( $post_id, '_campo_armonico', $campo_armonico );
    update_post_meta( $post_id, '_campo_armonico_predominante', $campo_armonico_predominante );
    update_post_meta( $post_id, '_prestamos_tonales_json', wp_json_encode( $prestamos ) );
    update_post_meta( $post_id, '_modulaciones_json', wp_json_encode( $modulaciones ) );

    $versos_result = wpss_replace_cancion_versos( $post_id, $versos );
    if ( is_wp_error( $versos_result ) ) {
        return new WP_REST_Response( [ 'message' => $versos_result->get_error_message() ], 500 );
    }

    $secciones_json = wp_json_encode( $secciones, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( false === $secciones_json ) {
        $secciones_json = wp_json_encode( $secciones );
    }

    update_post_meta( $post_id, '_secciones_json', $secciones_json );

    if ( $estructura_personalizada_flag ) {
        $estructura_json = wp_json_encode( $estructura_sanitizada, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === $estructura_json ) {
            $estructura_json = wp_json_encode( $estructura_sanitizada );
        }

        update_post_meta( $post_id, '_estructura_json', $estructura_json );
    } else {
        delete_post_meta( $post_id, '_estructura_json' );
        $estructura_sanitizada = $estructura_default;
    }

    $tiene_prestamos    = wpss_calculate_song_has_prestamos( $prestamos, $versos );
    $tiene_modulaciones = wpss_calculate_song_has_modulaciones( $modulaciones, $versos );

    update_post_meta( $post_id, '_tiene_prestamos', $tiene_prestamos ? 1 : 0 );
    update_post_meta( $post_id, '_tiene_modulaciones', $tiene_modulaciones ? 1 : 0 );
    update_post_meta( $post_id, '_conteo_versos', count( $versos ) );

    $colecciones_ids = wpss_sanitize_coleccion_ids( isset( $params['colecciones'] ) ? $params['colecciones'] : [] );

    $previas = wp_get_post_terms( $post_id, 'coleccion', [ 'fields' => 'ids' ] );
    if ( is_wp_error( $previas ) ) {
        $previas = [];
    }

    $previas = array_map( 'intval', $previas );

    wp_set_post_terms( $post_id, $colecciones_ids, 'coleccion', false );

    foreach ( $colecciones_ids as $coleccion_id ) {
        wpss_append_song_to_coleccion_order( $coleccion_id, $post_id );
    }

    foreach ( $previas as $coleccion_id ) {
        if ( ! in_array( $coleccion_id, $colecciones_ids, true ) ) {
            wpss_remove_song_from_coleccion_order( $coleccion_id, $post_id );
        }
    }

    $response = [
        'ok'                 => true,
        'id'                 => (int) $post_id,
        'tiene_prestamos'    => $tiene_prestamos,
        'tiene_modulaciones' => $tiene_modulaciones,
        'secciones'          => $secciones,
        'estructura'         => $estructura_sanitizada,
        'estructura_personalizada' => (bool) $estructura_personalizada_flag,
        'colecciones'        => wpss_get_song_colecciones( $post_id ),
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
    if ( empty( $value ) ) {
        return [];
    }

    if ( is_array( $value ) ) {
        return $value;
    }

    $candidates = [];

    $maybe_add_candidate = static function ( $candidate ) use ( &$candidates ) {
        if ( ! is_string( $candidate ) || '' === $candidate ) {
            return;
        }

        $candidates[] = $candidate;

        $unslashed = wp_unslash( $candidate );
        if ( $unslashed !== $candidate ) {
            $candidates[] = $unslashed;
        }
    };

    $maybe_add_candidate( $value );

    $maybe_unserialized = maybe_unserialize( $value );
    if ( $maybe_unserialized !== $value ) {
        if ( is_array( $maybe_unserialized ) ) {
            return $maybe_unserialized;
        }

        $maybe_add_candidate( $maybe_unserialized );
    }

    foreach ( $candidates as $candidate ) {
        $decoded = json_decode( $candidate, true );

        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            return $decoded;
        }
    }

    return [];
}

/**
 * Obtiene y rehidrata los segmentos almacenados en meta evitando falsos positivos.
 *
 * @param int $verso_id ID del verso.
 * @return array|null Array de segmentos o null si debe usarse la ruta retrocompatible.
 */
function wpss_get_segmentos_from_meta( $verso_id ) {
    $raw = get_post_meta( $verso_id, '_segmentos_json', true );

    if ( empty( $raw ) ) {
        return null;
    }

    if ( is_array( $raw ) ) {
        return $raw;
    }

    $candidates = [];

    if ( is_string( $raw ) ) {
        $candidates[] = $raw;
    }

    $maybe_unserialized = maybe_unserialize( $raw );
    if ( $maybe_unserialized !== $raw ) {
        if ( is_array( $maybe_unserialized ) ) {
            return $maybe_unserialized;
        }

        if ( is_string( $maybe_unserialized ) ) {
            $candidates[] = $maybe_unserialized;
        }
    }

    $unslashed = wp_unslash( $raw );
    if ( $unslashed !== $raw ) {
        if ( is_array( $unslashed ) ) {
            return $unslashed;
        }

        if ( is_string( $unslashed ) ) {
            $candidates[] = $unslashed;
        }
    }

    foreach ( $candidates as $candidate ) {
        $decoded = json_decode( $candidate, true );

        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            return $decoded;
        }
    }

    error_log( 'wpss: segmentos corruptos en verso ' . $verso_id );

    return null;
}

/**
 * Limita la longitud de una cadena respetando caracteres multibyte cuando es posible.
 *
 * @param string $value  Cadena a truncar.
 * @param int    $length Longitud máxima.
 * @return string
 */
function wpss_truncate_string( $value, $length = 64 ) {
    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $value, 0, $length );
    }

    return substr( $value, 0, $length );
}

/**
 * Sanitiza y normaliza un segmento texto-acorde individual.
 *
 * @param array $segmento Segmento recibido.
 * @return array|null
 */
function wpss_normalize_segmento_item( $segmento ) {
    if ( ! is_array( $segmento ) ) {
        return null;
    }

    $texto  = isset( $segmento['texto'] ) ? sanitize_textarea_field( $segmento['texto'] ) : '';
    $acorde = isset( $segmento['acorde'] ) ? sanitize_text_field( $segmento['acorde'] ) : '';

    if ( '' !== $acorde ) {
        $acorde = wpss_truncate_string( $acorde, 64 );
    }

    if ( '' === $texto && '' === $acorde ) {
        return null;
    }

    return [
        'texto'  => $texto,
        'acorde' => $acorde,
    ];
}

/**
 * Sanitiza un arreglo de segmentos texto-acorde.
 *
 * @param array $segmentos Segmentos recibidos.
 * @return array
 */
function wpss_sanitize_segmentos_array( array $segmentos ) {
    $limpios = [];

    foreach ( $segmentos as $segmento ) {
        $normalizado = wpss_normalize_segmento_item( $segmento );
        if ( null === $normalizado ) {
            continue;
        }

        $limpios[] = $normalizado;
    }

    return $limpios;
}

/**
 * Concatena el texto de los segmentos aplicando una normalización mínima de espacios finales.
 *
 * @param array $segmentos Segmentos sanitizados.
 * @return string
 */
function wpss_implode_segmentos_text( array $segmentos ) {
    $texto = '';

    foreach ( $segmentos as $segmento ) {
        $texto .= isset( $segmento['texto'] ) ? $segmento['texto'] : '';
    }

    if ( '' === $texto ) {
        return '';
    }

    $texto = preg_replace( "/[ \t]+(\r?\n)/", '$1', $texto );

    return rtrim( $texto );
}

/**
 * Genera un identificador único para secciones nuevas.
 *
 * @return string
 */
function wpss_generate_unique_section_id() {
    $id = sanitize_key( uniqid( 'sec-', false ) );

    if ( '' === $id ) {
        $id = 'sec-' . wp_rand( 1000, 9999 );
    }

    return $id;
}

/**
 * Normaliza el arreglo de secciones asegurando IDs únicos y nombres válidos.
 *
 * @param array $secciones Secciones recibidas.
 * @return array
 */
function wpss_sanitize_secciones_array( array $secciones ) {
    $normalizadas = [];
    $ids_usados   = [];

    foreach ( $secciones as $index => $seccion ) {
        if ( $seccion instanceof Traversable ) {
            $seccion = iterator_to_array( $seccion );
        } elseif ( is_object( $seccion ) ) {
            $seccion = get_object_vars( $seccion );
        }

        if ( ! is_array( $seccion ) ) {
            continue;
        }

        $id = '';
        if ( isset( $seccion['id'] ) ) {
            $id = sanitize_key( $seccion['id'] );
        }

        if ( '' === $id ) {
            $id = wpss_generate_unique_section_id();
        }

        while ( isset( $ids_usados[ $id ] ) ) {
            $id = wpss_generate_unique_section_id();
        }

        $nombre = isset( $seccion['nombre'] ) ? sanitize_text_field( $seccion['nombre'] ) : '';
        $nombre = wpss_truncate_string( $nombre, 64 );

        if ( '' === $nombre ) {
            /* translators: %d: section number */
            $nombre = sprintf( __( 'Sección %d', 'wp-song-study' ), count( $normalizadas ) + 1 );
        }

        $ids_usados[ $id ]   = true;
        $normalizadas[] = [
            'id'     => $id,
            'nombre' => $nombre,
        ];
    }

    return $normalizadas;
}

/**
 * Genera la estructura por defecto a partir del orden de las secciones.
 *
 * @param array $secciones Secciones sanitizadas.
 * @return array
 */
function wpss_get_default_estructura( array $secciones ) {
    $estructura = [];

    foreach ( $secciones as $seccion ) {
        if ( $seccion instanceof Traversable ) {
            $seccion = iterator_to_array( $seccion );
        } elseif ( is_object( $seccion ) ) {
            $seccion = get_object_vars( $seccion );
        }

        if ( ! is_array( $seccion ) ) {
            continue;
        }

        $id = isset( $seccion['id'] ) ? sanitize_key( $seccion['id'] ) : '';

        if ( '' === $id ) {
            continue;
        }

        $estructura[] = [ 'ref' => $id ];
    }

    return $estructura;
}

/**
 * Determina si la estructura contiene anotaciones adicionales.
 *
 * @param array $estructura Estructura sanitizada.
 * @return bool
 */
function wpss_estructura_has_annotations( array $estructura ) {
    foreach ( $estructura as $llamada ) {
        if ( ! is_array( $llamada ) ) {
            continue;
        }

        if ( ! empty( $llamada['variante'] ) || ! empty( $llamada['notas'] ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Sanitiza y valida la estructura declarada para una canción.
 *
 * @param array $estructura          Datos recibidos.
 * @param array $secciones           Secciones disponibles.
 * @param bool  $personalizada       Si se trata de una estructura personalizada.
 * @param string $on_invalid         Estrategia frente a refs inválidas: "error" o "filter".
 * @param bool|null $had_orphans     Parámetro de salida para indicar si hubo referencias huérfanas.
 * @return array|WP_Error
 */
function wpss_sanitize_estructura_array( $estructura, array $secciones, $personalizada = true, $on_invalid = 'error', &$had_orphans = null ) {
    if ( ! is_array( $estructura ) ) {
        $estructura = [];
    }

    $default = wpss_get_default_estructura( $secciones );
    $valid_ids = [];

    foreach ( $secciones as $seccion ) {
        if ( ! is_array( $seccion ) ) {
            continue;
        }

        $id = isset( $seccion['id'] ) ? sanitize_key( $seccion['id'] ) : '';

        if ( '' === $id ) {
            continue;
        }

        $valid_ids[ $id ] = true;
    }

    $sanitized   = [];
    $had_orphans = false;

    foreach ( $estructura as $item ) {
        if ( ! is_array( $item ) ) {
            if ( 'filter' === $on_invalid ) {
                $had_orphans = true;
                continue;
            }

            if ( ! $personalizada ) {
                return $default;
            }

            return new WP_Error(
                'wpss_invalid_estructura_item',
                __( 'Cada elemento de la estructura debe incluir una referencia válida a la sección.', 'wp-song-study' )
            );
        }

        $ref = isset( $item['ref'] ) ? sanitize_key( $item['ref'] ) : '';

        if ( '' === $ref || ! isset( $valid_ids[ $ref ] ) ) {
            if ( 'filter' === $on_invalid ) {
                $had_orphans = true;
                continue;
            }

            if ( ! $personalizada ) {
                return $default;
            }

            return new WP_Error(
                'wpss_invalid_estructura_ref',
                __( 'La estructura incluye referencias a secciones inexistentes.', 'wp-song-study' )
            );
        }

        $entrada = [ 'ref' => $ref ];

        if ( isset( $item['variante'] ) ) {
            $variant = sanitize_text_field( $item['variante'] );
            $variant = wpss_truncate_string( $variant, 16 );

            if ( '' !== $variant ) {
                $entrada['variante'] = $variant;
            }
        }

        if ( isset( $item['notas'] ) ) {
            $notes = sanitize_text_field( $item['notas'] );
            $notes = wpss_truncate_string( $notes, 128 );

            if ( '' !== $notes ) {
                $entrada['notas'] = $notes;
            }
        }

        $sanitized[] = $entrada;
    }

    if ( ! $personalizada ) {
        return $default;
    }

    if ( empty( $sanitized ) ) {
        return $default;
    }

    return array_values( $sanitized );
}

/**
 * Determina si la estructura coincide con el orden por defecto de las secciones.
 *
 * @param array $estructura Estructura sanitizada.
 * @param array $secciones  Secciones disponibles.
 * @return bool
 */
function wpss_is_default_structure( array $estructura, array $secciones ) {
    $default_refs    = array_map(
        static function( $seccion ) {
            return isset( $seccion['id'] ) ? sanitize_key( $seccion['id'] ) : '';
        },
        $secciones
    );
    $estructura_refs = array_map(
        static function( $llamada ) {
            return isset( $llamada['ref'] ) ? sanitize_key( $llamada['ref'] ) : '';
        },
        $estructura
    );

    return $estructura_refs === $default_refs;
}

/**
 * Deriva secciones y asignaciones de versos a partir de los campos legacy.
 *
 * @param array $versos Versos con datos legacy.
 * @return array[] Array con dos elementos: secciones y versos actualizados.
 */
function wpss_derive_sections_from_versos( array $versos ) {
    if ( empty( $versos ) ) {
        return [ [], $versos ];
    }

    $secciones       = [];
    $asignaciones    = [];
    $seccion_indice  = 1;
    $seccion_actual  = 'sec-1';
    $seccion_nombre  = __( 'Sección 1', 'wp-song-study' );
    $secciones[]     = [
        'id'     => $seccion_actual,
        'nombre' => $seccion_nombre,
    ];
    $asignaciones[ $seccion_actual ] = 0;

    foreach ( $versos as &$verso ) {
        $verso['section_id'] = $seccion_actual;
        $asignaciones[ $seccion_actual ]++;

        if ( empty( $verso['fin_de_estrofa'] ) ) {
            continue;
        }

        $seccion_indice++;
        $seccion_actual = 'sec-' . $seccion_indice;

        $nombre_sugerido = isset( $verso['nombre_estrofa'] ) ? (string) $verso['nombre_estrofa'] : '';
        $nombre_sugerido = sanitize_text_field( $nombre_sugerido );
        $nombre_sugerido = wpss_truncate_string( $nombre_sugerido, 64 );

        if ( '' === $nombre_sugerido ) {
            /* translators: %d: section number */
            $nombre_sugerido = sprintf( __( 'Sección %d', 'wp-song-study' ), $seccion_indice );
        }

        $secciones[] = [
            'id'     => $seccion_actual,
            'nombre' => $nombre_sugerido,
        ];
        $asignaciones[ $seccion_actual ] = 0;
    }
    unset( $verso );

    $secciones = array_values(
        array_filter(
            $secciones,
            static function( $seccion ) use ( $asignaciones ) {
                return ! empty( $asignaciones[ $seccion['id'] ] );
            }
        )
    );

    if ( empty( $secciones ) ) {
        $secciones = [
            [
                'id'     => 'sec-1',
                'nombre' => __( 'Sección 1', 'wp-song-study' ),
            ],
        ];

        foreach ( $versos as &$verso ) {
            $verso['section_id'] = 'sec-1';
        }
        unset( $verso );
    }

    return [ $secciones, $versos ];
}

/**
 * Garantiza que existan secciones válidas y que los versos apunten a ellas.
 *
 * @param array $secciones Secciones sanitizadas.
 * @param array $versos    Versos sanitizados.
 * @return array[]
 */
function wpss_ensure_sections_for_versos( array $secciones, array $versos ) {
    if ( empty( $secciones ) ) {
        return wpss_derive_sections_from_versos( $versos );
    }

    $ids = wp_list_pluck( $secciones, 'id' );

    if ( empty( $ids ) ) {
        return wpss_derive_sections_from_versos( $versos );
    }

    $primera = $ids[0];

    foreach ( $versos as &$verso ) {
        $section_id = isset( $verso['section_id'] ) ? sanitize_key( $verso['section_id'] ) : '';
        if ( '' === $section_id || ! in_array( $section_id, $ids, true ) ) {
            $section_id = $primera;
        }
        $verso['section_id'] = $section_id;
    }
    unset( $verso );

    return [ $secciones, $versos ];
}

/**
 * Rellena los campos legacy de fin de estrofa y nombre derivados de las secciones.
 *
 * @param array $versos     Versos con section_id.
 * @param array $secciones  Secciones ordenadas.
 * @return array
 */
function wpss_apply_legacy_stanza_markers( array $versos, array $secciones ) {
    if ( empty( $versos ) ) {
        return $versos;
    }

    foreach ( $versos as &$verso ) {
        $verso['fin_de_estrofa'] = false;
        $verso['nombre_estrofa'] = '';
    }
    unset( $verso );

    foreach ( $secciones as $index => $seccion ) {
        $id = $seccion['id'];
        $versos_seccion = array_keys(
            array_filter(
                $versos,
                static function( $verso ) use ( $id ) {
                    return isset( $verso['section_id'] ) && $verso['section_id'] === $id;
                }
            )
        );

        if ( empty( $versos_seccion ) ) {
            continue;
        }

        $ultimo_indice = end( $versos_seccion );
        if ( false === $ultimo_indice ) {
            continue;
        }

        if ( isset( $versos[ $ultimo_indice ] ) ) {
            $versos[ $ultimo_indice ]['fin_de_estrofa'] = ( $index < count( $secciones ) - 1 );
            if ( $index < count( $secciones ) - 1 ) {
                $versos[ $ultimo_indice ]['nombre_estrofa'] = $secciones[ $index + 1 ]['nombre'];
            }
        }
    }

    return $versos;
}

/**
 * Sanitiza un arreglo de colecciones recibido vía REST.
 *
 * @param mixed $colecciones Datos recibidos.
 * @return int[]
 */
function wpss_sanitize_coleccion_ids( $colecciones ) {
    if ( ! is_array( $colecciones ) ) {
        return [];
    }

    $ids = [];

    foreach ( $colecciones as $value ) {
        if ( is_array( $value ) && isset( $value['id'] ) ) {
            $value = $value['id'];
        }

        $term_id = absint( $value );

        if ( $term_id <= 0 ) {
            continue;
        }

        $term = get_term( $term_id, 'coleccion' );
        if ( $term && ! is_wp_error( $term ) && ! in_array( $term_id, $ids, true ) ) {
            $ids[] = $term_id;
        }
    }

    return $ids;
}

/**
 * Obtiene la lista de colecciones registradas.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_get_colecciones( WP_REST_Request $request ) {
    $terms = get_terms(
        [
            'taxonomy'   => 'coleccion',
            'hide_empty' => false,
        ]
    );

    if ( is_wp_error( $terms ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No fue posible obtener las colecciones.', 'wp-song-study' ) ], 500 );
    }

    $items = [];

    foreach ( $terms as $term ) {
        if ( $term instanceof WP_Term ) {
            $items[] = wpss_prepare_coleccion_for_response( $term );
        }
    }

    return rest_ensure_response( $items );
}

/**
 * Devuelve el detalle de una colección específica.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_get_coleccion( WP_REST_Request $request ) {
    $term_id = absint( $request->get_param( 'id' ) );
    $term    = get_term( $term_id, 'coleccion' );

    if ( ! $term || is_wp_error( $term ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Colección no encontrada.', 'wp-song-study' ) ], 404 );
    }

    return rest_ensure_response( wpss_prepare_coleccion_for_response( $term, true ) );
}

/**
 * Crea o actualiza una colección.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_save_coleccion( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }

    $term_id     = isset( $params['id'] ) ? absint( $params['id'] ) : 0;
    $nombre      = isset( $params['nombre'] ) ? sanitize_text_field( $params['nombre'] ) : '';
    $descripcion = isset( $params['descripcion'] ) ? sanitize_textarea_field( $params['descripcion'] ) : '';
    $orden_raw   = isset( $params['orden'] ) ? (array) $params['orden'] : [];

    if ( '' === $nombre ) {
        return new WP_REST_Response( [ 'message' => __( 'El nombre de la colección es obligatorio.', 'wp-song-study' ) ], 400 );
    }

    $nombre_length = function_exists( 'mb_strlen' ) ? mb_strlen( $nombre ) : strlen( $nombre );
    if ( $nombre_length > 128 ) {
        return new WP_REST_Response( [ 'message' => __( 'El nombre de la colección no debe exceder 128 caracteres.', 'wp-song-study' ) ], 400 );
    }

    $orden = wpss_normalize_coleccion_order_ids( $orden_raw );

    if ( $term_id ) {
        $existing = get_term( $term_id, 'coleccion' );
        if ( ! $existing || is_wp_error( $existing ) ) {
            return new WP_REST_Response( [ 'message' => __( 'Colección no encontrada.', 'wp-song-study' ) ], 404 );
        }

        $update_args = [
            'name'        => $nombre,
            'description' => $descripcion,
        ];

        $result = wp_update_term( $term_id, 'coleccion', wp_slash( $update_args ) );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => __( 'No fue posible actualizar la colección.', 'wp-song-study' ) ], 500 );
        }

        $term_id = (int) $result['term_id'];
    } else {
        $insert_args = [
            'description' => $descripcion,
        ];

        $result = wp_insert_term( $nombre, 'coleccion', wp_slash( $insert_args ) );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'message' => __( 'No fue posible crear la colección.', 'wp-song-study' ) ], 400 );
        }

        $term_id = (int) $result['term_id'];
    }

    wpss_update_coleccion_order( $term_id, $orden );

    $current = get_objects_in_term( $term_id, 'coleccion', [ 'fields' => 'ids' ] );
    if ( is_wp_error( $current ) ) {
        $current = [];
    }

    $current = array_map( 'intval', $current );

    foreach ( $current as $post_id ) {
        if ( ! in_array( $post_id, $orden, true ) ) {
            wp_remove_object_terms( $post_id, $term_id, 'coleccion' );
        }
    }

    foreach ( $orden as $post_id ) {
        wp_set_object_terms( $post_id, [ $term_id ], 'coleccion', true );
    }

    $term = get_term( $term_id, 'coleccion' );

    return rest_ensure_response( wpss_prepare_coleccion_for_response( $term, true ) );
}

/**
 * Elimina una colección existente.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_delete_coleccion( WP_REST_Request $request ) {
    $term_id = absint( $request->get_param( 'id' ) );

    delete_term_meta( $term_id, '_orden_ids' );

    $result = wp_delete_term( $term_id, 'coleccion' );

    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No fue posible eliminar la colección.', 'wp-song-study' ) ], 500 );
    }

    return rest_ensure_response(
        [
            'deleted' => true,
            'id'      => $term_id,
        ]
    );
}

/**
 * Prepara los datos de una colección para la salida REST.
 *
 * @param WP_Term $term           Término base.
 * @param bool    $include_items  Incluir la lista ordenada de canciones.
 * @return array
 */
function wpss_prepare_coleccion_for_response( WP_Term $term, $include_items = false ) {
    $term_id = (int) $term->term_id;

    $orden = wpss_get_coleccion_sorted_song_ids( $term_id );

    $data = [
        'id'           => $term_id,
        'nombre'       => $term->name,
        'descripcion'  => $term->description,
        'items_count'  => count( $orden ),
    ];

    if ( $include_items ) {
        $items = [];

        foreach ( $orden as $post_id ) {
            $items[] = [
                'id'     => (int) $post_id,
                'titulo' => get_the_title( $post_id ),
            ];
        }

        $data['orden'] = $orden;
        $data['items'] = $items;
    }

    return $data;
}

/**
 * Prepara secciones y versos para la salida REST combinando metadatos y compatibilidad legacy.
 *
 * @param int   $post_id ID de la canción.
 * @param array $versos  Versos base.
 * @return array[]
 */
function wpss_prepare_sections_for_output( $post_id, array $versos ) {
    $secciones_meta = wpss_decode_json_meta( get_post_meta( $post_id, '_secciones_json', true ) );
    $secciones      = wpss_sanitize_secciones_array( $secciones_meta );

    list( $secciones, $versos_actualizados ) = wpss_ensure_sections_for_versos( $secciones, $versos );
    $versos_actualizados = wpss_apply_legacy_stanza_markers( $versos_actualizados, $secciones );

    return [ $secciones, $versos_actualizados ];
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
        $segmentos_meta = wpss_get_segmentos_from_meta( $verso->ID );
        $segmentos      = [];

        if ( ! empty( $segmentos_meta ) ) {
            $segmentos = wpss_sanitize_segmentos_array( (array) $segmentos_meta );
        }

        if ( empty( $segmentos ) ) {
            $texto_verso  = sanitize_textarea_field( $verso->post_content );
            $acorde_verso = sanitize_text_field( get_post_meta( $verso->ID, '_acorde_absoluto', true ) );

            if ( '' !== $acorde_verso ) {
                $acorde_verso = wpss_truncate_string( $acorde_verso, 64 );
            }

            if ( '' !== $texto_verso || '' !== $acorde_verso ) {
                $segmentos = [
                    [
                        'texto'  => $texto_verso,
                        'acorde' => $acorde_verso,
                    ],
                ];
            }
        }

        if ( empty( $segmentos ) ) {
            $segmentos = [
                [
                    'texto'  => '',
                    'acorde' => '',
                ],
            ];
        }

        $evento_raw     = wpss_decode_json_meta( get_post_meta( $verso->ID, '_evento_armonico_json', true ) );
        $evento         = wpss_sanitize_evento_armonico( $evento_raw );
        if ( $evento && isset( $evento['segment_index'] ) ) {
            $segment_index = (int) $evento['segment_index'];
            if ( $segment_index < 0 || $segment_index >= count( $segmentos ) ) {
                unset( $evento['segment_index'] );
            }
        }
        $comentario     = sanitize_text_field( get_post_meta( $verso->ID, '_funcion_relativa', true ) );
        $section_id     = sanitize_key( get_post_meta( $verso->ID, '_section_id', true ) );
        $fin_de_estrofa = (bool) absint( get_post_meta( $verso->ID, '_fin_de_estrofa', true ) );
        $nombre_estrofa = sanitize_text_field( get_post_meta( $verso->ID, '_nombre_estrofa', true ) );
        $nombre_estrofa = wpss_truncate_string( $nombre_estrofa, 64 );
        $texto_base     = wpss_implode_segmentos_text( $segmentos );
        $acorde         = isset( $segmentos[0]['acorde'] ) ? $segmentos[0]['acorde'] : '';

        $data[] = [
            'id'              => (int) $verso->ID,
            'orden'           => (int) get_post_meta( $verso->ID, '_orden', true ),
            'texto'           => $texto_base,
            'acorde'          => $acorde,
            'segmentos'       => $segmentos,
            'comentario'      => $comentario,
            'evento_armonico' => $evento,
            'section_id'      => $section_id,
            'fin_de_estrofa'  => $fin_de_estrofa,
            'nombre_estrofa'  => $nombre_estrofa,
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
        $seccion          = isset( $modulacion['seccion'] ) ? sanitize_text_field( $modulacion['seccion'] ) : '';
        $destino          = isset( $modulacion['destino'] ) ? sanitize_text_field( $modulacion['destino'] ) : '';
        $destino_tonica   = isset( $modulacion['destino_tonica'] ) ? sanitize_text_field( $modulacion['destino_tonica'] ) : '';
        $destino_campo    = isset( $modulacion['destino_campo'] ) ? sanitize_text_field( $modulacion['destino_campo'] ) : '';
        $destino_campo_alt = isset( $modulacion['destino_campo_armonico'] ) ? sanitize_text_field( $modulacion['destino_campo_armonico'] ) : '';

        if ( '' === $seccion && '' === $destino && '' === $destino_tonica && '' === $destino_campo && '' === $destino_campo_alt ) {
            continue;
        }

        $item = [
            'seccion' => $seccion,
        ];

        if ( '' !== $destino ) {
            $item['destino'] = $destino;
        }

        if ( '' !== $destino_tonica ) {
            $item['destino_tonica'] = $destino_tonica;
        }

        $campo_value = '' !== $destino_campo ? $destino_campo : $destino_campo_alt;
        if ( '' !== $campo_value ) {
            $item['destino_campo'] = $campo_value;
        }

        $limpias[] = $item;
    }

    return $limpias;
}

/**
 * Sanitiza un evento armónico individual.
 *
 * @param mixed $evento Datos entrantes.
 * @return array|null
 */
function wpss_sanitize_evento_armonico( $evento ) {
    if ( empty( $evento ) ) {
        return null;
    }

    if ( is_string( $evento ) ) {
        $decoded = json_decode( $evento, true );
        if ( is_array( $decoded ) ) {
            $evento = $decoded;
        }
    }

    if ( ! is_array( $evento ) ) {
        return null;
    }

    $tipo = isset( $evento['tipo'] ) ? sanitize_text_field( $evento['tipo'] ) : '';

    if ( ! in_array( $tipo, [ 'modulacion', 'prestamo' ], true ) ) {
        return null;
    }

    $limpio = [ 'tipo' => $tipo ];
    $has_detail = false;

    if ( 'modulacion' === $tipo ) {
        $tonica_destino = isset( $evento['tonica_destino'] ) ? sanitize_text_field( $evento['tonica_destino'] ) : '';
        $campo_destino  = isset( $evento['campo_armonico_destino'] ) ? sanitize_text_field( $evento['campo_armonico_destino'] ) : '';

        if ( '' !== $tonica_destino ) {
            $limpio['tonica_destino'] = $tonica_destino;
            $has_detail               = true;
        }

        if ( '' !== $campo_destino ) {
            $limpio['campo_armonico_destino'] = $campo_destino;
            $has_detail                       = true;
        }
    } else {
        $tonica_origen = isset( $evento['tonica_origen'] ) ? sanitize_text_field( $evento['tonica_origen'] ) : '';
        $campo_origen  = isset( $evento['campo_armonico_origen'] ) ? sanitize_text_field( $evento['campo_armonico_origen'] ) : '';

        if ( '' !== $tonica_origen ) {
            $limpio['tonica_origen'] = $tonica_origen;
            $has_detail              = true;
        }

        if ( '' !== $campo_origen ) {
            $limpio['campo_armonico_origen'] = $campo_origen;
            $has_detail                      = true;
        }
    }

    if ( isset( $evento['segment_index'] ) ) {
        $segment_index = $evento['segment_index'];
        if ( is_numeric( $segment_index ) ) {
            $segment_index = (int) $segment_index;
            if ( $segment_index >= 0 ) {
                $limpio['segment_index'] = $segment_index;
                $has_detail              = true;
            }
        }
    }

    if ( ! $has_detail ) {
        return null;
    }

    return $limpio;
}

/**
 * Limpia y filtra la estructura de versos.
 *
 * @param array $versos Datos recibidos.
 * @return array
 */
function wpss_sanitize_versos_array( array $versos, array $section_ids = [] ) {
    $limpios = [];

    foreach ( $versos as $verso ) {
        if ( ! is_array( $verso ) ) {
            continue;
        }

        $orden        = isset( $verso['orden'] ) ? absint( $verso['orden'] ) : 0;
        $comentario   = isset( $verso['comentario'] ) ? sanitize_text_field( $verso['comentario'] ) : '';
        $evento_input = isset( $verso['evento_armonico'] ) ? $verso['evento_armonico'] : null;

        $segmentos_input = [];
        if ( isset( $verso['segmentos'] ) && is_array( $verso['segmentos'] ) ) {
            $segmentos_input = $verso['segmentos'];
        } else {
            $texto_legacy  = isset( $verso['texto'] ) ? sanitize_textarea_field( $verso['texto'] ) : '';
            $acorde_legacy = isset( $verso['acorde'] ) ? sanitize_text_field( $verso['acorde'] ) : '';
            if ( '' !== $acorde_legacy ) {
                $acorde_legacy = wpss_truncate_string( $acorde_legacy, 64 );
            }

            if ( '' !== $texto_legacy || '' !== $acorde_legacy ) {
                $segmentos_input = [
                    [
                        'texto'  => $texto_legacy,
                        'acorde' => $acorde_legacy,
                    ],
                ];
            }
        }

        $segmentos = wpss_sanitize_segmentos_array( $segmentos_input );

        $evento = null;
        if ( null !== $evento_input ) {
            $evento = wpss_sanitize_evento_armonico( $evento_input );
            if ( $evento && isset( $evento['segment_index'] ) ) {
                $segment_index = (int) $evento['segment_index'];
                if ( $segment_index < 0 || $segment_index >= count( $segmentos ) ) {
                    return new WP_Error( 'wpss_rest_invalid_event_segment', __( 'El evento armónico debe anclarse a un segmento válido.', 'wp-song-study' ) );
                }
            }
        }

        $fin_de_estrofa = ! empty( $verso['fin_de_estrofa'] ) ? 1 : 0;
        $nombre_estrofa = '';

        if ( isset( $verso['nombre_estrofa'] ) ) {
            $nombre_estrofa = sanitize_text_field( $verso['nombre_estrofa'] );
            $nombre_estrofa = wpss_truncate_string( $nombre_estrofa, 64 );
        }

        if ( ! $fin_de_estrofa ) {
            $nombre_estrofa = '';
        }

        $section_id = '';
        if ( isset( $verso['section_id'] ) ) {
            $section_id = sanitize_key( $verso['section_id'] );
        }

        if ( ! empty( $section_ids ) && '' !== $section_id && ! in_array( $section_id, $section_ids, true ) ) {
            return new WP_Error( 'wpss_rest_invalid_section', __( 'Cada verso debe referenciar una sección válida.', 'wp-song-study' ) );
        }

        if ( empty( $segmentos ) ) {
            if ( '' === $comentario && null === $evento ) {
                continue;
            }

            return new WP_Error( 'wpss_rest_invalid_segmentos', __( 'Cada verso debe incluir al menos un segmento con texto o acorde.', 'wp-song-study' ) );
        }

        $limpios[] = [
            'orden'           => $orden,
            'segmentos'       => $segmentos,
            'texto'           => wpss_implode_segmentos_text( $segmentos ),
            'acorde'          => isset( $segmentos[0]['acorde'] ) ? $segmentos[0]['acorde'] : '',
            'comentario'      => $comentario,
            'evento_armonico' => $evento,
            'section_id'      => $section_id,
            'fin_de_estrofa'  => (bool) $fin_de_estrofa,
            'nombre_estrofa'  => $nombre_estrofa,
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
        $segmentos = isset( $verso['segmentos'] ) && is_array( $verso['segmentos'] ) ? $verso['segmentos'] : [];
        if ( empty( $segmentos ) ) {
            continue;
        }

        $texto_base = wpss_implode_segmentos_text( $segmentos );

        $verso_post = [
            'post_type'   => 'verso',
            'post_status' => 'publish',
            'post_title'  => sprintf( __( 'Verso %1$d', 'wp-song-study' ), $index + 1 ),
            'post_content'=> $texto_base,
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
        update_post_meta( $verso_id, '_acorde_absoluto', isset( $segmentos[0]['acorde'] ) ? $segmentos[0]['acorde'] : '' );
        update_post_meta( $verso_id, '_funcion_relativa', $verso['comentario'] );
        update_post_meta( $verso_id, '_notas_verso', '' );
        $segmentos_json = wp_json_encode( $segmentos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( false === $segmentos_json ) {
            $segmentos_json = wp_json_encode( $segmentos );
        }

        update_post_meta( $verso_id, '_segmentos_json', $segmentos_json );

        $section_meta = isset( $verso['section_id'] ) ? sanitize_key( $verso['section_id'] ) : '';
        if ( '' !== $section_meta ) {
            update_post_meta( $verso_id, '_section_id', $section_meta );
        } else {
            delete_post_meta( $verso_id, '_section_id' );
        }

        if ( ! empty( $verso['fin_de_estrofa'] ) ) {
            update_post_meta( $verso_id, '_fin_de_estrofa', 1 );
        } else {
            delete_post_meta( $verso_id, '_fin_de_estrofa' );
        }

        $nombre_estrofa = isset( $verso['nombre_estrofa'] ) ? wpss_truncate_string( (string) $verso['nombre_estrofa'], 64 ) : '';
        if ( '' !== $nombre_estrofa ) {
            update_post_meta( $verso_id, '_nombre_estrofa', $nombre_estrofa );
        } else {
            delete_post_meta( $verso_id, '_nombre_estrofa' );
        }

        if ( ! empty( $verso['evento_armonico'] ) ) {
            update_post_meta( $verso_id, '_evento_armonico_json', wp_json_encode( $verso['evento_armonico'] ) );
        } else {
            delete_post_meta( $verso_id, '_evento_armonico_json' );
        }

    }

    // Elimina versos sobrantes.
    if ( ! empty( $existing_ids ) ) {
        foreach ( $existing_ids as $remaining_id ) {
            wp_delete_post( $remaining_id, true );
        }
    }

    return true;
}

/**
 * Calcula si una canción tiene préstamos considerando versos y préstamos generales.
 *
 * @param array $prestamos Prestamos registrados a nivel canción.
 * @param array $versos    Versos normalizados.
 * @return bool
 */
function wpss_calculate_song_has_prestamos( array $prestamos, array $versos ) {
    if ( ! empty( $prestamos ) ) {
        return true;
    }

    foreach ( $versos as $verso ) {
        if ( isset( $verso['evento_armonico'] ) && is_array( $verso['evento_armonico'] ) && isset( $verso['evento_armonico']['tipo'] ) && 'prestamo' === $verso['evento_armonico']['tipo'] ) {
            return true;
        }
    }

    return false;
}

/**
 * Calcula si una canción tiene modulaciones considerando versos y modulaciones generales.
 *
 * @param array $modulaciones Modulaciones a nivel canción.
 * @param array $versos       Versos normalizados.
 * @return bool
 */
function wpss_calculate_song_has_modulaciones( array $modulaciones, array $versos ) {
    if ( ! empty( $modulaciones ) ) {
        return true;
    }

    foreach ( $versos as $verso ) {
        if ( isset( $verso['evento_armonico'] ) && is_array( $verso['evento_armonico'] ) && isset( $verso['evento_armonico']['tipo'] ) && 'modulacion' === $verso['evento_armonico']['tipo'] ) {
            return true;
        }
    }

    return false;
}

/**
 * Devuelve la biblioteca base de campos armónicos.
 *
 * @return array
 */
function wpss_get_default_campos_armonicos() {
    return [
        'jonico' => [
            'slug'        => 'jonico',
            'nombre'      => __( 'Jónico', 'wp-song-study' ),
            'sistema'     => 'mayor',
            'intervalos'  => '1 2 3 4 5 6 7',
            'descripcion' => '',
            'notas'       => '',
            'activo'      => true,
        ],
        'dorico' => [
            'slug'        => 'dorico',
            'nombre'      => __( 'Dórico', 'wp-song-study' ),
            'sistema'     => 'mayor',
            'intervalos'  => '1 2 b3 4 5 6 b7',
            'descripcion' => '',
            'notas'       => '',
            'activo'      => true,
        ],
        'frigio' => [
            'slug'        => 'frigio',
            'nombre'      => __( 'Frigio', 'wp-song-study' ),
            'sistema'     => 'mayor',
            'intervalos'  => '1 b2 b3 4 5 b6 b7',
            'descripcion' => '',
            'notas'       => '',
            'activo'      => true,
        ],
        'lidio' => [
            'slug'        => 'lidio',
            'nombre'      => __( 'Lidio', 'wp-song-study' ),
            'sistema'     => 'mayor',
            'intervalos'  => '1 2 3 #4 5 6 7',
            'descripcion' => '',
            'notas'       => '',
            'activo'      => true,
        ],
        'mixolidio' => [
            'slug'        => 'mixolidio',
            'nombre'      => __( 'Mixolidio', 'wp-song-study' ),
            'sistema'     => 'mayor',
            'intervalos'  => '1 2 3 4 5 6 b7',
            'descripcion' => '',
            'notas'       => '',
            'activo'      => true,
        ],
        'eolico' => [
            'slug'        => 'eolico',
            'nombre'      => __( 'Eólico', 'wp-song-study' ),
            'sistema'     => 'mayor',
            'intervalos'  => '1 2 b3 4 5 b6 b7',
            'descripcion' => '',
            'notas'       => '',
            'activo'      => true,
        ],
        'locrio' => [
            'slug'        => 'locrio',
            'nombre'      => __( 'Locrio', 'wp-song-study' ),
            'sistema'     => 'mayor',
            'intervalos'  => '1 b2 b3 4 b5 b6 b7',
            'descripcion' => '',
            'notas'       => '',
            'activo'      => true,
        ],
        'frigio_dominante' => [
            'slug'        => 'frigio_dominante',
            'nombre'      => __( 'Frigio Dominante', 'wp-song-study' ),
            'sistema'     => 'menor_armonico',
            'intervalos'  => '1 b2 3 4 5 b6 b7',
            'descripcion' => '',
            'notas'       => '',
            'activo'      => true,
        ],
    ];
}

/**
 * Normaliza un elemento de campo armónico arbitrario.
 *
 * @param array $item Datos a limpiar.
 * @return array|null
 */
function wpss_normalize_campo_armonico_item( $item ) {
    if ( ! is_array( $item ) ) {
        return null;
    }

    $slug_source = '';
    if ( isset( $item['slug'] ) ) {
        $slug_source = sanitize_title( $item['slug'] );
    }

    if ( '' === $slug_source && isset( $item['nombre'] ) ) {
        $slug_source = sanitize_title( $item['nombre'] );
    }

    if ( '' === $slug_source ) {
        return null;
    }

    $nombre = isset( $item['nombre'] ) ? sanitize_text_field( $item['nombre'] ) : '';
    if ( '' === $nombre ) {
        $nombre = ucwords( str_replace( '-', ' ', str_replace( '_', ' ', $slug_source ) ) );
    }

    $sistema = isset( $item['sistema'] ) ? sanitize_key( $item['sistema'] ) : 'otro';
    $permitidos = [ 'mayor', 'menor_armonico', 'menor_melodico', 'otro' ];
    if ( ! in_array( $sistema, $permitidos, true ) ) {
        $sistema = 'otro';
    }

    $intervalos  = isset( $item['intervalos'] ) ? sanitize_textarea_field( $item['intervalos'] ) : '';
    $descripcion = isset( $item['descripcion'] ) ? sanitize_textarea_field( $item['descripcion'] ) : '';
    $notas       = isset( $item['notas'] ) ? sanitize_textarea_field( $item['notas'] ) : '';
    $activo      = isset( $item['activo'] ) ? (bool) $item['activo'] : true;

    return [
        'slug'        => $slug_source,
        'nombre'      => $nombre,
        'sistema'     => $sistema,
        'intervalos'  => $intervalos,
        'descripcion' => $descripcion,
        'notas'       => $notas,
        'activo'      => $activo,
    ];
}

/**
 * Obtiene la biblioteca completa fusionando defaults y elementos guardados.
 *
 * @return array
 */
function wpss_get_campos_armonicos_library() {
    $defaults = wpss_get_default_campos_armonicos();
    $stored   = get_option( 'wpss_campos_armonicos', [] );

    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    $library = [];

    foreach ( $stored as $item ) {
        $campo = wpss_normalize_campo_armonico_item( $item );
        if ( ! $campo ) {
            continue;
        }

        $slug = $campo['slug'];
        $base = isset( $defaults[ $slug ] ) ? $defaults[ $slug ] : [];
        $library[ $slug ] = array_merge( $base, $campo );
    }

    foreach ( $defaults as $slug => $default ) {
        if ( ! isset( $library[ $slug ] ) ) {
            $library[ $slug ] = $default;
        }
    }

    return $library;
}

/**
 * Persiste la biblioteca enviada reemplazando los elementos por slug.
 *
 * @param array $items Elementos recibidos.
 * @return array Biblioteca final.
 */
function wpss_save_campos_armonicos_library( array $items ) {
    $defaults   = wpss_get_default_campos_armonicos();
    $normalized = [];

    foreach ( $items as $item ) {
        $campo = wpss_normalize_campo_armonico_item( $item );
        if ( ! $campo ) {
            continue;
        }

        $slug = $campo['slug'];
        $base = isset( $defaults[ $slug ] ) ? $defaults[ $slug ] : [];
        $normalized[ $slug ] = array_merge( $base, $campo );
    }

    update_option( 'wpss_campos_armonicos', array_values( $normalized ), false );

    return wpss_get_campos_armonicos_library();
}

/**
 * Devuelve la biblioteca de campos armónicos vía REST.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_get_campos_armonicos( WP_REST_Request $request ) {
    $campos = array_values( wpss_get_campos_armonicos_library() );

    return rest_ensure_response( $campos );
}

/**
 * Guarda la biblioteca de campos armónicos vía REST.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response
 */
function wpss_rest_save_campos_armonicos( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }

    if ( isset( $params['campos'] ) ) {
        $params = $params['campos'];
    }

    $items = is_array( $params ) ? $params : [];

    $library = wpss_save_campos_armonicos_library( $items );

    return rest_ensure_response(
        [
            'ok'     => true,
            'campos' => array_values( $library ),
        ]
    );
}
