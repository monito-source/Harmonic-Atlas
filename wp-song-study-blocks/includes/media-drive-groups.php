<?php
/**
 * Integración de adjuntos multimedia, Google Drive y agrupaciones musicales.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'wpss_register_song_media_meta' );
add_action( 'init', 'wpss_maybe_handle_google_drive_query_callback', 1 );
add_action( 'admin_init', 'wpss_register_google_drive_settings' );
add_action( 'rest_api_init', 'wpss_register_media_drive_group_routes' );
add_action( 'admin_post_wpss_google_drive_connect', 'wpss_handle_google_drive_connect' );
add_action( 'admin_post_wpss_google_drive_callback', 'wpss_handle_google_drive_callback' );
add_action( 'admin_post_nopriv_wpss_google_drive_callback', 'wpss_handle_google_drive_callback' );
add_action( 'before_delete_post', 'wpss_cleanup_song_media_on_post_delete', 10, 1 );
add_action( 'show_user_profile', 'wpss_render_google_drive_user_profile_fields' );
add_action( 'edit_user_profile', 'wpss_render_google_drive_user_profile_fields' );
add_action( 'personal_options_update', 'wpss_save_google_drive_user_profile_fields' );
add_action( 'edit_user_profile_update', 'wpss_save_google_drive_user_profile_fields' );

/**
 * Registra el meta JSON de adjuntos multimedia por canción.
 *
 * @return void
 */
function wpss_register_song_media_meta() {
    register_post_meta(
        'cancion',
        '_adjuntos_multimedia_json',
        [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'auth_callback'     => static function() {
                return current_user_can( 'edit_posts' );
            },
            'sanitize_callback' => 'wpss_sanitize_json_string',
        ]
    );

    register_post_meta(
        'cancion',
        '_adjuntos_multimedia_acl_json',
        [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'auth_callback'     => static function() {
                return current_user_can( 'edit_posts' );
            },
            'sanitize_callback' => 'wpss_sanitize_json_string',
        ]
    );
}

/**
 * Registra opciones globales para OAuth de Google Drive.
 *
 * @return void
 */
function wpss_register_google_drive_settings() {
    register_setting(
        'wpss_settings',
        'wpss_google_drive_client_id',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]
    );

    register_setting(
        'wpss_settings',
        'wpss_google_drive_client_secret',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]
    );
}

/**
 * Registra rutas REST para la nueva capa multimedia.
 *
 * @return void
 */
function wpss_register_media_drive_group_routes() {
    register_rest_route(
        'wpss/v1',
        '/agrupaciones-musicales',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_get_agrupaciones_musicales',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/agrupacion-musical',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'wpss_rest_save_agrupacion_musical',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/agrupacion-musical/(?P<id>\d+)',
        [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'wpss_rest_get_agrupacion_musical',
                'permission_callback' => 'wpss_rest_verify_permissions',
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => 'wpss_rest_delete_agrupacion_musical',
                'permission_callback' => 'wpss_rest_verify_permissions',
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/mi/google-drive',
        [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'wpss_rest_get_google_drive_status',
                'permission_callback' => 'wpss_rest_verify_permissions',
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'wpss_rest_save_google_drive_settings',
                'permission_callback' => 'wpss_rest_verify_permissions',
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/mi/google-drive/disconnect',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'wpss_rest_disconnect_google_drive',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/google-drive/callback',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_google_drive_callback',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/google-drive/upload',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'wpss_rest_upload_song_media_to_google_drive',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/media/stream/(?P<song_id>\d+)/(?P<attachment_id>[a-zA-Z0-9_-]+)',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'wpss_rest_stream_song_media_attachment',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/media/attachment/(?P<song_id>\d+)/(?P<attachment_id>[a-zA-Z0-9_-]+)',
        [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => 'wpss_rest_update_song_media_attachment',
                'permission_callback' => 'wpss_rest_verify_permissions',
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => 'wpss_rest_delete_song_media_attachment',
                'permission_callback' => 'wpss_rest_verify_permissions',
            ],
        ]
    );

    register_rest_route(
        'wpss/v1',
        '/media/attachment/(?P<song_id>\d+)/(?P<attachment_id>[a-zA-Z0-9_-]+)/unlink',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'wpss_rest_unlink_song_media_attachment',
            'permission_callback' => 'wpss_rest_verify_permissions',
        ]
    );
}

/**
 * Devuelve los roles válidos dentro de una agrupación musical.
 *
 * @return array<string,string>
 */
function wpss_get_agrupacion_member_role_options() {
    return [
        'admin'        => __( 'Admin', 'wp-song-study' ),
        'colega'       => __( 'Colega musical', 'wp-song-study' ),
        'contribuidor' => __( 'Contribuidor', 'wp-song-study' ),
    ];
}

/**
 * Obtiene la URL del callback OAuth.
 *
 * @return string
 */
function wpss_get_google_drive_redirect_uri() {
    return add_query_arg( 'wpss_google_drive_callback', '1', home_url( '/' ) );
}

/**
 * Registra una línea de debug del flujo Google Drive cuando WP_DEBUG_LOG está activo.
 *
 * @param string $message Mensaje.
 * @param array  $context Contexto opcional.
 * @return void
 */
function wpss_google_drive_debug_log( $message, array $context = [] ) {
    if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
        return;
    }

    $prefix = '[WPSS Drive OAuth] ' . (string) $message;
    if ( ! empty( $context ) ) {
        $encoded = wp_json_encode( $context );
        if ( false !== $encoded ) {
            $prefix .= ' ' . $encoded;
        }
    }

    error_log( $prefix );
}

/**
 * Resume un identificador sensible para debug.
 *
 * @param string $value Valor.
 * @return string
 */
function wpss_google_drive_debug_hint( $value ) {
    $value = trim( (string) $value );
    if ( '' === $value ) {
        return '';
    }

    if ( strlen( $value ) <= 12 ) {
        return $value;
    }

    return substr( $value, 0, 6 ) . '...' . substr( $value, -6 );
}

/**
 * Codifica un valor en base64url.
 *
 * @param string $value Valor.
 * @return string
 */
function wpss_google_drive_base64url_encode( $value ) {
    $encoded = base64_encode( (string) $value );
    return rtrim( strtr( $encoded, '+/', '-_' ), '=' );
}

/**
 * Decodifica un valor base64url.
 *
 * @param string $value Valor.
 * @return string
 */
function wpss_google_drive_base64url_decode( $value ) {
    $value = strtr( (string) $value, '-_', '+/' );
    $pad   = strlen( $value ) % 4;
    if ( $pad > 0 ) {
        $value .= str_repeat( '=', 4 - $pad );
    }

    $decoded = base64_decode( $value, true );
    return false === $decoded ? '' : (string) $decoded;
}

/**
 * Construye un state OAuth firmado y autocontenido.
 *
 * @param int    $user_id    Usuario.
 * @param string $return_url URL de retorno.
 * @param string $flow_id    Identificador aleatorio del flujo.
 * @return string
 */
function wpss_build_google_drive_oauth_state( $user_id, $return_url, $flow_id ) {
    $payload = [
        'u' => absint( $user_id ),
        'r' => esc_url_raw( (string) $return_url ),
        'f' => sanitize_text_field( (string) $flow_id ),
        'i' => time(),
    ];

    $json = wp_json_encode( $payload );
    if ( false === $json ) {
        return '';
    }

    $signature = hash_hmac( 'sha256', $json, wp_salt( 'auth' ) );
    return wpss_google_drive_base64url_encode( wp_json_encode( [ 'p' => $payload, 's' => $signature ] ) );
}

/**
 * Resuelve un state OAuth firmado.
 *
 * @param string $state State.
 * @return array
 */
function wpss_parse_google_drive_oauth_state( $state ) {
    $decoded = wpss_google_drive_base64url_decode( $state );
    if ( '' === $decoded ) {
        return [];
    }

    $container = json_decode( $decoded, true );
    if ( ! is_array( $container ) || empty( $container['p'] ) || empty( $container['s'] ) || ! is_array( $container['p'] ) ) {
        return [];
    }

    $payload_json = wp_json_encode( $container['p'] );
    if ( false === $payload_json ) {
        return [];
    }

    $expected = hash_hmac( 'sha256', $payload_json, wp_salt( 'auth' ) );
    if ( ! hash_equals( $expected, (string) $container['s'] ) ) {
        return [];
    }

    $user_id    = isset( $container['p']['u'] ) ? absint( $container['p']['u'] ) : 0;
    $return_url = isset( $container['p']['r'] ) ? esc_url_raw( (string) $container['p']['r'] ) : '';
    $flow_id    = isset( $container['p']['f'] ) ? sanitize_text_field( (string) $container['p']['f'] ) : '';
    $issued_at  = isset( $container['p']['i'] ) ? absint( $container['p']['i'] ) : 0;

    if ( $user_id <= 0 || '' === $flow_id || $issued_at <= 0 ) {
        return [];
    }

    if ( $issued_at < ( time() - 15 * MINUTE_IN_SECONDS ) || $issued_at > ( time() + 5 * MINUTE_IN_SECONDS ) ) {
        return [];
    }

    return [
        'user_id'    => $user_id,
        'return_url' => $return_url,
        'flow_id'    => $flow_id,
        'issued_at'  => $issued_at,
    ];
}

/**
 * Obtiene el client id configurado.
 *
 * @return string
 */
function wpss_get_google_drive_client_id() {
    return trim( (string) get_option( 'wpss_google_drive_client_id', '' ) );
}

/**
 * Obtiene el client secret configurado.
 *
 * @return string
 */
function wpss_get_google_drive_client_secret() {
    return trim( (string) get_option( 'wpss_google_drive_client_secret', '' ) );
}

/**
 * Obtiene el client id override del usuario.
 *
 * @param int $user_id ID del usuario.
 * @return string
 */
function wpss_get_google_drive_user_client_id( $user_id ) {
    return trim( (string) get_user_meta( absint( $user_id ), '_wpss_google_drive_client_id', true ) );
}

/**
 * Obtiene el client secret override del usuario.
 *
 * @param int $user_id ID del usuario.
 * @return string
 */
function wpss_get_google_drive_user_client_secret( $user_id ) {
    return trim( (string) get_user_meta( absint( $user_id ), '_wpss_google_drive_client_secret', true ) );
}

/**
 * Devuelve las credenciales OAuth efectivas para un usuario.
 *
 * @param int $user_id ID del usuario.
 * @return array{client_id:string,client_secret:string,source:string}
 */
function wpss_get_google_drive_oauth_credentials( $user_id = 0 ) {
    $user_id = absint( $user_id );

    $user_client_id     = $user_id > 0 ? wpss_get_google_drive_user_client_id( $user_id ) : '';
    $user_client_secret = $user_id > 0 ? wpss_get_google_drive_user_client_secret( $user_id ) : '';

    if ( '' !== $user_client_id && '' !== $user_client_secret ) {
        return [
            'client_id'     => $user_client_id,
            'client_secret' => $user_client_secret,
            'source'        => 'user',
        ];
    }

    return [
        'client_id'     => wpss_get_google_drive_client_id(),
        'client_secret' => wpss_get_google_drive_client_secret(),
        'source'        => 'global',
    ];
}

/**
 * Indica si un usuario tiene credenciales OAuth efectivas para Drive.
 *
 * @param int $user_id ID del usuario.
 * @return bool
 */
function wpss_google_drive_is_configured_for_user( $user_id = 0 ) {
    $credentials = wpss_get_google_drive_oauth_credentials( $user_id );
    return '' !== $credentials['client_id'] && '' !== $credentials['client_secret'];
}

/**
 * Indica si la integración global está configurada.
 *
 * @return bool
 */
function wpss_google_drive_is_configured() {
    return wpss_google_drive_is_configured_for_user( 0 );
}

/**
 * Obtiene la configuración Drive del usuario.
 *
 * @param int $user_id ID del usuario.
 * @return array
 */
function wpss_get_google_drive_user_config( $user_id ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return [];
    }

    $config = get_user_meta( $user_id, '_wpss_google_drive_config', true );
    if ( ! is_array( $config ) ) {
        $config = [];
    }

    return [
        'provider'        => 'google_drive',
        'connected'       => ! empty( $config['refresh_token'] ) || ! empty( $config['access_token'] ),
        'has_access_token'=> ! empty( $config['access_token'] ),
        'has_refresh_token'=> ! empty( $config['refresh_token'] ),
        'account_email'   => isset( $config['account_email'] ) ? sanitize_email( $config['account_email'] ) : '',
        'folder_id'       => isset( $config['folder_id'] ) ? sanitize_text_field( $config['folder_id'] ) : '',
        'folder_name'     => isset( $config['folder_name'] ) ? sanitize_text_field( $config['folder_name'] ) : '',
        'folder_url'      => isset( $config['folder_url'] ) ? esc_url_raw( $config['folder_url'] ) : '',
        'access_token'    => isset( $config['access_token'] ) ? (string) $config['access_token'] : '',
        'refresh_token'   => isset( $config['refresh_token'] ) ? (string) $config['refresh_token'] : '',
        'token_expires_at'=> isset( $config['token_expires_at'] ) ? absint( $config['token_expires_at'] ) : 0,
        'connected_at'    => isset( $config['connected_at'] ) ? sanitize_text_field( $config['connected_at'] ) : '',
    ];
}

/**
 * Persiste la configuración Drive del usuario.
 *
 * @param int   $user_id ID del usuario.
 * @param array $config  Configuración.
 * @return void
 */
function wpss_set_google_drive_user_config( $user_id, array $config ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return;
    }

    update_user_meta( $user_id, '_wpss_google_drive_config', $config );
}

/**
 * Elimina la configuración Drive del usuario.
 *
 * @param int $user_id ID del usuario.
 * @return void
 */
function wpss_delete_google_drive_user_config( $user_id ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return;
    }

    delete_user_meta( $user_id, '_wpss_google_drive_config' );
    delete_user_meta( $user_id, '_wpss_google_drive_oauth_state' );
}

/**
 * Registra el último error OAuth/Drive del usuario.
 *
 * @param int    $user_id ID del usuario.
 * @param string $code    Código corto.
 * @param string $message Mensaje legible.
 * @return void
 */
function wpss_set_google_drive_last_error( $user_id, $code, $message ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return;
    }

    update_user_meta(
        $user_id,
        '_wpss_google_drive_last_error',
        [
            'code'       => sanitize_key( (string) $code ),
            'message'    => sanitize_textarea_field( (string) $message ),
            'recorded_at'=> current_time( 'mysql' ),
        ]
    );
}

/**
 * Obtiene el último error OAuth/Drive del usuario.
 *
 * @param int $user_id ID del usuario.
 * @return array
 */
function wpss_get_google_drive_last_error( $user_id ) {
    $value = get_user_meta( absint( $user_id ), '_wpss_google_drive_last_error', true );
    if ( ! is_array( $value ) ) {
        return [];
    }

    return [
        'code'        => isset( $value['code'] ) ? sanitize_key( (string) $value['code'] ) : '',
        'message'     => isset( $value['message'] ) ? sanitize_textarea_field( (string) $value['message'] ) : '',
        'recorded_at' => isset( $value['recorded_at'] ) ? sanitize_text_field( (string) $value['recorded_at'] ) : '',
    ];
}

/**
 * Limpia el último error OAuth/Drive del usuario.
 *
 * @param int $user_id ID del usuario.
 * @return void
 */
function wpss_clear_google_drive_last_error( $user_id ) {
    delete_user_meta( absint( $user_id ), '_wpss_google_drive_last_error' );
}

/**
 * Obtiene la clave transient para mapear un state OAuth con un usuario.
 *
 * @param string $state State OAuth.
 * @return string
 */
function wpss_get_google_drive_state_transient_key( $state ) {
    return 'wpss_drive_state_' . md5( (string) $state );
}

/**
 * Guarda un state OAuth temporal para resolver el callback aunque se pierda la sesión.
 *
 * @param string $state      State OAuth.
 * @param int    $user_id    Usuario destino.
 * @param string $return_url URL de retorno.
 * @return void
 */
function wpss_store_google_drive_state_payload( $state, $user_id, $return_url ) {
    $state = sanitize_text_field( (string) $state );
    if ( '' === $state ) {
        return;
    }

    set_transient(
        wpss_get_google_drive_state_transient_key( $state ),
        [
            'user_id'    => absint( $user_id ),
            'return_url' => esc_url_raw( (string) $return_url ),
        ],
        15 * MINUTE_IN_SECONDS
    );
}

