<?php
/**
 * Importación y exportación versionada de canciones.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'wpss_register_song_import_export_routes' );
add_action( 'admin_post_wpss_song_export', 'wpss_handle_song_export_download' );

/**
 * Registra rutas REST para importación.
 *
 * @return void
 */
function wpss_register_song_import_export_routes() {
    register_rest_route(
        'wpss/v1',
        '/canciones/import',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'wpss_rest_import_song_package',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );
}

/**
 * Devuelve la versión del paquete de exportación.
 *
 * @return int
 */
function wpss_song_package_version() {
    return 1;
}

/**
 * Determina si el usuario actual puede usar import/export.
 *
 * @return bool
 */
function wpss_current_user_can_manage_song_import_export() {
    if ( function_exists( 'wpss_user_can_manage_songbook' ) ) {
        return wpss_user_can_manage_songbook();
    }

    $capability = defined( 'WPSS_CAP_MANAGE' ) ? WPSS_CAP_MANAGE : 'edit_posts';
    return current_user_can( $capability );
}

/**
 * Maneja la descarga de una exportación.
 *
 * @return void
 */
function wpss_handle_song_export_download() {
    if ( ! wpss_current_user_can_manage_song_import_export() ) {
        wp_die( esc_html__( 'No tienes permisos para exportar canciones.', 'wp-song-study' ), 403 );
    }

    check_admin_referer( 'wpss_song_export' );

    $scope = isset( $_REQUEST['scope'] ) ? sanitize_key( (string) $_REQUEST['scope'] ) : 'selection';
    $include_attachments = ! empty( $_REQUEST['include_attachments'] );
    $song_ids = [];

    if ( 'all' === $scope ) {
        $song_ids = wpss_get_exportable_song_ids();
    } else {
        $raw_ids = isset( $_REQUEST['song_ids'] ) ? wp_unslash( $_REQUEST['song_ids'] ) : '';
        if ( is_array( $raw_ids ) ) {
            $raw_ids = implode( ',', array_map( 'strval', $raw_ids ) );
        }
        $song_ids = wpss_get_exportable_song_ids( $raw_ids );
    }

    if ( empty( $song_ids ) ) {
        wp_die( esc_html__( 'Selecciona al menos una canción válida para exportar.', 'wp-song-study' ), 400 );
    }

    $inline_binary = ! class_exists( 'ZipArchive' );
    $package_bundle = wpss_build_song_export_package(
        $song_ids,
        [
            'include_attachments' => $include_attachments,
            'inline_binary'       => $inline_binary,
        ]
    );

    if ( is_wp_error( $package_bundle ) ) {
        wp_die( esc_html( $package_bundle->get_error_message() ), 500 );
    }

    $file_basename = 'wpss-song-export-' . gmdate( 'Ymd-His' );
    nocache_headers();

    if ( ! $inline_binary ) {
        $served = wpss_serve_song_export_zip( $file_basename, $package_bundle );
        if ( true === $served ) {
            exit;
        }
    }

    $manifest_json = wp_json_encode(
        $package_bundle['package'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    if ( false === $manifest_json ) {
        wp_die( esc_html__( 'No fue posible serializar el paquete de exportación.', 'wp-song-study' ), 500 );
    }

    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file_basename . '.json' ) . '"' );
    header( 'Content-Length: ' . strlen( $manifest_json ) );
    echo $manifest_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}

/**
 * Sirve un ZIP con la exportación.
 *
 * @param string $file_basename Nombre base de archivo.
 * @param array  $package_bundle Paquete y binarios.
 * @return bool
 */
