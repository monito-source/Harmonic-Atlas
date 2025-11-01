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

    if ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error( 'wpss_rest_forbidden', __( 'No tienes permisos suficientes para esta acción.', 'wp-song-study' ), [ 'status' => 403 ] );
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

    $meta_query = [];

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

    $query = new WP_Query( $args );

    $items = [];

    foreach ( $query->posts as $post_id ) {
        $tonica_value        = sanitize_text_field( get_post_meta( $post_id, '_tonica', true ) );
        $campo_armonico_value = sanitize_text_field( get_post_meta( $post_id, '_campo_armonico', true ) );

        $items[] = [
            'id'                => (int) $post_id,
            'titulo'            => get_the_title( $post_id ),
            'tonica'            => $tonica_value,
            'tonalidad'         => $tonica_value,
            'campo_armonico'    => $campo_armonico_value,
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

    $tonica                 = sanitize_text_field( get_post_meta( $post_id, '_tonica', true ) );
    $campo_armonico         = sanitize_text_field( get_post_meta( $post_id, '_campo_armonico', true ) );
    $campo_predominante     = sanitize_textarea_field( get_post_meta( $post_id, '_campo_armonico_predominante', true ) );
    $prestamos_cancion      = wpss_decode_json_meta( get_post_meta( $post_id, '_prestamos_tonales_json', true ) );
    $modulaciones_cancion   = wpss_decode_json_meta( get_post_meta( $post_id, '_modulaciones_json', true ) );

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
        'versos'                     => wpss_get_cancion_versos( $post_id ),
        'tiene_prestamos'            => (bool) get_post_meta( $post_id, '_tiene_prestamos', true ),
        'tiene_modulaciones'         => (bool) get_post_meta( $post_id, '_tiene_modulaciones', true ),
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
    $versos       = wpss_sanitize_versos_array( isset( $params['versos'] ) ? (array) $params['versos'] : [] );

    if ( is_wp_error( $versos ) ) {
        return new WP_REST_Response( [ 'message' => $versos->get_error_message() ], 400 );
    }

    $versos = wpss_normalize_versos_order( $versos );

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

    $tiene_prestamos    = wpss_calculate_song_has_prestamos( $prestamos, $versos );
    $tiene_modulaciones = wpss_calculate_song_has_modulaciones( $modulaciones, $versos );

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
        $segmentos_meta = wpss_decode_json_meta( get_post_meta( $verso->ID, '_segmentos_json', true ) );
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

        $evento_raw = wpss_decode_json_meta( get_post_meta( $verso->ID, '_evento_armonico_json', true ) );
        $evento     = wpss_sanitize_evento_armonico( $evento_raw );

        $comentario = sanitize_text_field( get_post_meta( $verso->ID, '_funcion_relativa', true ) );
        $texto_base = wpss_implode_segmentos_text( $segmentos );
        $acorde     = isset( $segmentos[0]['acorde'] ) ? $segmentos[0]['acorde'] : '';

        $data[] = [
            'id'              => (int) $verso->ID,
            'orden'           => (int) get_post_meta( $verso->ID, '_orden', true ),
            'texto'           => $texto_base,
            'acorde'          => $acorde,
            'segmentos'       => $segmentos,
            'comentario'      => $comentario,
            'evento_armonico' => $evento,
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

    if ( 'modulacion' === $tipo ) {
        $tonica_destino = isset( $evento['tonica_destino'] ) ? sanitize_text_field( $evento['tonica_destino'] ) : '';
        $campo_destino  = isset( $evento['campo_armonico_destino'] ) ? sanitize_text_field( $evento['campo_armonico_destino'] ) : '';

        if ( '' === $tonica_destino && '' === $campo_destino ) {
            return null;
        }

        if ( '' !== $tonica_destino ) {
            $limpio['tonica_destino'] = $tonica_destino;
        }

        if ( '' !== $campo_destino ) {
            $limpio['campo_armonico_destino'] = $campo_destino;
        }
    } else {
        $tonica_origen = isset( $evento['tonica_origen'] ) ? sanitize_text_field( $evento['tonica_origen'] ) : '';
        $campo_origen  = isset( $evento['campo_armonico_origen'] ) ? sanitize_text_field( $evento['campo_armonico_origen'] ) : '';

        if ( '' === $tonica_origen && '' === $campo_origen ) {
            return null;
        }

        if ( '' !== $tonica_origen ) {
            $limpio['tonica_origen'] = $tonica_origen;
        }

        if ( '' !== $campo_origen ) {
            $limpio['campo_armonico_origen'] = $campo_origen;
        }
    }

    return $limpio;
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
        if ( ! is_array( $verso ) ) {
            continue;
        }

        $orden      = isset( $verso['orden'] ) ? absint( $verso['orden'] ) : 0;
        $comentario = isset( $verso['comentario'] ) ? sanitize_text_field( $verso['comentario'] ) : '';
        $evento     = isset( $verso['evento_armonico'] ) ? wpss_sanitize_evento_armonico( $verso['evento_armonico'] ) : null;

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
        update_post_meta( $verso_id, '_segmentos_json', wp_json_encode( $segmentos ) );

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