/**
 * Obtiene el payload asociado a un state OAuth.
 *
 * @param string $state State OAuth.
 * @return array
 */
function wpss_get_google_drive_state_payload( $state ) {
    $state = sanitize_text_field( (string) $state );
    if ( '' === $state ) {
        return [];
    }

    $payload = get_transient( wpss_get_google_drive_state_transient_key( $state ) );
    return is_array( $payload ) ? $payload : [];
}

/**
 * Elimina el payload temporal de un state OAuth.
 *
 * @param string $state State OAuth.
 * @return void
 */
function wpss_delete_google_drive_state_payload( $state ) {
    $state = sanitize_text_field( (string) $state );
    if ( '' === $state ) {
        return;
    }

    delete_transient( wpss_get_google_drive_state_transient_key( $state ) );
}

/**
 * Determina si debe mostrarse la configuración Drive en el perfil del usuario.
 *
 * @param WP_User $user Usuario objetivo.
 * @return bool
 */
function wpss_should_show_google_drive_profile_fields( WP_User $user ) {
    if ( ! $user instanceof WP_User ) {
        return false;
    }

    if ( user_can( $user, 'manage_options' ) ) {
        return true;
    }

    $roles     = is_array( $user->roles ) ? $user->roles : [];
    $role_name = defined( 'WPSS_ROLE_COLEGA' ) ? WPSS_ROLE_COLEGA : 'colega_musical';

    return in_array( $role_name, $roles, true );
}

/**
 * Renderiza la sección Google Drive en el perfil de usuario.
 *
 * @param WP_User $user Usuario objetivo.
 * @return void
 */
function wpss_render_google_drive_user_profile_fields( $user ) {
    if ( ! $user instanceof WP_User || ! current_user_can( 'edit_user', $user->ID ) || ! wpss_should_show_google_drive_profile_fields( $user ) ) {
        return;
    }

    $user_client_id     = wpss_get_google_drive_user_client_id( $user->ID );
    $user_client_secret = wpss_get_google_drive_user_client_secret( $user->ID );
    $credentials        = wpss_get_google_drive_oauth_credentials( $user->ID );
    $drive_status       = wpss_get_google_drive_status_payload( $user->ID );
    $profile_url        = get_current_user_id() === (int) $user->ID
        ? admin_url( 'profile.php' )
        : add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) );
    $connect_url        = wpss_get_google_drive_connect_url( $user->ID, $profile_url );
    $error_code         = isset( $_GET['wpss_drive_error'] ) ? sanitize_key( wp_unslash( $_GET['wpss_drive_error'] ) ) : '';
    $status_code        = isset( $_GET['wpss_drive_status'] ) ? sanitize_key( wp_unslash( $_GET['wpss_drive_status'] ) ) : '';
    $source_label       = 'user' === $credentials['source']
        ? __( 'Credenciales propias del usuario', 'wp-song-study' )
        : __( 'Usando credenciales globales del plugin', 'wp-song-study' );
    $error_messages     = [
        'nonce'  => __( 'El enlace de conexión expiró. Vuelve a pulsar "Conectar Google Drive".', 'wp-song-study' ),
        'user'   => __( 'La conexión solo puede iniciarse para el usuario que tiene la sesión activa.', 'wp-song-study' ),
        'config' => __( 'Faltan credenciales OAuth válidas para este usuario.', 'wp-song-study' ),
        'state'  => __( 'La autorización de Google Drive no pudo validarse correctamente.', 'wp-song-study' ),
        'code'   => __( 'Google no devolvió un código de autorización.', 'wp-song-study' ),
        'token'  => __( 'No fue posible completar el intercambio de tokens con Google Drive.', 'wp-song-study' ),
    ];

    wp_nonce_field( 'wpss_save_google_drive_profile_' . $user->ID, 'wpss_google_drive_profile_nonce' );
    ?>
    <h2><?php echo esc_html__( 'Google Drive para Cancionario', 'wp-song-study' ); ?></h2>
    <?php if ( isset( $error_messages[ $error_code ] ) ) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html( $error_messages[ $error_code ] ); ?></p></div>
    <?php elseif ( 'connected' === $status_code ) : ?>
        <div class="notice notice-success inline"><p><?php echo esc_html__( 'Google Drive conectado correctamente.', 'wp-song-study' ); ?></p></div>
    <?php endif; ?>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="wpss_google_drive_user_client_id"><?php echo esc_html__( 'OAuth Client ID', 'wp-song-study' ); ?></label></th>
            <td>
                <input
                    type="text"
                    name="wpss_google_drive_user_client_id"
                    id="wpss_google_drive_user_client_id"
                    value="<?php echo esc_attr( $user_client_id ); ?>"
                    class="regular-text code"
                />
                <p class="description">
                    <?php echo esc_html__( 'Opcional. Si lo dejas vacío, este usuario usará el Client ID global del plugin.', 'wp-song-study' ); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th><label for="wpss_google_drive_user_client_secret"><?php echo esc_html__( 'OAuth Client Secret', 'wp-song-study' ); ?></label></th>
            <td>
                <input
                    type="password"
                    name="wpss_google_drive_user_client_secret"
                    id="wpss_google_drive_user_client_secret"
                    value="<?php echo esc_attr( $user_client_secret ); ?>"
                    class="regular-text code"
                    autocomplete="new-password"
                />
                <p class="description">
                    <?php echo esc_html__( 'Opcional. Si completas este par de credenciales, la autorización de este usuario se hará con su propia app de Google.', 'wp-song-study' ); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th><?php echo esc_html__( 'Origen activo', 'wp-song-study' ); ?></th>
            <td>
                <strong><?php echo esc_html( $source_label ); ?></strong>
                <p class="description">
                    <?php echo esc_html__( 'Redirect URI para registrar en Google Cloud:', 'wp-song-study' ); ?>
                    <code><?php echo esc_html( wpss_get_google_drive_redirect_uri() ); ?></code>
                </p>
            </td>
        </tr>
        <tr>
            <th><?php echo esc_html__( 'Estado de conexión', 'wp-song-study' ); ?></th>
            <td>
                <p>
                    <?php
                    echo esc_html(
                        ! empty( $drive_status['connected'] )
                            ? sprintf(
                                /* translators: %s email de Google Drive. */
                                __( 'Conectado como %s', 'wp-song-study' ),
                                $drive_status['account_email'] ? $drive_status['account_email'] : __( 'cuenta sin email visible', 'wp-song-study' )
                            )
                            : __( 'Sin conexión activa a Google Drive.', 'wp-song-study' )
                    );
                    ?>
                </p>
                <p class="description">
                    <?php echo esc_html__( 'La carpeta y la conexión OAuth siguen administrándose desde el menú "Mi Drive" del cancionero.', 'wp-song-study' ); ?>
                </p>
                <p>
                    <?php if ( ! empty( $drive_status['configured'] ) ) : ?>
                        <a class="button button-secondary" href="<?php echo esc_url( $connect_url ); ?>">
                            <?php echo esc_html__( 'Conectar Google Drive', 'wp-song-study' ); ?>
                        </a>
                    <?php else : ?>
                        <span class="description"><?php echo esc_html__( 'Completa Client ID y Client Secret para habilitar la conexión OAuth.', 'wp-song-study' ); ?></span>
                    <?php endif; ?>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-mi-drive' ) ); ?>">
                        <?php echo esc_html__( 'Abrir Mi Drive', 'wp-song-study' ); ?>
                    </a>
                </p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Guarda la configuración Google Drive del perfil de usuario.
 *
 * @param int $user_id ID del usuario.
 * @return void
 */
function wpss_save_google_drive_user_profile_fields( $user_id ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 || ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user instanceof WP_User || ! wpss_should_show_google_drive_profile_fields( $user ) ) {
        return;
    }

    $nonce = isset( $_POST['wpss_google_drive_profile_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpss_google_drive_profile_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wpss_save_google_drive_profile_' . $user_id ) ) {
        return;
    }

    $client_id     = isset( $_POST['wpss_google_drive_user_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wpss_google_drive_user_client_id'] ) ) : '';
    $client_secret = isset( $_POST['wpss_google_drive_user_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['wpss_google_drive_user_client_secret'] ) ) : '';

    if ( '' !== $client_id ) {
        update_user_meta( $user_id, '_wpss_google_drive_client_id', $client_id );
    } else {
        delete_user_meta( $user_id, '_wpss_google_drive_client_id' );
    }

    if ( '' !== $client_secret ) {
        update_user_meta( $user_id, '_wpss_google_drive_client_secret', $client_secret );
    } else {
        delete_user_meta( $user_id, '_wpss_google_drive_client_secret' );
    }
}

/**
 * Extrae un folder id desde un texto o URL de Drive.
 *
 * @param string $value Valor recibido.
 * @return string
 */
function wpss_parse_google_drive_folder_id( $value ) {
    $value = trim( (string) $value );
    if ( '' === $value ) {
        return '';
    }

    if ( preg_match( '#/folders/([a-zA-Z0-9_-]+)#', $value, $matches ) ) {
        return sanitize_text_field( $matches[1] );
    }

    if ( preg_match( '/^[a-zA-Z0-9_-]{10,}$/', $value ) ) {
        return sanitize_text_field( $value );
    }

    return '';
}

/**
 * Resuelve la URL de retorno para el flujo OAuth.
 *
 * @param int    $user_id      ID del usuario.
 * @param string $redirect_to  URL solicitada.
 * @return string
 */
function wpss_get_google_drive_return_url( $user_id, $redirect_to = '' ) {
    $user_id      = absint( $user_id );
    $default_url  = admin_url( 'admin.php?page=wpss-mi-drive' );
    $fallback_url = $default_url;

    if ( $user_id > 0 && current_user_can( 'edit_user', $user_id ) ) {
        $fallback_url = get_current_user_id() === $user_id
            ? admin_url( 'profile.php' )
            : add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) );
    }

    $redirect_to = is_string( $redirect_to ) ? trim( $redirect_to ) : '';
    if ( '' === $redirect_to ) {
        return $default_url;
    }

    return wp_validate_redirect( $redirect_to, $fallback_url );
}

/**
 * Crea la URL de conexión OAuth para el usuario actual.
 *
 * @param int    $user_id     ID del usuario.
 * @param string $redirect_to URL opcional de retorno.
 * @return string
 */
function wpss_get_google_drive_connect_url( $user_id, $redirect_to = '' ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 || ! wpss_google_drive_is_configured_for_user( $user_id ) ) {
        return '';
    }

    $action_url = admin_url( 'admin-post.php?action=wpss_google_drive_connect' );
    return wp_nonce_url(
        add_query_arg(
            [
                'user_id'     => $user_id,
                'redirect_to' => wpss_get_google_drive_return_url( $user_id, $redirect_to ),
            ],
            $action_url
        ),
        'wpss_google_drive_connect_' . $user_id
    );
}

/**
 * Inicia el flujo OAuth contra Google Drive.
 *
 * @return void
 */
