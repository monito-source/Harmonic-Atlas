<?php
/**
 * Páginas de administración y carga de assets para el SPA del cancionario.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hooks registrados para las páginas del SPA.
 *
 * @var string[]
 */
global $wpss_admin_page_hooks;
$wpss_admin_page_hooks = [];

add_action( 'admin_menu', 'wpss_register_admin_pages' );
add_action( 'admin_enqueue_scripts', 'wpss_enqueue_admin_assets' );
add_action( 'admin_init', 'wpss_register_settings' );

/**
 * Registra el menú "Cancionario Armónico" con sus páginas SPA.
 */
function wpss_register_admin_pages() {
    global $wpss_admin_page_hooks;

    $capability  = defined( 'WPSS_CAP_MANAGE' ) ? WPSS_CAP_MANAGE : 'edit_posts';
    $parent_slug = 'wpss-cancionario';

    $dashboard_hook = add_menu_page(
        __( 'Cancionario Armónico', 'wp-song-study' ),
        __( 'Cancionario Armónico', 'wp-song-study' ),
        $capability,
        $parent_slug,
        'wpss_render_dashboard_page',
        'dashicons-playlist-audio',
        26
    );

    // Asegura que el submenú muestre el nombre deseado.
    add_submenu_page(
        $parent_slug,
        __( 'Dashboard / Biblioteca', 'wp-song-study' ),
        __( 'Dashboard / Biblioteca', 'wp-song-study' ),
        $capability,
        $parent_slug,
        'wpss_render_dashboard_page'
    );

    $new_song_hook = add_submenu_page(
        $parent_slug,
        __( 'Nueva Canción', 'wp-song-study' ),
        __( 'Nueva Canción', 'wp-song-study' ),
        $capability,
        'wpss-cancion-nueva',
        'wpss_render_new_song_page'
    );

    $chords_hook = add_submenu_page(
        $parent_slug,
        __( 'Acordes', 'wp-song-study' ),
        __( 'Acordes', 'wp-song-study' ),
        $capability,
        'wpss-acordes',
        'wpss_render_chords_page'
    );

    $groups_hook = add_submenu_page(
        $parent_slug,
        __( 'Agrupaciones', 'wp-song-study' ),
        __( 'Agrupaciones', 'wp-song-study' ),
        $capability,
        'wpss-agrupaciones',
        'wpss_render_groups_page'
    );

    $project_rehearsals_hook = add_submenu_page(
        $parent_slug,
        __( 'Planificador de ensayos', 'wp-song-study' ),
        __( 'Planificador de ensayos', 'wp-song-study' ),
        $capability,
        'wpss-ensayos-proyecto',
        'wpss_render_project_rehearsals_page'
    );

    add_submenu_page(
        $parent_slug,
        __( 'Notificaciones de ensayos', 'wp-song-study' ),
        __( 'Notificaciones de ensayos', 'wp-song-study' ),
        'manage_options',
        'wpss-rehearsal-notifications',
        'wpss_render_rehearsal_notification_settings_page'
    );

    $drive_hook = add_submenu_page(
        $parent_slug,
        __( 'Mi Drive', 'wp-song-study' ),
        __( 'Mi Drive', 'wp-song-study' ),
        $capability,
        'wpss-mi-drive',
        'wpss_render_drive_page'
    );

    $import_export_hook = add_submenu_page(
        $parent_slug,
        __( 'Importar / Exportar', 'wp-song-study' ),
        __( 'Importar / Exportar', 'wp-song-study' ),
        $capability,
        'wpss-import-export',
        'wpss_render_import_export_page'
    );

    add_submenu_page(
        $parent_slug,
        __( 'Drive Global', 'wp-song-study' ),
        __( 'Drive Global', 'wp-song-study' ),
        'manage_options',
        'wpss-drive-global-settings',
        'wpss_render_google_drive_global_settings_page'
    );

    $wpss_admin_page_hooks = [ $dashboard_hook, $new_song_hook, $chords_hook, $groups_hook, $project_rehearsals_hook, $drive_hook, $import_export_hook ];

    add_submenu_page(
        $parent_slug,
        __( 'Ajustes MIDI', 'wp-song-study' ),
        __( 'Ajustes MIDI', 'wp-song-study' ),
        'manage_options',
        'wpss-settings',
        'wpss_render_settings_page'
    );
}

/**
 * Renderiza el contenedor del SPA para la biblioteca.
 */
function wpss_render_dashboard_page() {
    echo '<div id="wpss-cancion-app" class="wpss-cancion-app" data-view="dashboard"></div>';
}

/**
 * Renderiza el contenedor del SPA para crear una nueva canción.
 */
function wpss_render_new_song_page() {
    echo '<div id="wpss-cancion-app" class="wpss-cancion-app" data-view="new"></div>';
}

/**
 * Renderiza el contenedor del SPA para administrar acordes.
 */
function wpss_render_chords_page() {
    echo '<div id="wpss-cancion-app" class="wpss-cancion-app" data-view="chords"></div>';
}

/**
 * Renderiza el contenedor del SPA para administrar agrupaciones musicales.
 */
function wpss_render_groups_page() {
    echo '<div id="wpss-cancion-app" class="wpss-cancion-app" data-view="groups"></div>';
}

