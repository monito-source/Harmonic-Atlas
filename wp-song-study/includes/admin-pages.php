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

/**
 * Registra el menú "Cancionario Armónico" con sus páginas SPA.
 */
function wpss_register_admin_pages() {
    global $wpss_admin_page_hooks;

    $capability  = 'edit_posts';
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

    $wpss_admin_page_hooks = [ $dashboard_hook, $new_song_hook ];
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
 * Encola scripts y estilos necesarios únicamente en las páginas del cancionario.
 *
 * @param string $hook Hook actual.
 */
function wpss_enqueue_admin_assets( $hook ) {
    global $wpss_admin_page_hooks;

    $allowed_pages = [ 'wpss-cancionario', 'wpss-cancion-nueva' ];

    if (
        ( empty( $wpss_admin_page_hooks ) || ! in_array( $hook, $wpss_admin_page_hooks, true ) )
        && ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], $allowed_pages, true ) )
    ) {
        return;
    }

    $base_dir = plugin_dir_path( WPSS_PLUGIN_FILE );
    $base_url = plugins_url( '/', WPSS_PLUGIN_FILE );

    $js_rel  = 'assets/cancion-dashboard.js';
    $css_rel = 'assets/cancion-dashboard.css';

    $js_path  = $base_dir . $js_rel;
    $css_path = $base_dir . $css_rel;

    if ( ! file_exists( $js_path ) ) {
        error_log( sprintf( 'WPSS: JS no encontrado en %s (hook=%s)', $js_path, $hook ) );
        return;
    }

    $js_url  = $base_url . $js_rel;
    $css_url = $base_url . $css_rel;

    $js_version  = @filemtime( $js_path );
    $script_deps = [ 'wp-api-fetch' ];

    wp_enqueue_script(
        'wpss-cancion-dashboard',
        $js_url,
        $script_deps,
        $js_version ? $js_version : wpss_get_asset_version_fallback(),
        true
    );

    if ( file_exists( $css_path ) ) {
        $css_version = @filemtime( $css_path );
        wp_enqueue_style(
            'wpss-cancion-dashboard',
            $css_url,
            [],
            $css_version ? $css_version : wpss_get_asset_version_fallback()
        );
    } else {
        error_log( sprintf( 'WPSS: CSS no encontrado en %s (hook=%s)', $css_path, $hook ) );
    }

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
        array_map(
            static function( $campo ) {
                return isset( $campo['nombre'] ) ? $campo['nombre'] : '';
            },
            array_filter(
                $campos_library,
                static function( $campo ) {
                    return ! empty( $campo['activo'] );
                }
            )
        )
    );

    $localized_data = [
        'restUrl'      => esc_url_raw( rest_url( 'wpss/v1/' ) ),
        'wpRestNonce'  => wp_create_nonce( 'wp_rest' ),
        'wpssNonce'    => wp_create_nonce( 'wpss' ),
        'tonicas'      => $tonicas,
        'camposArmonicos' => $campos_library,
        'camposArmonicosNombres' => $campos_armonicos,
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
            'segmentAdd'       => __( 'Añadir segmento', 'wp-song-study' ),
            'segmentDuplicate' => __( 'Duplicar segmento', 'wp-song-study' ),
            'segmentSplit'     => __( 'Dividir en el cursor', 'wp-song-study' ),
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
        'segmentRequired'  => __( 'Cada verso necesita al menos un segmento con texto o acorde.', 'wp-song-study' ),
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
    ],
    ];

    wp_localize_script( 'wpss-cancion-dashboard', 'WPSS', $localized_data );
}

if ( ! function_exists( 'wpss_get_asset_version_fallback' ) ) {
    /**
     * Obtiene una versión de respaldo para assets cuando no existe el archivo físico.
     *
     * @return string
     */
    function wpss_get_asset_version_fallback() {
        return defined( 'WPSS_VERSION' ) ? WPSS_VERSION : '1.0.0';
    }
}
