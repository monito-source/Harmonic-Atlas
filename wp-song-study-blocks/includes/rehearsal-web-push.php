<?php
/**
 * Soporte de Web Push para el planificador de ensayos.
 *
 * @package WP_Song_Study_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ajustes por defecto para Web Push.
 *
 * @return array<string, mixed>
 */
function wpssb_get_default_rehearsal_web_push_settings() {
    $admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );

    return [
        'enabled'     => 0,
        'subject'     => '' !== $admin_email ? 'mailto:' . $admin_email : '',
        'public_key'  => '',
        'private_key' => '',
    ];
}

/**
 * Sanea los ajustes persistidos de Web Push.
 *
 * @param mixed $settings Valores crudos.
 * @return array<string, mixed>
 */
function wpssb_sanitize_rehearsal_web_push_settings( $settings ) {
    $defaults = wpssb_get_default_rehearsal_web_push_settings();
    $stored   = get_option( 'wpss_rehearsal_web_push_settings', [] );
    $settings = wp_parse_args( is_array( $settings ) ? $settings : [], is_array( $stored ) ? $stored : [] );

    $subject = trim( sanitize_text_field( (string) ( $settings['subject'] ?? '' ) ) );
    if ( '' !== $subject && 0 !== strpos( $subject, 'mailto:' ) && ! wp_http_validate_url( $subject ) ) {
        $subject = (string) $defaults['subject'];
    }

    return [
        'enabled'     => ! empty( $settings['enabled'] ) ? 1 : 0,
        'subject'     => '' !== $subject ? $subject : (string) $defaults['subject'],
        'public_key'  => trim( sanitize_text_field( (string) ( $settings['public_key'] ?? '' ) ) ),
        'private_key' => trim( sanitize_text_field( (string) ( $settings['private_key'] ?? '' ) ) ),
    ];
}

/**
 * Devuelve los ajustes saneados de Web Push.
 *
 * @return array<string, mixed>
 */
function wpssb_get_rehearsal_web_push_settings() {
    $defaults = wpssb_get_default_rehearsal_web_push_settings();
    $stored   = get_option( 'wpss_rehearsal_web_push_settings', [] );

    return wp_parse_args(
        wpssb_sanitize_rehearsal_web_push_settings( $stored ),
        $defaults
    );
}

/**
 * Indica si Web Push quedó listo para usarse.
 *
 * @param array<string, mixed>|null $settings Ajustes opcionales.
 * @return bool
 */
function wpssb_rehearsal_web_push_is_configured( $settings = null ) {
    $settings = is_array( $settings ) ? $settings : wpssb_get_rehearsal_web_push_settings();

    return ! empty( $settings['enabled'] )
        && ! empty( $settings['subject'] )
        && ! empty( $settings['public_key'] )
        && ! empty( $settings['private_key'] )
        && class_exists( '\Minishlink\WebPush\WebPush' )
        && class_exists( '\Minishlink\WebPush\VAPID' )
        && class_exists( '\Minishlink\WebPush\Subscription' );
}

/**
 * Genera un par nuevo de llaves VAPID.
 *
 * @return array<string, string>|WP_Error
 */
function wpssb_generate_rehearsal_web_push_keys() {
    if ( ! class_exists( '\Minishlink\WebPush\VAPID' ) ) {
        return new WP_Error( 'wpssb_web_push_missing_library', __( 'La libreria de Web Push no esta disponible en este plugin.', 'wp-song-study-blocks' ) );
    }

    try {
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
    } catch ( Exception $exception ) {
        return new WP_Error( 'wpssb_web_push_key_generation_failed', sanitize_text_field( $exception->getMessage() ) );
    } catch ( Error $error ) {
        return new WP_Error( 'wpssb_web_push_key_generation_failed', sanitize_text_field( $error->getMessage() ) );
    }

    return [
        'public_key'  => sanitize_text_field( (string) ( $keys['publicKey'] ?? '' ) ),
        'private_key' => sanitize_text_field( (string) ( $keys['privateKey'] ?? '' ) ),
    ];
}