function wpss_handle_google_drive_connect() {
    if ( ! is_user_logged_in() ) {
        wp_die( esc_html__( 'Debes iniciar sesión para conectar Google Drive.', 'wp-song-study' ) );
    }

    $current_user_id = get_current_user_id();
    $requested_user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
    $user_id = $requested_user_id > 0 ? $requested_user_id : $current_user_id;
    $return_url = wpss_get_google_drive_return_url(
        $user_id,
        isset( $_GET['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_GET['redirect_to'] ) ) : ''
    );

    if ( $user_id <= 0 || $user_id !== $current_user_id ) {
        wpss_set_google_drive_last_error( $current_user_id, 'user', __( 'La conexión se intentó para un usuario distinto al de la sesión activa.', 'wp-song-study' ) );
        wp_safe_redirect( add_query_arg( 'wpss_drive_error', 'user', $return_url ) );
        exit;
    }

    if ( ! wpss_google_drive_is_configured_for_user( $user_id ) ) {
        wpss_set_google_drive_last_error( $user_id, 'config', __( 'Faltan credenciales OAuth válidas para este usuario.', 'wp-song-study' ) );
        wp_safe_redirect( add_query_arg( 'wpss_drive_error', 'config', $return_url ) );
        exit;
    }

    wpss_clear_google_drive_last_error( $user_id );
    $credentials = wpss_get_google_drive_oauth_credentials( $user_id );

    $flow_id = wp_generate_password( 40, false, false );
    $state   = wpss_build_google_drive_oauth_state( $user_id, $return_url, $flow_id );

    update_user_meta( $user_id, '_wpss_google_drive_oauth_state', $flow_id );
    update_user_meta( $user_id, '_wpss_google_drive_oauth_redirect_to', $return_url );
    wpss_store_google_drive_state_payload( $flow_id, $user_id, $return_url );
    wpss_google_drive_debug_log(
        'Inicio de conexión OAuth.',
        [
            'user_id'      => $user_id,
            'return_url'   => $return_url,
            'redirect_uri' => wpss_get_google_drive_redirect_uri(),
            'source'       => $credentials['source'],
            'state_mode'   => 'signed',
            'flow_id_prefix' => substr( $flow_id, 0, 8 ),
            'client_id_hint' => wpss_google_drive_debug_hint( $credentials['client_id'] ),
        ]
    );

    $query = [
        'client_id'             => $credentials['client_id'],
        'redirect_uri'          => wpss_get_google_drive_redirect_uri(),
        'response_type'         => 'code',
        'access_type'           => 'offline',
        'prompt'                => 'consent',
        'include_granted_scopes'=> 'true',
        'scope'                 => implode(
            ' ',
            [
                'https://www.googleapis.com/auth/drive.file',
                'https://www.googleapis.com/auth/userinfo.email',
                'openid',
            ]
        ),
        'state'                 => $state,
    ];

    wp_redirect( 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ), 302 );
    exit;
}

/**
 * Completa el callback OAuth de Google.
 *
 * @param array $params Parámetros recibidos.
 * @return void
 */
function wpss_complete_google_drive_callback( array $params ) {
    $state       = isset( $params['state'] ) ? sanitize_text_field( (string) $params['state'] ) : '';
    $code        = isset( $params['code'] ) ? sanitize_text_field( (string) $params['code'] ) : '';
    $oauth_error = isset( $params['error'] ) ? sanitize_key( (string) $params['error'] ) : '';

    $signed_state  = wpss_parse_google_drive_oauth_state( $state );
    $state_key     = ! empty( $signed_state['flow_id'] ) ? $signed_state['flow_id'] : $state;
    $state_payload = wpss_get_google_drive_state_payload( $state_key );
    $user_id       = ! empty( $signed_state['user_id'] )
        ? absint( $signed_state['user_id'] )
        : ( ! empty( $state_payload['user_id'] ) ? absint( $state_payload['user_id'] ) : get_current_user_id() );
    $return_url    = wpss_get_google_drive_return_url(
        $user_id,
        ! empty( $signed_state['return_url'] )
            ? (string) $signed_state['return_url']
            : ( isset( $state_payload['return_url'] ) ? (string) $state_payload['return_url'] : '' )
    );

    wpss_google_drive_debug_log(
        'Callback OAuth recibido.',
        [
            'user_id'       => $user_id,
            'has_state'     => '' !== $state,
            'has_code'      => '' !== $code,
            'oauth_error'   => $oauth_error,
            'return_url'    => $return_url,
            'redirect_uri'  => wpss_get_google_drive_redirect_uri(),
            'request_uri'   => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
            'state_mode'    => ! empty( $signed_state ) ? 'signed' : ( ! empty( $state_payload ) ? 'transient' : 'none' ),
            'state_length'  => strlen( $state ),
            'signed_user_id'=> ! empty( $signed_state['user_id'] ) ? absint( $signed_state['user_id'] ) : 0,
            'flow_id_prefix'=> ! empty( $signed_state['flow_id'] ) ? substr( (string) $signed_state['flow_id'], 0, 8 ) : '',
        ]
    );

    if ( $user_id <= 0 ) {
        wpss_google_drive_debug_log( 'Callback sin usuario resoluble.' );
        wp_die( esc_html__( 'No fue posible resolver el usuario para completar la conexión con Google Drive.', 'wp-song-study' ) );
    }

    $expected_state = (string) get_user_meta( $user_id, '_wpss_google_drive_oauth_state', true );
    $received_flow_id = ! empty( $signed_state['flow_id'] ) ? (string) $signed_state['flow_id'] : (string) $state;

    if ( '' === $expected_state || '' === $received_flow_id || ! hash_equals( $expected_state, $received_flow_id ) ) {
        delete_user_meta( $user_id, '_wpss_google_drive_oauth_redirect_to' );
        delete_user_meta( $user_id, '_wpss_google_drive_oauth_state' );
        wpss_delete_google_drive_state_payload( $state_key );
        wpss_set_google_drive_last_error( $user_id, 'state', __( 'El state recibido en el callback no coincide con el esperado para este usuario.', 'wp-song-study' ) );
        wpss_google_drive_debug_log(
            'State inválido en callback.',
            [
                'user_id'        => $user_id,
                'expected_state' => '' !== $expected_state,
                'received_state' => '' !== $received_flow_id,
            ]
        );
        wp_safe_redirect( add_query_arg( 'wpss_drive_error', 'state', $return_url ) );
        exit;
    }

    delete_user_meta( $user_id, '_wpss_google_drive_oauth_state' );
    delete_user_meta( $user_id, '_wpss_google_drive_oauth_redirect_to' );
    wpss_delete_google_drive_state_payload( $state_key );

    if ( '' !== $oauth_error ) {
        $message = sprintf(
            /* translators: %s error de Google OAuth. */
            __( 'Google devolvió un error OAuth: %s', 'wp-song-study' ),
            $oauth_error
        );
        wpss_set_google_drive_last_error( $user_id, $oauth_error, $message );
        wpss_google_drive_debug_log(
            'Google devolvió error OAuth.',
            [
                'user_id'     => $user_id,
                'oauth_error' => $oauth_error,
            ]
        );
        wp_safe_redirect( add_query_arg( 'wpss_drive_error', 'token', $return_url ) );
        exit;
    }

    if ( '' === $code ) {
        wpss_set_google_drive_last_error( $user_id, 'code', __( 'Google no devolvió un código de autorización.', 'wp-song-study' ) );
        wpss_google_drive_debug_log( 'Callback sin código de autorización.', [ 'user_id' => $user_id ] );
        wp_safe_redirect( add_query_arg( 'wpss_drive_error', 'code', $return_url ) );
        exit;
    }

    $credentials = wpss_get_google_drive_oauth_credentials( $user_id );
    if ( '' === $credentials['client_id'] || '' === $credentials['client_secret'] ) {
        wpss_set_google_drive_last_error( $user_id, 'config', __( 'Faltan credenciales OAuth válidas al volver del callback.', 'wp-song-study' ) );
        wpss_google_drive_debug_log( 'Callback sin credenciales válidas.', [ 'user_id' => $user_id ] );
        wp_safe_redirect( add_query_arg( 'wpss_drive_error', 'config', $return_url ) );
        exit;
    }

    $token_response = wp_remote_post(
        'https://oauth2.googleapis.com/token',
        [
            'timeout' => 20,
            'body'    => [
                'code'          => $code,
                'client_id'     => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'redirect_uri'  => wpss_get_google_drive_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ],
        ]
    );

    if ( is_wp_error( $token_response ) ) {
        wpss_set_google_drive_last_error( $user_id, 'token_request', $token_response->get_error_message() );
        wpss_google_drive_debug_log(
            'Falló la petición de token.',
            [
                'user_id' => $user_id,
                'error'   => $token_response->get_error_message(),
            ]
        );
        wp_safe_redirect( add_query_arg( 'wpss_drive_error', 'token', $return_url ) );
        exit;
    }

    $token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
    if ( ! is_array( $token_body ) || empty( $token_body['access_token'] ) ) {
        $token_error_message = is_array( $token_body ) && ! empty( $token_body['error_description'] )
            ? (string) $token_body['error_description']
            : wp_remote_retrieve_body( $token_response );
        wpss_set_google_drive_last_error( $user_id, 'token_body', $token_error_message );
        wpss_google_drive_debug_log(
            'Respuesta inválida al intercambiar token.',
            [
                'user_id' => $user_id,
                'body'    => $token_error_message,
            ]
        );
        wp_safe_redirect( add_query_arg( 'wpss_drive_error', 'token', $return_url ) );
        exit;
    }

    $existing_config = wpss_get_google_drive_user_config( $user_id );

    $userinfo_response = wp_remote_get(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token_body['access_token'],
            ],
        ]
    );

    $userinfo = [];
    if ( ! is_wp_error( $userinfo_response ) ) {
        $userinfo = json_decode( wp_remote_retrieve_body( $userinfo_response ), true );
        if ( ! is_array( $userinfo ) ) {
            $userinfo = [];
        }
    }

    $config = [
        'provider'         => 'google_drive',
        'account_email'    => isset( $userinfo['email'] ) ? sanitize_email( $userinfo['email'] ) : '',
        'folder_id'        => ! empty( $existing_config['folder_id'] ) ? sanitize_text_field( $existing_config['folder_id'] ) : '',
        'folder_name'      => ! empty( $existing_config['folder_name'] ) ? sanitize_text_field( $existing_config['folder_name'] ) : '',
        'folder_url'       => ! empty( $existing_config['folder_url'] ) ? esc_url_raw( $existing_config['folder_url'] ) : '',
        'access_token'     => sanitize_text_field( $token_body['access_token'] ),
        'refresh_token'    => isset( $token_body['refresh_token'] ) ? sanitize_text_field( $token_body['refresh_token'] ) : '',
        'token_expires_at' => time() + max( 60, absint( isset( $token_body['expires_in'] ) ? $token_body['expires_in'] : 3600 ) ),
        'connected_at'     => ! empty( $existing_config['connected_at'] ) ? sanitize_text_field( $existing_config['connected_at'] ) : current_time( 'mysql' ),
    ];

    if ( '' === $config['refresh_token'] ) {
        $existing = wpss_get_google_drive_user_config( $user_id );
        if ( ! empty( $existing['refresh_token'] ) ) {
            $config['refresh_token'] = $existing['refresh_token'];
        }
    }

    wpss_set_google_drive_user_config( $user_id, $config );
    wpss_clear_google_drive_last_error( $user_id );
    wpss_google_drive_debug_log(
        'Tokens guardados tras callback.',
        [
            'user_id'           => $user_id,
            'has_access_token'  => ! empty( $config['access_token'] ),
            'has_refresh_token' => ! empty( $config['refresh_token'] ),
            'account_email'     => $config['account_email'],
        ]
    );

    $folder = wpss_google_drive_ensure_default_folder( $user_id );
    if ( is_array( $folder ) && ! empty( $folder['id'] ) ) {
        $config['folder_id']   = sanitize_text_field( $folder['id'] );
        $config['folder_name'] = sanitize_text_field( $folder['name'] );
        $config['folder_url']  = 'https://drive.google.com/drive/folders/' . rawurlencode( $folder['id'] );
        wpss_set_google_drive_user_config( $user_id, $config );
        wpss_google_drive_debug_log(
            'Carpeta por defecto confirmada.',
            [
                'user_id'   => $user_id,
                'folder_id' => $config['folder_id'],
            ]
        );
    }

    wp_safe_redirect( add_query_arg( 'wpss_drive_status', 'connected', $return_url ) );
    exit;
}

/**
 * Atiende el callback OAuth legado vía admin-post.
 *
 * @return void
 */
function wpss_handle_google_drive_callback() {
    wpss_complete_google_drive_callback( wp_unslash( $_GET ) );
}

/**
 * Atiende el callback OAuth vía query param en el home del sitio.
 *
 * @return void
 */
function wpss_maybe_handle_google_drive_query_callback() {
    if ( ! isset( $_GET['wpss_google_drive_callback'] ) ) {
        return;
    }

    wpss_google_drive_debug_log(
        'Query callback bootstrap.',
        [
            'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
            'query_keys'  => array_keys( wp_unslash( $_GET ) ),
        ]
    );

    wpss_complete_google_drive_callback( wp_unslash( $_GET ) );
}

/**
 * Atiende el callback OAuth vía REST pública.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return void
 */
function wpss_rest_google_drive_callback( WP_REST_Request $request ) {
    $params = $request->get_params();

    if ( empty( $params['state'] ) && isset( $_GET['state'] ) ) {
        $params['state'] = wp_unslash( $_GET['state'] );
    }

    if ( empty( $params['code'] ) && isset( $_GET['code'] ) ) {
        $params['code'] = wp_unslash( $_GET['code'] );
    }

    if ( empty( $params['error'] ) && isset( $_GET['error'] ) ) {
        $params['error'] = wp_unslash( $_GET['error'] );
    }

    wpss_google_drive_debug_log(
        'REST callback params normalizados.',
        [
            'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
            'query_keys'  => array_keys( wp_unslash( $_GET ) ),
            'has_state'   => ! empty( $params['state'] ),
            'has_code'    => ! empty( $params['code'] ),
            'has_error'   => ! empty( $params['error'] ),
        ]
    );

    wpss_complete_google_drive_callback( $params );
}

/**
 * Devuelve un access token válido, refrescando si es necesario.
 *
 * @param int $user_id ID del usuario.
 * @return string|WP_Error
 */
function wpss_get_google_drive_access_token( $user_id ) {
    $user_id = absint( $user_id );
    $config  = wpss_get_google_drive_user_config( $user_id );
    $credentials = wpss_get_google_drive_oauth_credentials( $user_id );

    if ( empty( $config['access_token'] ) && empty( $config['refresh_token'] ) ) {
        return new WP_Error( 'wpss_drive_not_connected', __( 'El usuario no tiene Google Drive conectado.', 'wp-song-study' ) );
    }

    if ( ! empty( $config['access_token'] ) && ! empty( $config['token_expires_at'] ) && (int) $config['token_expires_at'] > time() + 60 ) {
        return (string) $config['access_token'];
    }

    if ( empty( $config['refresh_token'] ) ) {
        return new WP_Error( 'wpss_drive_refresh_missing', __( 'No hay refresh token disponible para Google Drive.', 'wp-song-study' ) );
    }

    if ( '' === $credentials['client_id'] || '' === $credentials['client_secret'] ) {
        return new WP_Error( 'wpss_drive_config_missing', __( 'Faltan las credenciales OAuth de Google Drive para este usuario.', 'wp-song-study' ) );
    }

    $response = wp_remote_post(
        'https://oauth2.googleapis.com/token',
        [
            'timeout' => 20,
            'body'    => [
                'client_id'     => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $config['refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
        return new WP_Error( 'wpss_drive_refresh_failed', __( 'No fue posible refrescar la sesión de Google Drive.', 'wp-song-study' ) );
    }

    $config['access_token']     = sanitize_text_field( $body['access_token'] );
    $config['token_expires_at'] = time() + max( 60, absint( isset( $body['expires_in'] ) ? $body['expires_in'] : 3600 ) );
    wpss_set_google_drive_user_config( $user_id, $config );

    return (string) $config['access_token'];
}

/**
 * Ejecuta una petición autenticada a Google Drive.
 *
 * @param int    $user_id ID del usuario propietario.
 * @param string $method  Método HTTP.
 * @param string $url     URL destino.
 * @param array  $args    Argumentos extra.
 * @return array|WP_Error
 */
function wpss_google_drive_request( $user_id, $method, $url, array $args = [] ) {
    $token = wpss_get_google_drive_access_token( $user_id );
    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : [];
    $headers['Authorization'] = 'Bearer ' . $token;

    $request_args          = $args;
    $request_args['method'] = strtoupper( $method );
    $request_args['headers'] = $headers;
    if ( ! isset( $request_args['timeout'] ) ) {
        $request_args['timeout'] = 30;
    }

    $response = wp_remote_request( $url, $request_args );
    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code >= 400 ) {
        return new WP_Error(
            'wpss_drive_http_error',
            sprintf( 'Google Drive HTTP %d', $code ),
            [ 'body' => wp_remote_retrieve_body( $response ) ]
        );
    }

    $body = wp_remote_retrieve_body( $response );
    $decoded = json_decode( $body, true );

    return [
        'code'    => $code,
        'headers' => wp_remote_retrieve_headers( $response ),
        'body'    => $body,
        'json'    => is_array( $decoded ) ? $decoded : null,
    ];
}

/**
 * Crea una carpeta por defecto en Drive para el usuario si no existe.
 *
 * @param int $user_id ID del usuario.
 * @return array|WP_Error
 */
function wpss_google_drive_ensure_default_folder( $user_id ) {
    $config = wpss_get_google_drive_user_config( $user_id );
    if ( ! empty( $config['folder_id'] ) ) {
        return [
            'id'   => $config['folder_id'],
            'name' => $config['folder_name'] ? $config['folder_name'] : 'HarmonyAtlas',
        ];
    }

    $metadata = [
        'name'     => 'HarmonyAtlas',
        'mimeType' => 'application/vnd.google-apps.folder',
    ];

    $result = wpss_google_drive_request(
        $user_id,
        'POST',
        'https://www.googleapis.com/drive/v3/files?fields=id,name',
        [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body'    => wp_json_encode( $metadata ),
        ]
    );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return is_array( $result['json'] ) ? $result['json'] : new WP_Error( 'wpss_drive_folder_failed', __( 'No fue posible crear la carpeta por defecto en Google Drive.', 'wp-song-study' ) );
}

/**
 * Normaliza nombres de carpetas para Drive.
 *
 * @param string $value Valor original.
 * @param string $fallback Fallback.
 * @return string
 */
function wpss_google_drive_normalize_folder_name( $value, $fallback = 'Sin nombre' ) {
    $value = wp_strip_all_tags( (string) $value );
    $value = preg_replace( '/[\\\\\/]+/', '-', $value );
    $value = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );
    $value = preg_replace( '/\s+/u', ' ', $value );
    $value = trim( (string) $value, " .\t\n\r\0\x0B" );
    if ( '' === $value ) {
        $value = $fallback;
    }
    return mb_substr( $value, 0, 120 );
}

/**
 * Construye un nombre de archivo a partir del título visible preservando extensión.
 *
 * @param string $title             Título visible.
 * @param string $current_file_name Nombre actual.
 * @return string
 */
function wpss_song_media_build_file_name_from_title( $title, $current_file_name ) {
    $title             = sanitize_text_field( (string) $title );
    $current_file_name = sanitize_file_name( (string) $current_file_name );

    $base_name = sanitize_file_name( $title );
    if ( '' === $base_name ) {
        $base_name = 'archivo';
    }

    $extension = pathinfo( $current_file_name, PATHINFO_EXTENSION );
    if ( is_string( $extension ) && '' !== $extension ) {
        return sanitize_file_name( $base_name . '.' . $extension );
    }

    return $base_name;
}

/**
 * Escapa un valor para query de Google Drive.
 *
 * @param string $value Valor.
 * @return string
 */
function wpss_google_drive_escape_query_value( $value ) {
    return str_replace(
        [ '\\', "'" ],
        [ '\\\\', "\\'" ],
        (string) $value
    );
}

/**
 * Busca o crea una carpeta hija en Google Drive.
 *
 * @param int    $user_id   Usuario propietario.
 * @param string $parent_id Carpeta padre.
 * @param string $name      Nombre de carpeta.
 * @return array|WP_Error
 */
function wpss_google_drive_ensure_named_folder( $user_id, $parent_id, $name ) {
    $user_id   = absint( $user_id );
    $parent_id = sanitize_text_field( (string) $parent_id );
    $name      = wpss_google_drive_normalize_folder_name( $name );

    if ( $user_id <= 0 || '' === $parent_id || '' === $name ) {
        return new WP_Error( 'wpss_drive_folder_invalid', __( 'No hay datos suficientes para ubicar la carpeta en Drive.', 'wp-song-study' ) );
    }

    $query = sprintf(
        "mimeType='application/vnd.google-apps.folder' and trashed=false and name='%s' and '%s' in parents",
        wpss_google_drive_escape_query_value( $name ),
        wpss_google_drive_escape_query_value( $parent_id )
    );

    $lookup = wpss_google_drive_request(
        $user_id,
        'GET',
        'https://www.googleapis.com/drive/v3/files?q=' . rawurlencode( $query ) . '&fields=files(id,name)&pageSize=1&supportsAllDrives=true&includeItemsFromAllDrives=true'
    );

    if ( is_wp_error( $lookup ) ) {
        return $lookup;
    }

    $existing = isset( $lookup['json']['files'][0] ) && is_array( $lookup['json']['files'][0] )
        ? $lookup['json']['files'][0]
        : null;
    if ( $existing && ! empty( $existing['id'] ) ) {
        return $existing;
    }

    $metadata = [
        'name'     => $name,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents'  => [ $parent_id ],
    ];

    $created = wpss_google_drive_request(
        $user_id,
        'POST',
        'https://www.googleapis.com/drive/v3/files?fields=id,name',
        [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body'    => wp_json_encode( $metadata ),
        ]
    );

    if ( is_wp_error( $created ) ) {
        return $created;
    }

    return is_array( $created['json'] ) ? $created['json'] : new WP_Error( 'wpss_drive_folder_failed', __( 'No fue posible crear la carpeta de contexto en Google Drive.', 'wp-song-study' ) );
}

/**
 * Obtiene el mapa de carpetas Drive por canción.
 *
 * @param int $song_id Canción.
 * @return array
 */
function wpss_get_song_media_drive_folder_map( $song_id ) {
    $raw = get_post_meta( absint( $song_id ), '_adjuntos_multimedia_drive_folders_json', true );
    if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
        return [];
    }

    $decoded = json_decode( $raw, true );
    return is_array( $decoded ) ? $decoded : [];
}