function wpss_serve_song_export_zip( $file_basename, array $package_bundle ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return false;
    }

    $tmp_file = wp_tempnam( $file_basename . '.zip' );
    if ( ! $tmp_file ) {
        return false;
    }

    $zip = new ZipArchive();
    if ( true !== $zip->open( $tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
        @unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return false;
    }

    $manifest_json = wp_json_encode(
        $package_bundle['package'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    if ( false === $manifest_json ) {
        $zip->close();
        @unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return false;
    }

    $zip->addFromString( 'manifest.json', $manifest_json );

    $files = isset( $package_bundle['files'] ) && is_array( $package_bundle['files'] ) ? $package_bundle['files'] : [];
    foreach ( $files as $file ) {
        if ( empty( $file['path'] ) || ! array_key_exists( 'contents', $file ) ) {
            continue;
        }

        $zip->addFromString( (string) $file['path'], (string) $file['contents'] );
    }

    $zip->close();

    if ( ! file_exists( $tmp_file ) ) {
        return false;
    }

    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file_basename . '.zip' ) . '"' );
    header( 'Content-Length: ' . filesize( $tmp_file ) );
    readfile( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    @unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    return true;
}

/**
 * Obtiene IDs de canciones exportables.
 *
 * @param string|array|null $requested_ids IDs solicitados.
 * @return int[]
 */
function wpss_get_exportable_song_ids( $requested_ids = null ) {
    $ids = [];

    if ( null === $requested_ids || '' === $requested_ids ) {
        $query = new WP_Query(
            [
                'post_type'      => 'cancion',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ]
        );

        $ids = is_array( $query->posts ) ? array_map( 'intval', $query->posts ) : [];
    } else {
        if ( is_string( $requested_ids ) ) {
            $requested_ids = array_filter( array_map( 'trim', explode( ',', $requested_ids ) ) );
        }

        $ids = array_values(
            array_filter(
                array_map( 'absint', (array) $requested_ids )
            )
        );
    }

    $result = [];
    foreach ( $ids as $song_id ) {
        $post = get_post( $song_id );
        if ( ! $post || 'cancion' !== $post->post_type ) {
            continue;
        }

        if ( ! wpss_user_can_read_song( $song_id ) ) {
            continue;
        }

        $result[] = $song_id;
    }

    return array_values( array_unique( $result ) );
}

/**
 * Construye el paquete de exportación.
 *
 * @param int[] $song_ids IDs de canciones.
 * @param array $args Opciones.
 * @return array|WP_Error
 */
function wpss_build_song_export_package( array $song_ids, array $args = [] ) {
    $include_attachments = ! empty( $args['include_attachments'] );
    $inline_binary       = ! empty( $args['inline_binary'] );
    $songs               = [];
    $files               = [];
    $warnings            = [];

    foreach ( $song_ids as $song_id ) {
        $entry = wpss_build_song_export_entry( $song_id, $include_attachments, $inline_binary );
        if ( is_wp_error( $entry ) ) {
            $warnings[] = [
                'song_id'  => (int) $song_id,
                'message'  => $entry->get_error_message(),
                'code'     => $entry->get_error_code(),
            ];
            continue;
        }

        $songs[] = $entry['song'];
        if ( ! empty( $entry['files'] ) ) {
            $files = array_merge( $files, $entry['files'] );
        }
        if ( ! empty( $entry['warnings'] ) ) {
            $warnings = array_merge( $warnings, $entry['warnings'] );
        }
    }

    if ( empty( $songs ) ) {
        return new WP_Error(
            'wpss_export_empty',
            __( 'No fue posible preparar ninguna canción para exportación.', 'wp-song-study' )
        );
    }

    $current_user_id = get_current_user_id();
    return [
        'package' => [
            'schema'           => 'wpss-song-package',
            'package_version'  => wpss_song_package_version(),
            'plugin_version'   => defined( 'WPSSB_VERSION' ) ? WPSSB_VERSION : ( defined( 'WPSS_VERSION' ) ? WPSS_VERSION : '1.0.0' ),
            'exported_at_gmt'  => gmdate( 'c' ),
            'exported_by'      => wpss_prepare_song_export_user_snapshot( $current_user_id ),
            'site'             => [
                'name'     => get_bloginfo( 'name' ),
                'home_url' => home_url( '/' ),
            ],
            'options'          => [
                'include_attachments' => $include_attachments,
                'inline_binary'       => $inline_binary,
            ],
            'warnings'         => $warnings,
            'songs'            => $songs,
        ],
        'files'   => $files,
    ];
}

/**
 * Construye una entrada exportable para una canción.
 *
 * @param int  $song_id ID canción.
 * @param bool $include_attachments Incluir adjuntos.
 * @param bool $inline_binary Incrustar binarios en JSON.
 * @return array|WP_Error
 */
function wpss_build_song_export_entry( $song_id, $include_attachments = false, $inline_binary = false ) {
    $payload = wpss_get_song_payload_for_export( $song_id );
    if ( is_wp_error( $payload ) ) {
        return $payload;
    }

    $post = get_post( $song_id );
    if ( ! $post instanceof WP_Post ) {
        return new WP_Error(
            'wpss_export_missing_post',
            __( 'La canción original ya no existe.', 'wp-song-study' )
        );
    }

    $attachment_bundle = wpss_get_song_attachment_export_bundle( $song_id, $include_attachments, $inline_binary );
    if ( is_wp_error( $attachment_bundle ) ) {
        return $attachment_bundle;
    }

    $collection_ids = array_map( 'intval', wp_list_pluck( isset( $payload['colecciones'] ) ? $payload['colecciones'] : [], 'id' ) );

    return [
        'song'     => [
            'source'                 => [
                'song_id'        => (int) $song_id,
                'title'          => sanitize_text_field( get_the_title( $song_id ) ),
                'slug'           => sanitize_title( $post->post_name ),
                'author'         => wpss_prepare_song_export_user_snapshot( (int) $post->post_author ),
                'post_status'    => sanitize_key( $post->post_status ),
                'created_gmt'    => mysql_to_rfc3339( $post->post_date_gmt ),
                'modified_gmt'   => mysql_to_rfc3339( $post->post_modified_gmt ),
            ],
            'payload'                => $payload,
            'post_meta'              => wpss_collect_song_export_meta( $song_id ),
            'repertorio_assignments' => function_exists( 'wpss_get_song_repertorio_assignments' )
                ? wpss_get_song_repertorio_assignments( $song_id )
                : [],
            'collections_snapshot'   => wpss_get_song_export_collection_snapshots( $collection_ids ),
            'visibility_snapshot'    => [
                'projects' => wpss_get_song_export_post_snapshots( isset( $payload['visibility_project_ids'] ) ? $payload['visibility_project_ids'] : [], 'proyecto' ),
                'groups'   => wpss_get_song_export_post_snapshots( isset( $payload['visibility_group_ids'] ) ? $payload['visibility_group_ids'] : [], 'agrupacion_musical' ),
                'users'    => wpss_get_song_export_user_snapshots( isset( $payload['visibility_user_ids'] ) ? $payload['visibility_user_ids'] : [] ),
            ],
            'rehearsal_projects_snapshot' => wpss_get_song_export_post_snapshots( isset( $payload['rehearsal_project_ids'] ) ? $payload['rehearsal_project_ids'] : [], 'proyecto' ),
            'raw_attachments'        => function_exists( 'wpss_get_song_media_attachments_raw' )
                ? wpss_get_song_media_attachments_raw( $song_id )
                : [],
            'attachment_exports'     => $attachment_bundle['attachments'],
            'warnings'               => $attachment_bundle['warnings'],
        ],
        'files'    => $attachment_bundle['files'],
        'warnings' => $attachment_bundle['warnings'],
    ];
}

/**
 * Obtiene el payload canonico de una canción para exportación.
 *
 * @param int $song_id ID canción.
 * @return array|WP_Error
 */
function wpss_get_song_payload_for_export( $song_id ) {
    $request = new WP_REST_Request( 'GET', '/wpss/v1/cancion/' . (int) $song_id );
    $request->set_param( 'id', (int) $song_id );
    $response = wpss_rest_get_cancion( $request );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    if ( $response instanceof WP_REST_Response ) {
        return (array) $response->get_data();
    }

    return is_array( $response ) ? $response : [];
}

/**
 * Obtiene snapshots de usuarios.
 *
 * @param int[] $user_ids IDs usuario.
 * @return array
 */
function wpss_get_song_export_user_snapshots( $user_ids ) {
    $items = [];
    foreach ( array_map( 'absint', (array) $user_ids ) as $user_id ) {
        if ( $user_id <= 0 ) {
            continue;
        }

        $items[] = wpss_prepare_song_export_user_snapshot( $user_id );
    }

    return array_values(
        array_filter(
            $items,
            static function( $item ) {
                return ! empty( $item['id'] );
            }
        )
    );
}

/**
 * Prepara snapshot de usuario.
 *
 * @param int $user_id ID usuario.
 * @return array
 */
function wpss_prepare_song_export_user_snapshot( $user_id ) {
    $user = get_userdata( (int) $user_id );
    if ( ! $user instanceof WP_User ) {
        return [
            'id'    => 0,
            'name'  => '',
            'email' => '',
            'login' => '',
        ];
    }

    return [
        'id'    => (int) $user->ID,
        'name'  => sanitize_text_field( $user->display_name ),
        'email' => sanitize_email( $user->user_email ),
        'login' => sanitize_user( $user->user_login, true ),
    ];
}

/**
 * Obtiene snapshots de posts relacionados.
 *
 * @param int[]  $ids IDs de post.
 * @param string $post_type Tipo esperado.
 * @return array
 */
function wpss_get_song_export_post_snapshots( $ids, $post_type ) {
    $items = [];

    foreach ( array_map( 'absint', (array) $ids ) as $post_id ) {
        if ( $post_id <= 0 ) {
            continue;
        }

        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || $post->post_type !== $post_type ) {
            continue;
        }

        $items[] = [
            'id'         => (int) $post_id,
            'title'      => sanitize_text_field( get_the_title( $post_id ) ),
            'slug'       => sanitize_title( $post->post_name ),
            'post_type'  => sanitize_key( $post->post_type ),
            'author'     => wpss_prepare_song_export_user_snapshot( (int) $post->post_author ),
        ];
    }

    return $items;
}

