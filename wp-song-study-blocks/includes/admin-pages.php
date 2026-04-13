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

    $wpss_admin_page_hooks = [ $dashboard_hook, $new_song_hook, $chords_hook, $groups_hook, $drive_hook, $import_export_hook ];

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
 * Renderiza la configuración global de Google Drive.
 *
 * @return void
 */
function wpss_render_google_drive_global_settings_page() {
    $google_client_id = function_exists( 'wpss_get_google_drive_client_id' ) ? wpss_get_google_drive_client_id() : '';
    $google_client_secret = function_exists( 'wpss_get_google_drive_client_secret' ) ? wpss_get_google_drive_client_secret() : '';
    $google_redirect_uri = function_exists( 'wpss_get_google_drive_redirect_uri' ) ? wpss_get_google_drive_redirect_uri() : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Credenciales globales de Google Drive', 'wp-song-study' ) . '</h1>';
    echo '<p>' . esc_html__( 'Estas credenciales funcionan como respaldo global. Si un usuario configura su propio Client ID y Client Secret en su perfil, esas credenciales personales tienen prioridad.', 'wp-song-study' ) . '</p>';
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
    submit_button();
    echo '</form>';
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