/**
 * Persiste el mapa de carpetas Drive por canción.
 *
 * @param int   $song_id Canción.
 * @param array $map     Mapa.
 * @return void
 */
function wpss_set_song_media_drive_folder_map( $song_id, array $map ) {
    $song_id = absint( $song_id );
    if ( $song_id <= 0 ) {
        return;
    }

    if ( empty( $map ) ) {
        delete_post_meta( $song_id, '_adjuntos_multimedia_drive_folders_json' );
        return;
    }

    update_post_meta(
        $song_id,
        '_adjuntos_multimedia_drive_folders_json',
        wp_json_encode( $map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
    );
}

/**
 * Obtiene la entrada de mapa Drive para un usuario dentro de una canción.
 *
 * @param int $song_id  Canción.
 * @param int $user_id  Usuario.
 * @return array
 */
function wpss_get_song_media_drive_folder_entry( $song_id, $user_id ) {
    $map      = wpss_get_song_media_drive_folder_map( $song_id );
    $user_key = (string) absint( $user_id );
    $entry    = isset( $map['users'][ $user_key ] ) && is_array( $map['users'][ $user_key ] )
        ? $map['users'][ $user_key ]
        : [];

    if ( empty( $entry['section_folder_ids'] ) || ! is_array( $entry['section_folder_ids'] ) ) {
        $entry['section_folder_ids'] = [];
    }

    return $entry;
}

/**
 * Actualiza la entrada de mapa Drive para un usuario dentro de una canción.
 *
 * @param int   $song_id Canción.
 * @param int   $user_id Usuario.
 * @param array $entry   Entrada.
 * @return void
 */
function wpss_set_song_media_drive_folder_entry( $song_id, $user_id, array $entry ) {
    $song_id  = absint( $song_id );
    $user_key = (string) absint( $user_id );
    if ( $song_id <= 0 || '0' === $user_key ) {
        return;
    }

    $map = wpss_get_song_media_drive_folder_map( $song_id );
    if ( empty( $map['users'] ) || ! is_array( $map['users'] ) ) {
        $map['users'] = [];
    }

    if ( empty( $entry['section_folder_ids'] ) || ! is_array( $entry['section_folder_ids'] ) ) {
        $entry['section_folder_ids'] = [];
    }

    $map['users'][ $user_key ] = $entry;
    wpss_set_song_media_drive_folder_map( $song_id, $map );
}

/**
 * Obtiene metadata básica de un archivo/carpeta en Google Drive.
 *
 * @param int    $user_id Usuario.
 * @param string $file_id Archivo.
 * @param string $fields  Campos.
 * @return array|WP_Error
 */
function wpss_google_drive_get_file_metadata( $user_id, $file_id, $fields = 'id,name,parents,mimeType' ) {
    $user_id = absint( $user_id );
    $file_id = sanitize_text_field( (string) $file_id );
    $fields  = sanitize_text_field( (string) $fields );

    if ( $user_id <= 0 || '' === $file_id ) {
        return new WP_Error( 'wpss_drive_file_invalid', __( 'No fue posible consultar el archivo en Google Drive.', 'wp-song-study' ) );
    }

    return wpss_google_drive_request(
        $user_id,
        'GET',
        add_query_arg(
            [
                'fields'            => $fields,
                'supportsAllDrives' => 'true',
            ],
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id )
        )
    );
}

/**
 * Renombra un archivo o carpeta en Google Drive.
 *
 * @param int    $user_id  Usuario.
 * @param string $file_id  Archivo.
 * @param string $new_name Nuevo nombre.
 * @return array|WP_Error
 */
function wpss_google_drive_rename_file( $user_id, $file_id, $new_name ) {
    $user_id  = absint( $user_id );
    $file_id  = sanitize_text_field( (string) $file_id );
    $new_name = wpss_google_drive_normalize_folder_name( $new_name );

    if ( $user_id <= 0 || '' === $file_id || '' === $new_name ) {
        return new WP_Error( 'wpss_drive_rename_invalid', __( 'No fue posible renombrar el elemento en Google Drive.', 'wp-song-study' ) );
    }

    return wpss_google_drive_request(
        $user_id,
        'PATCH',
        add_query_arg(
            [
                'fields'            => 'id,name,parents',
                'supportsAllDrives' => 'true',
            ],
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id )
        ),
        [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body'    => wp_json_encode(
                [
                    'name' => $new_name,
                ]
            ),
        ]
    );
}

/**
 * Obtiene la cadena de ancestros de un archivo en Drive, desde la raíz al archivo.
 *
 * @param int    $user_id Usuario.
 * @param string $file_id Archivo.
 * @param int    $max_hops Máximo de saltos.
 * @return array|WP_Error
 */
function wpss_google_drive_get_file_ancestry( $user_id, $file_id, $max_hops = 10 ) {
    $user_id  = absint( $user_id );
    $file_id  = sanitize_text_field( (string) $file_id );
    $max_hops = max( 1, absint( $max_hops ) );

    if ( $user_id <= 0 || '' === $file_id ) {
        return new WP_Error( 'wpss_drive_ancestry_invalid', __( 'No fue posible resolver la ruta del archivo en Google Drive.', 'wp-song-study' ) );
    }

    $chain      = [];
    $current_id = $file_id;

    for ( $i = 0; $i < $max_hops; $i++ ) {
        $meta = wpss_google_drive_get_file_metadata( $user_id, $current_id );
        if ( is_wp_error( $meta ) ) {
            return $meta;
        }

        $node = is_array( $meta['json'] ?? null ) ? $meta['json'] : [];
        if ( empty( $node['id'] ) ) {
            break;
        }

        $chain[] = $node;
        $parents = isset( $node['parents'] ) && is_array( $node['parents'] ) ? array_values( $node['parents'] ) : [];
        if ( empty( $parents[0] ) ) {
            break;
        }

        $current_id = sanitize_text_field( (string) $parents[0] );
    }

    return array_reverse( $chain );
}

/**
 * Intenta sembrar el mapa de carpetas Drive leyendo adjuntos existentes.
 *
 * @param int   $song_id      Canción.
 * @param int   $user_id      Usuario propietario.
 * @param array $attachments  Adjuntos opcionales.
 * @return array
 */
function wpss_google_drive_capture_song_folder_map_from_attachments( $song_id, $user_id, array $attachments = [] ) {
    $song_id = absint( $song_id );
    $user_id = absint( $user_id );
    if ( $song_id <= 0 || $user_id <= 0 ) {
        return wpss_get_song_media_drive_folder_entry( $song_id, $user_id );
    }

    if ( empty( $attachments ) && function_exists( 'wpss_get_song_media_attachments_raw' ) ) {
        $attachments = wpss_get_song_media_attachments_raw( $song_id );
    }

    $entry = wpss_get_song_media_drive_folder_entry( $song_id, $user_id );
    foreach ( $attachments as $attachment ) {
        if ( ! is_array( $attachment ) ) {
            continue;
        }

        if ( absint( $attachment['owner_user_id'] ?? 0 ) !== $user_id ) {
            continue;
        }

        if ( 'google_drive' !== ( $attachment['storage_provider'] ?? '' ) || empty( $attachment['file_id'] ) ) {
            continue;
        }

        $chain = wpss_google_drive_get_file_ancestry( $user_id, $attachment['file_id'] );
        if ( is_wp_error( $chain ) || empty( $chain ) ) {
            continue;
        }

        foreach ( $chain as $index => $node ) {
            $node_name = isset( $node['name'] ) ? mb_strtolower( wpss_google_drive_normalize_folder_name( $node['name'] ) ) : '';
            if ( 'harmonyatlas' === $node_name && ! empty( $chain[ $index + 1 ]['id'] ) && empty( $entry['song_folder_id'] ) ) {
                $entry['song_folder_id'] = sanitize_text_field( (string) $chain[ $index + 1 ]['id'] );
            }
        }

        $section_id = sanitize_key( (string) ( $attachment['section_id'] ?? '' ) );
        if ( '' !== $section_id && empty( $entry['section_folder_ids'][ $section_id ] ) && ! empty( $entry['song_folder_id'] ) ) {
            foreach ( $chain as $index => $node ) {
                if ( sanitize_text_field( (string) ( $node['id'] ?? '' ) ) !== $entry['song_folder_id'] ) {
                    continue;
                }

                $candidate = $chain[ $index + 1 ] ?? null;
                if (
                    is_array( $candidate ) &&
                    ! empty( $candidate['id'] ) &&
                    'application/vnd.google-apps.folder' === ( $candidate['mimeType'] ?? '' )
                ) {
                    $entry['section_folder_ids'][ $section_id ] = sanitize_text_field( (string) $candidate['id'] );
                }
                break;
            }
        }
    }

    wpss_set_song_media_drive_folder_entry( $song_id, $user_id, $entry );
    return $entry;
}

/**
 * Asegura la carpeta principal de una canción y sincroniza su nombre.
 *
 * @param int $user_id Usuario.
 * @param int $song_id Canción.
 * @return array|WP_Error
 */
function wpss_google_drive_ensure_song_root_folder( $user_id, $song_id ) {
    $user_id      = absint( $user_id );
    $song_id      = absint( $song_id );
    $desired_name = wpss_google_drive_normalize_folder_name( get_the_title( $song_id ), 'cancion-' . $song_id );

    if ( $user_id <= 0 || $song_id <= 0 ) {
        return new WP_Error( 'wpss_drive_song_folder_invalid', __( 'No fue posible resolver la carpeta principal de la canción.', 'wp-song-study' ) );
    }

    $entry = wpss_get_song_media_drive_folder_entry( $song_id, $user_id );
    if ( empty( $entry['song_folder_id'] ) ) {
        $entry = wpss_google_drive_capture_song_folder_map_from_attachments( $song_id, $user_id );
    }

    if ( ! empty( $entry['song_folder_id'] ) ) {
        $renamed = wpss_google_drive_rename_file( $user_id, $entry['song_folder_id'], $desired_name );
        if ( ! is_wp_error( $renamed ) ) {
            return [
                'id'   => sanitize_text_field( (string) $entry['song_folder_id'] ),
                'name' => $desired_name,
            ];
        }
    }

    $root = wpss_google_drive_ensure_default_folder( $user_id );
    if ( is_wp_error( $root ) ) {
        return $root;
    }

    $song_folder = wpss_google_drive_ensure_named_folder(
        $user_id,
        sanitize_text_field( (string) $root['id'] ),
        $desired_name
    );
    if ( is_wp_error( $song_folder ) ) {
        return $song_folder;
    }

    $entry['song_folder_id'] = sanitize_text_field( (string) $song_folder['id'] );
    wpss_set_song_media_drive_folder_entry( $song_id, $user_id, $entry );

    return $song_folder;
}

/**
 * Asegura la carpeta de una sección y sincroniza su nombre.
 *
 * @param int    $user_id         Usuario.
 * @param int    $song_id         Canción.
 * @param string $song_folder_id  Carpeta de la canción.
 * @param string $section_id      Sección.
 * @param string $section_label   Nombre esperado.
 * @return array|WP_Error
 */
function wpss_google_drive_ensure_section_folder( $user_id, $song_id, $song_folder_id, $section_id, $section_label ) {
    $user_id        = absint( $user_id );
    $song_id        = absint( $song_id );
    $song_folder_id = sanitize_text_field( (string) $song_folder_id );
    $section_id     = sanitize_key( (string) $section_id );
    $section_label  = wpss_google_drive_normalize_folder_name( $section_label, 'sin-seccion' );

    if ( $user_id <= 0 || $song_id <= 0 || '' === $song_folder_id ) {
        return new WP_Error( 'wpss_drive_section_folder_invalid', __( 'No fue posible resolver la carpeta de la sección.', 'wp-song-study' ) );
    }

    $entry = wpss_get_song_media_drive_folder_entry( $song_id, $user_id );
    if ( '' !== $section_id && empty( $entry['section_folder_ids'][ $section_id ] ) ) {
        $entry = wpss_google_drive_capture_song_folder_map_from_attachments( $song_id, $user_id );
    }

    $section_folder_id = '' !== $section_id && ! empty( $entry['section_folder_ids'][ $section_id ] )
        ? sanitize_text_field( (string) $entry['section_folder_ids'][ $section_id ] )
        : '';

    if ( '' !== $section_folder_id ) {
        $renamed = wpss_google_drive_rename_file( $user_id, $section_folder_id, $section_label );
        if ( ! is_wp_error( $renamed ) ) {
            return [
                'id'   => $section_folder_id,
                'name' => $section_label,
            ];
        }
    }

    $section_folder = wpss_google_drive_ensure_named_folder( $user_id, $song_folder_id, $section_label );
    if ( is_wp_error( $section_folder ) ) {
        return $section_folder;
    }

    if ( '' !== $section_id ) {
        $entry['section_folder_ids'][ $section_id ] = sanitize_text_field( (string) $section_folder['id'] );
        wpss_set_song_media_drive_folder_entry( $song_id, $user_id, $entry );
    }

    return $section_folder;
}

/**
 * Obtiene snapshot de secciones y versos para una canción.
 *
 * @param int $song_id ID canción.
 * @return array
 */
function wpss_get_song_media_structure_snapshot( $song_id ) {
    $song_id   = absint( $song_id );
    $sections  = [];
    $verses    = [];
    $raw_meta  = get_post_meta( $song_id, '_secciones_json', true );

    if ( function_exists( 'wpss_decode_json_meta' ) && function_exists( 'wpss_sanitize_secciones_array' ) ) {
        $sections = wpss_sanitize_secciones_array( wpss_decode_json_meta( $raw_meta ) );
    }
    if ( function_exists( 'wpss_get_cancion_versos' ) ) {
        $verses = wpss_get_cancion_versos( $song_id );
    }

    return [
        'sections' => is_array( $sections ) ? array_values( $sections ) : [],
        'verses'   => is_array( $verses ) ? array_values( $verses ) : [],
    ];
}

/**
 * Resuelve carpeta destino para un adjunto dentro del árbol HarmonyAtlas/<canción>/[#sección].
 *
 * @param int   $user_id     Usuario propietario.
 * @param int   $song_id     Canción.
 * @param array $attachment  Contexto del adjunto.
 * @return array|WP_Error
 */