/**
 * Obtiene snapshots de colecciones asociadas.
 *
 * @param int[] $collection_ids IDs colección.
 * @return array
 */
function wpss_get_song_export_collection_snapshots( $collection_ids ) {
    $items = [];

    foreach ( array_map( 'absint', (array) $collection_ids ) as $term_id ) {
        if ( $term_id <= 0 ) {
            continue;
        }

        $term = get_term( $term_id, 'coleccion' );
        if ( ! $term || is_wp_error( $term ) ) {
            continue;
        }

        $owner_id = function_exists( 'wpss_get_coleccion_owner_id' ) ? wpss_get_coleccion_owner_id( $term_id ) : 0;
        $shared_ids = function_exists( 'wpss_get_coleccion_shared_user_ids' ) ? wpss_get_coleccion_shared_user_ids( $term_id ) : [];
        $items[] = [
            'id'              => (int) $term_id,
            'name'            => sanitize_text_field( $term->name ),
            'slug'            => sanitize_title( $term->slug ),
            'description'     => sanitize_textarea_field( $term->description ),
            'owner'           => wpss_prepare_song_export_user_snapshot( $owner_id ),
            'shared_users'    => wpss_get_song_export_user_snapshots( $shared_ids ),
            'order_song_ids'  => function_exists( 'wpss_get_coleccion_sorted_song_ids' )
                ? array_map( 'intval', wpss_get_coleccion_sorted_song_ids( $term_id ) )
                : [],
        ];
    }

    return $items;
}

/**
 * Recopila meta crudo para máxima trazabilidad.
 *
 * @param int $song_id ID canción.
 * @return array
 */
function wpss_collect_song_export_meta( $song_id ) {
    $raw = get_post_meta( (int) $song_id );
    if ( ! is_array( $raw ) ) {
        return [];
    }

    $result = [];
    foreach ( $raw as $meta_key => $values ) {
        $meta_key = (string) $meta_key;
        if ( '' === $meta_key ) {
            continue;
        }

        if ( 0 === strpos( $meta_key, '_edit_' ) || 0 === strpos( $meta_key, '_oembed_' ) || '_wp_old_slug' === $meta_key ) {
            continue;
        }

        $normalized_values = [];
        foreach ( (array) $values as $value ) {
            $normalized_values[] = maybe_unserialize( $value );
        }

        $result[ $meta_key ] = $normalized_values;
    }

    return $result;
}

/**
 * Obtiene adjuntos exportables y binarios.
 *
 * @param int  $song_id ID canción.
 * @param bool $include_attachments Incluir bytes.
 * @param bool $inline_binary Incrustar bytes en JSON.
 * @return array|WP_Error
 */
function wpss_get_song_attachment_export_bundle( $song_id, $include_attachments = false, $inline_binary = false ) {
    $attachments = function_exists( 'wpss_get_song_media_attachments_raw' )
        ? wpss_get_song_media_attachments_raw( $song_id )
        : [];

    $bundle = [
        'attachments' => [],
        'files'       => [],
        'warnings'    => [],
    ];

    foreach ( $attachments as $attachment ) {
        if ( ! is_array( $attachment ) ) {
            continue;
        }

        $attachment_id = isset( $attachment['id'] ) ? sanitize_key( $attachment['id'] ) : '';
        $file_name = isset( $attachment['file_name'] ) ? sanitize_file_name( $attachment['file_name'] ) : '';
        $archive_path = '';
        $export_item = [
            'attachment_id'  => $attachment_id,
            'file_name'      => $file_name,
            'mime_type'      => isset( $attachment['mime_type'] ) ? sanitize_text_field( $attachment['mime_type'] ) : '',
            'size_bytes'     => isset( $attachment['size_bytes'] ) ? absint( $attachment['size_bytes'] ) : 0,
            'storage_provider' => isset( $attachment['storage_provider'] ) ? sanitize_key( $attachment['storage_provider'] ) : '',
            'status'         => 'metadata_only',
            'message'        => '',
        ];

        if ( ! $include_attachments ) {
            $bundle['attachments'][] = $export_item;
            continue;
        }

        if ( empty( $attachment['file_id'] ) || ! function_exists( 'wpss_google_drive_download_file' ) ) {
            $export_item['message'] = __( 'El adjunto no tiene un archivo legible para exportación binaria.', 'wp-song-study' );
            $bundle['attachments'][] = $export_item;
            continue;
        }

        $download = null;
        $candidates = function_exists( 'wpss_get_song_media_attachment_owner_candidates' )
            ? wpss_get_song_media_attachment_owner_candidates( $song_id, $attachment )
            : [ absint( $attachment['owner_user_id'] ?? 0 ) ];

        foreach ( $candidates as $candidate_user_id ) {
            if ( $candidate_user_id <= 0 ) {
                continue;
            }

            $download = wpss_google_drive_download_file(
                $candidate_user_id,
                (string) $attachment['file_id'],
                isset( $attachment['mime_type'] ) ? (string) $attachment['mime_type'] : '',
                [ 'timeout' => 60 ]
            );

            if ( ! is_wp_error( $download ) ) {
                break;
            }
        }

        if ( is_wp_error( $download ) || ! is_array( $download ) || ! array_key_exists( 'body', $download ) ) {
            $message = is_wp_error( $download ) ? $download->get_error_message() : __( 'No fue posible descargar el binario del adjunto.', 'wp-song-study' );
            $export_item['message'] = $message;
            $bundle['warnings'][] = [
                'song_id'       => (int) $song_id,
                'attachment_id' => $attachment_id,
                'message'       => $message,
            ];
            $bundle['attachments'][] = $export_item;
            continue;
        }

        $binary = (string) $download['body'];
        $archive_path = 'attachments/song-' . (int) $song_id . '/' . sanitize_file_name(
            ( $attachment_id ? $attachment_id . '-' : '' ) . ( $file_name ? $file_name : 'archivo.bin' )
        );
        $export_item['status'] = 'included';
        $export_item['archive_path'] = $archive_path;
        $export_item['sha1'] = sha1( $binary );
        $export_item['message'] = __( 'Adjunto incluido en el paquete.', 'wp-song-study' );

        if ( $inline_binary ) {
            $export_item['inline_encoding'] = 'base64';
            $export_item['inline_base64'] = base64_encode( $binary );
        } else {
            $bundle['files'][] = [
                'path'     => $archive_path,
                'contents' => $binary,
            ];
        }

        $bundle['attachments'][] = $export_item;
    }

    return $bundle;
}

/**
 * Importa un paquete de canciones.
 *
 * @param WP_REST_Request $request Solicitud entrante.
 * @return WP_REST_Response|WP_Error
 */