/**
 * Renderiza el archivo JS del service worker por AJAX.
 *
 * @return void
 */
function wpssb_render_rehearsal_push_service_worker() {
    $path = WPSSB_PATH . 'assets/project-frontend/rehearsal-push-sw.js';

    if ( ! file_exists( $path ) ) {
        status_header( 404 );
        exit;
    }

    nocache_headers();
    header( 'Content-Type: application/javascript; charset=UTF-8' );
    header( 'Service-Worker-Allowed: /' );

    readfile( $path );
    exit;
}
add_action( 'wp_ajax_wpssb_rehearsal_push_service_worker', 'wpssb_render_rehearsal_push_service_worker' );
add_action( 'wp_ajax_nopriv_wpssb_rehearsal_push_service_worker', 'wpssb_render_rehearsal_push_service_worker' );

/**
 * Devuelve la URL pública del service worker de Web Push.
 *
 * @return string
 */
function wpssb_get_rehearsal_push_service_worker_url() {
    return add_query_arg( 'action', 'wpssb_rehearsal_push_service_worker', admin_url( 'admin-ajax.php' ) );
}

/**
 * Devuelve el user meta donde se guardan suscripciones de Web Push.
 *
 * @return string
 */
function wpssb_get_rehearsal_push_subscription_meta_key() {
    return 'wpssb_rehearsal_push_subscriptions';
}

/**
 * Sanea una suscripcion individual de Web Push.
 *
 * @param mixed $entry Valor crudo.
 * @return array<string, mixed>
 */
function wpssb_sanitize_rehearsal_push_subscription_entry( $entry ) {
    $entry             = is_array( $entry ) ? $entry : [];
    $endpoint          = esc_url_raw( (string) ( $entry['endpoint'] ?? '' ) );
    $public_key        = trim( sanitize_text_field( (string) ( $entry['public_key'] ?? ( $entry['keys']['p256dh'] ?? '' ) ) ) );
    $auth_token        = trim( sanitize_text_field( (string) ( $entry['auth_token'] ?? ( $entry['keys']['auth'] ?? '' ) ) ) );
    $content_encoding  = sanitize_key( (string) ( $entry['content_encoding'] ?? $entry['contentEncoding'] ?? 'aes128gcm' ) );
    $allowed_encodings = [ 'aes128gcm', 'aesgcm' ];

    if ( ! in_array( $content_encoding, $allowed_encodings, true ) ) {
        $content_encoding = 'aes128gcm';
    }

    $project_ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    'absint',
                    is_array( $entry['project_ids'] ?? null ) ? $entry['project_ids'] : []
                )
            )
        )
    );

    $created_at = sanitize_text_field( (string) ( $entry['created_at_gmt'] ?? '' ) );
    $updated_at = sanitize_text_field( (string) ( $entry['updated_at_gmt'] ?? '' ) );

    if ( '' === $created_at || false === strtotime( $created_at ) ) {
        $created_at = wpssb_get_project_rehearsal_browser_notification_now_gmt();
    }

    if ( '' === $updated_at || false === strtotime( $updated_at ) ) {
        $updated_at = wpssb_get_project_rehearsal_browser_notification_now_gmt();
    }

    return [
        'endpoint_hash'   => '' !== $endpoint ? hash( 'sha256', $endpoint ) : '',
        'endpoint'        => $endpoint,
        'public_key'      => $public_key,
        'auth_token'      => $auth_token,
        'content_encoding'=> $content_encoding,
        'project_ids'     => $project_ids,
        'created_at_gmt'  => $created_at,
        'updated_at_gmt'  => $updated_at,
        'user_agent'      => sanitize_text_field( (string) ( $entry['user_agent'] ?? '' ) ),
    ];
}

/**
 * Sanea una lista de suscripciones.
 *
 * @param mixed $entries Lista cruda.
 * @return array<int, array<string, mixed>>
 */