function wpss_google_drive_ensure_song_attachment_folder( $user_id, $song_id, array $attachment ) {
    $user_id = absint( $user_id );
    $song_id = absint( $song_id );
    if ( $user_id <= 0 || $song_id <= 0 ) {
        return new WP_Error( 'wpss_drive_context_invalid', __( 'No fue posible preparar la carpeta destino para el adjunto.', 'wp-song-study' ) );
    }

    $song_folder = wpss_google_drive_ensure_song_root_folder( $user_id, $song_id );
    if ( is_wp_error( $song_folder ) ) {
        return $song_folder;
    }

    $anchor_type = isset( $attachment['anchor_type'] ) ? sanitize_key( (string) $attachment['anchor_type'] ) : 'song';
    if ( 'song' === $anchor_type ) {
        return $song_folder;
    }

    $snapshot  = wpss_get_song_media_structure_snapshot( $song_id );
    $sections  = $snapshot['sections'];
    $verses    = $snapshot['verses'];
    $section_id = isset( $attachment['section_id'] ) ? sanitize_key( (string) $attachment['section_id'] ) : '';
    $verse_index = isset( $attachment['verse_index'] ) ? max( 0, absint( $attachment['verse_index'] ) ) : 0;
    $segment_index = isset( $attachment['segment_index'] ) ? max( 0, absint( $attachment['segment_index'] ) ) : 0;

    if ( '' === $section_id && isset( $verses[ $verse_index ]['section_id'] ) ) {
        $section_id = sanitize_key( (string) $verses[ $verse_index ]['section_id'] );
    }

    if ( '' === $section_id ) {
        return $song_folder;
    }

    $section_label = 'sin-seccion';
    foreach ( array_values( $sections ) as $section_index => $section ) {
        if ( sanitize_key( (string) ( $section['id'] ?? '' ) ) !== $section_id ) {
            continue;
        }
        $section_name  = wpss_google_drive_normalize_folder_name( $section['nombre'] ?? '', 'seccion-' . ( $section_index + 1 ) );
        $section_label = sprintf( '#%02d-%s', $section_index + 1, $section_name );
        break;
    }

    $section_folder = wpss_google_drive_ensure_section_folder( $user_id, $song_id, $song_folder['id'], $section_id, $section_label );
    if ( is_wp_error( $section_folder ) ) {
        return $section_folder;
    }

    return $section_folder;
}

/**
 * Sube bytes a Google Drive dentro del árbol contextual de canción.
 *
 * @param int    $user_id    Usuario propietario.
 * @param int    $song_id    Canción.
 * @param string $file_name  Nombre archivo.
 * @param string $mime_type  Mime type.
 * @param string $file_data  Bytes.
 * @param array  $attachment Contexto del adjunto.
 * @return array|WP_Error
 */
function wpss_google_drive_upload_song_media_bytes( $user_id, $song_id, $file_name, $mime_type, $file_data, array $attachment = [] ) {
    $folder = wpss_google_drive_ensure_song_attachment_folder( $user_id, $song_id, $attachment );
    if ( is_wp_error( $folder ) ) {
        return $folder;
    }

    $metadata = [
        'name'    => sanitize_file_name( $file_name ? $file_name : 'archivo' ),
        'parents' => ! empty( $folder['id'] ) ? [ sanitize_text_field( (string) $folder['id'] ) ] : [],
    ];

    $boundary = 'wpss-' . wp_generate_password( 16, false, false );
    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
    $body .= wp_json_encode( $metadata ) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= 'Content-Type: ' . sanitize_text_field( $mime_type ? $mime_type : 'application/octet-stream' ) . "\r\n\r\n";
    $body .= $file_data . "\r\n";
    $body .= "--{$boundary}--";

    return wpss_google_drive_request(
        $user_id,
        'POST',
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,mimeType,size',
        [
            'headers' => [
                'Content-Type' => 'multipart/related; boundary=' . $boundary,
            ],
            'body'    => $body,
        ]
    );
}

/**
 * Descarga bytes de un archivo de Drive usando el token del propietario.
 *
 * @param int    $owner_user_id Usuario origen.
 * @param string $file_id       File id.
 * @param string $mime_type     Mime esperado.
 * @return array|WP_Error
 */
function wpss_google_drive_download_file( $owner_user_id, $file_id, $mime_type = '' ) {
    return wpss_google_drive_request(
        absint( $owner_user_id ),
        'GET',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode( sanitize_text_field( (string) $file_id ) ) . '?alt=media',
        [
            'headers' => [
                'Accept' => $mime_type ? sanitize_text_field( $mime_type ) : '*/*',
            ],
        ]
    );
}

/**
 * Mueve un archivo existente al folder contextual correcto del árbol HarmonyAtlas.
 *
 * @param int   $owner_user_id Usuario propietario.
 * @param int   $song_id       Canción.
 * @param array $attachment    Contexto del adjunto.
 * @return true|WP_Error
 */
function wpss_google_drive_move_file_to_attachment_folder( $owner_user_id, $song_id, array $attachment ) {
    $owner_user_id = absint( $owner_user_id );
    $song_id       = absint( $song_id );
    $file_id       = isset( $attachment['file_id'] ) ? sanitize_text_field( (string) $attachment['file_id'] ) : '';

    if ( $owner_user_id <= 0 || $song_id <= 0 || '' === $file_id ) {
        return new WP_Error( 'wpss_drive_move_invalid', __( 'No hay datos suficientes para reubicar el archivo en Drive.', 'wp-song-study' ) );
    }

    $target_folder = wpss_google_drive_ensure_song_attachment_folder( $owner_user_id, $song_id, $attachment );
    if ( is_wp_error( $target_folder ) ) {
        return $target_folder;
    }

    $metadata = wpss_google_drive_request(
        $owner_user_id,
        'GET',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id ) . '?fields=id,parents&supportsAllDrives=true'
    );

    if ( is_wp_error( $metadata ) ) {
        return $metadata;
    }

    $parents = isset( $metadata['json']['parents'] ) && is_array( $metadata['json']['parents'] )
        ? array_values( array_filter( array_map( 'sanitize_text_field', $metadata['json']['parents'] ) ) )
        : [];

    $target_folder_id = sanitize_text_field( (string) $target_folder['id'] );
    if ( '' === $target_folder_id ) {
        return new WP_Error( 'wpss_drive_move_target_missing', __( 'No fue posible resolver la carpeta destino en Drive.', 'wp-song-study' ) );
    }

    if ( in_array( $target_folder_id, $parents, true ) && 1 === count( $parents ) ) {
        return true;
    }

    $remove_parents = implode( ',', array_filter( $parents, static function( $parent_id ) use ( $target_folder_id ) {
        return $parent_id !== $target_folder_id;
    } ) );

    $query_args = [
        'addParents'       => $target_folder_id,
        'supportsAllDrives'=> 'true',
        'fields'           => 'id,parents',
    ];

    if ( '' !== $remove_parents ) {
        $query_args['removeParents'] = $remove_parents;
    }

    $moved = wpss_google_drive_request(
        $owner_user_id,
        'PATCH',
        add_query_arg( $query_args, 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id ) ),
        [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body'    => wp_json_encode( new stdClass() ),
        ]
    );

    if ( is_wp_error( $moved ) ) {
        return $moved;
    }

    return true;
}

/**
 * Sincroniza nombres de carpetas Drive para una canción ya guardada.
 *
 * @param int $song_id Canción.
 * @return void
 */
function wpss_google_drive_sync_song_media_folder_names( $song_id ) {
    $song_id = absint( $song_id );
    if ( $song_id <= 0 || ! function_exists( 'wpss_get_song_media_attachments_raw' ) ) {
        return;
    }

    $attachments = wpss_get_song_media_attachments_raw( $song_id );
    if ( empty( $attachments ) || ! is_array( $attachments ) ) {
        return;
    }

    $snapshot = wpss_get_song_media_structure_snapshot( $song_id );
    $sections = is_array( $snapshot['sections'] ?? null ) ? array_values( $snapshot['sections'] ) : [];
    $owners   = [];

    foreach ( $attachments as $attachment ) {
        if ( ! is_array( $attachment ) ) {
            continue;
        }

        if ( 'google_drive' !== ( $attachment['storage_provider'] ?? '' ) ) {
            continue;
        }

        $owner_user_id = absint( $attachment['owner_user_id'] ?? 0 );
        if ( $owner_user_id <= 0 ) {
            continue;
        }

        if ( empty( $owners[ $owner_user_id ] ) ) {
            $owners[ $owner_user_id ] = [];
        }

        $section_id = sanitize_key( (string) ( $attachment['section_id'] ?? '' ) );
        if ( '' !== $section_id ) {
            $owners[ $owner_user_id ][ $section_id ] = true;
        }
    }

    foreach ( array_keys( $owners ) as $owner_user_id ) {
        $song_folder = wpss_google_drive_ensure_song_root_folder( $owner_user_id, $song_id );
        if ( is_wp_error( $song_folder ) ) {
            continue;
        }

        foreach ( $sections as $section_index => $section ) {
            $section_id = sanitize_key( (string) ( $section['id'] ?? '' ) );
            if ( '' === $section_id || empty( $owners[ $owner_user_id ][ $section_id ] ) ) {
                continue;
            }

            $section_name  = wpss_google_drive_normalize_folder_name( $section['nombre'] ?? '', 'seccion-' . ( $section_index + 1 ) );
            $section_label = sprintf( '#%02d-%s', $section_index + 1, $section_name );
            wpss_google_drive_ensure_section_folder( $owner_user_id, $song_id, $song_folder['id'], $section_id, $section_label );
        }

        foreach ( $attachments as $attachment ) {
            if ( ! is_array( $attachment ) ) {
                continue;
            }

            if ( 'google_drive' !== ( $attachment['storage_provider'] ?? '' ) ) {
                continue;
            }

            if ( $owner_user_id !== absint( $attachment['owner_user_id'] ?? 0 ) ) {
                continue;
            }

            if ( empty( $attachment['file_id'] ) ) {
                continue;
            }

            wpss_google_drive_move_file_to_attachment_folder( $owner_user_id, $song_id, $attachment );
        }
    }
}

/**
 * Copia adjuntos visibles de una canción al Drive de otro usuario y los registra en la canción destino.
 *
 * @param int   $source_song_id Canción origen.
 * @param int   $target_song_id Canción destino.
 * @param int   $target_user_id Usuario dueño de la reversión.
 * @param array $attachments    Adjunto visibles.
 * @return array
 */
function wpss_copy_song_media_attachments_to_user( $source_song_id, $target_song_id, $target_user_id, array $attachments ) {
    $source_song_id = absint( $source_song_id );
    $target_song_id = absint( $target_song_id );
    $target_user_id = absint( $target_user_id );

    if ( $source_song_id <= 0 || $target_song_id <= 0 || $target_user_id <= 0 ) {
        return [];
    }

    $config = wpss_get_google_drive_user_config( $target_user_id );
    if ( empty( $config['connected'] ) ) {
        return [];
    }

    $settings = wpss_get_song_media_access_settings( $target_song_id );
    $copied   = [];

    foreach ( $attachments as $attachment ) {
        if ( ! is_array( $attachment ) || empty( $attachment['file_id'] ) || empty( $attachment['owner_user_id'] ) ) {
            continue;
        }

        $download = wpss_google_drive_download_file(
            absint( $attachment['owner_user_id'] ),
            sanitize_text_field( (string) $attachment['file_id'] ),
            isset( $attachment['mime_type'] ) ? (string) $attachment['mime_type'] : ''
        );

        if ( is_wp_error( $download ) ) {
            continue;
        }

        $upload = wpss_google_drive_upload_song_media_bytes(
            $target_user_id,
            $target_song_id,
            isset( $attachment['file_name'] ) ? (string) $attachment['file_name'] : 'archivo',
            isset( $attachment['mime_type'] ) ? (string) $attachment['mime_type'] : 'application/octet-stream',
            (string) $download['body'],
            [
                'anchor_type'   => isset( $attachment['anchor_type'] ) ? $attachment['anchor_type'] : 'song',
                'section_id'    => isset( $attachment['section_id'] ) ? $attachment['section_id'] : '',
                'verse_index'   => isset( $attachment['verse_index'] ) ? $attachment['verse_index'] : 0,
                'segment_index' => isset( $attachment['segment_index'] ) ? $attachment['segment_index'] : 0,
            ]
        );

        if ( is_wp_error( $upload ) || empty( $upload['json']['id'] ) ) {
            continue;
        }

        $copied[] = [
            'id'                   => 'media-' . sanitize_key( wp_generate_uuid4() ),
            'type'                 => isset( $attachment['type'] ) ? sanitize_key( (string) $attachment['type'] ) : 'audio',
            'title'                => isset( $attachment['title'] ) ? sanitize_text_field( (string) $attachment['title'] ) : '',
            'source_kind'          => isset( $attachment['source_kind'] ) ? sanitize_key( (string) $attachment['source_kind'] ) : 'import',
            'anchor_type'          => isset( $attachment['anchor_type'] ) ? sanitize_key( (string) $attachment['anchor_type'] ) : 'song',
            'section_id'           => isset( $attachment['section_id'] ) ? sanitize_key( (string) $attachment['section_id'] ) : '',
            'verse_index'          => isset( $attachment['verse_index'] ) ? max( 0, absint( $attachment['verse_index'] ) ) : 0,
            'segment_index'        => isset( $attachment['segment_index'] ) ? max( 0, absint( $attachment['segment_index'] ) ) : 0,
            'visibility_mode'      => $settings['visibility_mode'],
            'visibility_group_ids' => $settings['visibility_group_ids'],
            'visibility_user_ids'  => $settings['visibility_user_ids'],
            'owner_user_id'        => $target_user_id,
            'storage_provider'     => 'google_drive',
            'file_id'              => sanitize_text_field( (string) $upload['json']['id'] ),
            'file_name'            => sanitize_file_name( (string) ( $upload['json']['name'] ?? $attachment['file_name'] ?? 'archivo' ) ),
            'mime_type'            => sanitize_text_field( (string) ( $upload['json']['mimeType'] ?? $attachment['mime_type'] ?? 'application/octet-stream' ) ),
            'size_bytes'           => isset( $upload['json']['size'] ) ? max( 0, absint( $upload['json']['size'] ) ) : ( isset( $attachment['size_bytes'] ) ? max( 0, absint( $attachment['size_bytes'] ) ) : 0 ),
            'duration_seconds'     => isset( $attachment['duration_seconds'] ) ? (float) $attachment['duration_seconds'] : 0,
            'created_at'           => current_time( 'mysql' ),
            'updated_at'           => current_time( 'mysql' ),
        ];
    }

    if ( $copied ) {
        wpss_replace_song_media_attachments( $target_song_id, $copied );
    }

    return $copied;
}

/**
 * Lista usuarios válidos para ACL explícita.
 *
 * @return int[]
 */
function wpss_get_explicit_media_user_ids() {
    $assignable = wpss_get_assignable_song_users();
    return array_map( 'intval', wp_list_pluck( $assignable, 'id' ) );
}

/**
 * Normaliza miembros de agrupación musical.
 *
 * @param mixed $members  Miembros recibidos.
 * @param int   $owner_id Propietario.
 * @return array
 */
function wpss_sanitize_agrupacion_members( $members, $owner_id = 0 ) {
    if ( ! is_array( $members ) ) {
        $members = [];
    }

    $allowed_roles = wpss_get_agrupacion_member_role_options();
    $allowed_users = wpss_get_explicit_media_user_ids();
    $allowed_map   = array_fill_keys( $allowed_users, true );
    $owner_id      = absint( $owner_id );
    $result        = [];

    foreach ( $members as $member ) {
        if ( $member instanceof Traversable ) {
            $member = iterator_to_array( $member );
        } elseif ( is_object( $member ) ) {
            $member = get_object_vars( $member );
        }

        if ( ! is_array( $member ) ) {
            continue;
        }

        $user_id = absint( isset( $member['user_id'] ) ? $member['user_id'] : ( isset( $member['id'] ) ? $member['id'] : 0 ) );
        $role    = isset( $member['role'] ) ? sanitize_key( $member['role'] ) : 'contribuidor';

        if ( $user_id <= 0 || ( $owner_id > 0 && $user_id === $owner_id ) || ! isset( $allowed_map[ $user_id ] ) ) {
            continue;
        }

        if ( ! isset( $allowed_roles[ $role ] ) ) {
            $role = 'contribuidor';
        }

        $result[ $user_id ] = [
            'user_id' => $user_id,
            'role'    => $role,
        ];
    }

    return array_values( $result );
}

/**
 * Obtiene el propietario de una agrupación.
 *
 * @param int $post_id ID agrupación.
 * @return int
 */
function wpss_get_agrupacion_owner_id( $post_id ) {
    return absint( get_post_meta( (int) $post_id, '_owner_user_id', true ) );
}

/**
 * Obtiene los miembros normalizados de una agrupación.
 *
 * @param int $post_id ID agrupación.
 * @return array
 */
function wpss_get_agrupacion_members( $post_id ) {
    $raw = get_post_meta( (int) $post_id, '_members_json', true );
    $decoded = is_array( $raw ) ? $raw : wpss_decode_json_meta( $raw );
    return wpss_sanitize_agrupacion_members( is_array( $decoded ) ? $decoded : [], wpss_get_agrupacion_owner_id( $post_id ) );
}