function wpss_rest_import_song_package( WP_REST_Request $request ) {
    $files = $request->get_file_params();
    $upload = isset( $files['package'] ) ? $files['package'] : null;

    if ( empty( $upload ) || empty( $upload['tmp_name'] ) || ! file_exists( $upload['tmp_name'] ) ) {
        return new WP_Error(
            'wpss_import_missing_file',
            __( 'Debes subir un archivo .zip o .json exportado desde el cancionero.', 'wp-song-study' ),
            [ 'status' => 400 ]
        );
    }

    $package_context = wpss_read_uploaded_song_package( $upload['tmp_name'], $upload['name'] ?? '' );
    if ( is_wp_error( $package_context ) ) {
        return $package_context;
    }

    $params = $request->get_body_params();
    $options = [
        'restore_attachments' => ! empty( $params['restore_attachments'] ),
        'restore_collections' => ! isset( $params['restore_collections'] ) || ! empty( $params['restore_collections'] ),
        'restore_visibility'  => ! isset( $params['restore_visibility'] ) || ! empty( $params['restore_visibility'] ),
        'restore_statuses'    => ! isset( $params['restore_statuses'] ) || ! empty( $params['restore_statuses'] ),
        'restore_extra_meta'  => ! isset( $params['restore_extra_meta'] ) || ! empty( $params['restore_extra_meta'] ),
    ];

    $current_user_id = get_current_user_id();
    $drive_status = function_exists( 'wpss_get_google_drive_status_payload' )
        ? wpss_get_google_drive_status_payload( $current_user_id, true )
        : [];
    $can_restore_attachments = $options['restore_attachments']
        && ! empty( $drive_status['connected'] )
        && ! empty( $drive_status['has_required_scope'] )
        && function_exists( 'wpss_google_drive_has_scopes_for_operation' )
        && wpss_google_drive_has_scopes_for_operation( isset( $drive_status['granted_scopes'] ) ? (array) $drive_status['granted_scopes'] : [], 'upload' );

    $manifest = $package_context['manifest'];
    $songs = isset( $manifest['songs'] ) && is_array( $manifest['songs'] ) ? $manifest['songs'] : [];

    $report = [
        'ok'                  => true,
        'package_version'     => isset( $manifest['package_version'] ) ? (int) $manifest['package_version'] : 0,
        'schema'              => isset( $manifest['schema'] ) ? sanitize_text_field( (string) $manifest['schema'] ) : '',
        'songs_imported'      => 0,
        'songs_failed'        => 0,
        'options'             => $options,
        'attachments_restored' => 0,
        'warnings'            => [],
        'items'               => [],
    ];

    foreach ( $songs as $index => $song_entry ) {
        $item_report = wpss_import_song_entry(
            $song_entry,
            $package_context,
            $options,
            [
                'current_user_id'         => $current_user_id,
                'can_restore_attachments' => $can_restore_attachments,
                'package_site'            => isset( $manifest['site'] ) && is_array( $manifest['site'] ) ? $manifest['site'] : [],
                'exported_at_gmt'         => isset( $manifest['exported_at_gmt'] ) ? sanitize_text_field( (string) $manifest['exported_at_gmt'] ) : '',
            ]
        );

        if ( ! empty( $item_report['ok'] ) ) {
            $report['songs_imported']++;
            $report['attachments_restored'] += isset( $item_report['attachments_restored'] ) ? (int) $item_report['attachments_restored'] : 0;
        } else {
            $report['songs_failed']++;
            $report['ok'] = false;
        }

        if ( ! empty( $item_report['warnings'] ) ) {
            $report['warnings'] = array_merge( $report['warnings'], $item_report['warnings'] );
        }

        $item_report['index'] = $index;
        $report['items'][] = $item_report;
    }

    if ( isset( $package_context['zip'] ) && $package_context['zip'] instanceof ZipArchive ) {
        $package_context['zip']->close();
    }

    return rest_ensure_response( $report );
}

/**
 * Lee y valida un paquete subido.
 *
 * @param string $file_path Ruta temporal.
 * @param string $original_name Nombre original.
 * @return array|WP_Error
 */
function wpss_read_uploaded_song_package( $file_path, $original_name = '' ) {
    $extension = strtolower( pathinfo( (string) $original_name, PATHINFO_EXTENSION ) );

    if ( 'zip' === $extension ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error(
                'wpss_import_zip_missing',
                __( 'El servidor no tiene soporte ZipArchive para importar este archivo.', 'wp-song-study' ),
                [ 'status' => 500 ]
            );
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $file_path ) ) {
            return new WP_Error(
                'wpss_import_zip_invalid',
                __( 'No fue posible abrir el ZIP de importación.', 'wp-song-study' ),
                [ 'status' => 400 ]
            );
        }

        $manifest_json = $zip->getFromName( 'manifest.json' );
        if ( false === $manifest_json ) {
            $zip->close();
            return new WP_Error(
                'wpss_import_manifest_missing',
                __( 'El ZIP no contiene un manifest.json válido.', 'wp-song-study' ),
                [ 'status' => 400 ]
            );
        }

        $manifest = json_decode( $manifest_json, true );
        if ( ! is_array( $manifest ) ) {
            $zip->close();
            return new WP_Error(
                'wpss_import_manifest_invalid',
                __( 'El manifest.json no tiene un formato válido.', 'wp-song-study' ),
                [ 'status' => 400 ]
            );
        }

        return wpss_validate_song_package_manifest(
            [
                'type'     => 'zip',
                'manifest' => $manifest,
                'zip'      => $zip,
            ]
        );
    }

    $json = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    if ( false === $json || '' === $json ) {
        return new WP_Error(
            'wpss_import_json_empty',
            __( 'No fue posible leer el archivo de importación.', 'wp-song-study' ),
            [ 'status' => 400 ]
        );
    }

    $manifest = json_decode( $json, true );
    if ( ! is_array( $manifest ) ) {
        return new WP_Error(
            'wpss_import_json_invalid',
            __( 'El archivo JSON de importación no es válido.', 'wp-song-study' ),
            [ 'status' => 400 ]
        );
    }

    return wpss_validate_song_package_manifest(
        [
            'type'     => 'json',
            'manifest' => $manifest,
        ]
    );
}

/**
 * Valida el manifiesto de un paquete.
 *
 * @param array $context Contexto del paquete.
 * @return array|WP_Error
 */