function wpssb_sanitize_rehearsal_push_subscription_entries( $entries ) {
    $entries = is_array( $entries ) ? $entries : [];
    $clean   = [];

    foreach ( $entries as $entry ) {
        $sanitized = wpssb_sanitize_rehearsal_push_subscription_entry( $entry );

        if ( '' === $sanitized['endpoint'] || '' === $sanitized['public_key'] || '' === $sanitized['auth_token'] ) {
            continue;
        }

        $clean[] = $sanitized;
    }

    return array_values( $clean );
}

/**
 * Obtiene las suscripciones Web Push de un usuario.
 *
 * @param int $user_id Usuario actual.
 * @return array<int, array<string, mixed>>
 */
function wpssb_get_user_rehearsal_push_subscriptions( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return [];
    }

    return wpssb_sanitize_rehearsal_push_subscription_entries(
        get_user_meta( $user_id, wpssb_get_rehearsal_push_subscription_meta_key(), true )
    );
}

/**
 * Persiste las suscripciones saneadas de un usuario.
 *
 * @param int                                  $user_id Usuario actual.
 * @param array<int, array<string, mixed>>     $entries Lista saneada.
 * @return void
 */
function wpssb_set_user_rehearsal_push_subscriptions( $user_id, $entries ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return;
    }

    update_user_meta(
        $user_id,
        wpssb_get_rehearsal_push_subscription_meta_key(),
        wpssb_sanitize_rehearsal_push_subscription_entries( $entries )
    );
}

/**
 * Vincula o actualiza una suscripcion Web Push con un proyecto.
 *
 * @param int                  $user_id      Usuario actual.
 * @param array<string, mixed> $subscription Suscripcion saneable.
 * @param int                  $project_id   Proyecto actual.
 * @return bool
 */
function wpssb_upsert_user_rehearsal_push_subscription( $user_id, $subscription, $project_id ) {
    $user_id      = absint( $user_id );
    $project_id   = absint( $project_id );
    $subscription = wpssb_sanitize_rehearsal_push_subscription_entry( $subscription );

    if ( $user_id <= 0 || $project_id <= 0 || '' === $subscription['endpoint'] || '' === $subscription['endpoint_hash'] ) {
        return false;
    }

    $entries = wpssb_get_user_rehearsal_push_subscriptions( $user_id );
    $found   = false;

    foreach ( $entries as $index => $entry ) {
        if ( ! is_array( $entry ) || $subscription['endpoint_hash'] !== ( $entry['endpoint_hash'] ?? '' ) ) {
            continue;
        }

        $project_ids = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        array_merge(
                            is_array( $entry['project_ids'] ?? null ) ? $entry['project_ids'] : [],
                            [ $project_id ]
                        )
                    )
                )
            )
        );

        $entries[ $index ] = array_merge(
            $entry,
            $subscription,
            [
                'project_ids'    => $project_ids,
                'updated_at_gmt' => wpssb_get_project_rehearsal_browser_notification_now_gmt(),
            ]
        );
        $found = true;
        break;
    }

    if ( ! $found ) {
        $subscription['project_ids']    = [ $project_id ];
        $subscription['created_at_gmt'] = wpssb_get_project_rehearsal_browser_notification_now_gmt();
        $subscription['updated_at_gmt'] = $subscription['created_at_gmt'];
        $entries[]                      = $subscription;
    }

    wpssb_set_user_rehearsal_push_subscriptions( $user_id, $entries );

    return true;
}

/**
 * Desvincula una suscripcion Web Push de un proyecto o la elimina por completo.
 *
 * @param int                  $user_id      Usuario actual.
 * @param array<string, mixed> $subscription Suscripcion o endpoint.
 * @param int                  $project_id   Proyecto actual.
 * @return bool
 */