/**
 * Determina si el usuario puede leer o editar una agrupación.
 *
 * @param int    $post_id ID agrupación.
 * @param string $mode    read|write.
 * @param int    $user_id ID usuario.
 * @return bool
 */
function wpss_user_can_access_agrupacion( $post_id, $mode = 'read', $user_id = 0 ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 ) {
        return false;
    }

    $post = get_post( $post_id );
    if ( ! $post || 'agrupacion_musical' !== $post->post_type ) {
        return false;
    }

    if ( wpss_user_can_bypass_coleccion_acl() ) {
        return true;
    }

    $user_id = absint( $user_id ? $user_id : get_current_user_id() );
    if ( $user_id <= 0 ) {
        return false;
    }

    $owner_id = wpss_get_agrupacion_owner_id( $post_id );
    if ( $owner_id > 0 && $owner_id === $user_id ) {
        return true;
    }

    $members = wpss_get_agrupacion_members( $post_id );
    foreach ( $members as $member ) {
        if ( (int) $member['user_id'] !== $user_id ) {
            continue;
        }

        if ( 'write' !== $mode ) {
            return true;
        }

        return 'admin' === $member['role'];
    }

    return false;
}

/**
 * Prepara una agrupación para respuesta REST.
 *
 * @param WP_Post $post Post agrupación.
 * @return array
 */
function wpss_prepare_agrupacion_for_response( WP_Post $post ) {
    $post_id    = (int) $post->ID;
    $owner_id   = wpss_get_agrupacion_owner_id( $post_id );
    $members    = wpss_get_agrupacion_members( $post_id );
    $role_names = wpss_get_agrupacion_member_role_options();

    $members_detail = [];
    foreach ( $members as $member ) {
        $user_id = (int) $member['user_id'];
        $role    = sanitize_key( $member['role'] );
        $members_detail[] = [
            'user_id'    => $user_id,
            'nombre'     => wpss_get_user_display_name( $user_id ),
            'role'       => $role,
            'role_label' => isset( $role_names[ $role ] ) ? $role_names[ $role ] : $role,
        ];
    }

    return [
        'id'            => $post_id,
        'nombre'        => get_the_title( $post_id ),
        'descripcion'   => sanitize_textarea_field( get_post_meta( $post_id, '_descripcion', true ) ),
        'owner_id'      => $owner_id,
        'owner_nombre'  => wpss_get_user_display_name( $owner_id ),
        'miembros'      => $members_detail,
        'members_count' => count( $members_detail ) + ( $owner_id > 0 ? 1 : 0 ),
        'can_edit'      => wpss_user_can_access_agrupacion( $post_id, 'write' ),
        'can_delete'    => wpss_user_can_access_agrupacion( $post_id, 'write' ),
    ];
}

/**
 * Devuelve agrupaciones accesibles al usuario.
 *
 * @return WP_REST_Response
 */
function wpss_rest_get_agrupaciones_musicales() {
    $query = new WP_Query(
        [
            'post_type'      => 'agrupacion_musical',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]
    );

    $items = [];
    foreach ( $query->posts as $post ) {
        if ( $post instanceof WP_Post && wpss_user_can_access_agrupacion( $post->ID, 'read' ) ) {
            $items[] = wpss_prepare_agrupacion_for_response( $post );
        }
    }

    wp_reset_postdata();

    return rest_ensure_response( $items );
}

/**
 * Obtiene una agrupación específica.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return WP_REST_Response
 */