function wpss_validate_song_package_manifest( array $context ) {
    $manifest = isset( $context['manifest'] ) && is_array( $context['manifest'] ) ? $context['manifest'] : [];
    $schema   = isset( $manifest['schema'] ) ? sanitize_text_field( (string) $manifest['schema'] ) : '';

    if ( 'wpss-song-package' !== $schema ) {
        if ( isset( $context['zip'] ) && $context['zip'] instanceof ZipArchive ) {
            $context['zip']->close();
        }

        return new WP_Error(
            'wpss_import_schema_invalid',
            __( 'El archivo no corresponde a un paquete de canciones WPSS.', 'wp-song-study' ),
            [ 'status' => 400 ]
        );
    }

    if ( empty( $manifest['songs'] ) || ! is_array( $manifest['songs'] ) ) {
        if ( isset( $context['zip'] ) && $context['zip'] instanceof ZipArchive ) {
            $context['zip']->close();
        }

        return new WP_Error(
            'wpss_import_songs_missing',
            __( 'El paquete no contiene canciones importables.', 'wp-song-study' ),
            [ 'status' => 400 ]
        );
    }

    return $context;
}

/**
 * Importa una canción individual desde el paquete.
 *
 * @param array $song_entry Entrada del paquete.
 * @param array $package_context Contexto general del paquete.
 * @param array $options Opciones de importación.
 * @param array $runtime Runtime de importación.
 * @return array
 */
function wpss_import_song_entry( array $song_entry, array $package_context, array $options, array $runtime ) {
    $payload = isset( $song_entry['payload'] ) && is_array( $song_entry['payload'] ) ? $song_entry['payload'] : [];
    $source  = isset( $song_entry['source'] ) && is_array( $song_entry['source'] ) ? $song_entry['source'] : [];
    $warnings = [];

    if ( empty( $payload ) || empty( $payload['titulo'] ) ) {
        return [
            'ok'      => false,
            'title'   => isset( $source['title'] ) ? sanitize_text_field( (string) $source['title'] ) : '',
            'message' => __( 'La entrada del paquete no contiene un payload de canción válido.', 'wp-song-study' ),
            'warnings' => [],
        ];
    }

    $collection_resolution = wpss_resolve_import_collections( $song_entry, $runtime['current_user_id'], $options );
    $visibility_resolution = wpss_resolve_import_visibility( $song_entry, $options );
    $rehearsal_project_ids = wpss_map_import_post_snapshots(
        isset( $song_entry['rehearsal_projects_snapshot'] ) ? $song_entry['rehearsal_projects_snapshot'] : [],
        'proyecto'
    );

    $save_payload = $payload;
    unset(
        $save_payload['id'],
        $save_payload['autor_id'],
        $save_payload['autor_nombre'],
        $save_payload['estado_transcripcion_label'],
        $save_payload['estado_ensayo_label'],
        $save_payload['reversion_origen_id'],
        $save_payload['reversion_origen_titulo'],
        $save_payload['reversion_raiz_id'],
        $save_payload['reversion_raiz_titulo'],
        $save_payload['reversion_autor_origen_id'],
        $save_payload['reversion_autor_origen_nombre'],
        $save_payload['es_reversion'],
        $save_payload['item']
    );

    $save_payload['colecciones'] = $collection_resolution['ids'];
    $save_payload['tags'] = isset( $payload['tags'] ) && is_array( $payload['tags'] ) ? $payload['tags'] : [];
    $save_payload['adjuntos'] = [];
    $save_payload['adjuntos_permisos'] = isset( $payload['adjuntos_permisos'] ) && is_array( $payload['adjuntos_permisos'] )
        ? $payload['adjuntos_permisos']
        : [];
    $save_payload['visibility_mode'] = $visibility_resolution['visibility_mode'];
    $save_payload['visibility_project_ids'] = $visibility_resolution['project_ids'];
    $save_payload['visibility_group_ids'] = $visibility_resolution['group_ids'];
    $save_payload['visibility_user_ids'] = $visibility_resolution['user_ids'];
    $save_payload['rehearsal_project_ids'] = $rehearsal_project_ids;

    $warnings = array_merge( $warnings, $collection_resolution['warnings'], $visibility_resolution['warnings'] );

    $save_request = new WP_REST_Request( 'POST', '/wpss/v1/cancion' );
    $save_request->set_body_params( $save_payload );
    $save_response = wpss_rest_save_cancion( $save_request );

    if ( is_wp_error( $save_response ) ) {
        return [
            'ok'      => false,
            'title'   => isset( $payload['titulo'] ) ? sanitize_text_field( (string) $payload['titulo'] ) : '',
            'message' => $save_response->get_error_message(),
            'warnings' => $warnings,
        ];
    }

    $save_status = $save_response instanceof WP_REST_Response ? (int) $save_response->get_status() : 200;
    $save_data = $save_response instanceof WP_REST_Response ? (array) $save_response->get_data() : (array) $save_response;
    $new_song_id = isset( $save_data['id'] ) ? absint( $save_data['id'] ) : 0;

    if ( $save_status >= 400 || $new_song_id <= 0 ) {
        return [
            'ok'      => false,
            'title'   => isset( $payload['titulo'] ) ? sanitize_text_field( (string) $payload['titulo'] ) : '',
            'message' => isset( $save_data['message'] ) ? sanitize_text_field( (string) $save_data['message'] ) : __( 'No fue posible recrear la canción importada.', 'wp-song-study' ),
            'warnings' => $warnings,
        ];
    }

    if ( ! empty( $options['restore_statuses'] ) ) {
        wpss_restore_import_song_statuses( $new_song_id, $payload );
    }

    if ( ! empty( $options['restore_extra_meta'] ) && ! empty( $song_entry['post_meta'] ) && is_array( $song_entry['post_meta'] ) ) {
        wpss_restore_import_passthrough_meta( $new_song_id, $song_entry['post_meta'] );
    }

    $attachments_restored = 0;
    if ( ! empty( $runtime['can_restore_attachments'] ) ) {
        $attachment_restore = wpss_restore_import_song_attachments( $new_song_id, $song_entry, $package_context, $runtime['current_user_id'] );
        $attachments_restored = isset( $attachment_restore['count'] ) ? (int) $attachment_restore['count'] : 0;
        if ( ! empty( $attachment_restore['warnings'] ) ) {
            $warnings = array_merge( $warnings, $attachment_restore['warnings'] );
        }
    } elseif ( ! empty( $options['restore_attachments'] ) && ! empty( $song_entry['attachment_exports'] ) ) {
        $warnings[] = [
            'song_id'  => $new_song_id,
            'message'  => __( 'Los adjuntos no se restauraron porque Google Drive no está operativo para este usuario.', 'wp-song-study' ),
        ];
    }

    wpss_store_song_import_provenance(
        $new_song_id,
        [
            'schema'          => 'wpss-song-package',
            'package_version' => isset( $package_context['manifest']['package_version'] ) ? (int) $package_context['manifest']['package_version'] : 0,
            'source_site'     => $runtime['package_site'],
            'exported_at_gmt' => $runtime['exported_at_gmt'],
            'source'          => $source,
            'warnings'        => $warnings,
            'collections'     => $collection_resolution['mapped'],
            'visibility'      => $visibility_resolution['mapped'],
            'attachments'     => wpss_strip_inline_attachment_payloads( isset( $song_entry['attachment_exports'] ) ? $song_entry['attachment_exports'] : [] ),
        ]
    );

    return [
        'ok'                  => true,
        'id'                  => $new_song_id,
        'title'               => sanitize_text_field( get_the_title( $new_song_id ) ),
        'source_title'        => isset( $source['title'] ) ? sanitize_text_field( (string) $source['title'] ) : '',
        'message'             => __( 'Canción importada correctamente.', 'wp-song-study' ),
        'attachments_restored' => $attachments_restored,
        'warnings'            => $warnings,
    ];
}

