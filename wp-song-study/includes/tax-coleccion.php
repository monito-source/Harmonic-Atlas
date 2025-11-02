<?php
/**
 * Taxonomía "coleccion" para agrupar canciones en playlists personalizadas.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra la taxonomía y el meta utilizado para guardar el orden.
 */
function wpss_register_coleccion_tax() {
    $labels = [
        'name'                       => _x( 'Colecciones', 'taxonomy general name', 'wp-song-study' ),
        'singular_name'              => _x( 'Colección', 'taxonomy singular name', 'wp-song-study' ),
        'search_items'               => __( 'Buscar colecciones', 'wp-song-study' ),
        'all_items'                  => __( 'Todas las colecciones', 'wp-song-study' ),
        'edit_item'                  => __( 'Editar colección', 'wp-song-study' ),
        'view_item'                  => __( 'Ver colección', 'wp-song-study' ),
        'update_item'                => __( 'Actualizar colección', 'wp-song-study' ),
        'add_new_item'               => __( 'Añadir nueva colección', 'wp-song-study' ),
        'new_item_name'              => __( 'Nombre de la colección', 'wp-song-study' ),
        'menu_name'                  => __( 'Colecciones', 'wp-song-study' ),
        'not_found'                  => __( 'No se encontraron colecciones.', 'wp-song-study' ),
    ];

    register_taxonomy(
        'coleccion',
        [ 'cancion' ],
        [
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
            'show_tagcloud'     => false,
        ]
    );

    register_term_meta(
        'coleccion',
        '_orden_ids',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wpss_sanitize_coleccion_order_meta',
            'auth_callback'     => '__return_true',
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'string',
                    'description' => __( 'Orden de canciones en la colección codificado como JSON.', 'wp-song-study' ),
                ],
            ],
        ]
    );
}

/**
 * Devuelve el orden registrado para una colección.
 *
 * @param int $term_id ID de la colección.
 * @return int[]
 */
function wpss_get_coleccion_order( $term_id ) {
    $raw = get_term_meta( $term_id, '_orden_ids', true );

    if ( empty( $raw ) ) {
        return [];
    }

    if ( is_string( $raw ) ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            $raw = $decoded;
        }
    }

    if ( ! is_array( $raw ) ) {
        return [];
    }

    return wpss_normalize_coleccion_order_ids( $raw );
}

/**
 * Actualiza el orden registrado para una colección.
 *
 * @param int   $term_id ID de la colección.
 * @param array $ids     Lista de IDs de canciones.
 * @return void
 */
function wpss_update_coleccion_order( $term_id, $ids ) {
    $ids = wpss_normalize_coleccion_order_ids( $ids );

    if ( empty( $ids ) ) {
        update_term_meta( $term_id, '_orden_ids', '' );
        return;
    }

    $encoded = wp_json_encode( $ids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    update_term_meta( $term_id, '_orden_ids', $encoded );
}

/**
 * Sanitiza el meta de orden de colecciones.
 *
 * @param mixed      $value   Valor recibido.
 * @param WP_Term    $term    Término asociado.
 * @param string     $meta_key Clave del meta.
 * @return string
 */
function wpss_sanitize_coleccion_order_meta( $value, $term, $meta_key ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    if ( is_string( $value ) && '' !== $value ) {
        $decoded = json_decode( $value, true );
        if ( is_array( $decoded ) ) {
            $value = $decoded;
        }
    }

    if ( ! is_array( $value ) ) {
        $value = [];
    }

    $normalized = wpss_normalize_coleccion_order_ids( $value );

    if ( empty( $normalized ) ) {
        return '';
    }

    return wp_json_encode( $normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Normaliza una lista de IDs asegurando que existan canciones válidas.
 *
 * @param array $ids IDs proporcionados.
 * @return int[]
 */
function wpss_normalize_coleccion_order_ids( $ids ) {
    if ( ! is_array( $ids ) ) {
        return [];
    }

    $normalized = [];
    foreach ( $ids as $id ) {
        $id = (int) $id;
        if ( $id > 0 && ! in_array( $id, $normalized, true ) ) {
            $normalized[] = $id;
        }
    }

    if ( empty( $normalized ) ) {
        return [];
    }

    $query = new WP_Query(
        [
            'post_type'      => 'cancion',
            'post_status'    => 'any',
            'post__in'       => $normalized,
            'orderby'        => 'post__in',
            'posts_per_page' => count( $normalized ),
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]
    );

    $existing = [];
    if ( ! empty( $query->posts ) ) {
        $existing = array_map( 'intval', $query->posts );
    }

    wp_reset_postdata();

    if ( empty( $existing ) ) {
        return [];
    }

    $existing_map = array_fill_keys( $existing, true );
    $result       = [];

    foreach ( $normalized as $id ) {
        if ( isset( $existing_map[ $id ] ) ) {
            $result[] = $id;
        }
    }

    return $result;
}

/**
 * Añade un ID de canción al final del orden si aún no existe.
 *
 * @param int $term_id ID de la colección.
 * @param int $song_id ID de la canción.
 * @return void
 */
function wpss_append_song_to_coleccion_order( $term_id, $song_id ) {
    $song_id = (int) $song_id;
    if ( $song_id <= 0 ) {
        return;
    }

    $order = wpss_get_coleccion_order( $term_id );

    if ( in_array( $song_id, $order, true ) ) {
        return;
    }

    $order[] = $song_id;
    wpss_update_coleccion_order( $term_id, $order );
}

/**
 * Elimina un ID de canción del orden registrado.
 *
 * @param int $term_id ID de la colección.
 * @param int $song_id ID de la canción.
 * @return void
 */
function wpss_remove_song_from_coleccion_order( $term_id, $song_id ) {
    $song_id = (int) $song_id;
    if ( $song_id <= 0 ) {
        return;
    }

    $order = wpss_get_coleccion_order( $term_id );
    if ( empty( $order ) ) {
        return;
    }

    $updated = array_values(
        array_filter(
            $order,
            static function( $current ) use ( $song_id ) {
                return (int) $current !== $song_id;
            }
        )
    );

    wpss_update_coleccion_order( $term_id, $updated );
}

/**
 * Obtiene los IDs de canciones de una colección respetando el orden definido.
 *
 * @param int $term_id ID de la colección.
 * @return int[]
 */
function wpss_get_coleccion_sorted_song_ids( $term_id ) {
    $term_id = (int) $term_id;
    if ( $term_id <= 0 ) {
        return [];
    }

    $order = wpss_get_coleccion_order( $term_id );

    $assigned = get_objects_in_term( $term_id, 'coleccion', [ 'fields' => 'ids' ] );
    if ( is_wp_error( $assigned ) || empty( $assigned ) ) {
        return $order;
    }

    foreach ( $assigned as $assigned_id ) {
        $assigned_id = (int) $assigned_id;
        if ( $assigned_id > 0 && ! in_array( $assigned_id, $order, true ) ) {
            $order[] = $assigned_id;
        }
    }

    return wpss_normalize_coleccion_order_ids( $order );
}