function wpssb_remove_user_rehearsal_push_subscription( $user_id, $subscription, $project_id = 0 ) {
    $user_id    = absint( $user_id );
    $project_id = absint( $project_id );

    if ( $user_id <= 0 ) {
        return false;
    }

    if ( is_string( $subscription ) ) {
        $subscription = [ 'endpoint' => $subscription ];
    }

    $subscription = wpssb_sanitize_rehearsal_push_subscription_entry( $subscription );
    $target_hash  = (string) ( $subscription['endpoint_hash'] ?? '' );

    if ( '' === $target_hash ) {
        return false;
    }

    $entries = wpssb_get_user_rehearsal_push_subscriptions( $user_id );
    $next    = [];
    $removed = false;

    foreach ( $entries as $entry ) {
        if ( ! is_array( $entry ) || $target_hash !== ( $entry['endpoint_hash'] ?? '' ) ) {
            $next[] = $entry;
            continue;
        }

        if ( $project_id > 0 ) {
            $project_ids = array_values(
                array_filter(
                    array_map(
                        'absint',
                        is_array( $entry['project_ids'] ?? null ) ? $entry['project_ids'] : []
                    ),
                    static function ( $candidate ) use ( $project_id ) {
                        return $project_id !== (int) $candidate;
                    }
                )
            );

            if ( ! empty( $project_ids ) ) {
                $entry['project_ids']    = $project_ids;
                $entry['updated_at_gmt'] = wpssb_get_project_rehearsal_browser_notification_now_gmt();
                $next[]                  = $entry;
            }
        }

        $removed = true;
    }

    if ( $removed ) {
        wpssb_set_user_rehearsal_push_subscriptions( $user_id, $next );
    }

    return $removed;
}

/**
 * Devuelve las suscripciones vigentes de Web Push para un proyecto.
 *
 * @param int   $post_id          Proyecto actual.
 * @param int[] $exclude_user_ids Usuarios a excluir.
 * @return array<int, array<string, mixed>>
 */
function wpssb_get_project_rehearsal_push_subscriptions( $post_id, $exclude_user_ids = [] ) {
    $post_id          = absint( $post_id );
    $exclude_user_ids = array_values( array_filter( array_map( 'absint', is_array( $exclude_user_ids ) ? $exclude_user_ids : [] ) ) );
    $subscriptions    = [];

    if ( $post_id <= 0 ) {
        return [];
    }

    foreach ( wpssb_get_project_rehearsal_members( $post_id ) as $member ) {
        if ( ! $member instanceof WP_User ) {
            continue;
        }

        $user_id = (int) $member->ID;

        if ( in_array( $user_id, $exclude_user_ids, true ) ) {
            continue;
        }

        foreach ( wpssb_get_user_rehearsal_push_subscriptions( $user_id ) as $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['project_ids'] ) || ! in_array( $post_id, array_map( 'intval', (array) $entry['project_ids'] ), true ) ) {
                continue;
            }

            $subscriptions[] = [
                'user_id'       => $user_id,
                'endpoint_hash' => sanitize_text_field( (string) ( $entry['endpoint_hash'] ?? '' ) ),
                'subscription'  => $entry,
            ];
        }
    }

    return $subscriptions;
}

/**
 * Envía un push real de navegador para un evento del planificador.
 *
 * @param int                  $post_id Proyecto actual.
 * @param array<string, mixed> $event   Evento saneado.
 * @return void
 */