/**
 * Resuelve colecciones durante la importación.
 *
 * @param array $song_entry Entrada del paquete.
 * @param int   $current_user_id Usuario actual.
 * @param array $options Opciones.
 * @return array
 */
function wpss_resolve_import_collections( array $song_entry, $current_user_id, array $options ) {
    $result = [
        'ids'      => [],
        'mapped'   => [],
        'warnings' => [],
    ];

    if ( empty( $options['restore_collections'] ) ) {
        return $result;
    }

    $snapshots = isset( $song_entry['collections_snapshot'] ) && is_array( $song_entry['collections_snapshot'] )
        ? $song_entry['collections_snapshot']
        : [];

    foreach ( $snapshots as $snapshot ) {
        if ( ! is_array( $snapshot ) ) {
            continue;
        }

        $name = isset( $snapshot['name'] ) ? sanitize_text_field( (string) $snapshot['name'] ) : '';
        if ( '' === $name ) {
            continue;
        }

        $slug = isset( $snapshot['slug'] ) ? sanitize_title( $snapshot['slug'] ) : '';
        $term = $slug ? get_term_by( 'slug', $slug, 'coleccion' ) : false;

        if ( ! $term || is_wp_error( $term ) ) {
            $term = get_term_by( 'name', $name, 'coleccion' );
        }

        $created = false;
        if ( $term && ! is_wp_error( $term ) && function_exists( 'wpss_user_can_access_coleccion' ) && ! wpss_user_can_access_coleccion( $term->term_id, 'write' ) ) {
            $term = false;
        }

        if ( ! $term || is_wp_error( $term ) ) {
            $insert_name = $name;
            $insert_slug = $slug;
            if ( function_exists( 'term_exists' ) && term_exists( $name, 'coleccion' ) ) {
                $insert_name = sprintf( __( '%s (importada)', 'wp-song-study' ), $name );
            }
            if ( '' !== $insert_slug && get_term_by( 'slug', $insert_slug, 'coleccion' ) ) {
                $insert_slug = '';
            }

            $insert = wp_insert_term(
                $insert_name,
                'coleccion',
                [
                    'description' => isset( $snapshot['description'] ) ? sanitize_textarea_field( (string) $snapshot['description'] ) : '',
                    'slug'        => $insert_slug,
                ]
            );

            if ( is_wp_error( $insert ) ) {
                $result['warnings'][] = [
                    'message' => sprintf(
                        /* translators: %s nombre de colección */
                        __( 'No fue posible crear la colección importada "%s".', 'wp-song-study' ),
                        $name
                    ),
                ];
                continue;
            }

            $term = get_term( (int) $insert['term_id'], 'coleccion' );
            $created = true;
        }

        if ( ! $term || is_wp_error( $term ) ) {
            continue;
        }

        $term_id = (int) $term->term_id;
        if ( function_exists( 'wpss_update_coleccion_owner_id' ) ) {
            $owner_id = function_exists( 'wpss_get_coleccion_owner_id' ) ? wpss_get_coleccion_owner_id( $term_id ) : 0;
            if ( $created || $owner_id <= 0 ) {
                wpss_update_coleccion_owner_id( $term_id, $current_user_id );
            }
        }

        if ( $created && function_exists( 'wpss_update_coleccion_shared_user_ids' ) ) {
            $shared_user_ids = wpss_map_import_users_by_snapshot( isset( $snapshot['shared_users'] ) ? $snapshot['shared_users'] : [] );
            wpss_update_coleccion_shared_user_ids( $term_id, $shared_user_ids );
        }

        $result['ids'][] = $term_id;
        $result['mapped'][] = [
            'source_id'   => isset( $snapshot['id'] ) ? absint( $snapshot['id'] ) : 0,
            'source_name' => $name,
            'term_id'     => $term_id,
            'created'     => $created,
        ];
    }

    $result['ids'] = array_values( array_unique( array_map( 'intval', $result['ids'] ) ) );
    return $result;
}

/**
 * Resuelve visibilidad durante la importación.
 *
 * @param array $song_entry Entrada del paquete.
 * @param array $options Opciones.
 * @return array
 */
function wpss_resolve_import_visibility( array $song_entry, array $options ) {
    $payload = isset( $song_entry['payload'] ) && is_array( $song_entry['payload'] ) ? $song_entry['payload'] : [];
    $snapshot = isset( $song_entry['visibility_snapshot'] ) && is_array( $song_entry['visibility_snapshot'] ) ? $song_entry['visibility_snapshot'] : [];
    $mode = isset( $payload['visibility_mode'] ) ? sanitize_key( (string) $payload['visibility_mode'] ) : 'private';

    $result = [
        'visibility_mode' => 'private',
        'project_ids'     => [],
        'group_ids'       => [],
        'user_ids'        => [],
        'mapped'          => [],
        'warnings'        => [],
    ];

    if ( empty( $options['restore_visibility'] ) ) {
        return $result;
    }

    $project_ids = wpss_map_import_post_snapshots( isset( $snapshot['projects'] ) ? $snapshot['projects'] : [], 'proyecto' );
    $group_ids   = wpss_map_import_post_snapshots( isset( $snapshot['groups'] ) ? $snapshot['groups'] : [], 'agrupacion_musical' );
    $user_ids    = wpss_map_import_users_by_snapshot( isset( $snapshot['users'] ) ? $snapshot['users'] : [] );

    $result['mapped'] = [
        'projects' => $project_ids,
        'groups'   => $group_ids,
        'users'    => $user_ids,
    ];

    if ( 'public' === $mode ) {
        $result['visibility_mode'] = 'public';
        return $result;
    }

    if ( 'private' === $mode ) {
        return $result;
    }

    if ( 'project' === $mode ) {
        if ( empty( $project_ids ) ) {
            $result['warnings'][] = [ 'message' => __( 'La visibilidad por proyecto no pudo restaurarse; se importó como privada.', 'wp-song-study' ) ];
            return $result;
        }

        $result['visibility_mode'] = 'project';
        $result['project_ids'] = $project_ids;
        return $result;
    }

    if ( 'groups' === $mode ) {
        if ( empty( $group_ids ) ) {
            $result['warnings'][] = [ 'message' => __( 'La visibilidad por agrupaciones no pudo restaurarse; se importó como privada.', 'wp-song-study' ) ];
            return $result;
        }

        $result['visibility_mode'] = 'groups';
        $result['group_ids'] = $group_ids;
        return $result;
    }

    if ( 'users' === $mode ) {
        if ( empty( $user_ids ) ) {
            $result['warnings'][] = [ 'message' => __( 'La visibilidad por usuarios no pudo restaurarse; se importó como privada.', 'wp-song-study' ) ];
            return $result;
        }

        $result['visibility_mode'] = 'users';
        $result['user_ids'] = $user_ids;
        return $result;
    }

    return $result;
}