/**
 * Renderiza el contenedor del SPA para administrar ensayos por proyecto.
 */
function wpss_render_project_rehearsals_page() {
    echo '<div id="wpss-cancion-app" class="wpss-cancion-app" data-view="project-rehearsals"></div>';
}

/**
 * Renderiza el contenedor del SPA para la conexión personal a Google Drive.
 */
function wpss_render_drive_page() {
    echo '<div id="wpss-cancion-app" class="wpss-cancion-app" data-view="drive"></div>';
}

/**
 * Renderiza el contenedor del SPA para importar y exportar canciones.
 */
function wpss_render_import_export_page() {
    echo '<div id="wpss-cancion-app" class="wpss-cancion-app" data-view="import-export"></div>';
}

/**
 * Registra las opciones del plugin.
 */
function wpss_register_settings() {
    register_setting(
        'wpss_settings',
        'wpss_midi_range_presets',
        [
            'type'              => 'array',
            'sanitize_callback' => 'wpss_sanitize_midi_range_presets',
            'default'           => wpss_get_default_midi_range_presets(),
        ]
    );

    register_setting(
        'wpss_settings',
        'wpss_midi_range_default',
        [
            'type'              => 'string',
            'sanitize_callback' => 'wpss_sanitize_midi_range_default',
            'default'           => 'medios',
        ]
    );

    register_setting(
        'wpss_rehearsal_notification_settings_group',
        'wpss_rehearsal_notification_settings',
        [
            'type'              => 'array',
            'sanitize_callback' => 'wpssb_sanitize_rehearsal_notification_settings',
            'default'           => function_exists( 'wpssb_get_default_rehearsal_notification_settings' )
                ? wpssb_get_default_rehearsal_notification_settings()
                : [],
        ]
    );
}

/**
 * Renderiza la página de ajustes MIDI.
 */
function wpss_render_settings_page() {
    $presets = wpss_sanitize_midi_range_presets( get_option( 'wpss_midi_range_presets', [] ) );
    $default = wpss_get_midi_range_default();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Ajustes MIDI', 'wp-song-study' ) . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'wpss_settings' );

    echo '<table class="form-table" role="presentation">';
    foreach ( $presets as $preset ) {
        $id = $preset['id'];
        $label = $preset['label'];
        $min = (int) $preset['min'];
        $max = (int) $preset['max'];

        echo '<tr>';
        echo '<th scope="row">' . esc_html( $label ) . '</th>';
        echo '<td>';
        echo '<label style="margin-right:16px;">';
        echo esc_html__( 'Etiqueta', 'wp-song-study' ) . ' ';
        echo '<input type="text" name="wpss_midi_range_presets[' . esc_attr( $id ) . '][label]" value="' . esc_attr( $label ) . '" class="regular-text" />';
        echo '</label>';
        echo '<label style="margin-right:16px;">';
        echo esc_html__( 'Nota mínima (0-127)', 'wp-song-study' ) . ' ';
        echo '<input type="number" min="0" max="127" name="wpss_midi_range_presets[' . esc_attr( $id ) . '][min]" value="' . esc_attr( $min ) . '" />';
        echo '</label>';
        echo '<label>';
        echo esc_html__( 'Nota máxima (0-127)', 'wp-song-study' ) . ' ';
        echo '<input type="number" min="0" max="127" name="wpss_midi_range_presets[' . esc_attr( $id ) . '][max]" value="' . esc_attr( $max ) . '" />';
        echo '</label>';
        echo '</td>';
        echo '</tr>';
    }

    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Preset por defecto', 'wp-song-study' ) . '</th>';
    echo '<td><select name="wpss_midi_range_default">';
    foreach ( $presets as $preset ) {
        $id = $preset['id'];
        $selected = selected( $default, $id, false );
        echo '<option value="' . esc_attr( $id ) . '"' . $selected . '>' . esc_html( $preset['label'] ) . '</option>';
    }
    echo '</select></td>';
    echo '</tr>';

    echo '</table>';

    submit_button();
    echo '</form>';
    echo '</div>';
}

/**
 * Renderiza la configuración de correo para notificaciones de ensayos.
 */