function wpssb_dispatch_project_rehearsal_web_push_notification( $post_id, $event ) {
    $post_id  = absint( $post_id );
    $settings = wpssb_get_rehearsal_web_push_settings();

    if ( $post_id <= 0 || ! wpssb_rehearsal_web_push_is_configured( $settings ) ) {
        return;
    }

    $event = wpssb_sanitize_project_rehearsal_browser_notification_event( $event );

    if ( '' === $event['title'] || '' === $event['body'] ) {
        return;
    }

    $deliveries = wpssb_get_project_rehearsal_push_subscriptions(
        $post_id,
        ! empty( $event['actor_id'] ) ? [ absint( $event['actor_id'] ) ] : []
    );

    if ( empty( $deliveries ) ) {
        return;
    }

    $payload = wp_json_encode(
        [
            'id'         => sanitize_text_field( (string) ( $event['id'] ?? '' ) ),
            'projectId'  => $post_id,
            'project'    => sanitize_text_field( (string) get_the_title( $post_id ) ),
            'title'      => sanitize_text_field( (string) ( $event['title'] ?? '' ) ),
            'body'       => sanitize_text_field( (string) ( $event['body'] ?? '' ) ),
            'url'        => esc_url_raw( (string) ( $event['url'] ?? wpssb_get_frontend_rehearsal_url( $post_id, 'calendar' ) ) ),
            'tag'        => 'wpssb-rehearsal:' . $post_id . ':' . sanitize_text_field( (string) ( $event['id'] ?? '' ) ),
            'created_at' => sanitize_text_field( (string) ( $event['created_at_gmt'] ?? '' ) ),
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ( ! is_string( $payload ) || '' === $payload ) {
        return;
    }

    try {
        $web_push = new \Minishlink\WebPush\WebPush(
            [
                'VAPID' => [
                    'subject'    => (string) $settings['subject'],
                    'publicKey'  => (string) $settings['public_key'],
                    'privateKey' => (string) $settings['private_key'],
                ],
            ],
            [
                'TTL' => 300,
            ],
            20
        );
        $web_push->setReuseVAPIDHeaders( true );
    } catch ( Exception $exception ) {
        return;
    } catch ( Error $error ) {
        return;
    }

    $endpoint_map = [];

    foreach ( $deliveries as $delivery ) {
        $subscription = is_array( $delivery['subscription'] ?? null ) ? $delivery['subscription'] : [];
        $endpoint     = esc_url_raw( (string) ( $subscription['endpoint'] ?? '' ) );

        if ( '' === $endpoint ) {
            continue;
        }

        try {
            $web_push->queueNotification(
                \Minishlink\WebPush\Subscription::create(
                    [
                        'endpoint'        => $endpoint,
                        'publicKey'       => sanitize_text_field( (string) ( $subscription['public_key'] ?? '' ) ),
                        'authToken'       => sanitize_text_field( (string) ( $subscription['auth_token'] ?? '' ) ),
                        'contentEncoding' => sanitize_key( (string) ( $subscription['content_encoding'] ?? 'aes128gcm' ) ),
                    ]
                ),
                $payload
            );
            $endpoint_map[ $endpoint ] = [
                'user_id'      => absint( $delivery['user_id'] ?? 0 ),
                'subscription' => $subscription,
            ];
        } catch ( Exception $exception ) {
            continue;
        } catch ( Error $error ) {
            continue;
        }
    }

    if ( empty( $endpoint_map ) ) {
        return;
    }

    foreach ( $web_push->flush() as $report ) {
        if ( ! $report instanceof \Minishlink\WebPush\MessageSentReport || ! $report->isSubscriptionExpired() ) {
            continue;
        }

        $endpoint = esc_url_raw( (string) $report->getEndpoint() );
        $target   = $endpoint_map[ $endpoint ] ?? null;

        if ( ! is_array( $target ) || empty( $target['user_id'] ) || empty( $target['subscription'] ) ) {
            continue;
        }

        wpssb_remove_user_rehearsal_push_subscription(
            absint( $target['user_id'] ),
            (array) $target['subscription']
        );
    }
}

/**
 * Guarda o actualiza la suscripcion Web Push del navegador actual.
 *
 * @return void
 */
function wpssb_handle_rehearsal_push_subscription_save() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Necesitas iniciar sesion para activar notificaciones push.', 'wp-song-study-blocks' ) ], 401 );
    }

    check_ajax_referer( 'wpssb_rehearsal_push_subscription', 'nonce' );

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wp_send_json_error( [ 'message' => __( 'No tienes permiso para activar notificaciones de este proyecto.', 'wp-song-study-blocks' ) ], 403 );
    }

    $raw_subscription = isset( $_POST['subscription'] ) ? wp_unslash( $_POST['subscription'] ) : '';
    $decoded          = is_string( $raw_subscription ) ? json_decode( $raw_subscription, true ) : [];
    $subscription     = wpssb_sanitize_rehearsal_push_subscription_entry( $decoded );
    $subscription['user_agent'] = sanitize_text_field( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

    if ( '' === $subscription['endpoint'] || '' === $subscription['public_key'] || '' === $subscription['auth_token'] ) {
        wp_send_json_error( [ 'message' => __( 'La suscripcion del navegador llego incompleta.', 'wp-song-study-blocks' ) ], 422 );
    }

    wpssb_upsert_user_rehearsal_push_subscription( $user_id, $subscription, $project_id );

    wp_send_json_success(
        [
            'message' => __( 'Suscripcion push guardada.', 'wp-song-study-blocks' ),
        ]
    );
}
add_action( 'wp_ajax_wpssb_save_rehearsal_push_subscription', 'wpssb_handle_rehearsal_push_subscription_save' );
add_action(
    'wp_ajax_nopriv_wpssb_save_rehearsal_push_subscription',
    static function() {
        wp_send_json_error( [ 'message' => __( 'Necesitas iniciar sesion para activar notificaciones push.', 'wp-song-study-blocks' ) ], 401 );
    }
);