/**
 * Mapea snapshots de posts por slug/título.
 *
 * @param array  $snapshots Snapshots exportados.
 * @param string $post_type Tipo esperado.
 * @return int[]
 */
function wpss_map_import_post_snapshots( $snapshots, $post_type ) {
    $ids = [];

    foreach ( (array) $snapshots as $snapshot ) {
        if ( ! is_array( $snapshot ) ) {
            continue;
        }

        $slug = isset( $snapshot['slug'] ) ? sanitize_title( $snapshot['slug'] ) : '';
        $title = isset( $snapshot['title'] ) ? sanitize_text_field( (string) $snapshot['title'] ) : '';
        $post = null;

        if ( '' !== $slug ) {
            $posts = get_posts(
                [
                    'post_type'      => $post_type,
                    'name'           => $slug,
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                ]
            );
            if ( ! empty( $posts[0] ) && $posts[0] instanceof WP_Post ) {
                $post = $posts[0];
            }
        }

        if ( ! $post && '' !== $title ) {
            $posts = get_posts(
                [
                    'post_type'      => $post_type,
                    'post_status'    => 'publish',
                    'posts_per_page' => 10,
                    's'              => $title,
                ]
            );

            foreach ( $posts as $candidate ) {
                if ( ! $candidate instanceof WP_Post ) {
                    continue;
                }

                if ( sanitize_text_field( get_the_title( $candidate->ID ) ) === $title ) {
                    $post = $candidate;
                    break;
                }
            }
        }

        if ( $post instanceof WP_Post ) {
            $ids[] = (int) $post->ID;
        }
    }

    return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Mapea usuarios por email/login.
 *
 * @param array $snapshots Snapshots exportados.
 * @return int[]
 */
function wpss_map_import_users_by_snapshot( $snapshots ) {
    $ids = [];

    foreach ( (array) $snapshots as $snapshot ) {
        if ( ! is_array( $snapshot ) ) {
            continue;
        }

        $user = false;
        $email = isset( $snapshot['email'] ) ? sanitize_email( $snapshot['email'] ) : '';
        $login = isset( $snapshot['login'] ) ? sanitize_user( $snapshot['login'], true ) : '';

        if ( '' !== $email ) {
            $user = get_user_by( 'email', $email );
        }

        if ( ! $user && '' !== $login ) {
            $user = get_user_by( 'login', $login );
        }

        if ( $user instanceof WP_User ) {
            $ids[] = (int) $user->ID;
        }
    }

    return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Restaura estados de canción.
 *
 * @param int   $song_id ID nueva canción.
 * @param array $payload Payload original.
 * @return void
 */
function wpss_restore_import_song_statuses( $song_id, array $payload ) {
    if ( isset( $payload['estado_transcripcion'] ) && function_exists( 'wpss_normalize_estado_transcripcion' ) ) {
        update_post_meta( $song_id, '_estado_transcripcion', wpss_normalize_estado_transcripcion( $payload['estado_transcripcion'] ) );
    }

    if ( isset( $payload['estado_ensayo'] ) && function_exists( 'wpss_normalize_estado_ensayo' ) && function_exists( 'wpss_get_user_estado_ensayo_map' ) && function_exists( 'wpss_save_user_estado_ensayo_map' ) ) {
        $normalized = wpss_normalize_estado_ensayo( $payload['estado_ensayo'] );
        $user_id    = get_current_user_id();
        if ( $user_id > 0 ) {
            $map = wpss_get_user_estado_ensayo_map( $user_id );
            if ( 'sin_ensayar' === $normalized ) {
                unset( $map[ $song_id ] );
            } else {
                $map[ $song_id ] = $normalized;
            }
            wpss_save_user_estado_ensayo_map( $user_id, $map );
        }
    }
}

/**
 * Restaura meta adicional no cubierta por el guardado canónico.
 *
 * @param int   $song_id ID nueva canción.
 * @param array $meta_snapshot Meta exportado.
 * @return void
 */
function wpss_restore_import_passthrough_meta( $song_id, array $meta_snapshot ) {
    $blocked_prefixes = [ '_wp_', '_edit_', '_oembed_' ];
    $handled_keys = [
        '_tonica',
        '_campo_armonico',
        '_campo_armonico_predominante',
        '_ficha_autores',
        '_ficha_anio',
        '_ficha_pais',
        '_ficha_estado_legal',
        '_ficha_licencia',
        '_ficha_fuente_verificacion',
        '_ficha_incompleta',
        '_ficha_incompleta_motivo',
        '_bpm',
        '_prestamos_tonales_json',
        '_modulaciones_json',
        '_adjuntos_multimedia_json',
        '_wpss_visibility_mode',
        '_wpss_visibility_project_ids',
        '_wpss_visibility_group_ids',
        '_wpss_visibility_user_ids',
        '_wpss_rehearsal_project_ids',
        '_estado_transcripcion',
        '_secciones_json',
        '_estructura_json',
        '_tiene_prestamos',
        '_tiene_modulaciones',
        '_conteo_versos',
        '_conteo_secciones',
        '_conteo_midi',
        '_conteo_versos_normales',
        '_conteo_versos_instrumentales',
        '_reversion_origen_id',
        '_reversion_origen_titulo',
        '_reversion_raiz_id',
        '_reversion_raiz_titulo',
        '_reversion_autor_origen_id',
        '_reversion_autor_origen_nombre',
        '_repertorio_asignaciones_json',
        '_wpss_import_provenance_json',
    ];

    foreach ( $meta_snapshot as $meta_key => $values ) {
        $meta_key = (string) $meta_key;
        if ( '' === $meta_key || in_array( $meta_key, $handled_keys, true ) ) {
            continue;
        }

        $blocked = false;
        foreach ( $blocked_prefixes as $prefix ) {
            if ( 0 === strpos( $meta_key, $prefix ) ) {
                $blocked = true;
                break;
            }
        }

        if ( $blocked ) {
            continue;
        }

        delete_post_meta( $song_id, $meta_key );

        foreach ( (array) $values as $value ) {
            add_post_meta( $song_id, $meta_key, $value );
        }
    }
}

/**
 * Restaura adjuntos reales desde el paquete.
 *
 * @param int   $song_id Nueva canción.
 * @param array $song_entry Entrada exportada.
 * @param array $package_context Contexto del paquete.
 * @param int   $current_user_id Usuario actual.
 * @return array
 */
function wpss_restore_import_song_attachments( $song_id, array $song_entry, array $package_context, $current_user_id ) {
    $raw_attachments = isset( $song_entry['raw_attachments'] ) && is_array( $song_entry['raw_attachments'] ) ? $song_entry['raw_attachments'] : [];
    $attachment_exports = isset( $song_entry['attachment_exports'] ) && is_array( $song_entry['attachment_exports'] ) ? $song_entry['attachment_exports'] : [];
    $imported = [];
    $warnings = [];

    $raw_map = [];
    foreach ( $raw_attachments as $attachment ) {
        if ( is_array( $attachment ) && ! empty( $attachment['id'] ) ) {
            $raw_map[ sanitize_key( $attachment['id'] ) ] = $attachment;
        }
    }

    foreach ( $attachment_exports as $export ) {
        if ( ! is_array( $export ) || 'included' !== ( $export['status'] ?? '' ) ) {
            continue;
        }

        $binary = wpss_get_import_attachment_binary( $export, $package_context );
        if ( false === $binary || '' === $binary ) {
            $warnings[] = [
                'song_id'       => $song_id,
                'attachment_id' => isset( $export['attachment_id'] ) ? sanitize_key( $export['attachment_id'] ) : '',
                'message'       => __( 'No fue posible leer el binario de un adjunto dentro del paquete.', 'wp-song-study' ),
            ];
            continue;
        }

        $attachment_id = isset( $export['attachment_id'] ) ? sanitize_key( $export['attachment_id'] ) : '';
        $source_attachment = isset( $raw_map[ $attachment_id ] ) ? $raw_map[ $attachment_id ] : [];
        $file_name = isset( $export['file_name'] ) ? sanitize_file_name( $export['file_name'] ) : ( $source_attachment['file_name'] ?? 'archivo.bin' );
        $mime_type = isset( $export['mime_type'] ) ? sanitize_text_field( $export['mime_type'] ) : ( $source_attachment['mime_type'] ?? 'application/octet-stream' );

        $upload = wpss_google_drive_upload_song_media_bytes(
            $current_user_id,
            $song_id,
            $file_name,
            $mime_type,
            $binary,
            is_array( $source_attachment ) ? $source_attachment : []
        );

        if ( is_wp_error( $upload ) ) {
            $warnings[] = [
                'song_id'       => $song_id,
                'attachment_id' => $attachment_id,
                'message'       => $upload->get_error_message(),
            ];
            continue;
        }

        $file_json = isset( $upload['json'] ) && is_array( $upload['json'] ) ? $upload['json'] : [];
        $imported[] = [
            'id'              => $attachment_id ? $attachment_id : 'media-' . wp_generate_uuid4(),
            'type'            => isset( $source_attachment['type'] ) ? sanitize_key( $source_attachment['type'] ) : 'audio',
            'attachment_role' => isset( $source_attachment['attachment_role'] ) ? sanitize_key( $source_attachment['attachment_role'] ) : 'default',
            'title'           => isset( $source_attachment['title'] ) ? sanitize_text_field( $source_attachment['title'] ) : '',
            'source_kind'     => 'import',
            'anchor_type'     => isset( $source_attachment['anchor_type'] ) ? sanitize_key( $source_attachment['anchor_type'] ) : 'song',
            'section_id'      => isset( $source_attachment['section_id'] ) ? sanitize_key( $source_attachment['section_id'] ) : '',
            'verse_index'     => isset( $source_attachment['verse_index'] ) ? absint( $source_attachment['verse_index'] ) : 0,
            'segment_index'   => isset( $source_attachment['segment_index'] ) ? absint( $source_attachment['segment_index'] ) : 0,
            'project_ids'     => isset( $source_attachment['project_ids'] ) ? (array) $source_attachment['project_ids'] : [],
            'visibility_mode' => isset( $source_attachment['visibility_mode'] ) ? sanitize_key( $source_attachment['visibility_mode'] ) : 'private',
            'visibility_group_ids' => isset( $source_attachment['visibility_group_ids'] ) ? (array) $source_attachment['visibility_group_ids'] : [],
            'visibility_user_ids'  => isset( $source_attachment['visibility_user_ids'] ) ? (array) $source_attachment['visibility_user_ids'] : [],
            'owner_user_id'   => $current_user_id,
            'storage_provider'=> 'google_drive',
            'file_id'         => isset( $file_json['id'] ) ? sanitize_text_field( (string) $file_json['id'] ) : '',
            'file_name'       => isset( $file_json['name'] ) ? sanitize_file_name( (string) $file_json['name'] ) : $file_name,
            'mime_type'       => isset( $file_json['mimeType'] ) ? sanitize_text_field( (string) $file_json['mimeType'] ) : $mime_type,
            'size_bytes'      => isset( $file_json['size'] ) ? absint( $file_json['size'] ) : strlen( $binary ),
            'duration_seconds'=> isset( $source_attachment['duration_seconds'] ) ? (float) $source_attachment['duration_seconds'] : 0,
            'created_at'      => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        ];
    }

    if ( ! empty( $imported ) && function_exists( 'wpss_replace_song_media_attachments' ) ) {
        wpss_replace_song_media_attachments( $song_id, $imported );
    }

    return [
        'count'    => count( $imported ),
        'warnings' => $warnings,
    ];
}

/**
 * Obtiene binario de adjunto desde ZIP o JSON.
 *
 * @param array $attachment_export Export del adjunto.
 * @param array $package_context Contexto del paquete.
 * @return string|false
 */
function wpss_get_import_attachment_binary( array $attachment_export, array $package_context ) {
    if ( ! empty( $attachment_export['inline_base64'] ) ) {
        return base64_decode( (string) $attachment_export['inline_base64'], true );
    }

    if ( empty( $attachment_export['archive_path'] ) ) {
        return false;
    }

    if ( isset( $package_context['zip'] ) && $package_context['zip'] instanceof ZipArchive ) {
        return $package_context['zip']->getFromName( (string) $attachment_export['archive_path'] );
    }

    return false;
}

/**
 * Guarda trazabilidad de importación.
 *
 * @param int   $song_id Canción importada.
 * @param array $data Datos de procedencia.
 * @return void
 */
function wpss_store_song_import_provenance( $song_id, array $data ) {
    update_post_meta(
        $song_id,
        '_wpss_import_provenance_json',
        wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
    );
}

/**
 * Elimina payloads inline pesados antes de guardar procedencia.
 *
 * @param array $attachments Adjuntos exportados.
 * @return array
 */
function wpss_strip_inline_attachment_payloads( $attachments ) {
    $result = [];

    foreach ( (array) $attachments as $attachment ) {
        if ( ! is_array( $attachment ) ) {
            continue;
        }

        unset( $attachment['inline_base64'] );
        $result[] = $attachment;
    }

    return $result;
}