function wpss_render_rehearsal_notification_settings_page() {
    $settings = function_exists( 'wpssb_get_rehearsal_notification_settings' )
        ? wpssb_get_rehearsal_notification_settings()
        : [
            'enabled'    => 1,
            'from_name'  => '',
            'from_email' => '',
            'reply_to'   => '',
        ];

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Notificaciones de ensayos', 'wp-song-study' ) . '</h1>';
    echo '<p>' . esc_html__( 'Configura aquí el remitente usado por el Planificador de ensayos cuando avisa al grupo sobre propuestas, votos y confirmaciones.', 'wp-song-study' ) . '</p>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'wpss_rehearsal_notification_settings_group' );

    echo '<table class="form-table" role="presentation">';
    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Activar avisos por correo', 'wp-song-study' ) . '</th>';
    echo '<td>';
    echo '<label>';
    echo '<input type="checkbox" name="wpss_rehearsal_notification_settings[enabled]" value="1" ' . checked( ! empty( $settings['enabled'] ), true, false ) . ' />';
    echo ' ' . esc_html__( 'Enviar notificaciones del planificador al resto del proyecto.', 'wp-song-study' );
    echo '</label>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Nombre del remitente', 'wp-song-study' ) . '</th>';
    echo '<td>';
    echo '<input type="text" class="regular-text" name="wpss_rehearsal_notification_settings[from_name]" value="' . esc_attr( (string) ( $settings['from_name'] ?? '' ) ) . '" />';
    echo '<p class="description">' . esc_html__( 'Ej. Harmony Atlas · Planificador de ensayos.', 'wp-song-study' ) . '</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Correo remitente', 'wp-song-study' ) . '</th>';
    echo '<td>';
    echo '<input type="email" class="regular-text" name="wpss_rehearsal_notification_settings[from_email]" value="' . esc_attr( (string) ( $settings['from_email'] ?? '' ) ) . '" />';
    echo '<p class="description">' . esc_html__( 'Usa aquí una cuenta de tu dominio, por ejemplo ensayos@tudominio.com.', 'wp-song-study' ) . '</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Reply-to', 'wp-song-study' ) . '</th>';
    echo '<td>';
    echo '<input type="email" class="regular-text" name="wpss_rehearsal_notification_settings[reply_to]" value="' . esc_attr( (string) ( $settings['reply_to'] ?? '' ) ) . '" />';
    echo '<p class="description">' . esc_html__( 'Opcional. Si alguien responde el correo, llegará a esta dirección.', 'wp-song-study' ) . '</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';

    echo '<p class="description">' . esc_html__( 'Importante: para que tu dominio entregue bien estos correos, también necesitas configurar SMTP y los registros SPF/DKIM/DMARC fuera de WordPress.', 'wp-song-study' ) . '</p>';

    submit_button( __( 'Guardar notificaciones', 'wp-song-study' ) );
    echo '</form>';
    echo '</div>';
}

/**
 * Renderiza la configuración global de Google Drive.
 *
 * @return void
 */
