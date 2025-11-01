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

    if ( empty( $wpss_admin_page_hooks ) || ! in_array( $hook, $wpss_admin_page_hooks, true ) ) {
        return;
    }

    $script_path = WPSS_PATH . 'assets/cancion-dashboard.js';
    $style_path  = WPSS_PATH . 'assets/cancion-dashboard.css';

    $script_url = WPSS_URL . 'assets/cancion-dashboard.js';
    $style_url  = WPSS_URL . 'assets/cancion-dashboard.css';

    $version = file_exists( $script_path ) ? filemtime( $script_path ) : wpss_get_asset_version_fallback();
    $deps    = [ 'wp-api-fetch' ];

    wp_enqueue_script( 'wpss-cancion-dashboard', $script_url, $deps, $version, true );

    if ( file_exists( $style_path ) ) {
        $style_version = filemtime( $style_path );
        wp_enqueue_style( 'wpss-cancion-dashboard', $style_url, [], $style_version );
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
            'camposSaved'      => __( 'Campos armónicos actualizados.', 'wp-song-study' ),
            'camposError'      => __( 'No fue posible guardar la biblioteca de campos armónicos.', 'wp-song-study' ),
            'camposEmpty'      => __( 'Aún no hay campos armónicos registrados.', 'wp-song-study' ),
            'camposAdd'        => __( 'Añadir modo', 'wp-song-study' ),
            'camposRemove'     => __( 'Eliminar', 'wp-song-study' ),
            'camposActive'     => __( 'Activo', 'wp-song-study' ),
            'readingEmpty'     => __( 'Agrega versos y segmentos para visualizar la canción.', 'wp-song-study' ),
            'segmentRequired'  => __( 'Cada verso necesita al menos un segmento con texto o acorde.', 'wp-song-study' ),
            'segmentConsecutive' => __( 'No se permiten segmentos consecutivos sin texto.', 'wp-song-study' ),
            'camposSlugRequired' => __( 'Cada modo necesita un identificador (slug).', 'wp-song-study' ),
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
