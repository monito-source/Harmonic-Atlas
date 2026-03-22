<?php
/**
 * Columnas personalizadas en el listado de canciones.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'manage_cancion_posts_columns', 'wpss_manage_cancion_posts_columns' );
add_action( 'manage_cancion_posts_custom_column', 'wpss_render_cancion_custom_columns', 10, 2 );
add_filter( 'manage_edit-cancion_sortable_columns', 'wpss_make_cancion_columns_sortable' );
add_action( 'pre_get_posts', 'wpss_adjust_cancion_admin_orderby' );
add_action( 'save_post_cancion', 'wpss_prepare_verso_count_meta' );
add_action( 'save_post_verso', 'wpss_prepare_verso_count_from_child', 10, 3 );
add_action( 'before_delete_post', 'wpss_prepare_verso_count_from_deleted_child' );

/**
 * Obtiene el conteo de versos evitando recalcular en cada fila del admin.
 *
 * @param int $post_id ID de la canción.
 * @return int
 */
function wpss_get_cached_verso_count( $post_id ) {
    $count = get_post_meta( $post_id, '_wpss_temp_verso_count', true );
    if ( '' !== $count ) {
        return (int) $count;
    }

    $count = get_post_meta( $post_id, '_conteo_versos', true );
    if ( '' !== $count ) {
        return (int) $count;
    }

    $ids = get_posts(
        [
            'post_type'        => 'verso',
            'numberposts'      => -1,
            'fields'           => 'ids',
            'meta_key'         => '_cancion_id',
            'meta_value'       => $post_id,
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ]
    );

    $count = is_array( $ids ) ? count( $ids ) : 0;
    update_post_meta( $post_id, '_wpss_temp_verso_count', $count );

    return $count;
}

/**
 * Añade columnas personalizadas para tonalidad, versos y estado armónico.
 *
 * @param array $columns Columnas originales.
 * @return array
 */
function wpss_manage_cancion_posts_columns( $columns ) {
    $columns['tonalidad']   = __( 'Tónica / campo', 'wp-song-study' );
    $columns['versos']      = __( 'Versos', 'wp-song-study' );
    $columns['indicadores'] = __( 'Indicadores armónicos', 'wp-song-study' );

    return $columns;
}

/**
 * Renderiza el contenido de las columnas personalizadas.
 *
 * @param string $column  Nombre de la columna.
 * @param int    $post_id ID de la canción.
 */
function wpss_render_cancion_custom_columns( $column, $post_id ) {
    if ( 'tonalidad' === $column ) {
        $tonica = sanitize_text_field( get_post_meta( $post_id, '_tonica', true ) );
        $campo  = sanitize_text_field( get_post_meta( $post_id, '_campo_armonico', true ) );

        if ( '' === $tonica && '' === $campo ) {
            echo '&mdash;';
            return;
        }

        $parts = [];
        if ( '' !== $tonica ) {
            $parts[] = $tonica;
        }
        if ( '' !== $campo ) {
            $parts[] = $campo;
        }

        echo esc_html( implode( ' · ', $parts ) );
        return;
    }

    if ( 'versos' === $column ) {
        $count = wpss_get_cached_verso_count( $post_id );
        echo esc_html( (string) $count );
        return;
    }

    if ( 'indicadores' === $column ) {
        $prestamos    = (bool) get_post_meta( $post_id, '_tiene_prestamos', true );
        $modulaciones = (bool) get_post_meta( $post_id, '_tiene_modulaciones', true );

        $items   = [];
        $items[] = $prestamos ? __( 'Préstamos: sí', 'wp-song-study' ) : __( 'Préstamos: no', 'wp-song-study' );
        $items[] = $modulaciones ? __( 'Modulaciones: sí', 'wp-song-study' ) : __( 'Modulaciones: no', 'wp-song-study' );

        echo esc_html( implode( ' | ', $items ) );
    }
}

/**
 * Permite ordenar por número de versos.
 *
 * @param array $columns Columnas ordenables existentes.
 * @return array
 */
function wpss_make_cancion_columns_sortable( $columns ) {
    $columns['versos'] = 'versos';
    return $columns;
}

/**
 * Ajusta la consulta para permitir ordenar por la cantidad de versos.
 *
 * @param WP_Query $query Consulta de WP.
 */
function wpss_adjust_cancion_admin_orderby( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( 'cancion' !== $query->get( 'post_type' ) ) {
        return;
    }

    if ( 'versos' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', '_wpss_temp_verso_count' );
        $query->set( 'orderby', 'meta_value_num' );
    }
}

/**
 * Calcula un metadato temporal con el número de versos para ordenación.
 *
 * @param int $post_id ID de la canción.
 */
function wpss_prepare_verso_count_meta( $post_id ) {
    if ( 'cancion' !== get_post_type( $post_id ) ) {
        return;
    }

    $count = get_posts(
        [
            'post_type'        => 'verso',
            'numberposts'      => -1,
            'fields'           => 'ids',
            'meta_key'         => '_cancion_id',
            'meta_value'       => $post_id,
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ]
    );

    update_post_meta( $post_id, '_wpss_temp_verso_count', count( $count ) );
}

/**
 * Actualiza el conteo de versos cuando un verso se guarda.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Objeto del post.
 * @param bool    $update  Si es actualización.
 */
function wpss_prepare_verso_count_from_child( $post_id, $post, $update ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    if ( 'verso' !== $post->post_type ) {
        return;
    }

    $parent_id = get_post_meta( $post_id, '_cancion_id', true );
    if ( $parent_id ) {
        wpss_prepare_verso_count_meta( (int) $parent_id );
    }
}

/**
 * Ajusta el conteo cuando un verso se elimina.
 *
 * @param int $post_id ID del verso eliminado.
 */
function wpss_prepare_verso_count_from_deleted_child( $post_id ) {
    if ( 'verso' !== get_post_type( $post_id ) ) {
        return;
    }

    $parent_id = get_post_meta( $post_id, '_cancion_id', true );
    if ( $parent_id ) {
        wpss_prepare_verso_count_meta( (int) $parent_id );
    }
}

/**
 * Limpia el metadato temporal utilizado para ordenar.
 */
function wpss_cleanup_temp_meta() {
    $posts = get_posts(
        [
            'post_type'        => 'cancion',
            'post_status'      => 'any',
            'numberposts'      => -1,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => true,
        ]
    );

    foreach ( $posts as $post_id ) {
        delete_post_meta( $post_id, '_wpss_temp_verso_count' );
    }
}