function wpss_render_google_drive_global_settings_page() {
    $google_client_id = function_exists( 'wpss_get_google_drive_client_id' ) ? wpss_get_google_drive_client_id() : '';
	$google_client_secret = function_exists( 'wpss_get_google_drive_client_secret' ) ? wpss_get_google_drive_client_secret() : '';
	$google_redirect_uri = function_exists( 'wpss_get_google_drive_redirect_uri' ) ? wpss_get_google_drive_redirect_uri() : '';
	$omr_provider = function_exists( 'wpss_get_omr_provider' ) ? wpss_get_omr_provider() : 'local_service';
	$omr_endpoint_url = function_exists( 'wpss_get_omr_endpoint_url' ) ? wpss_get_omr_endpoint_url() : '';
	$omr_api_key = function_exists( 'wpss_get_omr_api_key' ) ? wpss_get_omr_api_key() : '';
	$omr_timeout = function_exists( 'wpss_get_omr_timeout' ) ? wpss_get_omr_timeout() : 45;
	$omr_external_api_url = function_exists( 'wpss_get_omr_external_api_url' ) ? wpss_get_omr_external_api_url() : '';
	$omr_external_api_key = function_exists( 'wpss_get_omr_external_api_key' ) ? wpss_get_omr_external_api_key() : '';
	$omr_external_api_timeout = function_exists( 'wpss_get_omr_external_api_timeout' ) ? wpss_get_omr_external_api_timeout() : 45;
    $site_name = get_bloginfo( 'name' );
    $home_url = home_url( '/' );
    $privacy_policy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
    $home_parts = wp_parse_url( $home_url );
    $authorized_domain = is_array( $home_parts ) && ! empty( $home_parts['host'] ) ? preg_replace( '/^www\./', '', (string) $home_parts['host'] ) : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Credenciales globales de Google Drive', 'wp-song-study' ) . '</h1>';
    echo '<p>' . esc_html__( 'Estas credenciales funcionan como respaldo global. Si un usuario configura su propio Client ID y Client Secret en su perfil, esas credenciales personales tienen prioridad. Drive y Calendar pueden reutilizar este mismo cliente OAuth, pero cada integración mantiene su propio token y sus propios scopes.', 'wp-song-study' ) . '</p>';
    if ( isset( $_GET['calendar_cleanup'] ) && 'done' === sanitize_key( wp_unslash( $_GET['calendar_cleanup'] ) ) ) {
        $cleanup_found   = isset( $_GET['calendar_cleanup_found'] ) ? absint( wp_unslash( $_GET['calendar_cleanup_found'] ) ) : 0;
        $cleanup_deleted = isset( $_GET['calendar_cleanup_deleted'] ) ? absint( wp_unslash( $_GET['calendar_cleanup_deleted'] ) ) : 0;
        $cleanup_failed  = isset( $_GET['calendar_cleanup_failed'] ) ? absint( wp_unslash( $_GET['calendar_cleanup_failed'] ) ) : 0;
        $notice_class    = $cleanup_failed > 0 ? 'notice-warning' : 'notice-success';

        echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>';
        echo esc_html(
            sprintf(
                __( 'Limpieza de Calendar terminada: %1$d detectados, %2$d eliminados/limpiados, %3$d con error.', 'wp-song-study' ),
                $cleanup_found,
                $cleanup_deleted,
                $cleanup_failed
            )
        );
        echo '</p></div>';
    }
    echo '<div class="notice notice-info inline">';
    echo '<p><strong>' . esc_html__( 'Pantalla de consentimiento OAuth en Google Cloud', 'wp-song-study' ) . '</strong></p>';
    echo '<p>' . esc_html__( 'El nombre público de la app lo valida Google en Cloud Console, no WordPress. Para evitar rechazos, usa la marca real del sitio y no el nombre técnico del plugin.', 'wp-song-study' ) . '</p>';
    echo '<ul style="list-style:disc;margin-left:1.5em;">';
    echo '<li>' . esc_html__( 'Nombre recomendado de la app:', 'wp-song-study' ) . ' <code>' . esc_html( '' !== $site_name ? $site_name : __( 'Nombre del sitio', 'wp-song-study' ) ) . '</code></li>';
    echo '<li>' . esc_html__( 'Página principal de la app:', 'wp-song-study' ) . ' <code>' . esc_html( $home_url ) . '</code></li>';
    if ( '' !== $privacy_policy_url ) {
        echo '<li>' . esc_html__( 'Política de privacidad:', 'wp-song-study' ) . ' <code>' . esc_html( $privacy_policy_url ) . '</code></li>';
    }
    if ( '' !== $authorized_domain ) {
        echo '<li>' . esc_html__( 'Dominio autorizado:', 'wp-song-study' ) . ' <code>' . esc_html( $authorized_domain ) . '</code></li>';
    }
    echo '</ul>';
    echo '</div>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'wpss_settings' );
    echo '<table class="form-table" role="presentation">';

    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Google Drive Client ID', 'wp-song-study' ) . '</th>';
    echo '<td>';
    echo '<input type="text" name="wpss_google_drive_client_id" value="' . esc_attr( $google_client_id ) . '" class="regular-text code" />';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Google Drive Client Secret', 'wp-song-study' ) . '</th>';
    echo '<td>';
    echo '<input type="password" name="wpss_google_drive_client_secret" value="' . esc_attr( $google_client_secret ) . '" class="regular-text code" autocomplete="new-password" />';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Redirect URI', 'wp-song-study' ) . '</th>';
    echo '<td>';
    echo '<code>' . esc_html( $google_redirect_uri ) . '</code>';
    echo '<p class="description">' . esc_html__( 'Registra exactamente esta URL en tu proyecto OAuth de Google.', 'wp-song-study' ) . '</p>';
    echo '</td>';
    echo '</tr>';

    echo '</table>';

	echo '<h2>' . esc_html__( 'Interpretación de partituras (OMR)', 'wp-song-study' ) . '</h2>';
	echo '<p>' . esc_html__( 'Configura el proveedor que recibirá la imagen de la partitura y devolverá MusicXML. La conversión a midi_clips se mantiene dentro de HarmonyAtlas.', 'wp-song-study' ) . '</p>';
	echo '<table class="form-table" role="presentation">';

	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'Proveedor OMR', 'wp-song-study' ) . '</th>';
	echo '<td>';
	echo '<select name="wpss_omr_provider">';
	echo '<option value="local_service"' . selected( $omr_provider, 'local_service', false ) . '>' . esc_html__( 'local_service', 'wp-song-study' ) . '</option>';
	echo '<option value="external_api"' . selected( $omr_provider, 'external_api', false ) . '>' . esc_html__( 'external_api', 'wp-song-study' ) . '</option>';
	echo '</select>';
	echo '<p class="description">' . esc_html__( 'local_service usa el microservicio incluido en services/omr-service. external_api permite conectar otro proveedor HTTP con el mismo contrato JSON.', 'wp-song-study' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'Local service URL', 'wp-song-study' ) . '</th>';
	echo '<td>';
	echo '<input type="url" name="wpss_omr_endpoint_url" value="' . esc_attr( $omr_endpoint_url ) . '" class="regular-text code" placeholder="http://127.0.0.1:8080/omr" />';
	echo '<p class="description">' . esc_html__( 'Mantiene compatibilidad con el endpoint actual POST /omr.', 'wp-song-study' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'Local service API key', 'wp-song-study' ) . '</th>';
	echo '<td>';
	echo '<input type="password" name="wpss_omr_api_key" value="' . esc_attr( $omr_api_key ) . '" class="regular-text code" autocomplete="new-password" />';
	echo '<p class="description">' . esc_html__( 'Se enviará como Authorization: Bearer y X-WPSS-OMR-Key.', 'wp-song-study' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'Local service timeout', 'wp-song-study' ) . '</th>';
	echo '<td>';
	echo '<input type="number" name="wpss_omr_timeout" value="' . esc_attr( $omr_timeout ) . '" class="small-text" min="5" max="300" step="1" /> ';
	echo esc_html__( 'segundos', 'wp-song-study' );
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'External API URL', 'wp-song-study' ) . '</th>';
	echo '<td>';
	echo '<input type="url" name="wpss_omr_external_api_url" value="' . esc_attr( $omr_external_api_url ) . '" class="regular-text code" placeholder="https://proveedor.example/omr" />';
	echo '<p class="description">' . esc_html__( 'Debe aceptar el mismo payload y devolver ok, engine, musicxml, warnings y error.', 'wp-song-study' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'External API key', 'wp-song-study' ) . '</th>';
	echo '<td>';
	echo '<input type="password" name="wpss_omr_external_api_key" value="' . esc_attr( $omr_external_api_key ) . '" class="regular-text code" autocomplete="new-password" />';
	echo '<p class="description">' . esc_html__( 'Se enviará como Authorization: Bearer y X-WPSS-OMR-Key.', 'wp-song-study' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'External API timeout', 'wp-song-study' ) . '</th>';
	echo '<td>';
	echo '<input type="number" name="wpss_omr_external_api_timeout" value="' . esc_attr( $omr_external_api_timeout ) . '" class="small-text" min="5" max="300" step="1" /> ';
	echo esc_html__( 'segundos', 'wp-song-study' );
	echo '</td>';
	echo '</tr>';

	echo '</table>';

    submit_button();
    echo '</form>';

    if ( function_exists( 'wpssb_find_project_rehearsal_unconfirmed_calendar_events' ) ) {
        $orphan_events = wpssb_find_project_rehearsal_unconfirmed_calendar_events();

        echo '<hr />';
        echo '<h2>' . esc_html__( 'Limpieza de eventos de ensayos no confirmados', 'wp-song-study' ) . '</h2>';
        echo '<p>' . esc_html__( 'Esta utilidad busca eventos de Google Calendar cuyo ID quedó guardado en WordPress, pero cuya sesión no está confirmada ni completada. Sirve para limpiar eventos creados por errores previos del flujo de consenso.', 'wp-song-study' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'Límite de seguridad: si el evento existe en Google pero WordPress ya no conserva su event_id, esta herramienta no puede localizarlo sin hacer una búsqueda amplia en Calendar.', 'wp-song-study' ) . '</p>';

        if ( empty( $orphan_events ) ) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__( 'No se detectaron eventos huérfanos guardados en sesiones no confirmadas.', 'wp-song-study' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html(
                sprintf(
                    __( 'Se detectaron %d eventos candidatos para borrar de Google Calendar.', 'wp-song-study' ),
                    count( $orphan_events )
                )
            );
            echo '</p></div>';
            echo '<table class="widefat striped" style="max-width:1100px;">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Proyecto', 'wp-song-study' ) . '</th>';
            echo '<th>' . esc_html__( 'Sesión', 'wp-song-study' ) . '</th>';
            echo '<th>' . esc_html__( 'Estado', 'wp-song-study' ) . '</th>';
            echo '<th>' . esc_html__( 'Evento', 'wp-song-study' ) . '</th>';
            echo '<th>' . esc_html__( 'Cuenta', 'wp-song-study' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( array_slice( $orphan_events, 0, 50 ) as $event ) {
                $event_link = ! empty( $event['html_link'] )
                    ? '<a href="' . esc_url( (string) $event['html_link'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $event['event_id'] ) . '</a>'
                    : '<code>' . esc_html( (string) $event['event_id'] ) . '</code>';

                echo '<tr>';
                echo '<td><strong>' . esc_html( (string) ( $event['project_title'] ?? '' ) ) . '</strong><br/><code>#' . esc_html( (string) ( $event['project_id'] ?? '' ) ) . '</code></td>';
                echo '<td>' . esc_html( '' !== (string) ( $event['session_focus'] ?? '' ) ? (string) $event['session_focus'] : __( 'Sesión sin título', 'wp-song-study' ) ) . '<br/><small>' . esc_html( (string) ( $event['session_schedule'] ?? '' ) ) . '</small></td>';
                echo '<td><code>' . esc_html( (string) ( $event['session_status'] ?? '' ) ) . '</code></td>';
                echo '<td>' . $event_link . '</td>';
                echo '<td>' . esc_html( '' !== (string) ( $event['synced_by_label'] ?? '' ) ? (string) $event['synced_by_label'] : __( 'Sin cuenta registrada', 'wp-song-study' ) ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            if ( count( $orphan_events ) > 50 ) {
                echo '<p class="description">' . esc_html__( 'Se muestran solo los primeros 50 candidatos; el botón procesa todos los detectados.', 'wp-song-study' ) . '</p>';
            }

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1rem;">';
            echo '<input type="hidden" name="action" value="wpssb_cleanup_rehearsal_calendar_orphans" />';
            echo '<input type="hidden" name="project_id" value="0" />';
            wp_nonce_field( 'wpssb_cleanup_rehearsal_calendar_orphans', 'wpssb_calendar_cleanup_nonce' );
            submit_button( __( 'Borrar eventos huérfanos detectados', 'wp-song-study' ), 'delete', 'submit', false );
            echo '</form>';
        }
    }
    echo '</div>';
}

/**
 * Encola scripts y estilos necesarios únicamente en las páginas del cancionario.
 *
 * @param string $hook Hook actual.
 */
function wpss_enqueue_admin_assets( $hook ) {
    global $wpss_admin_page_hooks;

    if ( empty( $wpss_admin_page_hooks ) || ! in_array( $hook, $wpss_admin_page_hooks, true ) ) {
        return;
    }

    $localized_data = wpss_get_admin_localized_data();

    $dev_server = defined( 'WPSS_REACT_DEV_SERVER' ) ? (string) WPSS_REACT_DEV_SERVER : '';
    $react_handle = wpss_enqueue_react_assets( $dev_server );
    if ( $react_handle ) {
        $localized_data['useReactNative'] = true;
        wp_localize_script( $react_handle, 'WPSS', $localized_data );
    }
}

if ( ! function_exists( 'wpss_get_asset_version_fallback' ) ) {
    /**
     * Obtiene una versión de respaldo para assets cuando no existe el archivo físico.
     *
     * @return string
     */
    function wpss_get_asset_version_fallback() {
        if ( defined( 'WPSSB_VERSION' ) ) {
            return WPSSB_VERSION;
        }

        return defined( 'WPSS_VERSION' ) ? WPSS_VERSION : '1.0.0';
    }
}

/**
 * Devuelve la informacion localizable para la SPA de administracion.
 *
 * @return array
 */
function wpss_get_admin_localized_data() {
    $manage_cap = defined( 'WPSS_CAP_MANAGE' ) ? WPSS_CAP_MANAGE : 'edit_posts';
    $tonicas = [
        'C',
        'C#',
        'Db',
        'D',
        'D#',
        'Eb',
        'E',
        'F',
        'F#',
        'Gb',
        'G',
        'G#',
        'Ab',
        'A',
        'A#',
        'Bb',
        'B',
    ];

    $campos_library = array_values( wpss_get_campos_armonicos_library() );
    $campos_armonicos = array_values(
        array_filter(
            array_merge(
                ...array_map(
                    static function( $campo ) {
                        $labels = [];
                        if ( isset( $campo['nombre'] ) && '' !== $campo['nombre'] ) {
                            $labels[] = $campo['nombre'];
                        }
                        if ( isset( $campo['aliases'] ) && is_array( $campo['aliases'] ) ) {
                            foreach ( $campo['aliases'] as $alias ) {
                                if ( '' !== $alias ) {
                                    $labels[] = $alias;
                                }
                            }
                        }
                        return $labels;
                    },
                    array_filter(
                        $campos_library,
                        static function( $campo ) {
                            return ! empty( $campo['activo'] );
                        }
                    )
                )
            )
        )
    );
    $acordes_library = array_values( wpss_get_acordes_library() );
    $acordes_config = wpss_get_acordes_config();

    return [
        'restUrl'      => esc_url_raw( rest_url( 'wpss/v1/' ) ),
        'wpRestNonce'  => wp_create_nonce( 'wp_rest' ),
        'wpssNonce'    => wp_create_nonce( 'wpss' ),
        'isAdmin'      => current_user_can( 'manage_options' ),
        'canManage'    => function_exists( 'wpss_user_can_manage_songbook' ) ? wpss_user_can_manage_songbook() : current_user_can( $manage_cap ),
        'canRead'      => function_exists( 'wpss_user_can_read_songbook' ) ? wpss_user_can_read_songbook() : current_user_can( $manage_cap ),
        'currentUserId' => get_current_user_id(),
        'googleDriveStatus' => function_exists( 'wpss_get_google_drive_status_payload' )
            ? wpss_get_google_drive_status_payload( get_current_user_id() )
            : [
                'configured' => false,
                'connected'  => false,
            ],
        'adminUrls'    => [
            'drivePage'        => admin_url( 'admin.php?page=wpss-mi-drive' ),
            'groupsPage'       => admin_url( 'admin.php?page=wpss-agrupaciones' ),
            'projectRehearsalsPage' => admin_url( 'admin.php?page=wpss-ensayos-proyecto' ),
            'importExportPage' => admin_url( 'admin.php?page=wpss-import-export' ),
            'profilePage'      => admin_url( 'profile.php' ),
        ],
        'adminPostUrl' => admin_url( 'admin-post.php' ),
        'songExportNonce' => wp_create_nonce( 'wpss_song_export' ),
        'midiRanges'   => wpss_get_midi_range_presets(),
        'midiRangeDefault' => wpss_get_midi_range_default(),
        'tonicas'      => $tonicas,
        'camposArmonicos' => $campos_library,
        'camposArmonicosNombres' => $campos_armonicos,
        'chordsLibrary' => $acordes_library,
        'chordsConfig' => $acordes_config,
        'strings'      => [
            'filtersTitle'     => __( 'Canciones registradas', 'wp-song-study' ),
            'newSong'          => __( 'Nueva canción', 'wp-song-study' ),
            'saveSong'         => __( 'Guardar canción', 'wp-song-study' ),
            'saving'           => __( 'Guardando…', 'wp-song-study' ),
            'saved'            => __( 'Cambios guardados', 'wp-song-study' ),
            'error'            => __( 'Ocurrió un error al guardar.', 'wp-song-study' ),
            'listEmpty'        => __( 'No hay canciones registradas con los filtros actuales.', 'wp-song-study' ),
            'versesEmpty'      => __( 'Aún no hay versos.', 'wp-song-study' ),
            'loansEmpty'       => __( 'Sin préstamos tonales.', 'wp-song-study' ),
            'modsEmpty'        => __( 'Sin modulaciones.', 'wp-song-study' ),
            'loadingSong'      => __( 'Cargando canción…', 'wp-song-study' ),
            'songLoaded'       => __( 'Canción cargada.', 'wp-song-study' ),
            'loadSongError'    => __( 'No fue posible cargar la canción seleccionada.', 'wp-song-study' ),
            'loadSongsError'   => __( 'No fue posible cargar la lista de canciones.', 'wp-song-study' ),
            'titleRequired'    => __( 'El título es obligatorio.', 'wp-song-study' ),
            'tonicaRequired'   => __( 'La tónica es obligatoria.', 'wp-song-study' ),
            'modeRequired'     => __( 'El campo armónico es obligatorio.', 'wp-song-study' ),
            'eventoDatosRequeridos' => __( 'Completa la tónica o el campo armónico del evento antes de guardar.', 'wp-song-study' ),
            'eventoSegmentoInvalido' => __( 'Selecciona un segmento válido para el evento armónico.', 'wp-song-study' ),
            'segmentAdd'       => __( 'Añadir segmento', 'wp-song-study' ),
            'segmentDuplicate' => __( 'Duplicar segmento', 'wp-song-study' ),
            'segmentSplit'     => __( 'Dividir en el cursor', 'wp-song-study' ),
            'segmentEventSelect' => __( 'Anclar evento aquí', 'wp-song-study' ),
            'segmentEventSelected' => __( 'Evento anclado (clic para quitar)', 'wp-song-study' ),
            'segmentEventLabel' => __( 'Segmento', 'wp-song-study' ),
            'segmentEventHint' => __( 'Selecciona un segmento para resaltar el evento.', 'wp-song-study' ),
            'libraryView'      => __( 'Campos armónicos', 'wp-song-study' ),
            'dashboardView'    => __( 'Biblioteca', 'wp-song-study' ),
            'readingView'      => __( 'Vista de lectura', 'wp-song-study' ),
            'editorView'       => __( 'Editor', 'wp-song-study' ),
            'copyAsText'       => __( 'Copiar como texto', 'wp-song-study' ),
            'collectionsTab'   => __( 'Colecciones', 'wp-song-study' ),
            'collectionsLoading' => __( 'Cargando colecciones…', 'wp-song-study' ),
            'collectionsAll'   => __( 'Todas', 'wp-song-study' ),
            'collectionsFilter' => __( 'Colección', 'wp-song-study' ),
            'collectionsView'  => __( 'Ver colección', 'wp-song-study' ),
            'collectionsLabel' => __( 'Colecciones', 'wp-song-study' ),
            'collectionsEmpty' => __( 'Aún no hay colecciones disponibles.', 'wp-song-study' ),
            'collectionsListEmpty' => __( 'Aún no hay colecciones.', 'wp-song-study' ),
            'collectionsCatalogLoading' => __( 'Cargando canciones…', 'wp-song-study' ),
            'collectionsSidebar' => __( 'Colecciones', 'wp-song-study' ),
            'collectionsLoadError' => __( 'No fue posible obtener las colecciones.', 'wp-song-study' ),
            'collectionsCatalogError' => __( 'No fue posible cargar el catálogo de canciones.', 'wp-song-study' ),
            'collectionNew'    => __( 'Nueva colección', 'wp-song-study' ),
            'collectionRefresh' => __( 'Actualizar lista', 'wp-song-study' ),
            'collectionName'   => __( 'Nombre', 'wp-song-study' ),
            'collectionDescription' => __( 'Descripción', 'wp-song-study' ),
            'collectionSongs'  => __( 'Canciones', 'wp-song-study' ),
            'collectionSelectSong' => __( 'Selecciona una canción', 'wp-song-study' ),
            'collectionAddSong' => __( 'Añadir a la colección', 'wp-song-study' ),
            'collectionSave'   => __( 'Guardar colección', 'wp-song-study' ),
            'collectionDelete' => __( 'Eliminar colección', 'wp-song-study' ),
            'collectionLoadError' => __( 'No fue posible cargar la colección seleccionada.', 'wp-song-study' ),
            'collectionNameRequired' => __( 'El nombre de la colección es obligatorio.', 'wp-song-study' ),
            'collectionSaved'  => __( 'Colección guardada.', 'wp-song-study' ),
            'collectionSaveError' => __( 'No fue posible guardar la colección.', 'wp-song-study' ),
            'collectionDeleteConfirm' => __( '¿Eliminar la colección seleccionada?', 'wp-song-study' ),
            'collectionDeleted' => __( 'Colección eliminada.', 'wp-song-study' ),
            'collectionDeleteError' => __( 'No fue posible eliminar la colección.', 'wp-song-study' ),
            'collectionNoSongs' => __( 'Añade canciones a la colección.', 'wp-song-study' ),
            'collectionEmpty'  => __( 'La colección no tiene canciones asignadas.', 'wp-song-study' ),
            'collectionCurrent' => __( 'Colección', 'wp-song-study' ),
            'camposSaved'      => __( 'Campos armónicos actualizados.', 'wp-song-study' ),
            'camposError'      => __( 'No fue posible guardar la biblioteca de campos armónicos.', 'wp-song-study' ),
            'camposEmpty'      => __( 'Aún no hay campos armónicos registrados.', 'wp-song-study' ),
            'camposAdd'        => __( 'Añadir modo', 'wp-song-study' ),
            'camposRemove'     => __( 'Eliminar', 'wp-song-study' ),
            'camposActive'     => __( 'Activo', 'wp-song-study' ),
            'readingEmpty'     => __( 'Agrega versos y segmentos para visualizar la canción.', 'wp-song-study' ),
            'readingModeInline' => __( 'Acordes inline', 'wp-song-study' ),
            'readingModeStacked' => __( 'Acordes arriba', 'wp-song-study' ),
            'readingPrev'      => __( 'Anterior', 'wp-song-study' ),
            'readingProgress'  => __( 'Canción', 'wp-song-study' ),
            'readingNext'      => __( 'Siguiente', 'wp-song-study' ),
            'readingExit'      => __( 'Salir', 'wp-song-study' ),
            'segmentRequired'  => __( 'Cada verso necesita al menos un segmento con texto, acorde o MIDI.', 'wp-song-study' ),
            'segmentConsecutive' => __( 'No se permiten segmentos consecutivos sin texto.', 'wp-song-study' ),
            'camposSlugRequired' => __( 'Cada modo necesita un identificador (slug).', 'wp-song-study' ),
            'sectionsEmpty'    => __( 'Sin secciones registradas.', 'wp-song-study' ),
            'structureTitle'   => __( 'Estructura', 'wp-song-study' ),
            'structureToggleLabel' => __( 'Usar estructura personalizada', 'wp-song-study' ),
            'structureAddCall' => __( 'Añadir llamada', 'wp-song-study' ),
            'structureDuplicateCall' => __( 'Duplicar', 'wp-song-study' ),
            'structureRemoveCall' => __( 'Eliminar', 'wp-song-study' ),
            'structureMoveUp'  => __( 'Subir', 'wp-song-study' ),
            'structureMoveDown' => __( 'Bajar', 'wp-song-study' ),
            'structureEmpty'   => __( 'Aún no hay llamadas registradas.', 'wp-song-study' ),
            'structureVariantLabel' => __( 'Variante', 'wp-song-study' ),
            'structureNotesLabel' => __( 'Notas', 'wp-song-study' ),
            'structureSelectLabel' => __( 'Sección', 'wp-song-study' ),
            'structureReset'   => __( 'Restablecer al orden por secciones', 'wp-song-study' ),
            'structurePreviewLabel' => __( 'Resumen', 'wp-song-study' ),
            'readingFollowStructure' => __( 'Seguir estructura', 'wp-song-study' ),
            'readingFollowSections' => __( 'Ordenar por secciones', 'wp-song-study' ),
            'structureNotesPrefix' => __( 'Notas', 'wp-song-study' ),
            'chordsView'    => __( 'Acordes', 'wp-song-study' ),
            'chordsSaved'   => __( 'Acordes actualizados.', 'wp-song-study' ),
            'chordsError'   => __( 'No fue posible guardar la biblioteca de acordes.', 'wp-song-study' ),
            'chordsEmpty'   => __( 'Aún no hay acordes registrados.', 'wp-song-study' ),
            'chordsAdd'     => __( 'Añadir acorde', 'wp-song-study' ),
            'chordsRemove'  => __( 'Eliminar', 'wp-song-study' ),
            'importExportView' => __( 'Importar / Exportar', 'wp-song-study' ),
        ],
    ];
}

/**
 * Encola assets compilados con Vite o desde un dev server.
 *
 * @param string $dev_server URL del dev server de Vite.
 * @return string|false Handle del script principal o false si falla.
 */
function wpss_enqueue_react_assets( $dev_server = '' ) {
    $dev_server = trim( $dev_server );
    $plugin_path = defined( 'WPSSB_PATH' ) ? WPSSB_PATH : ( defined( 'WPSS_PATH' ) ? WPSS_PATH : '' );
    $plugin_url  = defined( 'WPSSB_URL' ) ? WPSSB_URL : ( defined( 'WPSS_URL' ) ? WPSS_URL : '' );

    if ( '' !== $dev_server ) {
        $dev_server = untrailingslashit( $dev_server );

        wp_enqueue_script( 'wpss-react-vite', $dev_server . '/@vite/client', [], null, true );
        wp_script_add_data( 'wpss-react-vite', 'type', 'module' );

        wp_enqueue_script( 'wpss-react-app', $dev_server . '/src/main.jsx', [], null, true );
        wp_script_add_data( 'wpss-react-app', 'type', 'module' );

        return 'wpss-react-app';
    }

    if ( '' === $plugin_path || '' === $plugin_url ) {
        return false;
    }

    $manifest_path = $plugin_path . 'assets/admin-build/manifest.json';
    if ( ! file_exists( $manifest_path ) ) {
        $manifest_path = $plugin_path . 'assets/admin-build/.vite/manifest.json';
    }
    if ( ! file_exists( $manifest_path ) ) {
        return false;
    }

    $manifest = json_decode( file_get_contents( $manifest_path ), true );
    if ( empty( $manifest ) || ! is_array( $manifest ) ) {
        return false;
    }

    $entry = isset( $manifest['index.html'] ) ? $manifest['index.html'] : null;
    if ( empty( $entry['file'] ) ) {
        return false;
    }

    $base_url = $plugin_url . 'assets/admin-build/';
    $version  = filemtime( $manifest_path );

    wp_enqueue_script( 'wpss-react-app', $base_url . $entry['file'], [], $version, true );
    wp_script_add_data( 'wpss-react-app', 'type', 'module' );

    if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
        foreach ( $entry['css'] as $index => $css_file ) {
            $handle = sprintf( 'wpss-react-style-%d', $index );
            wp_enqueue_style( $handle, $base_url . $css_file, [], $version );
        }
    }

    return 'wpss-react-app';
}