/**
 * Desactiva la suscripcion push del navegador actual para un proyecto.
 *
 * @return void
 */
function wpssb_handle_rehearsal_push_subscription_remove() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Necesitas iniciar sesion para modificar notificaciones push.', 'wp-song-study-blocks' ) ], 401 );
    }

    check_ajax_referer( 'wpssb_rehearsal_push_unsubscribe', 'nonce' );

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wp_send_json_error( [ 'message' => __( 'No tienes permiso para modificar notificaciones de este proyecto.', 'wp-song-study-blocks' ) ], 403 );
    }

    $raw_subscription = isset( $_POST['subscription'] ) ? wp_unslash( $_POST['subscription'] ) : '';
    $decoded          = is_string( $raw_subscription ) ? json_decode( $raw_subscription, true ) : [];
    $subscription     = wpssb_sanitize_rehearsal_push_subscription_entry( $decoded );

    if ( '' === $subscription['endpoint'] ) {
        wp_send_json_error( [ 'message' => __( 'No llegó el endpoint que se debía desactivar.', 'wp-song-study-blocks' ) ], 422 );
    }

    wpssb_remove_user_rehearsal_push_subscription( $user_id, $subscription, $project_id );

    wp_send_json_success(
        [
            'message' => __( 'Suscripcion push desactivada para este proyecto.', 'wp-song-study-blocks' ),
        ]
    );
}
add_action( 'wp_ajax_wpssb_remove_rehearsal_push_subscription', 'wpssb_handle_rehearsal_push_subscription_remove' );
add_action(
    'wp_ajax_nopriv_wpssb_remove_rehearsal_push_subscription',
    static function() {
        wp_send_json_error( [ 'message' => __( 'Necesitas iniciar sesion para modificar notificaciones push.', 'wp-song-study-blocks' ) ], 401 );
    }
);

/**
 * Genera o regenera llaves VAPID desde la pantalla de ajustes.
 *
 * @return void
 */
function wpssb_handle_rehearsal_web_push_key_generation() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos para generar llaves VAPID.', 'wp-song-study-blocks' ) );
    }

    check_admin_referer( 'wpssb_generate_rehearsal_web_push_keys', 'wpssb_generate_rehearsal_web_push_keys_nonce' );

    $result       = wpssb_generate_rehearsal_web_push_keys();
    $redirect_url = add_query_arg(
        [
            'page' => 'wpss-rehearsal-notifications',
        ],
        admin_url( 'admin.php' )
    );

    if ( is_wp_error( $result ) ) {
        $redirect_url = add_query_arg( 'wpssb_push_keys', 'error', $redirect_url );
        $redirect_url = add_query_arg( 'wpssb_push_keys_message', rawurlencode( $result->get_error_message() ), $redirect_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $settings                = wpssb_get_rehearsal_web_push_settings();
    $settings['public_key']  = sanitize_text_field( (string) ( $result['public_key'] ?? '' ) );
    $settings['private_key'] = sanitize_text_field( (string) ( $result['private_key'] ?? '' ) );
    update_option( 'wpss_rehearsal_web_push_settings', wpssb_sanitize_rehearsal_web_push_settings( $settings ), false );

    $redirect_url = add_query_arg( 'wpssb_push_keys', 'generated', $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wpssb_generate_rehearsal_web_push_keys', 'wpssb_handle_rehearsal_web_push_key_generation' );