function wpss_rest_get_agrupacion_musical( WP_REST_Request $request ) {
    $post_id = absint( $request->get_param( 'id' ) );
    $post    = get_post( $post_id );

    if ( ! $post || 'agrupacion_musical' !== $post->post_type ) {
        return new WP_REST_Response( [ 'message' => __( 'Agrupación musical no encontrada.', 'wp-song-study' ) ], 404 );
    }

    if ( ! wpss_user_can_access_agrupacion( $post_id, 'read' ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No tienes acceso a esta agrupación musical.', 'wp-song-study' ) ], 403 );
    }

    return rest_ensure_response( wpss_prepare_agrupacion_for_response( $post ) );
}

/**
 * Crea o actualiza una agrupación musical.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return WP_REST_Response
 */
function wpss_rest_save_agrupacion_musical( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }

    $current_user_id = get_current_user_id();
    $post_id         = isset( $params['id'] ) ? absint( $params['id'] ) : 0;
    $nombre          = isset( $params['nombre'] ) ? sanitize_text_field( $params['nombre'] ) : '';
    $descripcion     = isset( $params['descripcion'] ) ? sanitize_textarea_field( $params['descripcion'] ) : '';

    if ( '' === $nombre ) {
        return new WP_REST_Response( [ 'message' => __( 'El nombre de la agrupación es obligatorio.', 'wp-song-study' ) ], 400 );
    }

    if ( $post_id > 0 && ! wpss_user_can_access_agrupacion( $post_id, 'write' ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Solo los administradores de la agrupación pueden modificarla.', 'wp-song-study' ) ], 403 );
    }

    $owner_id = $post_id > 0 ? wpss_get_agrupacion_owner_id( $post_id ) : $current_user_id;
    if ( $owner_id <= 0 ) {
        $owner_id = $current_user_id;
    }

    $postarr = [
        'post_type'   => 'agrupacion_musical',
        'post_status' => 'publish',
        'post_title'  => $nombre,
    ];

    if ( $post_id > 0 ) {
        $postarr['ID'] = $post_id;
    } else {
        $postarr['post_author'] = $current_user_id;
    }

    $saved_id = wp_insert_post( wp_slash( $postarr ), true );
    if ( is_wp_error( $saved_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No fue posible guardar la agrupación musical.', 'wp-song-study' ) ], 500 );
    }

    $members = wpss_sanitize_agrupacion_members( isset( $params['miembros'] ) ? (array) $params['miembros'] : [], $owner_id );

    update_post_meta( $saved_id, '_owner_user_id', $owner_id );
    update_post_meta( $saved_id, '_descripcion', $descripcion );
    update_post_meta( $saved_id, '_members_json', wp_json_encode( $members, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

    return rest_ensure_response( wpss_prepare_agrupacion_for_response( get_post( $saved_id ) ) );
}

/**
 * Elimina una agrupación musical.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return WP_REST_Response
 */
function wpss_rest_delete_agrupacion_musical( WP_REST_Request $request ) {
    $post_id = absint( $request->get_param( 'id' ) );
    if ( ! wpss_user_can_access_agrupacion( $post_id, 'write' ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Solo los administradores de la agrupación pueden eliminarla.', 'wp-song-study' ) ], 403 );
    }

    $deleted = wp_delete_post( $post_id, true );
    if ( ! $deleted ) {
        return new WP_REST_Response( [ 'message' => __( 'No fue posible eliminar la agrupación musical.', 'wp-song-study' ) ], 500 );
    }

    return rest_ensure_response(
        [
            'deleted' => true,
            'id'      => $post_id,
        ]
    );
}

/**
 * Genera payload de estado Drive para la UI.
 *
 * @param int $user_id ID usuario.
 * @return array
 */
function wpss_get_google_drive_status_payload( $user_id ) {
    $config       = wpss_get_google_drive_user_config( $user_id );
    $credentials  = wpss_get_google_drive_oauth_credentials( $user_id );
    $last_error   = wpss_get_google_drive_last_error( $user_id );
    $redirect_uri = wpss_get_google_drive_redirect_uri();
    $redirect_parts = wp_parse_url( $redirect_uri );
    $authorized_origin = '';

    if ( is_array( $redirect_parts ) && ! empty( $redirect_parts['scheme'] ) && ! empty( $redirect_parts['host'] ) ) {
        $authorized_origin = $redirect_parts['scheme'] . '://' . $redirect_parts['host'];
        if ( ! empty( $redirect_parts['port'] ) ) {
            $authorized_origin .= ':' . absint( $redirect_parts['port'] );
        }
    }

    return [
        'configured'    => wpss_google_drive_is_configured_for_user( $user_id ),
        'connected'     => ! empty( $config['connected'] ),
        'has_access_token' => ! empty( $config['has_access_token'] ),
        'has_refresh_token' => ! empty( $config['has_refresh_token'] ),
        'account_email' => $config['account_email'],
        'folder_id'     => $config['folder_id'],
        'folder_name'   => $config['folder_name'],
        'folder_url'    => $config['folder_url'],
        'connected_at'  => $config['connected_at'],
        'credentials_source' => $credentials['source'],
        'has_user_client_id' => '' !== wpss_get_google_drive_user_client_id( $user_id ),
        'has_user_client_secret' => '' !== wpss_get_google_drive_user_client_secret( $user_id ),
        'last_error'    => $last_error,
        'authorized_origin' => $authorized_origin,
        'connect_url'   => wpss_get_google_drive_connect_url( $user_id ),
        'redirect_uri'  => $redirect_uri,
    ];
}

/**
 * Devuelve el estado Drive del usuario actual.
 *
 * @return WP_REST_Response
 */
function wpss_rest_get_google_drive_status() {
    return rest_ensure_response( wpss_get_google_drive_status_payload( get_current_user_id() ) );
}

/**
 * Actualiza carpeta destino del Drive del usuario actual.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return WP_REST_Response
 */
function wpss_rest_save_google_drive_settings( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    $config  = wpss_get_google_drive_user_config( $user_id );
    if ( empty( $config['connected'] ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Primero conecta tu cuenta de Google Drive.', 'wp-song-study' ) ], 400 );
    }

    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }

    $folder_id_input  = isset( $params['folder_id'] ) ? $params['folder_id'] : '';
    $folder_url_input = isset( $params['folder_url'] ) ? $params['folder_url'] : '';
    $folder_name      = isset( $params['folder_name'] ) ? sanitize_text_field( $params['folder_name'] ) : '';
    $folder_id        = wpss_parse_google_drive_folder_id( $folder_id_input ? $folder_id_input : $folder_url_input );

    if ( '' !== $folder_url_input ) {
        $config['folder_url'] = esc_url_raw( $folder_url_input );
    }
    if ( '' !== $folder_id ) {
        $config['folder_id'] = $folder_id;
    }
    if ( '' !== $folder_name ) {
        $config['folder_name'] = $folder_name;
    }
    if ( ! empty( $config['folder_id'] ) && empty( $config['folder_url'] ) ) {
        $config['folder_url'] = 'https://drive.google.com/drive/folders/' . rawurlencode( $config['folder_id'] );
    }

    wpss_set_google_drive_user_config( $user_id, $config );

    return rest_ensure_response( wpss_get_google_drive_status_payload( $user_id ) );
}

/**
 * Desconecta la cuenta Drive del usuario actual.
 *
 * @return WP_REST_Response
 */
function wpss_rest_disconnect_google_drive() {
    $user_id = get_current_user_id();
    wpss_delete_google_drive_user_config( $user_id );

    return rest_ensure_response(
        [
            'disconnected' => true,
            'status'       => wpss_get_google_drive_status_payload( $user_id ),
        ]
    );
}

/**
 * Sanitiza una lista de usuarios explícitos.
 *
 * @param mixed $user_ids IDs.
 * @return int[]
 */
function wpss_sanitize_media_explicit_user_ids( $user_ids ) {
    if ( ! is_array( $user_ids ) ) {
        return [];
    }

    $allowed = wpss_get_explicit_media_user_ids();
    $map     = array_fill_keys( $allowed, true );
    $result  = [];

    foreach ( $user_ids as $value ) {
        if ( is_array( $value ) && isset( $value['id'] ) ) {
            $value = $value['id'];
        }

        $user_id = absint( $value );
        if ( $user_id <= 0 || ! isset( $map[ $user_id ] ) ) {
            continue;
        }

        if ( ! in_array( $user_id, $result, true ) ) {
            $result[] = $user_id;
        }
    }

    return $result;
}

/**
 * Sanitiza IDs de agrupaciones para ACL.
 *
 * @param mixed $group_ids IDs.
 * @return int[]
 */
function wpss_sanitize_media_group_ids( $group_ids ) {
    if ( ! is_array( $group_ids ) ) {
        return [];
    }

    $result = [];
    foreach ( $group_ids as $value ) {
        if ( is_array( $value ) && isset( $value['id'] ) ) {
            $value = $value['id'];
        }

        $group_id = absint( $value );
        if ( $group_id <= 0 || ! wpss_user_can_access_agrupacion( $group_id, 'read' ) ) {
            continue;
        }

        if ( ! in_array( $group_id, $result, true ) ) {
            $result[] = $group_id;
        }
    }

    return $result;
}

/**
 * Normaliza adjuntos multimedia para guardar en la canción.
 *
 * @param mixed $attachments Datos recibidos.
 * @return array
 */
function wpss_sanitize_song_media_attachments( $attachments ) {
    if ( ! is_array( $attachments ) ) {
        return [];
    }

    $result = [];
    $allowed_visibility = [ 'public', 'private', 'groups', 'users' ];
    $allowed_type       = [ 'audio', 'photo' ];
    $allowed_anchor     = [ 'song', 'section', 'verse', 'segment' ];

    foreach ( $attachments as $attachment ) {
        if ( $attachment instanceof Traversable ) {
            $attachment = iterator_to_array( $attachment );
        } elseif ( is_object( $attachment ) ) {
            $attachment = get_object_vars( $attachment );
        }

        if ( ! is_array( $attachment ) ) {
            continue;
        }

        $id          = isset( $attachment['id'] ) ? sanitize_key( $attachment['id'] ) : '';
        $type        = isset( $attachment['type'] ) ? sanitize_key( $attachment['type'] ) : 'audio';
        $anchor_type = isset( $attachment['anchor_type'] ) ? sanitize_key( $attachment['anchor_type'] ) : 'song';
        $visibility  = isset( $attachment['visibility_mode'] ) ? sanitize_key( $attachment['visibility_mode'] ) : 'private';

        if ( '' === $id || ! in_array( $type, $allowed_type, true ) || ! in_array( $anchor_type, $allowed_anchor, true ) ) {
            continue;
        }

        if ( ! in_array( $visibility, $allowed_visibility, true ) ) {
            $visibility = 'private';
        }

        $group_ids = wpss_sanitize_media_group_ids( isset( $attachment['visibility_group_ids'] ) ? $attachment['visibility_group_ids'] : [] );
        $user_ids  = wpss_sanitize_media_explicit_user_ids( isset( $attachment['visibility_user_ids'] ) ? $attachment['visibility_user_ids'] : [] );

        $item = [
            'id'                   => $id,
            'type'                 => $type,
            'title'                => isset( $attachment['title'] ) ? sanitize_text_field( $attachment['title'] ) : '',
            'source_kind'          => isset( $attachment['source_kind'] ) ? sanitize_key( $attachment['source_kind'] ) : 'import',
            'anchor_type'          => $anchor_type,
            'section_id'           => isset( $attachment['section_id'] ) ? sanitize_key( $attachment['section_id'] ) : '',
            'verse_index'          => isset( $attachment['verse_index'] ) ? max( 0, absint( $attachment['verse_index'] ) ) : 0,
            'segment_index'        => isset( $attachment['segment_index'] ) ? max( 0, absint( $attachment['segment_index'] ) ) : 0,
            'visibility_mode'      => $visibility,
            'visibility_group_ids' => $group_ids,
            'visibility_user_ids'  => $user_ids,
            'owner_user_id'        => isset( $attachment['owner_user_id'] ) ? absint( $attachment['owner_user_id'] ) : 0,
            'storage_provider'     => isset( $attachment['storage_provider'] ) ? sanitize_key( $attachment['storage_provider'] ) : 'google_drive',
            'file_id'              => isset( $attachment['file_id'] ) ? sanitize_text_field( $attachment['file_id'] ) : '',
            'file_name'            => isset( $attachment['file_name'] ) ? sanitize_file_name( $attachment['file_name'] ) : '',
            'mime_type'            => isset( $attachment['mime_type'] ) ? sanitize_text_field( $attachment['mime_type'] ) : '',
            'size_bytes'           => isset( $attachment['size_bytes'] ) ? max( 0, absint( $attachment['size_bytes'] ) ) : 0,
            'duration_seconds'     => isset( $attachment['duration_seconds'] ) ? (float) $attachment['duration_seconds'] : 0,
            'created_at'           => isset( $attachment['created_at'] ) ? sanitize_text_field( $attachment['created_at'] ) : current_time( 'mysql' ),
            'updated_at'           => current_time( 'mysql' ),
        ];

        if ( '' === $item['file_id'] ) {
            continue;
        }

        $result[ $item['id'] ] = $item;
    }

    return array_values( $result );
}

/**
 * Normaliza la política compartida de acceso multimedia de una canción.
 *
 * @param mixed $settings Política recibida.
 * @return array
 */
function wpss_sanitize_song_media_access_settings( $settings ) {
    if ( $settings instanceof Traversable ) {
        $settings = iterator_to_array( $settings );
    } elseif ( is_object( $settings ) ) {
        $settings = get_object_vars( $settings );
    }

    if ( ! is_array( $settings ) ) {
        $settings = [];
    }

    $allowed_visibility = [ 'public', 'private', 'groups', 'users' ];
    $visibility         = isset( $settings['visibility_mode'] ) ? sanitize_key( $settings['visibility_mode'] ) : 'private';
    if ( ! in_array( $visibility, $allowed_visibility, true ) ) {
        $visibility = 'private';
    }

    return [
        'visibility_mode'      => $visibility,
        'visibility_group_ids' => wpss_sanitize_media_group_ids( isset( $settings['visibility_group_ids'] ) ? $settings['visibility_group_ids'] : [] ),
        'visibility_user_ids'  => wpss_sanitize_media_explicit_user_ids( isset( $settings['visibility_user_ids'] ) ? $settings['visibility_user_ids'] : [] ),
    ];
}

/**
 * Obtiene la política compartida de acceso multimedia de una canción.
 *
 * @param int $song_id ID canción.
 * @return array
 */
function wpss_get_song_media_access_settings( $song_id ) {
    $song_id = absint( $song_id );
    if ( $song_id <= 0 ) {
        return wpss_sanitize_song_media_access_settings( [] );
    }

    $raw     = get_post_meta( $song_id, '_adjuntos_multimedia_acl_json', true );
    $decoded = is_array( $raw ) ? $raw : wpss_decode_json_meta( $raw );
    if ( is_array( $decoded ) && ! empty( $decoded ) ) {
        return wpss_sanitize_song_media_access_settings( $decoded );
    }

    $attachments = wpss_get_song_media_attachments_raw( $song_id );
    if ( ! empty( $attachments[0] ) && is_array( $attachments[0] ) ) {
        return wpss_sanitize_song_media_access_settings(
            [
                'visibility_mode'      => isset( $attachments[0]['visibility_mode'] ) ? $attachments[0]['visibility_mode'] : 'private',
                'visibility_group_ids' => isset( $attachments[0]['visibility_group_ids'] ) ? $attachments[0]['visibility_group_ids'] : [],
                'visibility_user_ids'  => isset( $attachments[0]['visibility_user_ids'] ) ? $attachments[0]['visibility_user_ids'] : [],
            ]
        );
    }

    return wpss_sanitize_song_media_access_settings( [] );
}

/**
 * Persiste la política compartida de acceso multimedia y la refleja en los adjuntos existentes.
 *
 * @param int   $song_id   ID canción.
 * @param array $settings  Política.
 * @return array
 */
function wpss_set_song_media_access_settings( $song_id, array $settings ) {
    $song_id   = absint( $song_id );
    $settings  = wpss_sanitize_song_media_access_settings( $settings );
    $encoded   = wp_json_encode( $settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

    update_post_meta( $song_id, '_adjuntos_multimedia_acl_json', $encoded );

    $attachments = wpss_get_song_media_attachments_raw( $song_id );
    if ( ! empty( $attachments ) ) {
        foreach ( $attachments as &$attachment ) {
            $attachment['visibility_mode']      = $settings['visibility_mode'];
            $attachment['visibility_group_ids'] = $settings['visibility_group_ids'];
            $attachment['visibility_user_ids']  = $settings['visibility_user_ids'];
            $attachment['updated_at']           = current_time( 'mysql' );
        }
        unset( $attachment );
        wpss_replace_song_media_attachments( $song_id, $attachments );
    }

    return $settings;
}

/**
 * Obtiene adjuntos crudos de una canción.
 *
 * @param int $post_id ID canción.
 * @return array
 */
function wpss_get_song_media_attachments_raw( $post_id ) {
    $raw     = get_post_meta( (int) $post_id, '_adjuntos_multimedia_json', true );
    $decoded = is_array( $raw ) ? $raw : wpss_decode_json_meta( $raw );
    return wpss_sanitize_song_media_attachments( is_array( $decoded ) ? $decoded : [] );
}

/**
 * Determina si el usuario actual puede ver un adjunto.
 *
 * @param array $attachment Adjunto.
 * @param int   $song_id    Canción.
 * @param int   $user_id    Usuario opcional.
 * @return bool
 */
function wpss_current_user_can_access_song_attachment( array $attachment, $song_id, $user_id = 0 ) {
    $song_id = absint( $song_id );
    if ( $song_id <= 0 ) {
        return false;
    }

    if ( wpss_user_can_bypass_coleccion_acl() ) {
        return true;
    }

    $user_id = absint( $user_id ? $user_id : get_current_user_id() );
    if ( $user_id <= 0 ) {
        return false;
    }

    $owner_user_id = isset( $attachment['owner_user_id'] ) ? absint( $attachment['owner_user_id'] ) : 0;
    if ( $owner_user_id > 0 && $owner_user_id === $user_id ) {
        return true;
    }

    if ( wpss_current_user_can_manage_song( $song_id ) ) {
        return true;
    }

    $settings   = wpss_get_song_media_access_settings( $song_id );
    $visibility = isset( $settings['visibility_mode'] ) ? sanitize_key( $settings['visibility_mode'] ) : 'private';
    if ( 'public' === $visibility ) {
        return true;
    }

    if ( 'users' === $visibility ) {
        return in_array( $user_id, isset( $settings['visibility_user_ids'] ) ? (array) $settings['visibility_user_ids'] : [], true );
    }

    if ( 'groups' === $visibility ) {
        $group_ids = isset( $settings['visibility_group_ids'] ) ? (array) $settings['visibility_group_ids'] : [];
        foreach ( $group_ids as $group_id ) {
            if ( wpss_user_can_access_agrupacion( $group_id, 'read', $user_id ) ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Prepara un adjunto para respuesta REST.
 *
 * @param array $attachment Adjunto.
 * @param int   $song_id    Canción.
 * @return array
 */
function wpss_prepare_song_media_attachment_for_response( array $attachment, $song_id ) {
    $song_id = absint( $song_id );
    $stream_url = rest_url( sprintf( 'wpss/v1/media/stream/%d/%s', $song_id, rawurlencode( $attachment['id'] ) ) );
    $settings   = wpss_get_song_media_access_settings( $song_id );

    if ( is_user_logged_in() ) {
        $stream_url = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), $stream_url );
    }

    return [
        'id'                   => $attachment['id'],
        'type'                 => $attachment['type'],
        'title'                => $attachment['title'],
        'source_kind'          => $attachment['source_kind'],
        'anchor_type'          => $attachment['anchor_type'],
        'section_id'           => $attachment['section_id'],
        'verse_index'          => $attachment['verse_index'],
        'segment_index'        => $attachment['segment_index'],
        'visibility_mode'      => $settings['visibility_mode'],
        'visibility_group_ids' => array_map( 'intval', $settings['visibility_group_ids'] ),
        'visibility_user_ids'  => array_map( 'intval', $settings['visibility_user_ids'] ),
        'owner_user_id'        => (int) $attachment['owner_user_id'],
        'owner_user_name'      => wpss_get_user_display_name( (int) $attachment['owner_user_id'] ),
        'storage_provider'     => $attachment['storage_provider'],
        'file_id'              => $attachment['file_id'],
        'file_name'            => $attachment['file_name'],
        'mime_type'            => $attachment['mime_type'],
        'size_bytes'           => $attachment['size_bytes'],
        'duration_seconds'     => $attachment['duration_seconds'],
        'created_at'           => $attachment['created_at'],
        'updated_at'           => $attachment['updated_at'],
        'can_manage'           => wpss_current_user_can_manage_song_attachment( $attachment, $song_id ),
        'can_delete_file'      => wpss_current_user_can_delete_song_attachment_file( $attachment, $song_id ),
        'stream_url'           => $stream_url,
    ];
}

/**
 * Devuelve adjuntos visibles de una canción.
 *
 * @param int  $post_id       ID canción.
 * @param bool $only_public   Solo públicos.
 * @return array
 */
function wpss_get_song_media_attachments( $post_id, $only_public = false ) {
    $items = [];
    $settings = wpss_get_song_media_access_settings( $post_id );
    if ( $only_public && 'public' !== $settings['visibility_mode'] ) {
        return [];
    }

    foreach ( wpss_get_song_media_attachments_raw( $post_id ) as $attachment ) {
        if ( ! $only_public && ! wpss_current_user_can_access_song_attachment( $attachment, $post_id ) ) {
            continue;
        }

        $items[] = wpss_prepare_song_media_attachment_for_response( $attachment, $post_id );
    }

    return $items;
}

/**
 * Reemplaza por completo la lista de adjuntos de una canción.
 *
 * @param int   $song_id ID de canción.
 * @param array $items   Adjunto sanitizados o por sanitizar.
 * @return array
 */
function wpss_replace_song_media_attachments( $song_id, array $items ) {
    $items = wpss_sanitize_song_media_attachments( $items );
    update_post_meta( $song_id, '_adjuntos_multimedia_json', wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    return $items;
}

/**
 * Busca un adjunto por ID dentro de una canción.
 *
 * @param int    $song_id        Canción.
 * @param string $attachment_id  ID adjunto.
 * @return array|null
 */
function wpss_find_song_media_attachment_by_id( $song_id, $attachment_id ) {
    $attachment_id = sanitize_key( (string) $attachment_id );
    foreach ( wpss_get_song_media_attachments_raw( $song_id ) as $item ) {
        if ( isset( $item['id'] ) && $item['id'] === $attachment_id ) {
            return $item;
        }
    }

    return null;
}

/**
 * Determina si el usuario actual puede administrar un adjunto dentro de la canción.
 *
 * @param array $attachment Adjunto.
 * @param int   $song_id    Canción.
 * @param int   $user_id    Usuario opcional.
 * @return bool
 */
function wpss_current_user_can_manage_song_attachment( array $attachment, $song_id, $user_id = 0 ) {
    $song_id = absint( $song_id );
    if ( $song_id <= 0 ) {
        return false;
    }

    if ( wpss_user_can_bypass_coleccion_acl() ) {
        return true;
    }

    $user_id = absint( $user_id ? $user_id : get_current_user_id() );
    if ( $user_id <= 0 ) {
        return false;
    }

    $owner_user_id = isset( $attachment['owner_user_id'] ) ? absint( $attachment['owner_user_id'] ) : 0;
    if ( $owner_user_id > 0 && $owner_user_id === $user_id ) {
        return true;
    }

    $author_id = (int) get_post_field( 'post_author', $song_id );
    if ( $author_id > 0 && $author_id === $user_id ) {
        return true;
    }

    $settings  = wpss_get_song_media_access_settings( $song_id );
    $group_ids = isset( $settings['visibility_group_ids'] ) ? (array) $settings['visibility_group_ids'] : [];
    foreach ( $group_ids as $group_id ) {
        if ( wpss_user_can_access_agrupacion( $group_id, 'write', $user_id ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Determina si el usuario actual puede eliminar físicamente el archivo del adjunto.
 *
 * @param array $attachment Adjunto.
 * @param int   $song_id    Canción opcional.
 * @return bool
 */
function wpss_current_user_can_delete_song_attachment_file( array $attachment, $song_id = 0 ) {
    $current_user_id = get_current_user_id();
    if ( $current_user_id <= 0 ) {
        return false;
    }

    if ( wpss_user_can_bypass_coleccion_acl() ) {
        return true;
    }

    return (int) $attachment['owner_user_id'] === $current_user_id;
}

/**
 * Reemplaza un adjunto puntual dentro de la canción.
 *
 * @param int   $song_id    Canción.
 * @param array $attachment Adjunto validado.
 * @return array
 */
function wpss_update_song_media_attachment( $song_id, array $attachment ) {
    $song_id = absint( $song_id );
    if ( $song_id <= 0 || empty( $attachment['id'] ) ) {
        return [];
    }

    $attachment_id = sanitize_key( (string) $attachment['id'] );
    $items         = wpss_get_song_media_attachments_raw( $song_id );
    $found         = false;

    foreach ( $items as $index => $item ) {
        if ( ! isset( $item['id'] ) || $attachment_id !== $item['id'] ) {
            continue;
        }

        $items[ $index ] = $attachment;
        $found           = true;
        break;
    }

    if ( ! $found ) {
        $items[] = $attachment;
    }

    return wpss_replace_song_media_attachments( $song_id, $items );
}

/**
 * Elimina un archivo físico del Google Drive del propietario.
 *
 * @param int    $owner_user_id ID usuario propietario.
 * @param string $file_id       File id de Drive.
 * @return true|WP_Error
 */
function wpss_google_drive_delete_file( $owner_user_id, $file_id ) {
    $owner_user_id = absint( $owner_user_id );
    $file_id       = sanitize_text_field( (string) $file_id );

    if ( $owner_user_id <= 0 || '' === $file_id ) {
        return new WP_Error( 'wpss_drive_delete_invalid', __( 'No hay datos suficientes para eliminar el archivo de Drive.', 'wp-song-study' ) );
    }

    $token = wpss_get_google_drive_access_token( $owner_user_id );
    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $response = wp_remote_request(
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id ),
        [
            'method'  => 'DELETE',
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    if ( in_array( $status, [ 200, 202, 204, 404 ], true ) ) {
        return true;
    }

    return new WP_Error(
        'wpss_drive_delete_failed',
        sprintf( 'Google Drive HTTP %d', $status ),
        [ 'body' => wp_remote_retrieve_body( $response ) ]
    );
}

/**
 * Elimina todos los archivos adjuntos de una canción desde Google Drive.
 *
 * @param int $song_id ID de canción.
 * @return void
 */
function wpss_delete_all_song_media_files_from_drive( $song_id ) {
    $song_id = absint( $song_id );
    if ( $song_id <= 0 ) {
        return;
    }

    static $processed = [];
    if ( isset( $processed[ $song_id ] ) ) {
        return;
    }
    $processed[ $song_id ] = true;

    $attachments = wpss_get_song_media_attachments_raw( $song_id );
    if ( empty( $attachments ) ) {
        return;
    }

    foreach ( $attachments as $attachment ) {
        $owner_user_id = isset( $attachment['owner_user_id'] ) ? absint( $attachment['owner_user_id'] ) : 0;
        $file_id       = isset( $attachment['file_id'] ) ? sanitize_text_field( (string) $attachment['file_id'] ) : '';
        if ( $owner_user_id <= 0 || '' === $file_id ) {
            continue;
        }

        $deleted = wpss_google_drive_delete_file( $owner_user_id, $file_id );
        if ( is_wp_error( $deleted ) ) {
            error_log(
                sprintf(
                    'wpss: no se pudo borrar adjunto Drive song=%d attachment=%s file=%s error=%s',
                    $song_id,
                    isset( $attachment['id'] ) ? (string) $attachment['id'] : 'unknown',
                    $file_id,
                    $deleted->get_error_message()
                )
            );
        }
    }
}

/**
 * Limpia adjuntos multimedia antes de borrar la canción desde cualquier flujo.
 *
 * @param int $post_id ID del post.
 * @return void
 */
function wpss_cleanup_song_media_on_post_delete( $post_id ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || 'cancion' !== get_post_type( $post_id ) ) {
        return;
    }

    wpss_delete_all_song_media_files_from_drive( $post_id );
}

/**
 * Agrega un adjunto a la canción.
 *
 * @param int   $song_id     Canción.
 * @param array $attachment  Adjunto.
 * @return array
 */
function wpss_append_song_media_attachment( $song_id, array $attachment ) {
    $items   = wpss_get_song_media_attachments_raw( $song_id );
    $items[] = $attachment;
    return wpss_replace_song_media_attachments( $song_id, $items );
}

/**
 * Sube un archivo de canción al Drive del usuario actual y registra el adjunto.
 *
 * @param WP_REST_Request $request Solicitud multipart.
 * @return WP_REST_Response
 */
function wpss_rest_upload_song_media_to_google_drive( WP_REST_Request $request ) {
    $song_id = absint( $request->get_param( 'song_id' ) );
    if ( $song_id <= 0 || 'cancion' !== get_post_type( $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Primero guarda la canción antes de adjuntar archivos.', 'wp-song-study' ) ], 400 );
    }

    if ( ! wpss_current_user_can_manage_song( $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No puedes adjuntar archivos a una canción que no administras.', 'wp-song-study' ) ], 403 );
    }

    $user_id = get_current_user_id();
    $config  = wpss_get_google_drive_user_config( $user_id );
    if ( empty( $config['connected'] ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Conecta tu Google Drive antes de subir audio o fotos.', 'wp-song-study' ) ], 400 );
    }

    $files = $request->get_file_params();
    $file  = isset( $files['file'] ) ? $files['file'] : null;

    if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No se recibió un archivo válido para subir.', 'wp-song-study' ) ], 400 );
    }

    $file_name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : 'archivo';
    $mime_type = isset( $file['type'] ) ? sanitize_text_field( $file['type'] ) : 'application/octet-stream';
    $file_data = file_get_contents( $file['tmp_name'] );

    if ( false === $file_data ) {
        return new WP_REST_Response( [ 'message' => __( 'No fue posible leer el archivo recibido.', 'wp-song-study' ) ], 500 );
    }

    $upload = wpss_google_drive_upload_song_media_bytes(
        $user_id,
        $song_id,
        $file_name,
        $mime_type,
        $file_data,
        [
            'anchor_type'   => sanitize_key( (string) $request->get_param( 'anchor_type' ) ),
            'section_id'    => sanitize_key( (string) $request->get_param( 'section_id' ) ),
            'verse_index'   => max( 0, absint( $request->get_param( 'verse_index' ) ) ),
            'segment_index' => max( 0, absint( $request->get_param( 'segment_index' ) ) ),
        ]
    );

    if ( is_wp_error( $upload ) || empty( $upload['json']['id'] ) ) {
        $message = is_wp_error( $upload )
            ? $upload->get_error_message()
            : __( 'No fue posible subir el archivo a Google Drive.', 'wp-song-study' );
        return new WP_REST_Response( [ 'message' => $message ], 500 );
    }

    $type = sanitize_key( (string) $request->get_param( 'type' ) );
    if ( ! in_array( $type, [ 'audio', 'photo' ], true ) ) {
        $type = str_starts_with( $mime_type, 'image/' ) ? 'photo' : 'audio';
    }

    $settings = wpss_get_song_media_access_settings( $song_id );

    $attachment = [
        'id'                   => 'media-' . sanitize_key( wp_generate_uuid4() ),
        'type'                 => $type,
        'title'                => sanitize_text_field( (string) $request->get_param( 'title' ) ),
        'source_kind'          => sanitize_key( (string) $request->get_param( 'source_kind' ) ),
        'anchor_type'          => sanitize_key( (string) $request->get_param( 'anchor_type' ) ),
        'section_id'           => sanitize_key( (string) $request->get_param( 'section_id' ) ),
        'verse_index'          => max( 0, absint( $request->get_param( 'verse_index' ) ) ),
        'segment_index'        => max( 0, absint( $request->get_param( 'segment_index' ) ) ),
        'visibility_mode'      => $settings['visibility_mode'],
        'visibility_group_ids' => $settings['visibility_group_ids'],
        'visibility_user_ids'  => $settings['visibility_user_ids'],
        'owner_user_id'        => $user_id,
        'storage_provider'     => 'google_drive',
        'file_id'              => sanitize_text_field( $upload['json']['id'] ),
        'file_name'            => sanitize_file_name( isset( $upload['json']['name'] ) ? $upload['json']['name'] : $file_name ),
        'mime_type'            => sanitize_text_field( isset( $upload['json']['mimeType'] ) ? $upload['json']['mimeType'] : $mime_type ),
        'size_bytes'           => absint( isset( $upload['json']['size'] ) ? $upload['json']['size'] : filesize( $file['tmp_name'] ) ),
        'duration_seconds'     => (float) $request->get_param( 'duration_seconds' ),
        'created_at'           => current_time( 'mysql' ),
        'updated_at'           => current_time( 'mysql' ),
    ];

    $stored      = wpss_append_song_media_attachment( $song_id, $attachment );
    $attachment_out = null;
    foreach ( $stored as $item ) {
        if ( $item['id'] === $attachment['id'] ) {
            $attachment_out = wpss_prepare_song_media_attachment_for_response( $item, $song_id );
            break;
        }
    }

    return rest_ensure_response(
        [
            'ok'       => true,
            'song_id'   => $song_id,
            'attachment'=> $attachment_out,
            'adjuntos'  => wpss_get_song_media_attachments( $song_id ),
        ]
    );
}

/**
 * Hace proxy de un archivo privado almacenado en Google Drive.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return WP_REST_Response|WP_Error
 */
function wpss_rest_stream_song_media_attachment( WP_REST_Request $request ) {
    $song_id       = absint( $request->get_param( 'song_id' ) );
    $attachment_id = sanitize_key( (string) $request->get_param( 'attachment_id' ) );

    $post = get_post( $song_id );
    if ( ! $post || 'cancion' !== $post->post_type ) {
        return new WP_Error( 'wpss_not_found', __( 'Canción no encontrada.', 'wp-song-study' ), [ 'status' => 404 ] );
    }

    $is_song_public = wpss_is_song_publicly_visible( $song_id );
    $attachment = null;
    foreach ( wpss_get_song_media_attachments_raw( $song_id ) as $item ) {
        if ( $item['id'] === $attachment_id ) {
            $attachment = $item;
            break;
        }
    }

    if ( ! is_array( $attachment ) ) {
        return new WP_Error( 'wpss_media_not_found', __( 'Adjunto no encontrado.', 'wp-song-study' ), [ 'status' => 404 ] );
    }

    $settings = wpss_get_song_media_access_settings( $song_id );
    if ( ! $is_song_public || 'public' !== $settings['visibility_mode'] ) {
        if ( ! is_user_logged_in() || ! wpss_current_user_can_access_song_attachment( $attachment, $song_id ) ) {
            return new WP_Error( 'wpss_media_forbidden', __( 'No tienes acceso a este adjunto.', 'wp-song-study' ), [ 'status' => 403 ] );
        }
    }

    $owner_user_id = absint( $attachment['owner_user_id'] );
    if ( $owner_user_id <= 0 ) {
        return new WP_Error( 'wpss_media_owner_missing', __( 'El adjunto no tiene un propietario de Drive válido.', 'wp-song-study' ), [ 'status' => 500 ] );
    }

    $download = wpss_google_drive_request(
        $owner_user_id,
        'GET',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $attachment['file_id'] ) . '?alt=media',
        [
            'headers' => [
                'Accept' => isset( $attachment['mime_type'] ) ? $attachment['mime_type'] : '*/*',
            ],
        ]
    );

    if ( is_wp_error( $download ) ) {
        return new WP_Error( 'wpss_media_download_failed', $download->get_error_message(), [ 'status' => 500 ] );
    }

    $mime_type = ! empty( $attachment['mime_type'] ) ? $attachment['mime_type'] : 'application/octet-stream';
    $body      = (string) $download['body'];
    $length    = strlen( $body );

    while ( ob_get_level() > 0 ) {
        ob_end_clean();
    }

    status_header( 200 );
    nocache_headers();
    header( 'Content-Type: ' . $mime_type );
    header( 'Content-Length: ' . $length );
    header( 'Cache-Control: private, max-age=300' );
    header( 'Content-Disposition: inline; filename="' . rawurlencode( ! empty( $attachment['file_name'] ) ? $attachment['file_name'] : $attachment['id'] ) . '"' );
    header( 'Accept-Ranges: none' );

    echo $body;
    exit;
}

/**
 * Actualiza metadatos de un adjunto ya almacenado.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return WP_REST_Response
 */
function wpss_rest_update_song_media_attachment( WP_REST_Request $request ) {
    $song_id       = absint( $request->get_param( 'song_id' ) );
    $attachment_id = sanitize_key( (string) $request->get_param( 'attachment_id' ) );

    if ( $song_id <= 0 || 'cancion' !== get_post_type( $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Canción no encontrada.', 'wp-song-study' ) ], 404 );
    }

    $attachment = wpss_find_song_media_attachment_by_id( $song_id, $attachment_id );
    if ( ! is_array( $attachment ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Adjunto no encontrado.', 'wp-song-study' ) ], 404 );
    }

    if ( ! wpss_current_user_can_manage_song_attachment( $attachment, $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No puedes editar este adjunto.', 'wp-song-study' ) ], 403 );
    }

    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }

    $settings = wpss_get_song_media_access_settings( $song_id );

    $next_attachment = [
        'id'                   => $attachment['id'],
        'type'                 => isset( $params['type'] ) ? $params['type'] : $attachment['type'],
        'title'                => array_key_exists( 'title', $params ) ? $params['title'] : $attachment['title'],
        'source_kind'          => array_key_exists( 'source_kind', $params ) ? $params['source_kind'] : $attachment['source_kind'],
        'anchor_type'          => array_key_exists( 'anchor_type', $params ) ? $params['anchor_type'] : $attachment['anchor_type'],
        'section_id'           => array_key_exists( 'section_id', $params ) ? $params['section_id'] : $attachment['section_id'],
        'verse_index'          => array_key_exists( 'verse_index', $params ) ? $params['verse_index'] : $attachment['verse_index'],
        'segment_index'        => array_key_exists( 'segment_index', $params ) ? $params['segment_index'] : $attachment['segment_index'],
        'visibility_mode'      => $settings['visibility_mode'],
        'visibility_group_ids' => $settings['visibility_group_ids'],
        'visibility_user_ids'  => $settings['visibility_user_ids'],
        'owner_user_id'        => $attachment['owner_user_id'],
        'storage_provider'     => $attachment['storage_provider'],
        'file_id'              => $attachment['file_id'],
        'file_name'            => $attachment['file_name'],
        'mime_type'            => $attachment['mime_type'],
        'size_bytes'           => $attachment['size_bytes'],
        'duration_seconds'     => array_key_exists( 'duration_seconds', $params ) ? $params['duration_seconds'] : $attachment['duration_seconds'],
        'created_at'           => $attachment['created_at'],
        'updated_at'           => current_time( 'mysql' ),
    ];

    $sanitized = wpss_sanitize_song_media_attachments( [ $next_attachment ] );
    if ( empty( $sanitized[0] ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Los datos del adjunto no son válidos.', 'wp-song-study' ) ], 400 );
    }

    $title_changed = isset( $sanitized[0]['title'], $attachment['title'] )
        && sanitize_text_field( (string) $sanitized[0]['title'] ) !== sanitize_text_field( (string) $attachment['title'] );

    if (
        ! empty( $sanitized[0]['file_id'] ) &&
        ! empty( $sanitized[0]['owner_user_id'] ) &&
        'google_drive' === ( $sanitized[0]['storage_provider'] ?? '' )
    ) {
        if ( $title_changed ) {
            $renamed_file_name = wpss_song_media_build_file_name_from_title(
                $sanitized[0]['title'],
                $attachment['file_name'] ?? ''
            );

            $renamed = wpss_google_drive_rename_file(
                absint( $sanitized[0]['owner_user_id'] ),
                sanitize_text_field( (string) $sanitized[0]['file_id'] ),
                $renamed_file_name
            );

            if ( is_wp_error( $renamed ) ) {
                return new WP_REST_Response(
                    [
                        'message' => $renamed->get_error_message(),
                        'code'    => $renamed->get_error_code(),
                    ],
                    500
                );
            }

            $sanitized[0]['file_name'] = sanitize_file_name(
                (string) ( $renamed['json']['name'] ?? $renamed_file_name )
            );
        }

        $moved = wpss_google_drive_move_file_to_attachment_folder(
            absint( $sanitized[0]['owner_user_id'] ),
            $song_id,
            $sanitized[0]
        );

        if ( is_wp_error( $moved ) ) {
            return new WP_REST_Response(
                [
                    'message' => $moved->get_error_message(),
                    'code'    => $moved->get_error_code(),
                ],
                500
            );
        }
    }

    $stored      = wpss_update_song_media_attachment( $song_id, $sanitized[0] );
    $updated_out = null;
    foreach ( $stored as $item ) {
        if ( isset( $item['id'] ) && $attachment_id === $item['id'] ) {
            $updated_out = wpss_prepare_song_media_attachment_for_response( $item, $song_id );
            break;
        }
    }

    return rest_ensure_response(
        [
            'ok'         => true,
            'song_id'    => $song_id,
            'attachment' => $updated_out,
            'adjuntos'   => wpss_get_song_media_attachments( $song_id ),
            'message'    => __( 'Adjunto actualizado.', 'wp-song-study' ),
        ]
    );
}

/**
 * Desvincula un adjunto de la canción sin borrar el archivo del Drive.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return WP_REST_Response
 */
function wpss_rest_unlink_song_media_attachment( WP_REST_Request $request ) {
    $song_id       = absint( $request->get_param( 'song_id' ) );
    $attachment_id = sanitize_key( (string) $request->get_param( 'attachment_id' ) );

    if ( $song_id <= 0 || 'cancion' !== get_post_type( $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Canción no encontrada.', 'wp-song-study' ) ], 404 );
    }

    $attachment = wpss_find_song_media_attachment_by_id( $song_id, $attachment_id );
    if ( ! is_array( $attachment ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Adjunto no encontrado.', 'wp-song-study' ) ], 404 );
    }

    if ( ! wpss_current_user_can_manage_song_attachment( $attachment, $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No puedes quitar este adjunto de la canción.', 'wp-song-study' ) ], 403 );
    }

    $remaining = array_values(
        array_filter(
            wpss_get_song_media_attachments_raw( $song_id ),
            static function( $item ) use ( $attachment_id ) {
                return ! isset( $item['id'] ) || $item['id'] !== $attachment_id;
            }
        )
    );

    wpss_replace_song_media_attachments( $song_id, $remaining );

    return rest_ensure_response(
        [
            'ok'       => true,
            'song_id'  => $song_id,
            'removed'  => $attachment_id,
            'adjuntos' => wpss_get_song_media_attachments( $song_id ),
            'message'  => __( 'Adjunto desvinculado de la canción.', 'wp-song-study' ),
        ]
    );
}

/**
 * Elimina definitivamente un adjunto: archivo físico en Drive y referencia en la canción.
 *
 * @param WP_REST_Request $request Solicitud.
 * @return WP_REST_Response
 */
function wpss_rest_delete_song_media_attachment( WP_REST_Request $request ) {
    $song_id       = absint( $request->get_param( 'song_id' ) );
    $attachment_id = sanitize_key( (string) $request->get_param( 'attachment_id' ) );

    if ( $song_id <= 0 || 'cancion' !== get_post_type( $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Canción no encontrada.', 'wp-song-study' ) ], 404 );
    }

    $attachment = wpss_find_song_media_attachment_by_id( $song_id, $attachment_id );
    if ( ! is_array( $attachment ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Adjunto no encontrado.', 'wp-song-study' ) ], 404 );
    }

    if ( ! wpss_current_user_can_manage_song_attachment( $attachment, $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'No puedes eliminar este adjunto.', 'wp-song-study' ) ], 403 );
    }

    if ( ! wpss_current_user_can_delete_song_attachment_file( $attachment, $song_id ) ) {
        return new WP_REST_Response( [ 'message' => __( 'Solo el propietario del adjunto o un administrador global puede borrar el archivo del Drive.', 'wp-song-study' ) ], 403 );
    }

    $deleted = wpss_google_drive_delete_file( (int) $attachment['owner_user_id'], (string) $attachment['file_id'] );
    if ( is_wp_error( $deleted ) ) {
        return new WP_REST_Response( [ 'message' => $deleted->get_error_message() ], 500 );
    }

    $remaining = array_values(
        array_filter(
            wpss_get_song_media_attachments_raw( $song_id ),
            static function( $item ) use ( $attachment_id ) {
                return ! isset( $item['id'] ) || $item['id'] !== $attachment_id;
            }
        )
    );
    wpss_replace_song_media_attachments( $song_id, $remaining );

    return rest_ensure_response(
        [
            'ok'        => true,
            'song_id'   => $song_id,
            'deleted'   => $attachment_id,
            'adjuntos'  => wpss_get_song_media_attachments( $song_id ),
            'message'   => __( 'Adjunto eliminado también del Google Drive.', 'wp-song-study' ),
        ]
    );
}
