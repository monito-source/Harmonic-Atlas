<?php
add_action(
    'after_setup_theme',
    function () {
        load_theme_textdomain( 'pertenencia-digital', get_template_directory() . '/languages' );
        add_theme_support( 'title-tag' );
        add_theme_support( 'wp-block-styles' );
        add_theme_support( 'responsive-embeds' );
        add_theme_support( 'editor-styles' );
        add_editor_style( pd_get_theme_editor_stylesheet_paths() );
        add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
        add_theme_support( 'post-thumbnails', [ 'post', 'page', 'proyecto' ] );
        register_nav_menus(
            [
                'menu_principal' => __( 'Menú principal', 'pertenencia-digital' ),
            ]
        );
    }
);

/**
 * Devuelve el manifiesto ordenado de hojas de estilo del tema.
 *
 * @return array<int, array<string, mixed>>
 */
function pd_get_theme_stylesheet_manifest(): array {
    return [
        [
            'handle'        => 'pertenencia-digital-fonts',
            'relative_path' => 'assets/css/fonts.css',
            'base'          => 'template',
            'deps'          => [],
        ],
        [
            'handle'        => 'pertenencia-digital-tokens',
            'relative_path' => 'assets/css/tokens.css',
            'base'          => 'template',
            'deps'          => [ 'pertenencia-digital-fonts' ],
        ],
        [
            'handle'        => 'pertenencia-digital-layout-shell',
            'relative_path' => 'assets/css/layout-shell.css',
            'base'          => 'template',
            'deps'          => [ 'pertenencia-digital-tokens' ],
        ],
        [
            'handle'        => 'pertenencia-digital-components-base',
            'relative_path' => 'assets/css/components-base.css',
            'base'          => 'template',
            'deps'          => [ 'pertenencia-digital-layout-shell' ],
        ],
        [
            'handle'        => 'pertenencia-digital-service-components',
            'relative_path' => 'assets/css/service-components.css',
            'base'          => 'template',
            'deps'          => [ 'pertenencia-digital-components-base' ],
        ],
        [
            'handle'        => 'pertenencia-digital-navigation',
            'relative_path' => 'assets/css/navigation.css',
            'base'          => 'template',
            'deps'          => [ 'pertenencia-digital-service-components' ],
        ],
        [
            'handle'        => 'pertenencia-digital-header',
            'relative_path' => 'assets/css/header.css',
            'base'          => 'template',
            'deps'          => [ 'pertenencia-digital-navigation' ],
        ],
        [
            'handle'        => 'pertenencia-digital-style',
            'relative_path' => 'style.css',
            'base'          => 'stylesheet',
            'deps'          => [ 'pertenencia-digital-header' ],
        ],
    ];
}

/**
 * Devuelve las rutas relativas que WordPress debe cargar como estilos del editor.
 *
 * @return array<int, string>
 */
function pd_get_theme_editor_stylesheet_paths(): array {
    return array_values(
        array_map(
            static function ( array $asset ): string {
                return (string) $asset['relative_path'];
            },
            pd_get_theme_stylesheet_manifest()
        )
    );
}

/**
 * Resuelve la ruta absoluta de una hoja de estilo del manifiesto.
 *
 * @param array<string, mixed> $asset Configuración de la hoja de estilo.
 */
function pd_get_theme_stylesheet_path( array $asset ): string {
    $base_dir = 'stylesheet' === ( $asset['base'] ?? 'template' )
        ? get_stylesheet_directory()
        : get_template_directory();

    return trailingslashit( $base_dir ) . ltrim( (string) $asset['relative_path'], '/' );
}

/**
 * Resuelve la URL pública de una hoja de estilo del manifiesto.
 *
 * @param array<string, mixed> $asset Configuración de la hoja de estilo.
 */
function pd_get_theme_stylesheet_url( array $asset ): string {
    $base_uri = 'stylesheet' === ( $asset['base'] ?? 'template' )
        ? get_stylesheet_directory_uri()
        : get_template_directory_uri();

    return trailingslashit( $base_uri ) . ltrim( (string) $asset['relative_path'], '/' );
}

/**
 * Encola el manifiesto ordenado de estilos compartidos del tema.
 */
function pd_enqueue_theme_stylesheet_manifest(): void {
    $theme   = wp_get_theme();
    $version = $theme->get( 'Version' );

    foreach ( pd_get_theme_stylesheet_manifest() as $asset ) {
        $path = pd_get_theme_stylesheet_path( $asset );

        if ( ! file_exists( $path ) ) {
            continue;
        }

        wp_enqueue_style(
            (string) $asset['handle'],
            pd_get_theme_stylesheet_url( $asset ),
            isset( $asset['deps'] ) && is_array( $asset['deps'] ) ? $asset['deps'] : [],
            (string) filemtime( $path ) ?: $version
        );
    }
}

/**
 * Devuelve las plantillas de página personalizadas del tema.
 *
 * @return array<string, string>
 */
function pd_get_custom_page_templates(): array {
    return [
        'acceso'                 => __( 'Acceso', 'pertenencia-digital' ),
        'inicio'                 => __( 'Inicio / Landing', 'pertenencia-digital' ),
        'presskit'               => __( 'Press Kit', 'pertenencia-digital' ),
        'ensayos'                => __( 'Ensayos', 'pertenencia-digital' ),
        'mi-pertenencia'         => __( 'Mi pertenencia', 'pertenencia-digital' ),
        'proyectos-musica'       => __( 'Proyectos (Música)', 'pertenencia-digital' ),
        'proyectos-tecnologias'  => __( 'Proyectos (Tecnologías y web)', 'pertenencia-digital' ),
    ];
}

/**
 * Refuerza el registro de plantillas de página en el selector del editor.
 *
 * En algunos flujos de FSE la detección vía theme.json no se refleja de inmediato
 * en el selector de plantilla. Este filtro actúa como fallback para asegurar que
 * las plantillas del tema sí aparezcan disponibles para páginas.
 *
 * @param array              $page_templates Plantillas detectadas por WordPress.
 * @param WP_Theme|null      $theme          Tema actual.
 * @param WP_Post|null       $post           Post actual.
 * @param string             $post_type      Tipo de post.
 * @return array
 */
function pd_register_page_templates_fallback( array $page_templates, $theme = null, $post = null, string $post_type = 'page' ): array {
    if ( 'page' !== $post_type ) {
        return $page_templates;
    }

    return array_merge( $page_templates, pd_get_custom_page_templates() );
}
add_filter( 'theme_page_templates', 'pd_register_page_templates_fallback', 10, 4 );

/**
 * Registra la categoría de patrones del tema.
 */
function pd_register_block_pattern_categories(): void {
    if ( ! function_exists( 'register_block_pattern_category' ) ) {
        return;
    }

    register_block_pattern_category(
        'pertenencia-digital',
        [
            'label'       => __( 'Pertenencia Digital', 'pertenencia-digital' ),
            'description' => __( 'Patrones reutilizables del tema Pertenencia Digital.', 'pertenencia-digital' ),
        ]
    );
}
add_action( 'init', 'pd_register_block_pattern_categories' );

/**
 * Añade atributos de overlay al bloque core/group.
 *
 * WordPress permite extender metadata de bloques core con `block_type_metadata`.
 * Eso evita escribir claves arbitrarias dentro de `style`, que en este caso
 * estaba rompiendo el editor del bloque.
 *
 * @param array $metadata Metadata original del bloque.
 * @return array
 */
function pd_extend_group_block_metadata( array $metadata ): array {
    if ( empty( $metadata['name'] ) || 'core/group' !== $metadata['name'] ) {
        return $metadata;
    }

    if ( empty( $metadata['attributes'] ) || ! is_array( $metadata['attributes'] ) ) {
        $metadata['attributes'] = [];
    }

    $metadata['attributes']['pdOverlayEnabled'] = [
        'type'    => 'boolean',
        'default' => false,
    ];
    $metadata['attributes']['pdOverlayColor'] = [
        'type'    => 'string',
        'default' => '',
    ];
    $metadata['attributes']['pdOverlayGradient'] = [
        'type'    => 'string',
        'default' => '',
    ];
    $metadata['attributes']['pdOverlayOpacity'] = [
        'type'    => 'number',
        'default' => 55,
    ];

    return $metadata;
}
add_filter( 'block_type_metadata', 'pd_extend_group_block_metadata' );

add_action(
    'wp_enqueue_scripts',
    function () {
        $script_path = get_template_directory() . '/assets/js/account-access.js';
        $nav_path    = get_template_directory() . '/assets/js/site-navigation.js';

        pd_enqueue_theme_stylesheet_manifest();

        if ( file_exists( $script_path ) ) {
            wp_enqueue_script(
                'pertenencia-digital-account-access',
                get_template_directory_uri() . '/assets/js/account-access.js',
                [],
                (string) filemtime( $script_path ),
                true
            );
        }

        if ( file_exists( $nav_path ) ) {
            wp_enqueue_script(
                'pertenencia-digital-site-navigation',
                get_template_directory_uri() . '/assets/js/site-navigation.js',
                [],
                (string) filemtime( $nav_path ),
                true
            );
        }
    }
);

/**
 * Registra scripts usados por los bloques dinamicos del tema dentro del editor.
 */
function pd_register_theme_block_editor_script(): void {
    $script_path = get_template_directory() . '/assets/js/theme-blocks-editor.js';

    if ( ! file_exists( $script_path ) ) {
        return;
    }

    wp_register_script(
        'pertenencia-digital-theme-blocks-editor',
        get_template_directory_uri() . '/assets/js/theme-blocks-editor.js',
        [ 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-compose', 'wp-hooks', 'wp-api-fetch' ],
        (string) filemtime( $script_path ),
        true
    );

    wp_add_inline_script(
        'pertenencia-digital-theme-blocks-editor',
        'window.pdEditorialShellThemeSettings = ' . wp_json_encode(
            [
                'canManage' => current_user_can( 'manage_options' ),
                'settings'  => pd_get_editorial_shell_theme_settings(),
            ]
        ) . ';',
        'before'
    );
}
add_action( 'init', 'pd_register_theme_block_editor_script', 5 );

/**
 * Sanitiza valores CSS guardados como ajustes globales del tema.
 */
function pd_sanitize_css_custom_value( $value ): string {
    $value = trim( wp_strip_all_tags( (string) $value ) );

    return str_replace(
        [ ';', '{', '}', "\r", "\n" ],
        '',
        $value
    );
}

/**
 * Devuelve el mapa de ajustes globales del shell editorial.
 *
 * @return array<string, array<string, string>>
 */
function pd_get_editorial_shell_theme_setting_map(): array {
    return [
        'shellBackground'   => [
            'option'   => 'pd_editorial_shell_background',
            'css_prop' => '--wp--custom--editorial-shell--background',
        ],
        'landingBackground' => [
            'option'   => 'pd_editorial_shell_landing_background',
            'css_prop' => '--wp--custom--editorial-shell--landing-background',
        ],
        'accent'            => [
            'option'   => 'pd_editorial_shell_accent',
            'css_prop' => '--wp--custom--editorial-shell--accent',
        ],
        'accentMusic'       => [
            'option'   => 'pd_editorial_shell_accent_music',
            'css_prop' => '--wp--custom--editorial-shell--accent-music',
        ],
        'accentTechnology'  => [
            'option'   => 'pd_editorial_shell_accent_technology',
            'css_prop' => '--wp--custom--editorial-shell--accent-technology',
        ],
        'accentLegal'       => [
            'option'   => 'pd_editorial_shell_accent_legal',
            'css_prop' => '--wp--custom--editorial-shell--accent-legal',
        ],
        'heroBackground'    => [
            'option'   => 'pd_editorial_hero_background',
            'css_prop' => '--wp--custom--editorial-shell--hero-background',
        ],
        'heroBorder'        => [
            'option'   => 'pd_editorial_hero_border',
            'css_prop' => '--wp--custom--editorial-shell--hero-border',
        ],
        'heroText'          => [
            'option'   => 'pd_editorial_hero_text',
            'css_prop' => '--wp--custom--editorial-shell--hero-text',
        ],
        'heroTitle'         => [
            'option'   => 'pd_editorial_hero_title',
            'css_prop' => '--wp--custom--editorial-shell--hero-title',
        ],
        'surfaceBackground' => [
            'option'   => 'pd_editorial_surface_background',
            'css_prop' => '--wp--custom--editorial-shell--surface-background',
        ],
        'surfaceBorder'     => [
            'option'   => 'pd_editorial_surface_border',
            'css_prop' => '--wp--custom--editorial-shell--surface-border',
        ],
        'surfaceText'       => [
            'option'   => 'pd_editorial_surface_text',
            'css_prop' => '--wp--custom--editorial-shell--surface-text',
        ],
        'surfaceHeading'    => [
            'option'   => 'pd_editorial_surface_heading',
            'css_prop' => '--wp--custom--editorial-shell--surface-heading',
        ],
    ];
}

/**
 * Registra ajustes globales del shell para exponerlos en REST.
 */
function pd_register_editorial_shell_theme_settings(): void {
    foreach ( pd_get_editorial_shell_theme_setting_map() as $setting ) {
        register_setting(
            'general',
            $setting['option'],
            [
                'type'              => 'string',
                'sanitize_callback' => 'pd_sanitize_css_custom_value',
                'default'           => '',
                'show_in_rest'      => [
                    'schema' => [
                        'type' => 'string',
                    ],
                ],
            ]
        );
    }
}
add_action( 'init', 'pd_register_editorial_shell_theme_settings', 6 );

/**
 * Devuelve los ajustes globales actuales del shell editorial.
 *
 * @return array<string, string>
 */
function pd_get_editorial_shell_theme_settings(): array {
    $values = [];

    foreach ( pd_get_editorial_shell_theme_setting_map() as $key => $setting ) {
        $values[ $key ] = (string) get_option( $setting['option'], '' );
    }

    return $values;
}

/**
 * Genera las custom properties globales del shell editorial.
 */
function pd_get_editorial_shell_theme_css(): string {
    $declarations = [];

    foreach ( pd_get_editorial_shell_theme_setting_map() as $setting ) {
        $value = pd_sanitize_css_custom_value( get_option( $setting['option'], '' ) );

        if ( '' === $value ) {
            continue;
        }

        $declarations[] = $setting['css_prop'] . ': ' . $value;
    }

    if ( empty( $declarations ) ) {
        return '';
    }

    return ':root {' . implode( '; ', $declarations ) . '; }';
}

/**
 * Encola overrides globales del shell en frontend.
 */
function pd_enqueue_editorial_shell_theme_styles(): void {
    $css = pd_get_editorial_shell_theme_css();

    if ( '' === $css ) {
        return;
    }

    wp_register_style(
        'pertenencia-digital-editorial-shell-globals',
        false,
        [ 'pertenencia-digital-tokens' ],
        null
    );
    wp_enqueue_style( 'pertenencia-digital-editorial-shell-globals' );
    wp_add_inline_style( 'pertenencia-digital-editorial-shell-globals', $css );
}
add_action( 'wp_enqueue_scripts', 'pd_enqueue_editorial_shell_theme_styles', 35 );

/**
 * Encola overrides globales del shell dentro del editor.
 */
function pd_enqueue_editorial_shell_editor_styles(): void {
    $css = pd_get_editorial_shell_theme_css();

    if ( '' === $css ) {
        return;
    }

    wp_register_style(
        'pertenencia-digital-editorial-shell-globals-editor',
        false,
        [ 'wp-block-library' ],
        null
    );
    wp_enqueue_style( 'pertenencia-digital-editorial-shell-globals-editor' );
    wp_add_inline_style( 'pertenencia-digital-editorial-shell-globals-editor', $css );
}
add_action( 'enqueue_block_editor_assets', 'pd_enqueue_editorial_shell_editor_styles', 35 );

/**
 * Obtiene la URL preferida para volver al espacio de pertenencia.
 */
function pd_get_default_membership_url(): string {
    $membership_page = get_page_by_path( 'musica/mi-pertenencia' );

    if ( $membership_page instanceof WP_Post ) {
        $membership_url = get_permalink( $membership_page );

        if ( is_string( $membership_url ) && '' !== $membership_url ) {
            return $membership_url;
        }
    }

    return home_url( '/' );
}

/**
 * Devuelve el ID de la pagina frontend de acceso.
 */
function pd_get_login_page_id(): int {
    static $page_id = null;

    if ( null !== $page_id ) {
        return $page_id;
    }

    $page    = get_page_by_path( 'acceso' );
    $page_id = $page instanceof WP_Post ? (int) $page->ID : 0;

    return $page_id;
}

/**
 * Devuelve la URL base de la pagina de acceso.
 */
function pd_get_login_page_base_url(): string {
    $page_id = pd_get_login_page_id();

    if ( $page_id <= 0 ) {
        return '';
    }

    $url = get_permalink( $page_id );

    return is_string( $url ) ? $url : '';
}

/**
 * Construye la URL del acceso frontend.
 *
 * @param string $redirect_to Destino posterior al login.
 * @param string $action      Vista que debe mostrarse.
 */
function pd_get_login_page_url( string $redirect_to = '', string $action = 'login' ): string {
    $base_url = pd_get_login_page_base_url();

    if ( '' === $redirect_to ) {
        $redirect_to = pd_get_default_membership_url();
    }

    if ( '' === $base_url ) {
        $args = [];

        if ( 'login' !== $action ) {
            $args['action'] = $action;
        }

        if ( '' !== $redirect_to ) {
            $args['redirect_to'] = $redirect_to;
        }

        return add_query_arg( $args, network_site_url( 'wp-login.php', 'login' ) );
    }

    $args = [];

    if ( 'login' !== $action ) {
        $args['action'] = $action;
    }

    if ( '' !== $redirect_to ) {
        $args['redirect_to'] = $redirect_to;
    }

    return add_query_arg( $args, $base_url );
}

/**
 * Construye la URL del registro frontend.
 *
 * @param string $redirect_to Destino posterior al registro/login.
 */
function pd_get_register_page_url( string $redirect_to = '' ): string {
    if ( ! get_option( 'users_can_register' ) ) {
        return '';
    }

    if ( '' === $redirect_to ) {
        $redirect_to = pd_get_default_membership_url();
    }

    $base_url = pd_get_login_page_base_url();

    if ( '' === $base_url ) {
        $args = [];

        if ( '' !== $redirect_to ) {
            $args['redirect_to'] = $redirect_to;
        }

        return add_query_arg( $args, wp_registration_url() );
    }

    $args = [
        'action' => 'register',
    ];

    if ( '' !== $redirect_to ) {
        $args['redirect_to'] = $redirect_to;
    }

    return add_query_arg( $args, $base_url );
}

/**
 * Indica si el usuario actual puede acceder al area privada de musica.
 */
function pd_current_user_can_access_private_music_area(): bool {
    if ( function_exists( 'wpssb_user_can_access_private_music_area' ) ) {
        return wpssb_user_can_access_private_music_area();
    }

    if ( ! is_user_logged_in() ) {
        return false;
    }

    if ( current_user_can( 'manage_options' ) || current_user_can( 'pd_colaborador' ) || current_user_can( 'edit_presskits' ) ) {
        return true;
    }

    return function_exists( 'wpss_user_is_colega_musical' ) ? wpss_user_is_colega_musical() : false;
}

/**
 * Devuelve el copy base de acceso restringido por contexto.
 *
 * @param string $context Contexto solicitado.
 * @return array<string, string>
 */
function pd_get_private_music_access_context( string $context ): array {
    $contexts = [
        'membership'       => [
            'eyebrow'      => __( 'Mi pertenencia', 'pertenencia-digital' ),
            'login_title'  => __( 'Accede a tu pertenencia digital', 'pertenencia-digital' ),
            'login_intro'  => __( 'Mi pertenencia es un espacio privado para colaboradores musicales, colaboradores y administradores. Inicia sesion con una cuenta autorizada para editar tu perfil, mantener tu presskit y revisar tus proyectos.', 'pertenencia-digital' ),
            'blocked_title'=> __( 'Esta cuenta no puede entrar a Mi pertenencia', 'pertenencia-digital' ),
            'blocked_intro'=> __( 'Has iniciado sesion, pero esta cuenta todavia no tiene uno de los roles requeridos para entrar a esta seccion privada.', 'pertenencia-digital' ),
        ],
        'rehearsals'       => [
            'eyebrow'      => __( 'Planificador de ensayos', 'pertenencia-digital' ),
            'login_title'  => __( 'Accede al Planificador de ensayos', 'pertenencia-digital' ),
            'login_intro'  => __( 'El Planificador de ensayos es un espacio privado para colegas musicales, colaboradores y administradores que ya pertenecen a un proyecto musical. Inicia sesion con una cuenta autorizada para registrar disponibilidad, responder propuestas y revisar la bitacora del proyecto.', 'pertenencia-digital' ),
            'blocked_title'=> __( 'Esta cuenta no puede entrar al Planificador de ensayos', 'pertenencia-digital' ),
            'blocked_intro'=> __( 'Necesitas una cuenta vinculada a un proyecto musical para consultar disponibilidad, responder sesiones y revisar la bitacora del grupo.', 'pertenencia-digital' ),
        ],
        'study-repertoire' => [
            'eyebrow'      => __( 'Estudiar repertorio', 'pertenencia-digital' ),
            'login_title'  => __( 'Accede a estudiar repertorio', 'pertenencia-digital' ),
            'login_intro'  => __( 'Estudiar repertorio es un espacio privado para colaboradores musicales, colaboradores y administradores. Inicia sesion con una cuenta autorizada para consultar el cancionero, estudiar material armonico y seguir el repertorio activo.', 'pertenencia-digital' ),
            'blocked_title'=> __( 'Esta cuenta no puede entrar a Estudiar repertorio', 'pertenencia-digital' ),
            'blocked_intro'=> __( 'Necesitas una cuenta con permisos de colaboracion musical para consultar el repertorio y los materiales de estudio de esta seccion.', 'pertenencia-digital' ),
        ],
    ];

    $fallback = [
        'eyebrow'       => __( 'Acceso privado', 'pertenencia-digital' ),
        'login_title'   => __( 'Accede a esta seccion privada', 'pertenencia-digital' ),
        'login_intro'   => __( 'Inicia sesion con una cuenta autorizada para continuar.', 'pertenencia-digital' ),
        'blocked_title' => __( 'Tu cuenta no tiene permisos para entrar', 'pertenencia-digital' ),
        'blocked_intro' => __( 'Necesitas iniciar sesion con una cuenta que tenga permisos sobre esta seccion privada.', 'pertenencia-digital' ),
    ];

    return $contexts[ $context ] ?? $fallback;
}

/**
 * Indica si una URL de redireccion apunta al escritorio.
 */
function pd_url_targets_wp_admin( string $url ): bool {
    if ( '' === $url ) {
        return false;
    }

    $path = wp_parse_url( $url, PHP_URL_PATH );

    return is_string( $path ) && false !== strpos( $path, '/wp-admin' );
}

/**
 * Define si el flujo frontend debe reemplazar el login nativo.
 */
function pd_should_use_frontend_login( string $redirect_to = '' ): bool {
    if ( is_admin() || wp_doing_ajax() ) {
        return false;
    }

    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return false;
    }

    if ( pd_url_targets_wp_admin( $redirect_to ) ) {
        return false;
    }

    return '' !== pd_get_login_page_base_url();
}

/**
 * Reemplaza el login URL solo para flujos frontend.
 *
 * @param string $login_url    URL original.
 * @param string $redirect     Destino posterior.
 * @param bool   $force_reauth Bandera de reautenticacion.
 */
function pd_filter_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
    if ( ! empty( $GLOBALS['pd_rendering_loginizer_social'] ) ) {
        return $login_url;
    }

    if ( ! pd_should_use_frontend_login( $redirect ) ) {
        return $login_url;
    }

    return pd_get_login_page_url( $redirect );
}
add_filter( 'login_url', 'pd_filter_login_url', 10, 3 );

/**
 * Envia el login nativo a la plantilla frontal cuando no es un callback ni un POST.
 */
function pd_redirect_native_login_to_frontend(): void {
    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

    if ( 'GET' !== $request_method ) {
        return;
    }

    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login';

    if ( ! in_array( $action, [ '', 'login' ], true ) ) {
        return;
    }

    foreach ( [ 'code', 'state', 'oauth_token', 'oauth_verifier' ] as $oauth_param ) {
        if ( isset( $_GET[ $oauth_param ] ) ) {
            return;
        }
    }

    foreach ( array_keys( $_GET ) as $query_key ) {
        $query_key = strtolower( (string) $query_key );

        if ( false !== strpos( $query_key, 'loginizer' ) || false !== strpos( $query_key, 'social' ) ) {
            return;
        }
    }

    $redirect_to = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), pd_get_default_membership_url() ) : '';

    if ( ! pd_should_use_frontend_login( $redirect_to ) ) {
        return;
    }

    wp_safe_redirect( pd_get_login_page_url( $redirect_to ) );
    exit;
}
add_action( 'login_init', 'pd_redirect_native_login_to_frontend', 1 );

/**
 * Reemplaza la URL de recuperacion para flujos frontend.
 *
 * @param string $lostpassword_url URL original.
 * @param string $redirect         Destino posterior.
 */
function pd_filter_lostpassword_url( string $lostpassword_url, string $redirect ): string {
    if ( ! pd_should_use_frontend_login( $redirect ) ) {
        return $lostpassword_url;
    }

    return pd_get_login_page_url( $redirect, 'lostpassword' );
}
add_filter( 'lostpassword_url', 'pd_filter_lostpassword_url', 10, 2 );

/**
 * Reemplaza la URL de registro para flujos frontend.
 *
 * @param string $register_url URL original.
 */
function pd_filter_register_url( string $register_url ): string {
    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return $register_url;
    }

    $frontend_url = pd_get_register_page_url();

    return '' !== $frontend_url ? $frontend_url : $register_url;
}
add_filter( 'register_url', 'pd_filter_register_url' );

/**
 * Obtiene el feedback visual del flujo de acceso.
 *
 * @return array<string, string>
 */
function pd_get_auth_feedback(): array {
    $status = isset( $_GET['pd_auth_status'] ) ? sanitize_key( wp_unslash( $_GET['pd_auth_status'] ) ) : '';

    $messages = [
        'login_failed' => [
            'type'    => 'error',
            'message' => __( 'No se pudo iniciar sesion. Revisa tu usuario o correo y tu contrasena.', 'pertenencia-digital' ),
        ],
        'invalid_nonce' => [
            'type'    => 'error',
            'message' => __( 'La solicitud expiro. Intenta de nuevo.', 'pertenencia-digital' ),
        ],
        'logged_out' => [
            'type'    => 'success',
            'message' => __( 'Tu sesion se cerro correctamente.', 'pertenencia-digital' ),
        ],
        'recovery_sent' => [
            'type'    => 'success',
            'message' => __( 'Si la cuenta existe, te enviamos un enlace para restablecer la contrasena.', 'pertenencia-digital' ),
        ],
        'recovery_error' => [
            'type'    => 'error',
            'message' => __( 'No fue posible iniciar la recuperacion. Verifica el dato capturado e intentalo de nuevo.', 'pertenencia-digital' ),
        ],
        'registered' => [
            'type'    => 'success',
            'message' => __( 'Tu cuenta fue registrada. Revisa tu correo para completar el acceso y luego inicia sesion aqui.', 'pertenencia-digital' ),
        ],
        'register_disabled' => [
            'type'    => 'error',
            'message' => __( 'El registro publico esta desactivado en este momento.', 'pertenencia-digital' ),
        ],
        'register_invalid_username' => [
            'type'    => 'error',
            'message' => __( 'Escribe un nombre de usuario valido para crear tu cuenta.', 'pertenencia-digital' ),
        ],
        'register_invalid_email' => [
            'type'    => 'error',
            'message' => __( 'Escribe un correo electronico valido para crear tu cuenta.', 'pertenencia-digital' ),
        ],
        'register_username_exists' => [
            'type'    => 'error',
            'message' => __( 'Ese nombre de usuario ya existe. Prueba con otro o inicia sesion.', 'pertenencia-digital' ),
        ],
        'register_email_exists' => [
            'type'    => 'error',
            'message' => __( 'Ese correo ya tiene una cuenta. Inicia sesion o recupera tu acceso.', 'pertenencia-digital' ),
        ],
        'register_error' => [
            'type'    => 'error',
            'message' => __( 'No fue posible crear la cuenta. Revisa los datos e intentalo de nuevo.', 'pertenencia-digital' ),
        ],
        'google_config' => [
            'type'    => 'error',
            'message' => __( 'El acceso con Google no esta configurado todavia. Entra con tu usuario y contrasena.', 'pertenencia-digital' ),
        ],
        'google_cancelled' => [
            'type'    => 'error',
            'message' => __( 'Google cancelo el acceso antes de completarlo. Intenta de nuevo.', 'pertenencia-digital' ),
        ],
        'google_state' => [
            'type'    => 'error',
            'message' => __( 'No fue posible validar la solicitud de Google. Intenta de nuevo desde esta pantalla.', 'pertenencia-digital' ),
        ],
        'google_token' => [
            'type'    => 'error',
            'message' => __( 'No fue posible completar el intercambio con Google. Intenta de nuevo o entra con contrasena.', 'pertenencia-digital' ),
        ],
        'google_email' => [
            'type'    => 'error',
            'message' => __( 'Google no devolvio un correo verificado para iniciar sesion.', 'pertenencia-digital' ),
        ],
        'google_unknown' => [
            'type'    => 'error',
            'message' => __( 'Ese correo de Google no corresponde a una cuenta registrada en el sitio. Entra con tu acceso existente o solicita que registren ese correo.', 'pertenencia-digital' ),
        ],
    ];

    return $messages[ $status ] ?? [];
}

/**
 * Procesa el login desde la interfaz frontend.
 */
function pd_handle_frontend_login(): void {
    $fallback_redirect = pd_get_default_membership_url();
    $redirect_to       = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), $fallback_redirect ) : $fallback_redirect;
    $return_url        = pd_get_login_page_url( $redirect_to );
    $nonce             = isset( $_POST['pd_auth_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_auth_nonce'] ) ) : '';

    if ( ! wp_verify_nonce( $nonce, 'pd_frontend_login' ) ) {
        wp_safe_redirect( add_query_arg( 'pd_auth_status', 'invalid_nonce', $return_url ) );
        exit;
    }

    if ( is_user_logged_in() ) {
        wp_safe_redirect( $redirect_to );
        exit;
    }

    $credentials = [
        'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
        'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
        'remember'      => ! empty( $_POST['rememberme'] ),
    ];

    $user = wp_signon( $credentials, is_ssl() );

    if ( is_wp_error( $user ) ) {
        wp_safe_redirect( add_query_arg( 'pd_auth_status', 'login_failed', $return_url ) );
        exit;
    }

    wp_safe_redirect( $redirect_to );
    exit;
}
add_action( 'admin_post_nopriv_pd_frontend_login', 'pd_handle_frontend_login' );
add_action( 'admin_post_pd_frontend_login', 'pd_handle_frontend_login' );

/**
 * Procesa el registro desde la interfaz frontend.
 */
function pd_handle_frontend_register(): void {
    $fallback_redirect = pd_get_default_membership_url();
    $redirect_to       = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), $fallback_redirect ) : $fallback_redirect;
    $return_url        = pd_get_register_page_url( $redirect_to );
    $nonce             = isset( $_POST['pd_register_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_register_nonce'] ) ) : '';

    if ( ! wp_verify_nonce( $nonce, 'pd_frontend_register' ) ) {
        wp_safe_redirect( add_query_arg( 'pd_auth_status', 'invalid_nonce', $return_url ) );
        exit;
    }

    if ( ! get_option( 'users_can_register' ) ) {
        wp_safe_redirect( add_query_arg( 'pd_auth_status', 'register_disabled', pd_get_login_page_url( $redirect_to ) ) );
        exit;
    }

    if ( is_user_logged_in() ) {
        wp_safe_redirect( $redirect_to );
        exit;
    }

    $user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
    $user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';

    if ( '' === $user_login ) {
        wp_safe_redirect( add_query_arg( 'pd_auth_status', 'register_invalid_username', $return_url ) );
        exit;
    }

    if ( '' === $user_email || ! is_email( $user_email ) ) {
        wp_safe_redirect( add_query_arg( 'pd_auth_status', 'register_invalid_email', $return_url ) );
        exit;
    }

    $result = register_new_user( $user_login, $user_email );

    if ( is_wp_error( $result ) ) {
        $status = 'register_error';

        foreach ( [ 'existing_user_login', 'username_exists', 'existing_user_email', 'email_exists', 'invalid_email', 'invalid_username' ] as $code ) {
            if ( $result->get_error_code( $code ) ) {
                switch ( $code ) {
                    case 'existing_user_login':
                    case 'username_exists':
                        $status = 'register_username_exists';
                        break;
                    case 'existing_user_email':
                    case 'email_exists':
                        $status = 'register_email_exists';
                        break;
                    case 'invalid_email':
                        $status = 'register_invalid_email';
                        break;
                    case 'invalid_username':
                        $status = 'register_invalid_username';
                        break;
                    default:
                        $status = 'register_error';
                        break;
                }
                break;
            }
        }

        wp_safe_redirect( add_query_arg( 'pd_auth_status', $status, $return_url ) );
        exit;
    }

    wp_safe_redirect( add_query_arg( 'pd_auth_status', 'registered', pd_get_login_page_url( $redirect_to ) ) );
    exit;
}
add_action( 'admin_post_nopriv_pd_frontend_register', 'pd_handle_frontend_register' );
add_action( 'admin_post_pd_frontend_register', 'pd_handle_frontend_register' );

/**
 * Procesa la recuperacion de contrasena desde frontend.
 */
function pd_handle_frontend_lostpassword(): void {
    $return_url = pd_get_login_page_url( '', 'lostpassword' );
    $nonce      = isset( $_POST['pd_lostpassword_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_lostpassword_nonce'] ) ) : '';

    if ( ! wp_verify_nonce( $nonce, 'pd_frontend_lostpassword' ) ) {
        wp_safe_redirect( add_query_arg( 'pd_auth_status', 'invalid_nonce', $return_url ) );
        exit;
    }

    $user_login = isset( $_POST['user_login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : '';
    $result     = retrieve_password( $user_login );

    if ( is_wp_error( $result ) ) {
        wp_safe_redirect( add_query_arg( 'pd_auth_status', 'recovery_error', $return_url ) );
        exit;
    }

    wp_safe_redirect( add_query_arg( 'pd_auth_status', 'recovery_sent', $return_url ) );
    exit;
}
add_action( 'admin_post_nopriv_pd_frontend_lostpassword', 'pd_handle_frontend_lostpassword' );
add_action( 'admin_post_pd_frontend_lostpassword', 'pd_handle_frontend_lostpassword' );

/**
 * Indica si el login propio con Google puede iniciar el flujo OAuth.
 */
function pd_google_login_is_available(): bool {
    return function_exists( 'wpss_google_drive_is_configured_for_user' )
        && function_exists( 'wpss_get_google_drive_oauth_credentials' )
        && function_exists( 'wpss_get_google_drive_redirect_uri' )
        && function_exists( 'wpss_build_google_drive_oauth_state' )
        && wpss_google_drive_is_configured_for_user( 0 );
}

/**
 * Normaliza el destino posterior al acceso.
 */
function pd_get_google_login_return_url( string $redirect_to = '' ): string {
    return wp_validate_redirect( $redirect_to, pd_get_default_membership_url() );
}

/**
 * Construye la URL local que inicia OAuth con Google.
 */
function pd_get_google_login_start_url( string $redirect_to = '' ): string {
    if ( ! pd_google_login_is_available() ) {
        return '';
    }

    $url = add_query_arg(
        [
            'action'      => 'pd_google_login',
            'redirect_to' => pd_get_google_login_return_url( $redirect_to ),
            '_wpnonce'    => wp_create_nonce( 'pd_google_login' ),
        ],
        admin_url( 'admin-post.php' )
    );

    return $url;
}

/**
 * Redirige al login frontal con un estado del flujo Google.
 */
function pd_redirect_google_login_status( string $status, string $return_url = '' ): void {
    wp_safe_redirect( add_query_arg( 'pd_auth_status', sanitize_key( $status ), pd_get_login_page_url( pd_get_google_login_return_url( $return_url ) ) ) );
    exit;
}

/**
 * Inicia el flujo OAuth propio para acceso con Google.
 */
function pd_handle_google_login_start(): void {
    $redirect_to = isset( $_GET['redirect_to'] )
        ? pd_get_google_login_return_url( (string) wp_unslash( $_GET['redirect_to'] ) )
        : pd_get_default_membership_url();

    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'pd_google_login' ) ) {
        pd_redirect_google_login_status( 'invalid_nonce', $redirect_to );
    }

    if ( is_user_logged_in() ) {
        wp_safe_redirect( $redirect_to );
        exit;
    }

    if ( ! pd_google_login_is_available() ) {
        pd_redirect_google_login_status( 'google_config', $redirect_to );
    }

    $credentials = wpss_get_google_drive_oauth_credentials( 0 );
    $flow_id     = wp_generate_password( 32, false, false );
    $state       = wpss_build_google_drive_oauth_state( 0, $redirect_to, $flow_id, 'google_login' );

    if ( '' === $state ) {
        pd_redirect_google_login_status( 'google_state', $redirect_to );
    }

    $auth_url = add_query_arg(
        [
            'client_id'     => $credentials['client_id'],
            'redirect_uri'  => wpss_get_google_drive_redirect_uri(),
            'response_type' => 'code',
            'scope'         => implode( ' ', [ 'openid', 'https://www.googleapis.com/auth/userinfo.email' ] ),
            'state'         => $state,
            'prompt'        => 'select_account',
        ],
        'https://accounts.google.com/o/oauth2/v2/auth'
    );

    wp_redirect( $auth_url );
    exit;
}
add_action( 'admin_post_nopriv_pd_google_login', 'pd_handle_google_login_start' );
add_action( 'admin_post_pd_google_login', 'pd_handle_google_login_start' );

/**
 * Completa el callback OAuth de Google para iniciar sesion con cuentas existentes.
 *
 * @param array<string, mixed> $params Parametros recibidos desde Google.
 */
function pd_complete_google_login_callback( array $params ): void {
    $state       = isset( $params['state'] ) ? sanitize_text_field( (string) $params['state'] ) : '';
    $code        = isset( $params['code'] ) ? sanitize_text_field( (string) $params['code'] ) : '';
    $oauth_error = isset( $params['error'] ) ? sanitize_key( (string) $params['error'] ) : '';

    $signed_state = function_exists( 'wpss_parse_google_drive_oauth_state' )
        ? wpss_parse_google_drive_oauth_state( $state )
        : [];
    $return_url   = pd_get_google_login_return_url( ! empty( $signed_state['return_url'] ) ? (string) $signed_state['return_url'] : '' );

    if ( '' !== $oauth_error ) {
        pd_redirect_google_login_status( 'google_cancelled', $return_url );
    }

    if ( empty( $signed_state ) || 'google_login' !== ( $signed_state['provider'] ?? '' ) ) {
        pd_redirect_google_login_status( 'google_state', $return_url );
    }

    if ( '' === $code || ! pd_google_login_is_available() ) {
        pd_redirect_google_login_status( '' === $code ? 'google_token' : 'google_config', $return_url );
    }

    $credentials = wpss_get_google_drive_oauth_credentials( 0 );
    $token_response = wp_remote_post(
        'https://oauth2.googleapis.com/token',
        [
            'timeout' => 15,
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
        pd_redirect_google_login_status( 'google_token', $return_url );
    }

    $token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
    if ( ! is_array( $token_body ) || empty( $token_body['access_token'] ) ) {
        pd_redirect_google_login_status( 'google_token', $return_url );
    }

    $userinfo_response = wp_remote_get(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . sanitize_text_field( (string) $token_body['access_token'] ),
            ],
        ]
    );

    if ( is_wp_error( $userinfo_response ) ) {
        pd_redirect_google_login_status( 'google_email', $return_url );
    }

    $userinfo = json_decode( wp_remote_retrieve_body( $userinfo_response ), true );
    if ( ! is_array( $userinfo ) ) {
        pd_redirect_google_login_status( 'google_email', $return_url );
    }

    $email          = isset( $userinfo['email'] ) ? sanitize_email( (string) $userinfo['email'] ) : '';
    $verified_value = $userinfo['verified_email'] ?? ( $userinfo['email_verified'] ?? false );
    $email_verified = true === $verified_value || 'true' === $verified_value || '1' === (string) $verified_value;

    if ( '' === $email || ! $email_verified ) {
        pd_redirect_google_login_status( 'google_email', $return_url );
    }

    $user = get_user_by( 'email', $email );
    if ( ! $user instanceof WP_User ) {
        pd_redirect_google_login_status( 'google_unknown', $return_url );
    }

    update_user_meta( $user->ID, '_pd_google_login_email', $email );
    $google_subject = ! empty( $userinfo['id'] ) ? $userinfo['id'] : ( $userinfo['sub'] ?? '' );
    if ( '' !== $google_subject ) {
        update_user_meta( $user->ID, '_pd_google_login_sub', sanitize_text_field( (string) $google_subject ) );
    }
    update_user_meta( $user->ID, '_pd_google_login_last_login', current_time( 'mysql' ) );

    wp_clear_auth_cookie();
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, false, is_ssl() );
    do_action( 'wp_login', $user->user_login, $user );

    wp_safe_redirect( $return_url );
    exit;
}

/**
 * Renderiza el acceso propio con Google para cuentas existentes.
 */
function pd_render_google_login_panel( string $context = 'login', string $redirect_to = '' ): string {
    $login_url = pd_get_google_login_start_url( $redirect_to );
    if ( '' === $login_url ) {
        return '';
    }

    $is_register = 'register' === $context;
    $title       = $is_register
        ? __( 'Ya tengo cuenta con este correo', 'pertenencia-digital' )
        : __( 'Entrar con Google', 'pertenencia-digital' );
    $description = $is_register
        ? __( 'Si tu correo de Google ya existe en el sitio, entra directo sin crear otra cuenta.', 'pertenencia-digital' )
        : __( 'Usa Google para entrar a la cuenta existente que tenga el mismo correo registrado en WordPress.', 'pertenencia-digital' );
    $divider     = $is_register
        ? __( 'o completa el registro manual', 'pertenencia-digital' )
        : __( 'o usa tu usuario y contrasena', 'pertenencia-digital' );

    $output  = '<section class="pd-auth-social" aria-label="' . esc_attr__( 'Acceso con Google', 'pertenencia-digital' ) . '">';
    $output .= '<p class="pd-auth-social__eyebrow">' . esc_html__( 'Google', 'pertenencia-digital' ) . '</p>';
    $output .= '<h3 class="pd-auth-social__title">' . esc_html( $title ) . '</h3>';
    $output .= '<p class="pd-auth-social__description">' . esc_html( $description ) . '</p>';
    $output .= '<div class="pd-auth-social__buttons">';
    $output .= '<a class="pd-auth-google-button wp-block-button__link wp-element-button" href="' . esc_url( $login_url ) . '"><span class="pd-auth-google-button__mark" aria-hidden="true">G</span><span>' . esc_html__( 'Continuar con Google', 'pertenencia-digital' ) . '</span></a>';
    $output .= '</div>';
    $output .= '</section>';
    $output .= '<p class="pd-auth-social__divider"><span>' . esc_html( $divider ) . '</span></p>';

    return $output;
}

/**
 * Renderiza el panel social de Loginizer cuando el shortcode esta disponible.
 */
function pd_render_loginizer_social_panel( string $context = 'login', string $redirect_to = '' ): string {
    if ( ! shortcode_exists( 'loginizer_social' ) ) {
        return '';
    }

    $was_rendering_loginizer_social           = ! empty( $GLOBALS['pd_rendering_loginizer_social'] );
    $GLOBALS['pd_rendering_loginizer_social'] = true;
    $social_markup                            = '';

    try {
        $shortcode = '[loginizer_social type="full" divider="none" shape="square"';

        if ( '' !== $redirect_to ) {
            $shortcode .= ' redirect_to="' . esc_url( $redirect_to ) . '"';
        }

        $shortcode    .= ']';
        $social_markup = trim( do_shortcode( $shortcode ) );
    } finally {
        $GLOBALS['pd_rendering_loginizer_social'] = $was_rendering_loginizer_social;
    }

    if ( '' === $social_markup ) {
        return '';
    }

    $is_register = 'register' === $context;
    $title       = $is_register
        ? __( 'Crear cuenta con acceso social', 'pertenencia-digital' )
        : __( 'Entrar con acceso social', 'pertenencia-digital' );
    $description = $is_register
        ? __( 'Si Loginizer tiene Google u otro proveedor habilitado, puedes crear tu cuenta sin capturar una contrasena aqui.', 'pertenencia-digital' )
        : __( 'Si Loginizer tiene Google u otro proveedor habilitado, puedes entrar sin usar la contrasena del sitio.', 'pertenencia-digital' );
    $divider     = $is_register
        ? __( 'o completa el registro manual', 'pertenencia-digital' )
        : __( 'o usa tu usuario y contrasena', 'pertenencia-digital' );
    $note        = __( 'Si es tu primera vez con ese proveedor, WordPress puede crear la cuenta y despues solo faltara asignarle el rol correcto para acceder a las areas privadas.', 'pertenencia-digital' );

    $output  = '<section class="pd-auth-social" aria-label="' . esc_attr__( 'Acceso social', 'pertenencia-digital' ) . '">';
    $output .= '<p class="pd-auth-social__eyebrow">' . esc_html__( 'Acceso rapido', 'pertenencia-digital' ) . '</p>';
    $output .= '<h3 class="pd-auth-social__title">' . esc_html( $title ) . '</h3>';
    $output .= '<p class="pd-auth-social__description">' . esc_html( $description ) . '</p>';
    $output .= '<div class="pd-auth-social__buttons">' . $social_markup . '</div>';
    $output .= '<p class="pd-auth-social__note">' . esc_html( $note ) . '</p>';
    $output .= '</section>';
    $output .= '<p class="pd-auth-social__divider"><span>' . esc_html( $divider ) . '</span></p>';

    return $output;
}

/**
 * Renderiza la interfaz de acceso frontend.
 *
 * @param array<string, mixed> $args Ajustes visuales.
 */
function pd_render_login_panel( array $args = [] ): string {
    $args = wp_parse_args(
        $args,
        [
            'title'       => __( 'Accede a tu pertenencia digital', 'pertenencia-digital' ),
            'intro'       => __( 'Inicia sesion para editar tu presskit, revisar tus proyectos y mantener actualizada tu presencia en el sitio.', 'pertenencia-digital' ),
            'redirect_to' => '',
            'show_register' => null,
            'register_intro' => __( 'Crea una cuenta con usuario y correo. El acceso a las areas privadas se habilita cuando tu cuenta reciba un rol autorizado.', 'pertenencia-digital' ),
        ]
    );

    $feedback        = pd_get_auth_feedback();
    $show_register   = is_bool( $args['show_register'] ) ? $args['show_register'] : (bool) get_option( 'users_can_register' );
    $current_action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login';
    $allowed_actions = $show_register ? [ 'login', 'lostpassword', 'register' ] : [ 'login', 'lostpassword' ];
    $current_action  = in_array( $current_action, $allowed_actions, true ) ? $current_action : 'login';
    $redirect_to    = is_string( $args['redirect_to'] ) && '' !== $args['redirect_to']
        ? wp_validate_redirect( (string) $args['redirect_to'], pd_get_default_membership_url() )
        : ( isset( $_GET['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), pd_get_default_membership_url() ) : pd_get_default_membership_url() );
    $login_url      = pd_get_login_page_url( $redirect_to );
    $recover_url    = pd_get_login_page_url( $redirect_to, 'lostpassword' );
    $register_url   = $show_register ? pd_get_register_page_url( $redirect_to ) : '';
    $membership_url = pd_get_default_membership_url();
    $logout_url     = wp_logout_url( $login_url );
    $home_url       = home_url( '/' );

    $output  = '<section class="pd-auth-shell">';
    $output .= '<div class="pd-auth-shell__intro">';
    $output .= '<p class="pd-auth-shell__eyebrow">' . esc_html__( 'Acceso', 'pertenencia-digital' ) . '</p>';
    $output .= '<h1 class="pd-auth-shell__title">' . esc_html( (string) $args['title'] ) . '</h1>';
    $output .= '<p class="pd-auth-shell__lead">' . esc_html( (string) $args['intro'] ) . '</p>';
    $output .= '<div class="pd-auth-shell__features">';
    $output .= '<article class="pd-auth-feature"><strong>' . esc_html__( 'Entrada directa', 'pertenencia-digital' ) . '</strong><span>' . esc_html__( 'Accede a tu espacio sin caer en la interfaz blanca de WordPress.', 'pertenencia-digital' ) . '</span></article>';
    $output .= '<article class="pd-auth-feature"><strong>' . esc_html__( 'Recuperación frontal', 'pertenencia-digital' ) . '</strong><span>' . esc_html__( 'Restablece tu contraseña desde aquí mismo cuando pierdas acceso.', 'pertenencia-digital' ) . '</span></article>';
    $output .= '<article class="pd-auth-feature"><strong>' . esc_html__( 'Compatibilidad intacta', 'pertenencia-digital' ) . '</strong><span>' . esc_html__( 'El acceso nativo a wp-admin sigue disponible para quien lo necesite.', 'pertenencia-digital' ) . '</span></article>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '<div class="pd-auth-card">';
    $output .= '<nav class="pd-auth-card__modes" aria-label="' . esc_attr__( 'Vista de acceso', 'pertenencia-digital' ) . '">';
    $output .= '<a class="pd-auth-card__mode' . ( 'login' === $current_action ? ' is-active' : '' ) . '" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Iniciar sesión', 'pertenencia-digital' ) . '</a>';
    $output .= '<a class="pd-auth-card__mode' . ( 'lostpassword' === $current_action ? ' is-active' : '' ) . '" href="' . esc_url( $recover_url ) . '">' . esc_html__( 'Recuperar acceso', 'pertenencia-digital' ) . '</a>';
    if ( '' !== $register_url ) {
        $output .= '<a class="pd-auth-card__mode' . ( 'register' === $current_action ? ' is-active' : '' ) . '" href="' . esc_url( $register_url ) . '">' . esc_html__( 'Registro', 'pertenencia-digital' ) . '</a>';
    }
    $output .= '</nav>';

    if ( ! empty( $feedback['message'] ) ) {
        $feedback_class = 'success' === ( $feedback['type'] ?? '' ) ? 'is-success' : 'is-error';
        $feedback_role  = 'success' === ( $feedback['type'] ?? '' ) ? 'status' : 'alert';
        $output        .= '<p class="pd-auth-feedback ' . esc_attr( $feedback_class ) . '" role="' . esc_attr( $feedback_role ) . '">' . esc_html( $feedback['message'] ) . '</p>';
    }

    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();

        $output .= '<div class="pd-auth-state">';
        $output .= '<p class="pd-auth-state__eyebrow">' . esc_html__( 'Sesion activa', 'pertenencia-digital' ) . '</p>';
        $output .= '<h2 class="pd-auth-state__title">' . esc_html( $current_user->display_name ) . '</h2>';
        $output .= '<p class="pd-auth-state__meta">' . esc_html( $current_user->user_email ) . '</p>';
        $output .= '<div class="pd-auth-state__actions">';
        $output .= '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $membership_url ) . '">' . esc_html__( 'Ir a mi pertenencia', 'pertenencia-digital' ) . '</a>';
        $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( admin_url() ) . '">' . esc_html__( 'Abrir escritorio', 'pertenencia-digital' ) . '</a>';
        $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $logout_url ) . '">' . esc_html__( 'Cerrar sesion', 'pertenencia-digital' ) . '</a>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</section>';

        return $output;
    }

    if ( 'lostpassword' === $current_action ) {
        $output .= '<h2 class="pd-auth-card__title">' . esc_html__( 'Recuperar contrasena', 'pertenencia-digital' ) . '</h2>';
        $output .= '<p class="pd-auth-card__description">' . esc_html__( 'Escribe tu usuario o correo y te enviaremos el enlace de recuperacion.', 'pertenencia-digital' ) . '</p>';
        $output .= '<form class="pd-auth-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $output .= '<input type="hidden" name="action" value="pd_frontend_lostpassword" />';
        $output .= wp_nonce_field( 'pd_frontend_lostpassword', 'pd_lostpassword_nonce', true, false );
        $output .= '<label><span>' . esc_html__( 'Usuario o correo electronico', 'pertenencia-digital' ) . '</span><input type="text" name="user_login" autocomplete="username" required /></label>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button">' . esc_html__( 'Enviar enlace', 'pertenencia-digital' ) . '</button>';
        $output .= '</form>';
        $output .= '<div class="pd-auth-card__support">';
        $output .= '<article class="pd-auth-support-card"><strong>' . esc_html__( 'Volver a entrar', 'pertenencia-digital' ) . '</strong><p>' . esc_html__( 'Si ya recordaste tu contraseña, vuelve al formulario principal sin salir de esta pantalla.', 'pertenencia-digital' ) . '</p><a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Volver al inicio de sesión', 'pertenencia-digital' ) . '</a></article>';
        $output .= '<article class="pd-auth-support-card"><strong>' . esc_html__( 'Seguir navegando', 'pertenencia-digital' ) . '</strong><p>' . esc_html__( 'Puedes volver al sitio mientras recuperas acceso. El enlace de restablecimiento llegará por correo si la cuenta existe.', 'pertenencia-digital' ) . '</p><a href="' . esc_url( $home_url ) . '">' . esc_html__( 'Volver al sitio', 'pertenencia-digital' ) . '</a></article>';
        $output .= '</div>';
    } elseif ( 'register' === $current_action && '' !== $register_url ) {
        $output .= '<h2 class="pd-auth-card__title">' . esc_html__( 'Crear cuenta', 'pertenencia-digital' ) . '</h2>';
        $output .= '<p class="pd-auth-card__description">' . esc_html( (string) $args['register_intro'] ) . '</p>';
        $output .= pd_render_google_login_panel( 'register', $redirect_to );
        $output .= '<form class="pd-auth-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $output .= '<input type="hidden" name="action" value="pd_frontend_register" />';
        $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $redirect_to ) . '" />';
        $output .= wp_nonce_field( 'pd_frontend_register', 'pd_register_nonce', true, false );
        $output .= '<label><span>' . esc_html__( 'Nombre de usuario', 'pertenencia-digital' ) . '</span><input type="text" name="user_login" autocomplete="username" required /></label>';
        $output .= '<label><span>' . esc_html__( 'Correo electronico', 'pertenencia-digital' ) . '</span><input type="text" name="user_email" autocomplete="email" required /></label>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button">' . esc_html__( 'Crear cuenta', 'pertenencia-digital' ) . '</button>';
        $output .= '</form>';
        $output .= '<p class="pd-auth-card__alt">' . esc_html__( 'Tener una cuenta no abre por si solo el acceso privado. Estas secciones requieren un rol de colaborador musical, colaborador o administrador.', 'pertenencia-digital' ) . '</p>';
        $output .= '<div class="pd-auth-card__support">';
        $output .= '<article class="pd-auth-support-card"><strong>' . esc_html__( 'Ya tengo cuenta', 'pertenencia-digital' ) . '</strong><p>' . esc_html__( 'Si tu cuenta ya existe, entra directamente desde el formulario principal.', 'pertenencia-digital' ) . '</p><a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Iniciar sesion', 'pertenencia-digital' ) . '</a></article>';
        $output .= '<article class="pd-auth-support-card"><strong>' . esc_html__( 'Necesito recuperar acceso', 'pertenencia-digital' ) . '</strong><p>' . esc_html__( 'Si olvidaste tu contrasena, puedes restablecerla sin salir de esta pantalla.', 'pertenencia-digital' ) . '</p><a href="' . esc_url( $recover_url ) . '">' . esc_html__( 'Recuperar acceso', 'pertenencia-digital' ) . '</a></article>';
        $output .= '</div>';
    } else {
        $output .= '<h2 class="pd-auth-card__title">' . esc_html__( 'Iniciar sesion', 'pertenencia-digital' ) . '</h2>';
        $output .= '<p class="pd-auth-card__description">' . esc_html__( 'Usa tu usuario o correo para entrar a tu pertenencia, editar tu material y retomar tu flujo de trabajo.', 'pertenencia-digital' ) . '</p>';
        $output .= pd_render_google_login_panel( 'login', $redirect_to );
        $output .= '<form class="pd-auth-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $output .= '<input type="hidden" name="action" value="pd_frontend_login" />';
        $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $redirect_to ) . '" />';
        $output .= wp_nonce_field( 'pd_frontend_login', 'pd_auth_nonce', true, false );
        $output .= '<label><span>' . esc_html__( 'Usuario o correo electronico', 'pertenencia-digital' ) . '</span><input type="text" name="log" autocomplete="username" required /></label>';
        $output .= '<label><span>' . esc_html__( 'Contrasena', 'pertenencia-digital' ) . '</span><input type="password" name="pwd" autocomplete="current-password" required /></label>';
        $output .= '<label class="pd-auth-form__checkbox"><input type="checkbox" name="rememberme" value="forever" /><span>' . esc_html__( 'Mantener sesion iniciada', 'pertenencia-digital' ) . '</span></label>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button">' . esc_html__( 'Entrar a mi espacio', 'pertenencia-digital' ) . '</button>';
        $output .= '</form>';
        $output .= '<div class="pd-auth-card__support">';
        $output .= '<article class="pd-auth-support-card"><strong>' . esc_html__( '¿Olvidaste tu contraseña?', 'pertenencia-digital' ) . '</strong><p>' . esc_html__( 'Activa la recuperación sin salir del flujo frontal y recibe el enlace de restablecimiento por correo.', 'pertenencia-digital' ) . '</p><a href="' . esc_url( $recover_url ) . '">' . esc_html__( 'Recuperar acceso', 'pertenencia-digital' ) . '</a></article>';
        if ( '' !== $register_url ) {
            $output .= '<article class="pd-auth-support-card"><strong>' . esc_html__( 'Soy usuario nuevo', 'pertenencia-digital' ) . '</strong><p>' . esc_html__( 'Crea tu cuenta desde aqui y despues solicita o confirma el rol autorizado para entrar a las areas privadas.', 'pertenencia-digital' ) . '</p><a href="' . esc_url( $register_url ) . '">' . esc_html__( 'Registrarme', 'pertenencia-digital' ) . '</a></article>';
        } else {
            $output .= '<article class="pd-auth-support-card"><strong>' . esc_html__( 'Explorar el sitio', 'pertenencia-digital' ) . '</strong><p>' . esc_html__( 'Si todavía no necesitas entrar, puedes volver al sitio público y retomar el acceso después.', 'pertenencia-digital' ) . '</p><a href="' . esc_url( $home_url ) . '">' . esc_html__( 'Volver al sitio', 'pertenencia-digital' ) . '</a></article>';
        }
        $output .= '</div>';
    }

    $output .= '</div>';
    $output .= '</section>';

    return $output;
}

/**
 * Renderiza el estado de acceso restringido para paginas privadas de musica.
 *
 * @param array<string, mixed> $args Ajustes visuales y de contexto.
 */
function pd_render_music_access_gate_panel( array $args = [] ): string {
    $args = wp_parse_args(
        $args,
        [
            'context'     => 'membership',
            'redirect_to' => '',
            'intro'       => '',
        ]
    );

    $context     = pd_get_private_music_access_context( sanitize_key( (string) $args['context'] ) );
    $redirect_to = is_string( $args['redirect_to'] ) && '' !== $args['redirect_to']
        ? wp_validate_redirect( (string) $args['redirect_to'], pd_get_default_membership_url() )
        : ( get_permalink() ? get_permalink() : pd_get_default_membership_url() );
    $login_intro = is_string( $args['intro'] ) && '' !== trim( $args['intro'] ) ? trim( (string) $args['intro'] ) : $context['login_intro'];

    if ( ! is_user_logged_in() ) {
        return pd_render_login_panel(
            [
                'title'         => $context['login_title'],
                'intro'         => $login_intro,
                'redirect_to'   => $redirect_to,
                'show_register' => true,
            ]
        );
    }

    if ( pd_current_user_can_access_private_music_area() ) {
        return '';
    }

    $current_user   = wp_get_current_user();
    $switch_url     = wp_logout_url( pd_get_login_page_url( $redirect_to ) );
    $register_url   = pd_get_register_page_url( $redirect_to );
    $home_url       = home_url( '/' );
    $roles_required = __( 'Roles autorizados: colaborador musical, colaborador o administrador.', 'pertenencia-digital' );

    $output  = '<section class="pd-auth-shell pd-auth-shell--restricted">';
    $output .= '<div class="pd-auth-shell__intro">';
    $output .= '<p class="pd-auth-shell__eyebrow">' . esc_html( $context['eyebrow'] ) . '</p>';
    $output .= '<h2 class="pd-auth-shell__title">' . esc_html( $context['blocked_title'] ) . '</h2>';
    $output .= '<p class="pd-auth-shell__lead">' . esc_html( $context['blocked_intro'] ) . '</p>';
    $output .= '<div class="pd-auth-shell__features">';
    $output .= '<article class="pd-auth-feature"><strong>' . esc_html__( 'Cuenta actual', 'pertenencia-digital' ) . '</strong><span>' . esc_html( $current_user->display_name . ' / ' . $current_user->user_email ) . '</span></article>';
    $output .= '<article class="pd-auth-feature"><strong>' . esc_html__( 'Permisos requeridos', 'pertenencia-digital' ) . '</strong><span>' . esc_html( $roles_required ) . '</span></article>';
    $output .= '<article class="pd-auth-feature"><strong>' . esc_html__( 'Siguiente paso', 'pertenencia-digital' ) . '</strong><span>' . esc_html__( 'Cierra esta sesion para entrar con otra cuenta o registra una nueva y luego asignale el rol correcto.', 'pertenencia-digital' ) . '</span></article>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '<div class="pd-auth-card">';
    $output .= '<p class="pd-auth-state__eyebrow">' . esc_html__( 'Acceso restringido', 'pertenencia-digital' ) . '</p>';
    $output .= '<h3 class="pd-auth-card__title">' . esc_html__( 'Esta cuenta no tiene permisos suficientes', 'pertenencia-digital' ) . '</h3>';
    $output .= '<p class="pd-auth-card__description">' . esc_html__( 'Puedes cambiar de cuenta desde aqui o volver al sitio publico mientras resuelves el acceso.', 'pertenencia-digital' ) . '</p>';
    $output .= '<div class="pd-auth-state__actions">';
    $output .= '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $switch_url ) . '">' . esc_html__( 'Iniciar con otra cuenta', 'pertenencia-digital' ) . '</a>';
    if ( '' !== $register_url ) {
        $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $register_url ) . '">' . esc_html__( 'Registrarme', 'pertenencia-digital' ) . '</a>';
    }
    $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $home_url ) . '">' . esc_html__( 'Volver al sitio', 'pertenencia-digital' ) . '</a>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</section>';

    return $output;
}

/**
 * Busca el primer bloque por nombre dentro de una lista parseada.
 *
 * @param array<int, array<string, mixed>> $blocks Bloques parseados.
 * @param string                           $block_name Nombre del bloque.
 * @return array<string, mixed>
 */
function pd_find_first_block_attributes( array $blocks, string $block_name ): array {
    foreach ( $blocks as $block ) {
        if ( isset( $block['blockName'] ) && $block_name === $block['blockName'] ) {
            return isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
        }

        if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
            $found = pd_find_first_block_attributes( $block['innerBlocks'], $block_name );

            if ( ! empty( $found ) ) {
                return $found;
            }
        }
    }

    return [];
}

/**
 * Obtiene el contenido del template principal de acceso.
 */
function pd_get_access_template_content(): string {
    if ( function_exists( 'get_block_template' ) ) {
        $template = get_block_template( get_stylesheet() . '//acceso', 'wp_template' );

        if ( $template && ! empty( $template->content ) && is_string( $template->content ) ) {
            return $template->content;
        }
    }

    $template_path = trailingslashit( get_stylesheet_directory() ) . 'templates/acceso.html';

    if ( file_exists( $template_path ) ) {
        $content = file_get_contents( $template_path );

        return is_string( $content ) ? $content : '';
    }

    return '';
}

/**
 * Obtiene los atributos del bloque principal de acceso.
 *
 * @return array<string, mixed>
 */
function pd_get_main_login_panel_attributes(): array {
    static $attributes = null;

    if ( null !== $attributes ) {
        return $attributes;
    }

    $attributes = [];
    $content    = pd_get_access_template_content();

    if ( '' === $content ) {
        return $attributes;
    }

    $attributes = pd_find_first_block_attributes( parse_blocks( $content ), 'pertenencia-digital/login-panel' );

    return is_array( $attributes ) ? $attributes : [];
}

/**
 * Render callback del bloque de acceso privado para musica.
 *
 * @param array<string, mixed> $attributes Atributos del bloque.
 */
function pd_render_block_music_access_gate( array $attributes = [] ): string {
    $context                = isset( $attributes['context'] ) ? sanitize_key( (string) $attributes['context'] ) : 'study-repertoire';
    $intro                  = isset( $attributes['intro'] ) && is_string( $attributes['intro'] ) ? sanitize_text_field( $attributes['intro'] ) : '';
    $use_main_access_colors = ! isset( $attributes['useMainAccessColors'] ) || (bool) $attributes['useMainAccessColors'];
    $state                  = pd_current_user_can_access_private_music_area() ? 'is-allowed' : 'is-restricted';
    $classes = [
        'pd-music-access-gate-block',
        'pd-music-access-gate-block--' . $context,
        $state,
        $use_main_access_colors ? 'is-using-main-access-colors' : 'is-using-local-colors',
    ];
    $style_rules             = [];
    $color_source_attributes = $use_main_access_colors ? pd_get_main_login_panel_attributes() : $attributes;
    $visual_tokens           = pd_resolve_access_visual_tokens( $color_source_attributes );
    $gate_tokens             = pd_resolve_access_visual_tokens( $attributes );

    foreach (
        [
            '--pd-music-gate-eyebrow'            => $visual_tokens['eyebrowColor'] ?? '',
            '--pd-music-gate-title'              => $visual_tokens['titleColor'] ?? '',
            '--pd-music-gate-intro-text'         => $visual_tokens['introTextColor'] ?? '',
            '--pd-music-gate-intro-background'   => $visual_tokens['introBackground'] ?? '',
            '--pd-music-gate-intro-glow'         => $visual_tokens['introGlow'] ?? '',
            '--pd-music-gate-feature-background' => $visual_tokens['featureBackground'] ?? '',
            '--pd-music-gate-feature-text'       => $visual_tokens['featureText'] ?? '',
            '--pd-music-gate-card-background'    => $visual_tokens['cardBackground'] ?? '',
            '--pd-music-gate-card-text'          => $visual_tokens['cardText'] ?? '',
            '--pd-music-gate-card-border'        => $visual_tokens['cardBorder'] ?? '',
            '--pd-music-gate-field-background'   => $visual_tokens['fieldBackground'] ?? '',
            '--pd-music-gate-field-text'         => $visual_tokens['fieldText'] ?? '',
            '--pd-music-gate-field-border'       => $visual_tokens['fieldBorder'] ?? '',
            '--pd-music-gate-link'               => $visual_tokens['linkColor'] ?? '',
            '--pd-music-gate-link-hover'         => $visual_tokens['linkHoverColor'] ?? '',
            '--pd-music-gate-button-background'  => $visual_tokens['buttonBackground'] ?? '',
            '--pd-music-gate-button-text'        => $visual_tokens['buttonText'] ?? '',
            '--pd-music-gate-button-border'      => $visual_tokens['buttonBorder'] ?? '',
            '--pd-music-gate-support-background' => $visual_tokens['supportBackground'] ?? '',
        ] as $property => $value
    ) {
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $style_rules[] = $property . ':' . trim( $value );
        }
    }

    if ( ! $use_main_access_colors ) {
        foreach (
            [
                '--pd-music-gate-shell-background' => $gate_tokens['shellBackground'] ?? '',
                '--pd-music-gate-shell-border'     => $gate_tokens['shellBorder'] ?? '',
            ] as $property => $value
        ) {
            if ( is_string( $value ) && '' !== trim( $value ) ) {
                $style_rules[] = $property . ':' . trim( $value );
            }
        }
    }

    $wrapper_style = ! empty( $style_rules ) ? pd_build_theme_custom_property_style( $style_rules ) : '';

    if ( ! pd_current_user_can_access_private_music_area() ) {
        $content = pd_render_music_access_gate_panel(
            [
                'context' => $context,
                'intro'   => $intro,
            ]
        );

        $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
            ? get_block_wrapper_attributes(
                [
                    'class' => implode( ' ', $classes ),
                    'style' => '' !== $wrapper_style ? $wrapper_style : null,
                ]
            )
            : 'class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( '' !== $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : '' );

        return '<div ' . $wrapper_attributes . '>' . $content . '</div>';
    }

    if ( function_exists( 'render_block' ) ) {
        $protected_block = null;

        if ( 'rehearsals' === $context ) {
            $protected_block = [
                'blockName'    => 'wp-song-study/current-rehearsals',
                'attrs'        => [
                    'layoutWidth' => 'immersive',
                ],
                'innerBlocks'  => [],
                'innerHTML'    => '',
                'innerContent' => [],
            ];
        } elseif ( 'membership' === $context ) {
            $protected_block = [
                'blockName'    => 'wp-song-study/current-membership',
                'attrs'        => [],
                'innerBlocks'  => [],
                'innerHTML'    => '',
                'innerContent' => [],
            ];
        }

        if ( is_array( $protected_block ) ) {
            $protected_content = render_block( $protected_block );

            if ( '' !== trim( $protected_content ) ) {
                if ( 'rehearsals' === $context ) {
                    // The rehearsal planner owns its interactive layout; the theme gate must not wrap it.
                    return $protected_content;
                }

                $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
                    ? get_block_wrapper_attributes(
                        [
                            'class' => implode( ' ', $classes ),
                            'style' => '' !== $wrapper_style ? $wrapper_style : null,
                        ]
                    )
                    : 'class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( '' !== $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : '' );

                return '<div ' . $wrapper_attributes . '>' . $protected_content . '</div>';
            }
        }
    }

    $post = get_post();

    if ( ! $post instanceof WP_Post ) {
        return '';
    }

    $content = apply_filters( 'the_content', $post->post_content );

    if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
        return '';
    }

    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'class' => implode( ' ', $classes ),
                'style' => '' !== $wrapper_style ? $wrapper_style : null,
            ]
        )
        : 'class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( '' !== $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : '' );

    return '<div ' . $wrapper_attributes . '><div class="wp-block-group alignwide pd-editorial-surface pd-editorial-surface--body"><div class="wp-block-post-content is-layout-constrained">' . $content . '</div></div></div>';
}

/**
 * Renderiza el acceso compacto del header.
 */
function pd_render_account_access_menu( array $attributes = [] ): string {
    $current_url = '';

    if ( ! is_admin() ) {
        $current_url = home_url( add_query_arg( [] ) );
    }

    $panel_align          = isset( $attributes['panelAlign'] ) && in_array( $attributes['panelAlign'], [ 'start', 'end' ], true ) ? $attributes['panelAlign'] : 'end';
    $show_identity        = ! isset( $attributes['showIdentity'] ) || (bool) $attributes['showIdentity'];
    $show_email           = ! empty( $attributes['showEmail'] );
    $show_membership_link = ! isset( $attributes['showMembershipLink'] ) || (bool) $attributes['showMembershipLink'];
    $show_logout_link     = ! isset( $attributes['showLogoutLink'] ) || (bool) $attributes['showLogoutLink'];
    $avatar_size          = isset( $attributes['avatarSize'] ) ? max( 28, min( 96, absint( $attributes['avatarSize'] ) ) ) : 40;

    if ( is_user_logged_in() ) {
        $current_user   = wp_get_current_user();
        $membership_url = pd_get_default_membership_url();
        $logout_url     = wp_logout_url( $current_url ? $current_url : home_url( '/' ) );
        $menu_id        = wp_unique_id( 'pd-account-menu-' );
        $avatar         = get_avatar(
            $current_user->ID,
            $avatar_size,
            '',
            $current_user->display_name,
            [
                'class'   => 'pd-account-menu__avatar-image',
                'loading' => 'lazy',
            ]
        );

        $output  = '<div class="pd-account-menu" data-account-menu data-panel-align="' . esc_attr( $panel_align ) . '">';
        $output .= '<button type="button" class="pd-account-menu__trigger" data-account-menu-trigger aria-expanded="false" aria-haspopup="true" aria-controls="' . esc_attr( $menu_id ) . '" aria-label="' . esc_attr__( 'Abrir opciones de usuario', 'pertenencia-digital' ) . '">';
        $output .= '<span class="pd-account-menu__avatar">' . $avatar . '</span>';
        $output .= '<span class="pd-account-menu__caret" aria-hidden="true"></span>';
        $output .= '</button>';
        $output .= '<div id="' . esc_attr( $menu_id ) . '" class="pd-account-menu__panel" data-account-menu-panel hidden>';
        if ( $show_identity ) {
            $output .= '<p class="pd-account-menu__identity">';
            $output .= '<strong>' . esc_html( $current_user->display_name ) . '</strong>';
            if ( $show_email ) {
                $output .= '<span>' . esc_html( $current_user->user_email ) . '</span>';
            }
            $output .= '</p>';
        }
        if ( $show_membership_link ) {
            $output .= '<a class="pd-account-menu__link" href="' . esc_url( $membership_url ) . '">' . esc_html__( 'Mi pertenencia', 'pertenencia-digital' ) . '</a>';
        }
        if ( $show_logout_link ) {
            $output .= '<a class="pd-account-menu__link" href="' . esc_url( $logout_url ) . '">' . esc_html__( 'Cerrar sesion', 'pertenencia-digital' ) . '</a>';
        }
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    $login_url = pd_get_login_page_url( $current_url ? $current_url : pd_get_default_membership_url() );
    $login_label = isset( $attributes['loginLabel'] ) && '' !== trim( (string) $attributes['loginLabel'] ) ? (string) $attributes['loginLabel'] : __( 'Acceso', 'pertenencia-digital' );

    return '<a class="pd-account-access__login" href="' . esc_url( $login_url ) . '">' . esc_html( $login_label ) . '</a>';
}

/**
 * Construye un atributo style seguro para variables CSS del tema.
 *
 * @param array<int, string> $rules Reglas en formato `--propiedad:valor`.
 * @return string
 */
function pd_build_theme_custom_property_style( array $rules ): string {
    $declarations = [];

    foreach ( $rules as $rule ) {
        if ( ! is_string( $rule ) || '' === trim( $rule ) || false === strpos( $rule, ':' ) ) {
            continue;
        }

        [ $property, $value ] = array_map( 'trim', explode( ':', $rule, 2 ) );

        if ( '' === $property || '' === $value || 0 !== strpos( $property, '--pd-' ) ) {
            continue;
        }

        $property = preg_replace( '/[^a-z0-9\-_]/i', '', $property );
        $value    = preg_replace( '/[{};<>]/', '', $value );

        if ( '' === $property || '' === $value ) {
            continue;
        }

        $declarations[] = $property . ':' . $value;
    }

    return implode( ';', $declarations );
}

/**
 * Obtiene un valor anidado dentro de atributos de bloque.
 *
 * @param array<string, mixed> $attributes Atributos del bloque.
 * @param array<int, string>   $path Ruta a resolver.
 */
function pd_get_nested_block_attribute_string( array $attributes, array $path ): string {
    $value = $attributes;

    foreach ( $path as $segment ) {
        if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
            return '';
        }

        $value = $value[ $segment ];
    }

    return is_string( $value ) ? trim( $value ) : '';
}

/**
 * Resuelve tokens visuales compartidos entre paneles de acceso y gates privados.
 *
 * Prioridad:
 * 1. Controles locales del bloque.
 * 2. Valores definidos desde el area de Diseño (`style` del bloque).
 * 3. Defaults del tema via CSS.
 *
 * @param array<string, mixed> $attributes Atributos del bloque.
 * @return array<string, string>
 */
function pd_resolve_access_visual_tokens( array $attributes ): array {
    $design_background    = pd_get_nested_block_attribute_string( $attributes, [ 'style', 'color', 'background' ] );
    $design_gradient      = pd_get_nested_block_attribute_string( $attributes, [ 'style', 'color', 'gradient' ] );
    $design_text          = pd_get_nested_block_attribute_string( $attributes, [ 'style', 'color', 'text' ] );
    $design_border        = pd_get_nested_block_attribute_string( $attributes, [ 'style', 'border', 'color' ] );
    $design_link          = pd_get_nested_block_attribute_string( $attributes, [ 'style', 'elements', 'link', 'color', 'text' ] );
    $design_button_bg     = pd_get_nested_block_attribute_string( $attributes, [ 'style', 'elements', 'button', 'color', 'background' ] );
    $design_button_text   = pd_get_nested_block_attribute_string( $attributes, [ 'style', 'elements', 'button', 'color', 'text' ] );
    $design_button_border = pd_get_nested_block_attribute_string( $attributes, [ 'style', 'elements', 'button', 'border', 'color' ] );
    $shared_background    = '' !== $design_gradient ? $design_gradient : $design_background;

    return [
        'shellBackground'   => isset( $attributes['shellBackground'] ) && is_string( $attributes['shellBackground'] ) ? trim( $attributes['shellBackground'] ) : $shared_background,
        'shellBorder'       => isset( $attributes['shellBorder'] ) && is_string( $attributes['shellBorder'] ) ? trim( $attributes['shellBorder'] ) : $design_border,
        'eyebrowColor'      => isset( $attributes['eyebrowColor'] ) && is_string( $attributes['eyebrowColor'] ) ? trim( $attributes['eyebrowColor'] ) : $design_text,
        'titleColor'        => isset( $attributes['titleColor'] ) && is_string( $attributes['titleColor'] ) ? trim( $attributes['titleColor'] ) : $design_text,
        'introTextColor'    => isset( $attributes['introTextColor'] ) && is_string( $attributes['introTextColor'] ) ? trim( $attributes['introTextColor'] ) : $design_text,
        'introBackground'   => isset( $attributes['introBackground'] ) && is_string( $attributes['introBackground'] ) ? trim( $attributes['introBackground'] ) : $shared_background,
        'introGlow'         => isset( $attributes['introGlow'] ) && is_string( $attributes['introGlow'] ) ? trim( $attributes['introGlow'] ) : '',
        'featureBackground' => isset( $attributes['featureBackground'] ) && is_string( $attributes['featureBackground'] ) ? trim( $attributes['featureBackground'] ) : $design_background,
        'featureText'       => isset( $attributes['featureText'] ) && is_string( $attributes['featureText'] ) ? trim( $attributes['featureText'] ) : $design_text,
        'cardBackground'    => isset( $attributes['cardBackground'] ) && is_string( $attributes['cardBackground'] ) ? trim( $attributes['cardBackground'] ) : $design_background,
        'cardText'          => isset( $attributes['cardText'] ) && is_string( $attributes['cardText'] ) ? trim( $attributes['cardText'] ) : $design_text,
        'cardBorder'        => isset( $attributes['cardBorder'] ) && is_string( $attributes['cardBorder'] ) ? trim( $attributes['cardBorder'] ) : $design_border,
        'fieldBackground'   => isset( $attributes['fieldBackground'] ) && is_string( $attributes['fieldBackground'] ) ? trim( $attributes['fieldBackground'] ) : $design_background,
        'fieldText'         => isset( $attributes['fieldText'] ) && is_string( $attributes['fieldText'] ) ? trim( $attributes['fieldText'] ) : $design_text,
        'fieldBorder'       => isset( $attributes['fieldBorder'] ) && is_string( $attributes['fieldBorder'] ) ? trim( $attributes['fieldBorder'] ) : $design_border,
        'linkColor'         => isset( $attributes['linkColor'] ) && is_string( $attributes['linkColor'] ) ? trim( $attributes['linkColor'] ) : $design_link,
        'linkHoverColor'    => isset( $attributes['linkHoverColor'] ) && is_string( $attributes['linkHoverColor'] ) ? trim( $attributes['linkHoverColor'] ) : '',
        'buttonBackground'  => isset( $attributes['buttonBackground'] ) && is_string( $attributes['buttonBackground'] ) ? trim( $attributes['buttonBackground'] ) : $design_button_bg,
        'buttonText'        => isset( $attributes['buttonText'] ) && is_string( $attributes['buttonText'] ) ? trim( $attributes['buttonText'] ) : $design_button_text,
        'buttonBorder'      => isset( $attributes['buttonBorder'] ) && is_string( $attributes['buttonBorder'] ) ? trim( $attributes['buttonBorder'] ) : $design_button_border,
        'supportBackground' => isset( $attributes['supportBackground'] ) && is_string( $attributes['supportBackground'] ) ? trim( $attributes['supportBackground'] ) : $design_background,
    ];
}

/**
 * Render callback del bloque de acceso compacto.
 */
function pd_render_block_account_access( array $attributes = [], string $content = '', ?WP_Block $block = null ): string {
    $style_rules = [];
    $trigger_size = isset( $attributes['triggerSize'] ) && in_array( $attributes['triggerSize'], [ 'small', 'medium', 'large' ], true ) ? $attributes['triggerSize'] : 'medium';
    $trigger_scale = isset( $attributes['triggerScale'] ) ? max( 70, min( 150, (int) $attributes['triggerScale'] ) ) : 100;

    foreach (
        [
            '--pd-account-trigger-background' => $attributes['triggerBackground'] ?? '',
            '--pd-account-trigger-text'       => $attributes['triggerText'] ?? '',
            '--pd-account-trigger-border'     => $attributes['triggerBorder'] ?? '',
            '--pd-account-panel-background'   => $attributes['panelBackground'] ?? '',
            '--pd-account-panel-text'         => $attributes['panelText'] ?? '',
            '--pd-account-panel-border'       => $attributes['panelBorder'] ?? '',
            '--pd-account-panel-width'        => $attributes['panelWidth'] ?? '',
            '--pd-account-panel-font-size'    => isset( $attributes['panelTextSize'] ) ? max( 12, min( 28, (int) $attributes['panelTextSize'] ) ) . 'px' : '',
        ] as $property => $value
    ) {
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $style_rules[] = $property . ':' . trim( $value );
        }
    }

    $size_presets = [
        'small'  => [
            '--pd-account-trigger-min-height:2.35rem',
            '--pd-account-trigger-padding-y:0.38rem',
            '--pd-account-trigger-padding-x:0.68rem',
            '--pd-account-trigger-gap:0.5rem',
            '--pd-account-trigger-font-size:0.84rem',
            '--pd-account-avatar-size:2rem',
            '--pd-account-caret-size:0.58rem',
        ],
        'medium' => [
            '--pd-account-trigger-min-height:2.75rem',
            '--pd-account-trigger-padding-y:0.5rem',
            '--pd-account-trigger-padding-x:0.85rem',
            '--pd-account-trigger-gap:0.65rem',
            '--pd-account-trigger-font-size:0.92rem',
            '--pd-account-avatar-size:2.25rem',
            '--pd-account-caret-size:0.65rem',
        ],
        'large'  => [
            '--pd-account-trigger-min-height:3.1rem',
            '--pd-account-trigger-padding-y:0.62rem',
            '--pd-account-trigger-padding-x:1rem',
            '--pd-account-trigger-gap:0.72rem',
            '--pd-account-trigger-font-size:0.98rem',
            '--pd-account-avatar-size:2.55rem',
            '--pd-account-caret-size:0.72rem',
        ],
    ];

    $style_rules = array_merge( $size_presets[ $trigger_size ], $style_rules );
    $style_rules[] = '--pd-account-trigger-scale:' . ( $trigger_scale / 100 );

    $wrapper_style = ! empty( $style_rules ) ? pd_build_theme_custom_property_style( $style_rules ) : '';

    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'class' => 'pd-account-access-block',
                'style' => '' !== $wrapper_style ? $wrapper_style : null,
            ]
        )
        : 'class="pd-account-access-block"' . ( '' !== $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : '' );

    return '<div ' . $wrapper_attributes . '>' . pd_render_account_access_menu( $attributes ) . '</div>';
}

/**
 * Render callback del bloque de panel de acceso.
 */
function pd_render_block_login_panel( array $attributes = [], string $content = '', ?WP_Block $block = null ): string {
    $style_rules = [];
    $visual_tokens = pd_resolve_access_visual_tokens( $attributes );
    $args = [
        'title' => isset( $attributes['title'] ) && '' !== trim( (string) $attributes['title'] )
            ? sanitize_text_field( (string) $attributes['title'] )
            : __( 'Accede a tu pertenencia digital', 'pertenencia-digital' ),
        'intro' => isset( $attributes['intro'] ) && '' !== trim( (string) $attributes['intro'] )
            ? sanitize_text_field( (string) $attributes['intro'] )
            : __( 'Usa esta pantalla para iniciar sesión, recuperar tu contraseña y volver a tu espacio con una interfaz frontal más clara y estable.', 'pertenencia-digital' ),
    ];

    foreach (
        [
            '--pd-login-custom-eyebrow'           => $visual_tokens['eyebrowColor'] ?? '',
            '--pd-login-custom-title'             => $visual_tokens['titleColor'] ?? '',
            '--pd-login-custom-intro-text'        => $visual_tokens['introTextColor'] ?? '',
            '--pd-login-custom-intro-background'  => $visual_tokens['introBackground'] ?? '',
            '--pd-login-custom-intro-glow'        => $visual_tokens['introGlow'] ?? '',
            '--pd-login-custom-feature-background'=> $visual_tokens['featureBackground'] ?? '',
            '--pd-login-custom-feature-text'      => $visual_tokens['featureText'] ?? '',
            '--pd-login-custom-card-background'   => $visual_tokens['cardBackground'] ?? '',
            '--pd-login-custom-card-text'         => $visual_tokens['cardText'] ?? '',
            '--pd-login-custom-card-border'       => $visual_tokens['cardBorder'] ?? '',
            '--pd-login-custom-field-background'  => $visual_tokens['fieldBackground'] ?? '',
            '--pd-login-custom-field-text'        => $visual_tokens['fieldText'] ?? '',
            '--pd-login-custom-field-border'      => $visual_tokens['fieldBorder'] ?? '',
            '--pd-login-custom-link'              => $visual_tokens['linkColor'] ?? '',
            '--pd-login-custom-link-hover'        => $visual_tokens['linkHoverColor'] ?? '',
            '--pd-login-custom-button-background' => $visual_tokens['buttonBackground'] ?? '',
            '--pd-login-custom-button-text'       => $visual_tokens['buttonText'] ?? '',
            '--pd-login-custom-button-border'     => $visual_tokens['buttonBorder'] ?? '',
            '--pd-login-custom-support-background'=> $visual_tokens['supportBackground'] ?? '',
        ] as $property => $value
    ) {
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $style_rules[] = $property . ':' . trim( $value );
        }
    }

    $wrapper_style = ! empty( $style_rules ) ? pd_build_theme_custom_property_style( $style_rules ) : '';

    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'class' => 'pd-login-panel-block',
                'style' => '' !== $wrapper_style ? $wrapper_style : null,
            ]
        )
        : 'class="pd-login-panel-block"' . ( '' !== $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : '' );

    return '<div ' . $wrapper_attributes . '>' . pd_render_login_panel( $args ) . '</div>';
}

/**
 * Determina si una URL del menu corresponde a la pagina actual.
 *
 * @param string $url URL del item.
 */
function pd_is_current_navigation_url( string $url ): bool {
    if ( is_admin() || '' === $url ) {
        return false;
    }

    $site_host    = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
    $target_host  = (string) wp_parse_url( $url, PHP_URL_HOST );
    $target_path  = (string) wp_parse_url( $url, PHP_URL_PATH );
    $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
    $current_path = (string) wp_parse_url( home_url( $request_uri ), PHP_URL_PATH );

    if ( '' !== $target_host && $target_host !== $site_host ) {
        return false;
    }

    $target_path  = '' !== $target_path ? untrailingslashit( $target_path ) : '/';
    $current_path = '' !== $current_path ? untrailingslashit( $current_path ) : '/';

    return $target_path === $current_path;
}

/**
 * Convierte bloques de navegacion en una estructura ligera de items.
 *
 * @param array<int, array<string, mixed>> $blocks Bloques parseados.
 * @return array<int, array<string, mixed>>
 */
function pd_get_navigation_items_from_blocks( array $blocks ): array {
    $items = [];

    foreach ( $blocks as $block ) {
        $block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
        $attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];

        if ( 'core/navigation-link' === $block_name || 'core/navigation-submenu' === $block_name ) {
            $label = isset( $attrs['label'] ) ? wp_strip_all_tags( (string) $attrs['label'] ) : '';

            if ( '' === $label && isset( $block['innerHTML'] ) ) {
                $label = wp_strip_all_tags( (string) $block['innerHTML'] );
            }

            $items[] = [
                'label'            => '' !== $label ? $label : __( 'Enlace', 'pertenencia-digital' ),
                'url'              => isset( $attrs['url'] ) ? (string) $attrs['url'] : '',
                'opens_in_new_tab' => ! empty( $attrs['opensInNewTab'] ),
                'rel'              => isset( $attrs['rel'] ) ? (string) $attrs['rel'] : '',
                'children'         => pd_get_navigation_items_from_blocks(
                    isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : []
                ),
            ];

            continue;
        }

        if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
            $items = array_merge( $items, pd_get_navigation_items_from_blocks( $block['innerBlocks'] ) );
        }
    }

    return $items;
}

/**
 * Obtiene items a partir de una navegacion FSE por referencia.
 *
 * @param int $navigation_ref ID del post wp_navigation.
 * @return array<int, array<string, mixed>>
 */
function pd_get_navigation_items_from_ref( int $navigation_ref ): array {
    if ( $navigation_ref <= 0 ) {
        return [];
    }

    $navigation_post = get_post( $navigation_ref );

    if ( ! $navigation_post instanceof WP_Post || '' === $navigation_post->post_content ) {
        return [];
    }

    return pd_get_navigation_items_from_blocks( parse_blocks( $navigation_post->post_content ) );
}

/**
 * Obtiene items desde un menu clasico asignado a una ubicacion.
 *
 * @param string $location Ubicacion del menu.
 * @return array<int, array<string, mixed>>
 */
function pd_get_navigation_items_from_location( string $location ): array {
    $locations = get_nav_menu_locations();

    if ( '' === $location || empty( $locations[ $location ] ) ) {
        return [];
    }

    $menu_items = wp_get_nav_menu_items(
        (int) $locations[ $location ],
        [
            'update_post_term_cache' => false,
        ]
    );

    if ( ! is_array( $menu_items ) ) {
        return [];
    }

    $items_by_parent = [];

    foreach ( $menu_items as $menu_item ) {
        if ( ! $menu_item instanceof WP_Post ) {
            continue;
        }

        $parent_id                     = (int) $menu_item->menu_item_parent;
        $items_by_parent[ $parent_id ] = $items_by_parent[ $parent_id ] ?? [];
        $items_by_parent[ $parent_id ][] = $menu_item;
    }

    $build_tree = static function ( int $parent_id ) use ( &$build_tree, $items_by_parent ): array {
        $branch = [];

        foreach ( $items_by_parent[ $parent_id ] ?? [] as $menu_item ) {
            $branch[] = [
                'label'            => $menu_item->title,
                'url'              => $menu_item->url,
                'opens_in_new_tab' => '_blank' === $menu_item->target,
                'rel'              => (string) $menu_item->xfn,
                'children'         => $build_tree( (int) $menu_item->ID ),
            ];
        }

        return $branch;
    };

    return $build_tree( 0 );
}

/**
 * Marca items activos si apuntan a la URL actual o contienen un hijo activo.
 *
 * @param array<int, array<string, mixed>> $items Items del menu.
 * @return bool
 */
function pd_mark_current_navigation_items( array &$items ): bool {
    $has_current = false;

    foreach ( $items as &$item ) {
        $child_current = false;

        if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
            $child_current = pd_mark_current_navigation_items( $item['children'] );
        }

        $item['current'] = pd_is_current_navigation_url( isset( $item['url'] ) ? (string) $item['url'] : '' ) || $child_current;
        $has_current     = $has_current || ! empty( $item['current'] );
    }

    return $has_current;
}

/**
 * Obtiene los items finales del bloque de navegacion del tema.
 *
 * @param array<string, mixed> $attributes Atributos del bloque.
 * @return array<int, array<string, mixed>>
 */
function pd_get_site_navigation_items( array $attributes ): array {
    $items = [];

    if ( ! empty( $attributes['ref'] ) ) {
        $items = pd_get_navigation_items_from_ref( (int) $attributes['ref'] );
    }

    if ( empty( $items ) ) {
        $location = isset( $attributes['menuLocation'] ) ? (string) $attributes['menuLocation'] : 'menu_principal';
        $items    = pd_get_navigation_items_from_location( $location );
    }

    if ( empty( $items ) && 'menu_principal' !== ( $attributes['menuLocation'] ?? 'menu_principal' ) ) {
        $items = pd_get_navigation_items_from_location( 'menu_principal' );
    }

    if ( ! empty( $items ) ) {
        pd_mark_current_navigation_items( $items );
    }

    return $items;
}

/**
 * Renderiza recursivamente una rama del menu.
 *
 * @param array<int, array<string, mixed>> $items Items a renderizar.
 * @param int                              $level Nivel actual.
 * @param int                              $index Contador global para animacion.
 */
function pd_render_site_navigation_list( array $items, int $level = 0, int &$index = 0 ): string {
    if ( empty( $items ) ) {
        return '';
    }

    $output = '<ul class="pd-site-navigation__list pd-site-navigation__list--level-' . $level . '">';

    foreach ( $items as $item ) {
        $has_children = ! empty( $item['children'] ) && is_array( $item['children'] );
        $is_current   = ! empty( $item['current'] );
        $item_classes = 'pd-site-navigation__item';
        $item_index   = $index;

        if ( $has_children ) {
            $item_classes .= ' has-children';
        }

        if ( $is_current ) {
            $item_classes .= ' is-current';
        }

        $style = ' style="--pd-nav-index:' . (int) $item_index . ';"';

        $output .= '<li class="' . esc_attr( $item_classes ) . '"' . $style . '>';
        ++$index;

        $link_classes = 'pd-site-navigation__link';
        $target       = ! empty( $item['opens_in_new_tab'] ) ? ' target="_blank"' : '';
        $rel          = isset( $item['rel'] ) ? trim( (string) $item['rel'] ) : '';

        if ( ! empty( $item['opens_in_new_tab'] ) ) {
            $rel = trim( $rel . ' noopener noreferrer' );
        }

        $aria_current = $is_current ? ' aria-current="page"' : '';
        $rel_attr     = '' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '';

        if ( ! empty( $item['url'] ) ) {
            $output .= '<a class="' . esc_attr( $link_classes ) . '" href="' . esc_url( (string) $item['url'] ) . '"' . $target . $rel_attr . $aria_current . '>' . esc_html( (string) $item['label'] ) . '</a>';
        } else {
            $output .= '<span class="' . esc_attr( $link_classes . ' pd-site-navigation__link--label' ) . '">' . esc_html( (string) $item['label'] ) . '</span>';
        }

        if ( $has_children ) {
            $output .= pd_render_site_navigation_list( $item['children'], $level + 1, $index );
        }

        $output .= '</li>';
    }

    $output .= '</ul>';

    return $output;
}

/**
 * Render callback del bloque de navegacion del tema.
 *
 * @param array<string, mixed> $attributes Atributos del bloque.
 */
function pd_render_block_site_navigation( array $attributes = [], string $content = '', ?WP_Block $block = null ): string {
    $items = pd_get_site_navigation_items( $attributes );

    if ( empty( $items ) ) {
        if ( current_user_can( 'edit_theme_options' ) ) {
            $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
                ? get_block_wrapper_attributes(
                    [
                        'class' => 'pd-site-navigation-block pd-site-navigation-block--empty',
                    ]
                )
                : 'class="pd-site-navigation-block pd-site-navigation-block--empty"';

            return '<div ' . $wrapper_attributes . '><div class="pd-site-navigation pd-site-navigation--empty">' . esc_html__( 'Asigna un menu para mostrar la navegacion.', 'pertenencia-digital' ) . '</div></div>';
        }

        return '';
    }

    $panel_id      = wp_unique_id( 'pd-site-navigation-' );
    $toggle_label  = isset( $attributes['toggleLabel'] ) && '' !== (string) $attributes['toggleLabel'] ? (string) $attributes['toggleLabel'] : __( 'Menu', 'pertenencia-digital' );
    $screen_reader = __( 'Abrir menu principal', 'pertenencia-digital' );
    $index         = 0;
    $panel_align   = isset( $attributes['panelAlign'] ) && in_array( $attributes['panelAlign'], [ 'start', 'end' ], true ) ? $attributes['panelAlign'] : 'start';
    $trigger_size = isset( $attributes['triggerSize'] ) && in_array( $attributes['triggerSize'], [ 'small', 'medium', 'large' ], true ) ? $attributes['triggerSize'] : 'medium';
    $trigger_scale = isset( $attributes['triggerScale'] ) ? max( 70, min( 150, (int) $attributes['triggerScale'] ) ) : 100;
    $hide_label_mobile = ! empty( $attributes['hideLabelOnMobile'] );
    $close_on_item_click = ! isset( $attributes['closeOnItemClick'] ) || (bool) $attributes['closeOnItemClick'];
    $stagger_step = isset( $attributes['staggerStep'] ) ? max( 0, absint( $attributes['staggerStep'] ) ) : 45;
    $style_rules = [];

    foreach (
        [
            '--pd-nav-trigger-background'   => $attributes['triggerBackground'] ?? '',
            '--pd-nav-trigger-text'         => $attributes['triggerText'] ?? '',
            '--pd-nav-trigger-border'       => $attributes['triggerBorder'] ?? '',
            '--pd-nav-panel-background'     => $attributes['panelBackground'] ?? '',
            '--pd-nav-panel-text'           => $attributes['panelText'] ?? '',
            '--pd-nav-panel-border'         => $attributes['panelBorder'] ?? '',
            '--pd-nav-item-background'      => $attributes['itemBackground'] ?? '',
            '--pd-nav-item-border'          => $attributes['itemBorder'] ?? '',
            '--pd-nav-item-hover-background'=> $attributes['itemHoverBackground'] ?? '',
            '--pd-nav-item-hover-border'    => $attributes['itemHoverBorder'] ?? '',
            '--pd-nav-item-hover-text'      => $attributes['itemHoverText'] ?? '',
            '--pd-nav-panel-width'          => $attributes['panelWidth'] ?? '',
            '--pd-nav-item-font-size'       => isset( $attributes['menuTextSize'] ) ? max( 12, min( 28, (int) $attributes['menuTextSize'] ) ) . 'px' : '',
            '--pd-nav-stagger-step'         => $stagger_step ? $stagger_step . 'ms' : '0ms',
        ] as $property => $value
    ) {
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $style_rules[] = $property . ':' . trim( $value );
        }
    }

    $size_presets = [
        'small'  => [
            '--pd-nav-trigger-min-height:2.35rem',
            '--pd-nav-trigger-padding-y:0.38rem',
            '--pd-nav-trigger-padding-x:0.7rem',
            '--pd-nav-trigger-gap:0.52rem',
            '--pd-nav-trigger-font-size:0.84rem',
            '--pd-nav-icon-width:0.92rem',
        ],
        'medium' => [
            '--pd-nav-trigger-min-height:2.75rem',
            '--pd-nav-trigger-padding-y:0.5rem',
            '--pd-nav-trigger-padding-x:0.85rem',
            '--pd-nav-trigger-gap:0.7rem',
            '--pd-nav-trigger-font-size:0.92rem',
            '--pd-nav-icon-width:1rem',
        ],
        'large'  => [
            '--pd-nav-trigger-min-height:3.1rem',
            '--pd-nav-trigger-padding-y:0.62rem',
            '--pd-nav-trigger-padding-x:1rem',
            '--pd-nav-trigger-gap:0.76rem',
            '--pd-nav-trigger-font-size:0.98rem',
            '--pd-nav-icon-width:1.08rem',
        ],
    ];

    $style_rules = array_merge( $size_presets[ $trigger_size ], $style_rules );
    $style_rules[] = '--pd-nav-trigger-scale:' . ( $trigger_scale / 100 );

    $wrapper_style = ! empty( $style_rules ) ? pd_build_theme_custom_property_style( $style_rules ) : '';

    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'class' => 'pd-site-navigation-block',
                'style' => '' !== $wrapper_style ? $wrapper_style : null,
            ]
        )
        : 'class="pd-site-navigation-block"' . ( '' !== $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : '' );

    $output  = '<div ' . $wrapper_attributes . '>';
    $output .= '<nav class="pd-site-navigation" data-site-navigation data-panel-align="' . esc_attr( $panel_align ) . '" data-close-on-item-click="' . ( $close_on_item_click ? 'true' : 'false' ) . '" data-hide-label-mobile="' . ( $hide_label_mobile ? 'true' : 'false' ) . '" aria-label="' . esc_attr__( 'Navegacion principal', 'pertenencia-digital' ) . '">';
    $output .= '<button type="button" class="pd-site-navigation__toggle" data-site-navigation-toggle aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">';
    $output .= '<span class="screen-reader-text">' . esc_html( $screen_reader ) . '</span>';
    $output .= '<span class="pd-site-navigation__toggle-label" aria-hidden="true">' . esc_html( $toggle_label ) . '</span>';
    $output .= '<span class="pd-site-navigation__toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>';
    $output .= '</button>';
    $output .= '<div id="' . esc_attr( $panel_id ) . '" class="pd-site-navigation__panel" data-site-navigation-panel hidden>';
    $output .= pd_render_site_navigation_list( $items, 0, $index );
    $output .= '</div>';
    $output .= '</nav>';
    $output .= '</div>';

    return $output;
}

/**
 * Obtiene la pagina raiz publicada de una seccion.
 */
function pd_get_section_root_page( string $path ): ?WP_Post {
    $section_page = get_page_by_path( $path );

    return $section_page instanceof WP_Post ? $section_page : null;
}

/**
 * Obtiene la pagina raiz de la seccion Musica.
 */
function pd_get_music_root_page(): ?WP_Post {
    return pd_get_section_root_page( 'musica' );
}

/**
 * Construye una etiqueta legible para una seccion segun su slug.
 */
function pd_get_section_label( string $parent_path = 'musica' ): string {
    $root_page = pd_get_section_root_page( $parent_path );

    if ( $root_page instanceof WP_Post ) {
        return wp_strip_all_tags( get_the_title( $root_page ) );
    }

    if ( 'musica' === $parent_path ) {
        return __( 'Musica', 'pertenencia-digital' );
    }

    return ucwords( str_replace( [ '-', '_' ], ' ', $parent_path ) );
}

/**
 * Devuelve etiquetas auxiliares para la subnavegacion de seccion.
 *
 * @return array{section:string, aria:string, empty:string}
 */
function pd_get_section_navigation_labels( string $parent_path = 'musica' ): array {
    $section_label = pd_get_section_label( $parent_path );

    return [
        'section' => $section_label,
        'aria'    => sprintf(
            /* translators: %s: title of the section shown in the submenu. */
            __( 'Submenu de %s', 'pertenencia-digital' ),
            $section_label
        ),
        'empty'   => sprintf(
            /* translators: %s: title of the section without published child pages. */
            __( 'No hay paginas hijas publicadas en la seccion %s.', 'pertenencia-digital' ),
            $section_label
        ),
    ];
}

/**
 * Obtiene las paginas hijas publicadas de una seccion.
 *
 * @param string $parent_path Slug base de la seccion.
 * @return array<int, WP_Post>
 */
function pd_get_section_child_pages( string $parent_path = 'musica' ): array {
    $section_root = pd_get_section_root_page( $parent_path );

    if ( ! $section_root instanceof WP_Post ) {
        return [];
    }

    $pages = get_pages(
        [
            'child_of'    => 0,
            'parent'      => (int) $section_root->ID,
            'sort_column' => 'menu_order,post_title',
            'sort_order'  => 'ASC',
            'post_status' => 'publish',
        ]
    );

    return is_array( $pages ) ? array_values( array_filter( $pages, static fn ( $page ) => $page instanceof WP_Post ) ) : [];
}

/**
 * Obtiene las paginas hijas publicadas de Musica.
 *
 * @param string $parent_path Slug base de la seccion.
 * @return array<int, WP_Post>
 */
function pd_get_music_child_pages( string $parent_path = 'musica' ): array {
    return pd_get_section_child_pages( $parent_path );
}

/**
 * Devuelve grupos editoriales para la subnavegacion de Tecnologias y Web.
 *
 * @param array<int, WP_Post> $pages Paginas hijas publicadas.
 * @return array<int, array{label:string,kind:string,items:array<int, WP_Post>}>
 */
function pd_get_technology_subnavigation_groups( array $pages ): array {
    $pages_by_slug = [];
    $root_page     = pd_get_section_root_page( 'tecnologias-web' );

    if ( $root_page instanceof WP_Post ) {
        $pages_by_slug['__root'] = $root_page;
    }

    foreach ( $pages as $page ) {
        if ( $page instanceof WP_Post ) {
            $pages_by_slug[ $page->post_name ] = $page;
        }
    }

    $group_blueprint = [
        [
            'label' => __( 'Pestañas principales', 'pertenencia-digital' ),
            'kind'  => 'primary',
            'slugs' => [
                '__root',
                'web',
                'tecnologias-digitales',
                'tickets',
            ],
        ],
        [
            'label' => __( 'Web: entender antes de contratar', 'pertenencia-digital' ),
            'kind'  => 'context',
            'slugs' => [
                'enfoque-tecnologico',
                'quieres-tu-propio-espacio-digital',
            ],
        ],
        [
            'label' => __( 'Web: espacio compartido y presencia propia', 'pertenencia-digital' ),
            'kind'  => 'web',
            'slugs' => [
                'presencia-basica-colaboracion',
                'presencia-basica',
            ],
        ],
        [
            'label' => __( 'Presencia propia, base mensual y WordPress', 'pertenencia-digital' ),
            'kind'  => 'web',
            'slugs' => [
                'auxilio-wordpress',
            ],
        ],
        [
            'label' => __( 'Operación profesional y sistemas', 'pertenencia-digital' ),
            'kind'  => 'web',
            'slugs' => [
                'sitio-profesional',
                'sitio-profesional-self-admin',
                'sitio-profesional-implementacion',
            ],
        ],
        [
            'label' => __( 'Tecnologías digitales y casos', 'pertenencia-digital' ),
            'kind'  => 'technology',
            'slugs' => [
                'necesito-trabajo-multimedia-por-comision',
                'servicio-tecnico-digital',
                'consultoria-tecnologias-digitales',
                'proyectos',
            ],
        ],
    ];

    $groups = [];

    foreach ( $group_blueprint as $group ) {
        $items = [];

        foreach ( $group['slugs'] as $slug ) {
            if ( isset( $pages_by_slug[ $slug ] ) ) {
                $items[] = $pages_by_slug[ $slug ];
            }
        }

        if ( ! empty( $items ) ) {
            $groups[] = [
                'label' => $group['label'],
                'kind'  => $group['kind'] ?? 'default',
                'items' => $items,
            ];
        }
    }

    return $groups;
}

/**
 * Devuelve el contexto de directorio para la navegacion de Tecnologias y Web.
 *
 * @param array<int, WP_Post> $pages Paginas hijas publicadas.
 * @return array{trail:array<int,array{label:string,url:string,current:bool}>,label:string,items:array<int,WP_Post>}
 */
function pd_get_technology_directory_context( array $pages, int $current_id ): array {
    $pages_by_slug = [];
    $root_page     = pd_get_section_root_page( 'tecnologias-web' );

    if ( $root_page instanceof WP_Post ) {
        $pages_by_slug['__root'] = $root_page;
    }

    foreach ( $pages as $page ) {
        if ( $page instanceof WP_Post ) {
            $pages_by_slug[ $page->post_name ] = $page;
        }
    }

    $directory_blueprint = [
        [
            'label'        => __( 'Inicio', 'pertenencia-digital' ),
            'directory'    => __( 'Pestañas principales', 'pertenencia-digital' ),
            'primary_slug' => '__root',
            'slugs'        => [ '__root', 'web', 'tecnologias-digitales', 'tickets' ],
        ],
        [
            'label'        => __( 'Web', 'pertenencia-digital' ),
            'directory'    => __( 'Entender antes de contratar', 'pertenencia-digital' ),
            'primary_slug' => 'web',
            'slugs'        => [ 'enfoque-tecnologico', 'quieres-tu-propio-espacio-digital' ],
        ],
        [
            'label'        => __( 'Web', 'pertenencia-digital' ),
            'directory'    => __( 'Espacio compartido', 'pertenencia-digital' ),
            'primary_slug' => 'web',
            'slugs'        => [ 'presencia-basica-colaboracion' ],
        ],
        [
            'label'        => __( 'Web', 'pertenencia-digital' ),
            'directory'    => __( 'Presencia mínima propia', 'pertenencia-digital' ),
            'primary_slug' => 'web',
            'slugs'        => [ 'presencia-basica', 'auxilio-wordpress' ],
        ],
        [
            'label'        => __( 'Web', 'pertenencia-digital' ),
            'directory'    => __( 'Sitio profesional', 'pertenencia-digital' ),
            'primary_slug' => 'web',
            'slugs'        => [ 'sitio-profesional', 'sitio-profesional-self-admin', 'sitio-profesional-implementacion' ],
        ],
        [
            'label'        => __( 'Tecnologías digitales', 'pertenencia-digital' ),
            'directory'    => __( 'Servicios y casos', 'pertenencia-digital' ),
            'primary_slug' => 'tecnologias-digitales',
            'slugs'        => [ 'necesito-trabajo-multimedia-por-comision', 'servicio-tecnico-digital', 'consultoria-tecnologias-digitales', 'proyectos' ],
        ],
    ];

    $current_slug      = '';
    $current_page      = null;
    $current_directory = $directory_blueprint[0];

    foreach ( $pages_by_slug as $slug => $page ) {
        if ( $page instanceof WP_Post && (int) $page->ID === $current_id ) {
            $current_slug = $slug;
            $current_page = $page;
            break;
        }
    }

    foreach ( $directory_blueprint as $directory ) {
        if ( in_array( $current_slug, $directory['slugs'], true ) ) {
            $current_directory = $directory;
            break;
        }
    }

    $items = [];

    foreach ( $current_directory['slugs'] as $slug ) {
        if ( isset( $pages_by_slug[ $slug ] ) && '__root' !== $slug ) {
            $items[] = $pages_by_slug[ $slug ];
        }
    }

    $trail = [];

    if ( isset( $pages_by_slug['__root'] ) ) {
        $root_url = get_permalink( $pages_by_slug['__root'] );

        if ( is_string( $root_url ) && '' !== $root_url ) {
            $trail[] = [
                'label'   => get_the_title( $pages_by_slug['__root'] ),
                'url'     => $root_url,
                'current' => (int) $pages_by_slug['__root']->ID === $current_id,
            ];
        }
    }

    $primary_slug = (string) ( $current_directory['primary_slug'] ?? '' );

    if ( '' !== $primary_slug && '__root' !== $primary_slug && isset( $pages_by_slug[ $primary_slug ] ) ) {
        $primary_url = get_permalink( $pages_by_slug[ $primary_slug ] );

        if ( is_string( $primary_url ) && '' !== $primary_url ) {
            $trail[] = [
                'label'   => get_the_title( $pages_by_slug[ $primary_slug ] ),
                'url'     => $primary_url,
                'current' => (int) $pages_by_slug[ $primary_slug ]->ID === $current_id,
            ];
        }
    }

    $primary_id = isset( $pages_by_slug[ $primary_slug ] ) ? (int) $pages_by_slug[ $primary_slug ]->ID : 0;
    $root_id    = isset( $pages_by_slug['__root'] ) ? (int) $pages_by_slug['__root']->ID : 0;

    if ( null !== $current_page && (int) $current_page->ID !== $primary_id && (int) $current_page->ID !== $root_id && '__root' !== $primary_slug && get_the_title( $current_page ) !== (string) $current_directory['directory'] ) {
        $trail[] = [
            'label'   => (string) $current_directory['directory'],
            'url'     => '',
            'current' => false,
        ];
    }

    if ( null !== $current_page && (int) $current_page->ID !== $primary_id && (int) $current_page->ID !== $root_id ) {
        $current_url = get_permalink( $current_page );

        if ( is_string( $current_url ) && '' !== $current_url ) {
            $trail[] = [
                'label'   => get_the_title( $current_page ),
                'url'     => $current_url,
                'current' => true,
            ];
        }
    }

    return [
        'trail' => $trail,
        'label' => (string) $current_directory['directory'],
        'items' => $items,
    ];
}

/**
 * Renderiza el directorio contextual de Tecnologias y Web.
 *
 * @param array<int, WP_Post> $pages Paginas hijas publicadas.
 */
function pd_render_technology_directory_navigation( array $pages, int $current_id, array $ancestors ): string {
    $context = pd_get_technology_directory_context( $pages, $current_id );
    $output  = '<div class="pd-technology-directory">';

    if ( ! empty( $context['trail'] ) ) {
        $output .= '<ol class="pd-technology-directory__trail" aria-label="' . esc_attr__( 'Ruta actual', 'pertenencia-digital' ) . '">';

        foreach ( $context['trail'] as $crumb ) {
            $classes = 'pd-technology-directory__crumb' . ( ! empty( $crumb['current'] ) ? ' is-current' : '' );
            $output .= '<li class="' . esc_attr( $classes ) . '">';

            if ( ! empty( $crumb['current'] ) ) {
                $output .= '<span aria-current="page">' . esc_html( $crumb['label'] ) . '</span>';
            } elseif ( empty( $crumb['url'] ) ) {
                $output .= '<span>' . esc_html( $crumb['label'] ) . '</span>';
            } else {
                $output .= '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a>';
            }

            $output .= '</li>';
        }

        $output .= '</ol>';
    }

    if ( ! empty( $context['items'] ) ) {
        $output .= '<div class="pd-technology-directory__section">';
        $output .= '<span class="pd-technology-directory__label">' . esc_html( $context['label'] ) . '</span>';
        $output .= '<ul class="pd-technology-directory__list">';

        foreach ( $context['items'] as $page ) {
            $output .= pd_render_section_subnavigation_item( $page, $current_id, $ancestors );
        }

        $output .= '</ul>';
        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

/**
 * Renderiza un enlace de la subnavegacion de seccion.
 */
function pd_render_section_subnavigation_item( WP_Post $page, int $current_id, array $ancestors ): string {
    $page_url = get_permalink( $page );

    if ( ! is_string( $page_url ) || '' === $page_url ) {
        return '';
    }

    $is_current = (int) $page->ID === $current_id || in_array( (int) $page->ID, $ancestors, true );
    $classes    = 'pd-music-subnav__item' . ( $is_current ? ' is-current' : '' );

    $output  = '<li class="' . esc_attr( $classes ) . '">';
    $output .= '<a class="pd-music-subnav__link" href="' . esc_url( $page_url ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>' . esc_html( get_the_title( $page ) ) . '</a>';
    $output .= '</li>';

    return $output;
}

/**
 * Render callback del submenu horizontal de una seccion.
 *
 * @param array<string, mixed> $attributes Atributos del bloque.
 */
function pd_render_block_music_subnavigation( array $attributes = [], string $content = '', ?WP_Block $block = null ): string {
    $parent_path = isset( $attributes['parentPath'] ) && '' !== (string) $attributes['parentPath'] ? (string) $attributes['parentPath'] : 'musica';
    $pages       = pd_get_section_child_pages( $parent_path );
    $labels      = pd_get_section_navigation_labels( $parent_path );

    if ( empty( $pages ) ) {
        if ( current_user_can( 'edit_theme_options' ) ) {
            $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
                ? get_block_wrapper_attributes(
                    [
                        'class' => 'pd-music-subnav-block pd-music-subnav-block--empty',
                    ]
                )
                : 'class="pd-music-subnav-block pd-music-subnav-block--empty"';

            return '<div ' . $wrapper_attributes . '><div class="pd-music-subnav pd-music-subnav--empty">' . esc_html( $labels['empty'] ) . '</div></div>';
        }

        return '';
    }

    $current_id = get_queried_object_id();
    $ancestors  = $current_id > 0 ? array_map( 'intval', get_post_ancestors( $current_id ) ) : [];
    $align_items = isset( $attributes['alignItems'] ) && in_array( $attributes['alignItems'], [ 'start', 'center', 'end' ], true ) ? $attributes['alignItems'] : 'center';
    $mobile_mode = isset( $attributes['mobileMode'] ) && in_array( $attributes['mobileMode'], [ 'scroll', 'wrap' ], true ) ? $attributes['mobileMode'] : 'scroll';
    $item_size   = isset( $attributes['itemSize'] ) && in_array( $attributes['itemSize'], [ 'small', 'medium', 'large' ], true ) ? $attributes['itemSize'] : 'medium';
    $item_scale  = isset( $attributes['itemScale'] ) ? max( 70, min( 150, (int) $attributes['itemScale'] ) ) : 100;
    $style_rules = [];

    foreach (
        [
            '--pd-subnav-text'                     => $attributes['textColor'] ?? '',
            '--pd-subnav-item-hover-background'   => $attributes['itemHoverBackground'] ?? '',
            '--pd-subnav-item-hover-border'       => $attributes['itemHoverBorder'] ?? '',
            '--pd-subnav-item-current-background' => $attributes['itemCurrentBackground'] ?? '',
            '--pd-subnav-item-current-border'     => $attributes['itemCurrentBorder'] ?? '',
            '--pd-subnav-item-current-text'       => $attributes['itemCurrentText'] ?? '',
        ] as $property => $value
    ) {
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $style_rules[] = $property . ':' . trim( $value );
        }
    }

    $size_presets = [
        'small'  => [
            '--pd-subnav-link-min-height:1.9rem',
            '--pd-subnav-link-padding-top:0.28rem',
            '--pd-subnav-link-padding-x:0.55rem',
            '--pd-subnav-link-padding-bottom:0.42rem',
            '--pd-subnav-link-font-size:0.9rem',
            '--pd-subnav-link-radius:0.72rem',
            '--pd-subnav-indicator-inset:0.55rem',
            '--pd-subnav-indicator-thickness:2px',
        ],
        'medium' => [
            '--pd-subnav-link-min-height:2.1rem',
            '--pd-subnav-link-padding-top:0.35rem',
            '--pd-subnav-link-padding-x:0.7rem',
            '--pd-subnav-link-padding-bottom:0.55rem',
            '--pd-subnav-link-font-size:1rem',
            '--pd-subnav-link-radius:0.8rem',
            '--pd-subnav-indicator-inset:0.7rem',
            '--pd-subnav-indicator-thickness:2px',
        ],
        'large'  => [
            '--pd-subnav-link-min-height:2.35rem',
            '--pd-subnav-link-padding-top:0.45rem',
            '--pd-subnav-link-padding-x:0.88rem',
            '--pd-subnav-link-padding-bottom:0.68rem',
            '--pd-subnav-link-font-size:1.08rem',
            '--pd-subnav-link-radius:0.92rem',
            '--pd-subnav-indicator-inset:0.88rem',
            '--pd-subnav-indicator-thickness:2.5px',
        ],
    ];

    $style_rules   = array_merge( $size_presets[ $item_size ], $style_rules );
    $style_rules[] = '--pd-subnav-link-scale:' . ( $item_scale / 100 );
    $wrapper_style = ! empty( $style_rules ) ? pd_build_theme_custom_property_style( $style_rules ) : '';
    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'class' => 'pd-music-subnav-block',
                'style' => '' !== $wrapper_style ? $wrapper_style : null,
            ]
        )
        : 'class="pd-music-subnav-block"' . ( '' !== $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : '' );
    $output     = '<div ' . $wrapper_attributes . '>';
    $nav_classes = 'pd-music-subnav';

    if ( 'tecnologias-web' === $parent_path ) {
        $nav_classes .= ' pd-music-subnav--grouped pd-music-subnav--technology';
    }

    $output    .= '<nav class="' . esc_attr( $nav_classes ) . '" data-align="' . esc_attr( $align_items ) . '" data-mobile-mode="' . esc_attr( $mobile_mode ) . '" aria-label="' . esc_attr( $labels['aria'] ) . '">';

    if ( 'tecnologias-web' === $parent_path ) {
        $output .= pd_render_technology_directory_navigation( $pages, $current_id, $ancestors );
        $output .= '</nav>';
        $output .= '</div>';

        return $output;
    }

    $output    .= '<ul class="pd-music-subnav__list">';

    foreach ( $pages as $page ) {
        $output .= pd_render_section_subnavigation_item( $page, $current_id, $ancestors );
    }

    $output .= '</ul>';
    $output .= '</nav>';
    $output .= '</div>';

    return $output;
}

/**
 * Shortcode del acceso compacto del header.
 */
function pd_account_access_shortcode(): string {
    return pd_render_block_account_access();
}
add_shortcode( 'pd_account_access', 'pd_account_access_shortcode' );

/**
 * Registra bloques dinamicos livianos del tema.
 */
function pd_register_dynamic_blocks(): void {
    $block_directory = get_template_directory() . '/blocks';

    register_block_type(
        $block_directory . '/account-access',
        [
            'render_callback' => 'pd_render_block_account_access',
        ]
    );

    register_block_type(
        $block_directory . '/login-panel',
        [
            'render_callback' => 'pd_render_block_login_panel',
        ]
    );

    register_block_type(
        $block_directory . '/site-navigation',
        [
            'render_callback' => 'pd_render_block_site_navigation',
        ]
    );

    register_block_type(
        $block_directory . '/music-subnavigation',
        [
            'render_callback' => 'pd_render_block_music_subnavigation',
        ]
    );

    register_block_type(
        $block_directory . '/music-access-gate',
        [
            'render_callback' => 'pd_render_block_music_access_gate',
        ]
    );
}
add_action( 'init', 'pd_register_dynamic_blocks' );

/**
 * Shortcode reutilizable para insertar el panel de acceso.
 *
 * @param array<string, string> $atts Atributos del shortcode.
 */
function pd_login_form_shortcode( array $atts = [] ): string {
    $atts = shortcode_atts(
        [
            'title'       => '',
            'intro'       => '',
            'redirect_to' => '',
        ],
        $atts,
        'pd_login_form'
    );

    return pd_render_login_panel(
        [
            'title'       => '' !== $atts['title'] ? $atts['title'] : __( 'Accede a tu pertenencia digital', 'pertenencia-digital' ),
            'intro'       => '' !== $atts['intro'] ? $atts['intro'] : __( 'Inicia sesion para editar tu presskit, revisar tus proyectos y mantener actualizada tu presencia en el sitio.', 'pertenencia-digital' ),
            'redirect_to' => $atts['redirect_to'],
        ]
    );
}
add_shortcode( 'pd_login_form', 'pd_login_form_shortcode' );

/**
 * Mejora la presentacion del login nativo como fallback.
 */
function pd_customize_wp_login_screen(): void {
    $theme      = wp_get_theme();
    $style_path = get_stylesheet_directory() . '/style.css';
    $version    = file_exists( $style_path ) ? (string) filemtime( $style_path ) : $theme->get( 'Version' );

    wp_enqueue_style(
        'pertenencia-digital-login-fonts',
        'https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'pertenencia-digital-login-style',
        get_stylesheet_uri(),
        [ 'pertenencia-digital-login-fonts' ],
        $version
    );

    wp_add_inline_style(
        'pertenencia-digital-login-style',
        '
body.login {
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(30, 58, 138, 0.18), transparent 32%),
    linear-gradient(160deg, #eef4ff 0%, #f8fafc 52%, #edf2f7 100%);
  color: #1f2937;
  font-family: "Libre Baskerville", serif;
}

body.login #login {
  width: min(92vw, 430px);
  padding: 4rem 0 2rem;
}

body.login h1 a {
  width: auto;
  height: auto;
  margin: 0 0 1.25rem;
  background: none;
  text-indent: 0;
  font-size: 1.85rem;
  font-weight: 700;
  line-height: 1.2;
  color: #1e3a8a;
}

body.login form {
  border: 0;
  border-radius: 24px;
  padding: 1.6rem;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 18px 45px rgba(30, 58, 138, 0.12);
}

body.login label,
body.login .forgetmenot,
body.login #nav,
body.login #backtoblog {
  color: #334155;
}

body.login input[type="text"],
body.login input[type="password"] {
  min-height: 48px;
  border: 1px solid rgba(31, 41, 55, 0.12);
  border-radius: 12px;
  padding-inline: 0.95rem;
}

body.login .button.button-primary {
  min-height: 48px;
  border: 0;
  border-radius: 999px;
  background: #1e3a8a;
  box-shadow: none;
  text-shadow: none;
}

body.login .button.button-primary:hover,
body.login .button.button-primary:focus {
  background: #1e40af;
}

body.login .message,
body.login #login_error,
body.login .success {
  border-left: 0;
  border-radius: 16px;
  box-shadow: 0 18px 45px rgba(30, 58, 138, 0.08);
}
'
    );
}
add_action( 'login_enqueue_scripts', 'pd_customize_wp_login_screen' );

add_filter(
    'login_headerurl',
    function (): string {
        return home_url( '/' );
    }
);

add_filter(
    'login_headertext',
    function (): string {
        return get_bloginfo( 'name' );
    }
);

/**
 * Determina si la página actual es hija directa de la página "musica".
 */
function pd_is_child_of_musica_page(): bool {
    if ( ! is_page() ) {
        return false;
    }

    $current_page_id = get_queried_object_id();

    if ( ! $current_page_id ) {
        return false;
    }

    static $musica_page_id = null;

    if ( null === $musica_page_id ) {
        $musica_page_id = 0;
        $musica_page    = get_page_by_path( 'musica' );

        if ( $musica_page instanceof WP_Post ) {
            $musica_page_id = (int) $musica_page->ID;
        }
    }

    if ( ! $musica_page_id ) {
        return false;
    }

    $parent_id = (int) wp_get_post_parent_id( $current_page_id );

    return $musica_page_id === $parent_id;
}

/**
 * Determina si la página actual usa la plantilla editorial de presskit.
 */
function pd_is_presskit_page(): bool {
    if ( ! is_page() ) {
        return false;
    }

    $current_page_id = get_queried_object_id();

    if ( ! $current_page_id ) {
        return false;
    }

    return 'presskit' === get_page_template_slug( $current_page_id );
}

/**
 * Usa la cabecera de música para páginas hijas de "música" y presskits.
 */
add_filter(
    'render_block_data',
    function ( array $parsed_block ): array {
        if ( is_admin() || wp_is_json_request() ) {
            return $parsed_block;
        }

        if ( is_front_page() ) {
            return $parsed_block;
        }

        if ( ! pd_is_child_of_musica_page() && ! pd_is_presskit_page() ) {
            return $parsed_block;
        }

        if ( 'core/template-part' !== ( $parsed_block['blockName'] ?? '' ) ) {
            return $parsed_block;
        }

        $slug = $parsed_block['attrs']['slug'] ?? '';

        if ( 'header' !== $slug ) {
            return $parsed_block;
        }

        $parsed_block['attrs']['slug'] = 'header-musica-hijas';

        return $parsed_block;
    },
    10,
    1
);

const PD_COLLABORATOR_CAP = 'pd_colaborador';
const PD_PROJECT_POST_TYPE = 'proyecto';
const PD_PROJECT_AREA_TAX = 'area_proyecto';

/**
 * Indica si el tema debe mantener el módulo legacy de proyectos.
 */
function pd_use_legacy_project_module(): bool {
    return ! defined( 'WPSSB_PROJECTS_CENTRALIZED' ) || ! WPSSB_PROJECTS_CENTRALIZED;
}

/**
 * Registra el rol de colaboradores digitales.
 */
function pd_register_collaborator_role(): void {
    if ( ! pd_use_legacy_project_module() ) {
        return;
    }

    if ( null === get_role( 'pd_colaborador' ) ) {
        add_role(
            'pd_colaborador',
            __( 'Colaborador digital', 'pertenencia-digital' ),
            [
                'read'                => true,
                PD_COLLABORATOR_CAP   => true,
            ]
        );
    }
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'init', 'pd_register_collaborator_role' );
}

/**
 * Registra el CPT de proyectos.
 */
function pd_register_proyecto_cpt(): void {
    if ( ! pd_use_legacy_project_module() ) {
        return;
    }

    $labels = [
        'name'               => __( 'Proyectos', 'pertenencia-digital' ),
        'singular_name'      => __( 'Proyecto', 'pertenencia-digital' ),
        'add_new'            => __( 'Añadir nuevo', 'pertenencia-digital' ),
        'add_new_item'       => __( 'Añadir nuevo proyecto', 'pertenencia-digital' ),
        'edit_item'          => __( 'Editar proyecto', 'pertenencia-digital' ),
        'new_item'           => __( 'Nuevo proyecto', 'pertenencia-digital' ),
        'view_item'          => __( 'Ver proyecto', 'pertenencia-digital' ),
        'search_items'       => __( 'Buscar proyectos', 'pertenencia-digital' ),
        'not_found'          => __( 'No se encontraron proyectos', 'pertenencia-digital' ),
        'not_found_in_trash' => __( 'No hay proyectos en la papelera', 'pertenencia-digital' ),
        'all_items'          => __( 'Todos los proyectos', 'pertenencia-digital' ),
    ];

    register_post_type(
        PD_PROJECT_POST_TYPE,
        [
            'labels'             => $labels,
            'public'             => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-networking',
            'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'has_archive'        => false,
            'rewrite'            => [
                'slug' => 'proyecto',
            ],
        ]
    );
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'init', 'pd_register_proyecto_cpt' );
}

/**
 * Registra la taxonomía de áreas para proyectos.
 */
function pd_register_proyecto_area_taxonomy(): void {
    if ( ! pd_use_legacy_project_module() ) {
        return;
    }

    $labels = [
        'name'          => __( 'Áreas del proyecto', 'pertenencia-digital' ),
        'singular_name' => __( 'Área del proyecto', 'pertenencia-digital' ),
        'search_items'  => __( 'Buscar áreas', 'pertenencia-digital' ),
        'all_items'     => __( 'Todas las áreas', 'pertenencia-digital' ),
        'edit_item'     => __( 'Editar área', 'pertenencia-digital' ),
        'update_item'   => __( 'Actualizar área', 'pertenencia-digital' ),
        'add_new_item'  => __( 'Añadir nueva área', 'pertenencia-digital' ),
        'new_item_name' => __( 'Nuevo nombre de área', 'pertenencia-digital' ),
        'menu_name'     => __( 'Áreas', 'pertenencia-digital' ),
    ];

    register_taxonomy(
        PD_PROJECT_AREA_TAX,
        [ PD_PROJECT_POST_TYPE ],
        [
            'labels'            => $labels,
            'public'            => true,
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [
                'slug' => 'area-proyecto',
            ],
        ]
    );
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'init', 'pd_register_proyecto_area_taxonomy' );
}

/**
 * Sanitiza IDs numéricos en un array.
 */
function pd_sanitize_id_list( $value ): array {
    if ( empty( $value ) ) {
        return [];
    }

    if ( is_string( $value ) ) {
        $value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
    }

    $ids = array_filter(
        array_map(
            static function ( $item ) {
                return max( 0, (int) $item );
            },
            (array) $value
        )
    );

    return array_values( array_unique( $ids ) );
}

/**
 * Meta del CPT proyecto.
 */
function pd_register_proyecto_meta(): void {
    if ( ! pd_use_legacy_project_module() ) {
        return;
    }

    register_post_meta(
        PD_PROJECT_POST_TYPE,
        'pd_proyecto_colaboradores',
        [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => 'pd_sanitize_id_list',
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]
    );

    register_post_meta(
        PD_PROJECT_POST_TYPE,
        'pd_proyecto_galeria',
        [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => 'pd_sanitize_id_list',
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]
    );

    register_post_meta(
        PD_PROJECT_POST_TYPE,
        'pd_proyecto_contacto',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        PD_PROJECT_POST_TYPE,
        'pd_proyecto_tagline',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        PD_PROJECT_POST_TYPE,
        'pd_proyecto_presskit',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        PD_PROJECT_POST_TYPE,
        'pd_proyecto_links',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'show_in_rest'      => true,
        ]
    );
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'init', 'pd_register_proyecto_meta' );
}

/**
 * Meta boxes para proyectos.
 */
function pd_add_proyecto_meta_boxes(): void {
    if ( ! pd_use_legacy_project_module() ) {
        return;
    }

    add_meta_box(
        'pd-proyecto-colaboradores',
        __( 'Colaboradores', 'pertenencia-digital' ),
        'pd_render_proyecto_colaboradores_meta_box',
        PD_PROJECT_POST_TYPE,
        'side',
        'default'
    );

    add_meta_box(
        'pd-proyecto-contacto',
        __( 'Contacto del proyecto', 'pertenencia-digital' ),
        'pd_render_proyecto_contacto_meta_box',
        PD_PROJECT_POST_TYPE,
        'normal',
        'default'
    );

    add_meta_box(
        'pd-proyecto-galeria',
        __( 'Galería del proyecto', 'pertenencia-digital' ),
        'pd_render_proyecto_galeria_meta_box',
        PD_PROJECT_POST_TYPE,
        'normal',
        'default'
    );

    add_meta_box(
        'pd-proyecto-presskit',
        __( 'Presskit del proyecto', 'pertenencia-digital' ),
        'pd_render_proyecto_presskit_meta_box',
        PD_PROJECT_POST_TYPE,
        'normal',
        'default'
    );
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'add_meta_boxes', 'pd_add_proyecto_meta_boxes' );
}

/**
 * Obtiene usuarios colaboradores.
 */
function pd_get_colaboradores(): array {
    return get_users(
        [
            'capability' => PD_COLLABORATOR_CAP,
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ]
    );
}

function pd_render_proyecto_colaboradores_meta_box( WP_Post $post ): void {
    wp_nonce_field( 'pd_save_proyecto_meta', 'pd_proyecto_meta_nonce' );

    $selected = pd_sanitize_id_list( get_post_meta( $post->ID, 'pd_proyecto_colaboradores', true ) );
    $users    = pd_get_colaboradores();

    if ( empty( $users ) ) {
        echo '<p>' . esc_html__( 'No hay colaboradores disponibles. Asigna el rol o capability primero.', 'pertenencia-digital' ) . '</p>';
        return;
    }

    echo '<div class="pd-proyecto-colaboradores-meta">';

    foreach ( $users as $user ) {
        $checked = in_array( (int) $user->ID, $selected, true ) ? 'checked' : '';
        printf(
            '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="pd_proyecto_colaboradores[]" value="%1$d" %2$s /> %3$s</label>',
            (int) $user->ID,
            $checked,
            esc_html( $user->display_name )
        );
    }

    echo '</div>';
}

function pd_render_proyecto_contacto_meta_box( WP_Post $post ): void {
    $contacto = get_post_meta( $post->ID, 'pd_proyecto_contacto', true );

    echo '<p>' . esc_html__( 'Cómo contactar al proyecto: email, teléfono, formulario o redes.', 'pertenencia-digital' ) . '</p>';
    printf(
        '<textarea name="pd_proyecto_contacto" rows="4" style="width:100%%;">%s</textarea>',
        esc_textarea( (string) $contacto )
    );
}

function pd_render_proyecto_galeria_meta_box( WP_Post $post ): void {
    $galeria = pd_sanitize_id_list( get_post_meta( $post->ID, 'pd_proyecto_galeria', true ) );

    echo '<div class="pd-proyecto-galeria-meta" data-initial="' . esc_attr( implode( ',', $galeria ) ) . '">';
    echo '<p>' . esc_html__( 'Selecciona imágenes para la galería del proyecto.', 'pertenencia-digital' ) . '</p>';
    echo '<input type="hidden" name="pd_proyecto_galeria" value="' . esc_attr( implode( ',', $galeria ) ) . '" />';
    echo '<button type="button" class="button pd-proyecto-galeria-select">' . esc_html__( 'Elegir imágenes', 'pertenencia-digital' ) . '</button>';
    echo '<button type="button" class="button pd-proyecto-galeria-clear" style="margin-left:6px;">' . esc_html__( 'Limpiar galería', 'pertenencia-digital' ) . '</button>';
    echo '<div class="pd-proyecto-galeria-preview" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;"></div>';
    echo '</div>';
}

function pd_render_proyecto_presskit_meta_box( WP_Post $post ): void {
    $tagline  = get_post_meta( $post->ID, 'pd_proyecto_tagline', true );
    $presskit = get_post_meta( $post->ID, 'pd_proyecto_presskit', true );
    $links    = get_post_meta( $post->ID, 'pd_proyecto_links', true );

    echo '<p>' . esc_html__( 'Resume el proyecto como presskit: tagline, descripción breve y enlaces.', 'pertenencia-digital' ) . '</p>';
    printf(
        '<label style="display:block;margin-bottom:10px;"><strong>%s</strong><br/><input type="text" name="pd_proyecto_tagline" value="%s" style="width:100%%;" /></label>',
        esc_html__( 'Tagline', 'pertenencia-digital' ),
        esc_attr( (string) $tagline )
    );

    printf(
        '<label style="display:block;margin-bottom:10px;"><strong>%s</strong><br/><textarea name="pd_proyecto_presskit" rows="4" style="width:100%%;">%s</textarea></label>',
        esc_html__( 'Descripción / Presskit', 'pertenencia-digital' ),
        esc_textarea( (string) $presskit )
    );

    printf(
        '<label style="display:block;"><strong>%s</strong><br/><textarea name="pd_proyecto_links" rows="3" style="width:100%%;">%s</textarea></label>',
        esc_html__( 'Links (uno por línea)', 'pertenencia-digital' ),
        esc_textarea( (string) $links )
    );
}

/**
 * Guarda meta del proyecto.
 */
function pd_save_proyecto_meta( int $post_id, WP_Post $post ): void {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! isset( $_POST['pd_proyecto_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pd_proyecto_meta_nonce'], 'pd_save_proyecto_meta' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $colaboradores = isset( $_POST['pd_proyecto_colaboradores'] ) ? pd_sanitize_id_list( $_POST['pd_proyecto_colaboradores'] ) : [];
    $galeria       = isset( $_POST['pd_proyecto_galeria'] ) ? pd_sanitize_id_list( $_POST['pd_proyecto_galeria'] ) : [];
    $contacto      = isset( $_POST['pd_proyecto_contacto'] ) ? wp_kses_post( wp_unslash( $_POST['pd_proyecto_contacto'] ) ) : '';
    $tagline       = isset( $_POST['pd_proyecto_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_proyecto_tagline'] ) ) : '';
    $presskit      = isset( $_POST['pd_proyecto_presskit'] ) ? wp_kses_post( wp_unslash( $_POST['pd_proyecto_presskit'] ) ) : '';
    $links         = isset( $_POST['pd_proyecto_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pd_proyecto_links'] ) ) : '';

    update_post_meta( $post_id, 'pd_proyecto_colaboradores', $colaboradores );
    update_post_meta( $post_id, 'pd_proyecto_galeria', $galeria );
    update_post_meta( $post_id, 'pd_proyecto_contacto', $contacto );
    update_post_meta( $post_id, 'pd_proyecto_tagline', $tagline );
    update_post_meta( $post_id, 'pd_proyecto_presskit', $presskit );
    update_post_meta( $post_id, 'pd_proyecto_links', $links );
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'save_post_' . PD_PROJECT_POST_TYPE, 'pd_save_proyecto_meta', 10, 2 );
}

/**
 * Scripts para meta boxes.
 */
function pd_enqueue_proyecto_meta_assets( string $hook ): void {
    if ( ! pd_use_legacy_project_module() ) {
        return;
    }

    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        if ( in_array( $hook, [ 'profile.php', 'user-edit.php' ], true ) ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'pd-colaborador-meta',
                get_template_directory_uri() . '/assets/js/colaborador-meta.js',
                [ 'jquery' ],
                '1.0',
                true
            );
        }

        return;
    }

    $screen = get_current_screen();

    if ( ! $screen || PD_PROJECT_POST_TYPE !== $screen->post_type ) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'pd-proyecto-meta',
        get_template_directory_uri() . '/assets/js/proyecto-meta.js',
        [ 'jquery' ],
        '1.0',
        true
    );
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'admin_enqueue_scripts', 'pd_enqueue_proyecto_meta_assets' );
}

/**
 * Shortcodes de colaboradores y proyectos.
 */
function pd_render_colaborador_card( WP_User $user ): string {
    $avatar = get_avatar( $user->ID, 96, '', $user->display_name, [ 'class' => 'pd-colaborador-avatar' ] );
    $bio    = get_user_meta( $user->ID, 'description', true );
    $tagline = get_user_meta( $user->ID, 'pd_colaborador_tagline', true );
    $url    = $user->user_url ? esc_url( $user->user_url ) : '';
    $author = function_exists( 'wpssb_get_collaborator_public_url' )
        ? wpssb_get_collaborator_public_url( $user->ID )
        : get_author_posts_url( $user->ID );

    $output  = '<article class="pd-colaborador-card">';
    $output .= '<div class="pd-colaborador-card__header">';
    $output .= $avatar ? '<div class="pd-colaborador-card__avatar">' . $avatar . '</div>' : '';
    $output .= '<h3 class="pd-colaborador-card__name"><a href="' . esc_url( $author ) . '">' . esc_html( $user->display_name ) . '</a></h3>';
    $output .= '</div>';

    if ( $bio ) {
        $output .= '<p class="pd-colaborador-card__bio">' . esc_html( $bio ) . '</p>';
    } elseif ( $tagline ) {
        $output .= '<p class="pd-colaborador-card__bio">' . esc_html( $tagline ) . '</p>';
    }

    if ( $url ) {
        $output .= '<p class="pd-colaborador-card__link"><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sitio / portafolio', 'pertenencia-digital' ) . '</a></p>';
    }

    $output .= '</article>';

    return $output;
}

function pd_shortcode_colaboradores(): string {
    $users = pd_get_colaboradores();

    if ( empty( $users ) ) {
        return '<p>' . esc_html__( 'Aún no hay colaboradores registrados.', 'pertenencia-digital' ) . '</p>';
    }

    $output = '<div class="pd-colaboradores-grid">';

    foreach ( $users as $user ) {
        $output .= pd_render_colaborador_card( $user );
    }

    $output .= '</div>';

    return $output;
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_colaboradores', 'pd_shortcode_colaboradores' );
}

function pd_shortcode_proyecto_colaboradores(): string {
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    $ids = pd_sanitize_id_list( get_post_meta( $post_id, 'pd_proyecto_colaboradores', true ) );
    if ( empty( $ids ) ) {
        return '<p>' . esc_html__( 'Este proyecto no tiene colaboradores asignados todavía.', 'pertenencia-digital' ) . '</p>';
    }

    $users = array_filter(
        array_map(
            static function ( $id ) {
                return get_user_by( 'id', (int) $id );
            },
            $ids
        )
    );

    if ( empty( $users ) ) {
        return '<p>' . esc_html__( 'No fue posible cargar los colaboradores.', 'pertenencia-digital' ) . '</p>';
    }

    $output = '<div class="pd-colaboradores-grid">';

    foreach ( $users as $user ) {
        $output .= pd_render_colaborador_card( $user );
    }

    $output .= '</div>';

    return $output;
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_proyecto_colaboradores', 'pd_shortcode_proyecto_colaboradores' );
}

function pd_shortcode_proyecto_galeria(): string {
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    $ids = pd_sanitize_id_list( get_post_meta( $post_id, 'pd_proyecto_galeria', true ) );

    if ( empty( $ids ) ) {
        return '<p>' . esc_html__( 'Aún no hay imágenes en la galería del proyecto.', 'pertenencia-digital' ) . '</p>';
    }

    $output = '<div class="pd-proyecto-galeria">';

    foreach ( $ids as $id ) {
        $image = wp_get_attachment_image( $id, 'medium_large' );
        if ( $image ) {
            $output .= '<figure class="pd-proyecto-galeria__item">' . $image . '</figure>';
        }
    }

    $output .= '</div>';

    return $output;
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_proyecto_galeria', 'pd_shortcode_proyecto_galeria' );
}

function pd_shortcode_proyecto_contacto(): string {
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    $contacto = get_post_meta( $post_id, 'pd_proyecto_contacto', true );

    if ( ! $contacto ) {
        return '<p>' . esc_html__( 'No hay información de contacto definida.', 'pertenencia-digital' ) . '</p>';
    }

    return '<div class="pd-proyecto-contacto">' . wpautop( wp_kses_post( $contacto ) ) . '</div>';
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_proyecto_contacto', 'pd_shortcode_proyecto_contacto' );
}

function pd_shortcode_proyecto_presskit(): string {
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    $tagline  = get_post_meta( $post_id, 'pd_proyecto_tagline', true );
    $presskit = get_post_meta( $post_id, 'pd_proyecto_presskit', true );
    $links    = get_post_meta( $post_id, 'pd_proyecto_links', true );

    if ( ! $tagline && ! $presskit && ! $links ) {
        return '<p>' . esc_html__( 'No hay información de presskit definida.', 'pertenencia-digital' ) . '</p>';
    }

    $output = '<div class="pd-proyecto-presskit">';

    if ( $tagline ) {
        $output .= '<p class="pd-proyecto-presskit__tagline">' . esc_html( $tagline ) . '</p>';
    }

    if ( $presskit ) {
        $output .= '<div class="pd-proyecto-presskit__text">' . wpautop( wp_kses_post( $presskit ) ) . '</div>';
    }

    if ( $links ) {
        $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $links ) ) );
        if ( ! empty( $lines ) ) {
            $output .= '<ul class="pd-proyecto-presskit__links">';
            foreach ( $lines as $line ) {
                $url = esc_url( $line );
                if ( $url ) {
                    $output .= '<li><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a></li>';
                } else {
                    $output .= '<li>' . esc_html( $line ) . '</li>';
                }
            }
            $output .= '</ul>';
        }
    }

    $output .= '</div>';

    return $output;
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_proyecto_presskit', 'pd_shortcode_proyecto_presskit' );
}

/**
 * Meta para presskit de colaboradores.
 */
function pd_register_colaborador_meta(): void {
    if ( ! pd_use_legacy_project_module() ) {
        return;
    }

    register_meta(
        'user',
        'pd_colaborador_tagline',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]
    );

    register_meta(
        'user',
        'pd_colaborador_presskit',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_meta(
        'user',
        'pd_colaborador_links',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'show_in_rest'      => true,
        ]
    );

    register_meta(
        'user',
        'pd_colaborador_contacto',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_meta(
        'user',
        'pd_colaborador_galeria',
        [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => 'pd_sanitize_id_list',
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]
    );
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'init', 'pd_register_colaborador_meta' );
}

function pd_render_colaborador_presskit_fields( WP_User $user ): void {
    $tagline  = get_user_meta( $user->ID, 'pd_colaborador_tagline', true );
    $presskit = get_user_meta( $user->ID, 'pd_colaborador_presskit', true );
    $links    = get_user_meta( $user->ID, 'pd_colaborador_links', true );
    $contacto = get_user_meta( $user->ID, 'pd_colaborador_contacto', true );
    $galeria  = pd_sanitize_id_list( get_user_meta( $user->ID, 'pd_colaborador_galeria', true ) );

    echo '<h2>' . esc_html__( 'Presskit del colaborador', 'pertenencia-digital' ) . '</h2>';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th><label for="pd_colaborador_tagline">' . esc_html__( 'Tagline', 'pertenencia-digital' ) . '</label></th>';
    echo '<td><input type="text" name="pd_colaborador_tagline" id="pd_colaborador_tagline" value="' . esc_attr( (string) $tagline ) . '" class="regular-text" /></td></tr>';

    echo '<tr><th><label for="pd_colaborador_presskit">' . esc_html__( 'Descripción / Presskit', 'pertenencia-digital' ) . '</label></th>';
    echo '<td><textarea name="pd_colaborador_presskit" id="pd_colaborador_presskit" rows="4" class="large-text">' . esc_textarea( (string) $presskit ) . '</textarea></td></tr>';

    echo '<tr><th><label for="pd_colaborador_links">' . esc_html__( 'Links (uno por línea)', 'pertenencia-digital' ) . '</label></th>';
    echo '<td><textarea name="pd_colaborador_links" id="pd_colaborador_links" rows="3" class="large-text">' . esc_textarea( (string) $links ) . '</textarea></td></tr>';

    echo '<tr><th><label for="pd_colaborador_contacto">' . esc_html__( 'Contacto', 'pertenencia-digital' ) . '</label></th>';
    echo '<td><textarea name="pd_colaborador_contacto" id="pd_colaborador_contacto" rows="3" class="large-text">' . esc_textarea( (string) $contacto ) . '</textarea></td></tr>';

    echo '<tr><th>' . esc_html__( 'Galería', 'pertenencia-digital' ) . '</th><td>';
    echo '<div class="pd-colaborador-galeria-meta" data-initial="' . esc_attr( implode( ',', $galeria ) ) . '">';
    echo '<input type="hidden" name="pd_colaborador_galeria" value="' . esc_attr( implode( ',', $galeria ) ) . '" />';
    echo '<button type="button" class="button pd-colaborador-galeria-select">' . esc_html__( 'Elegir imágenes', 'pertenencia-digital' ) . '</button>';
    echo '<button type="button" class="button pd-colaborador-galeria-clear" style="margin-left:6px;">' . esc_html__( 'Limpiar galería', 'pertenencia-digital' ) . '</button>';
    echo '<div class="pd-colaborador-galeria-preview" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;"></div>';
    echo '</div>';
    echo '</td></tr>';
    echo '</table>';
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'show_user_profile', 'pd_render_colaborador_presskit_fields' );
    add_action( 'edit_user_profile', 'pd_render_colaborador_presskit_fields' );
}

function pd_save_colaborador_presskit_fields( int $user_id ): void {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    update_user_meta( $user_id, 'pd_colaborador_tagline', isset( $_POST['pd_colaborador_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_colaborador_tagline'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_presskit', isset( $_POST['pd_colaborador_presskit'] ) ? wp_kses_post( wp_unslash( $_POST['pd_colaborador_presskit'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_links', isset( $_POST['pd_colaborador_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pd_colaborador_links'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_contacto', isset( $_POST['pd_colaborador_contacto'] ) ? wp_kses_post( wp_unslash( $_POST['pd_colaborador_contacto'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_galeria', isset( $_POST['pd_colaborador_galeria'] ) ? pd_sanitize_id_list( wp_unslash( $_POST['pd_colaborador_galeria'] ) ) : [] );
}

if ( pd_use_legacy_project_module() ) {
    add_action( 'personal_options_update', 'pd_save_colaborador_presskit_fields' );
    add_action( 'edit_user_profile_update', 'pd_save_colaborador_presskit_fields' );
}

function pd_shortcode_colaborador_presskit( array $atts = [] ): string {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts );
    $user_id = (int) $atts['id'];

    if ( ! $user_id ) {
        $user_id = get_query_var( 'author' ) ? (int) get_query_var( 'author' ) : 0;
    }

    if ( ! $user_id ) {
        return '';
    }

    $user     = get_user_by( 'id', $user_id );
    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $tagline  = get_user_meta( $user_id, 'pd_colaborador_tagline', true );
    $presskit = get_user_meta( $user_id, 'pd_colaborador_presskit', true );
    $links    = get_user_meta( $user_id, 'pd_colaborador_links', true );

    $output  = '<section class="pd-colaborador-presskit">';
    $output .= '<div class="pd-colaborador-presskit__header">';
    $output .= get_avatar( $user_id, 120, '', $user->display_name, [ 'class' => 'pd-colaborador-presskit__avatar' ] );
    $output .= '<div>';
    $output .= '<h1 class="pd-colaborador-presskit__name">' . esc_html( $user->display_name ) . '</h1>';
    if ( $tagline ) {
        $output .= '<p class="pd-colaborador-presskit__tagline">' . esc_html( $tagline ) . '</p>';
    }
    $output .= '</div></div>';

    if ( $presskit ) {
        $output .= '<div class="pd-colaborador-presskit__text">' . wpautop( wp_kses_post( $presskit ) ) . '</div>';
    } elseif ( $user->description ) {
        $output .= '<div class="pd-colaborador-presskit__text">' . wpautop( esc_html( $user->description ) ) . '</div>';
    }

    if ( $links ) {
        $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $links ) ) );
        if ( ! empty( $lines ) ) {
            $output .= '<ul class="pd-colaborador-presskit__links">';
            foreach ( $lines as $line ) {
                $url = esc_url( $line );
                if ( $url ) {
                    $output .= '<li><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a></li>';
                } else {
                    $output .= '<li>' . esc_html( $line ) . '</li>';
                }
            }
            $output .= '</ul>';
        }
    }

    $output .= '</section>';

    return $output;
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_colaborador_presskit', 'pd_shortcode_colaborador_presskit' );
}

function pd_shortcode_colaborador_galeria( array $atts = [] ): string {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts );
    $user_id = (int) $atts['id'];

    if ( ! $user_id ) {
        $user_id = get_query_var( 'author' ) ? (int) get_query_var( 'author' ) : 0;
    }

    if ( ! $user_id ) {
        return '';
    }

    $ids = pd_sanitize_id_list( get_user_meta( $user_id, 'pd_colaborador_galeria', true ) );
    if ( empty( $ids ) ) {
        return '<p>' . esc_html__( 'Aún no hay imágenes en la galería del colaborador.', 'pertenencia-digital' ) . '</p>';
    }

    $output = '<div class="pd-proyecto-galeria">';
    foreach ( $ids as $id ) {
        $image = wp_get_attachment_image( $id, 'medium_large' );
        if ( $image ) {
            $output .= '<figure class="pd-proyecto-galeria__item">' . $image . '</figure>';
        }
    }
    $output .= '</div>';

    return $output;
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_colaborador_galeria', 'pd_shortcode_colaborador_galeria' );
}

function pd_shortcode_colaborador_contacto( array $atts = [] ): string {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts );
    $user_id = (int) $atts['id'];

    if ( ! $user_id ) {
        $user_id = get_query_var( 'author' ) ? (int) get_query_var( 'author' ) : 0;
    }

    if ( ! $user_id ) {
        return '';
    }

    $contacto = get_user_meta( $user_id, 'pd_colaborador_contacto', true );
    if ( ! $contacto ) {
        return '<p>' . esc_html__( 'No hay información de contacto definida.', 'pertenencia-digital' ) . '</p>';
    }

    return '<div class="pd-proyecto-contacto">' . wpautop( wp_kses_post( $contacto ) ) . '</div>';
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_colaborador_contacto', 'pd_shortcode_colaborador_contacto' );
}

function pd_shortcode_colaborador_proyectos( array $atts = [] ): string {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts );
    $user_id = (int) $atts['id'];

    if ( ! $user_id ) {
        $user_id = get_query_var( 'author' ) ? (int) get_query_var( 'author' ) : 0;
    }

    if ( ! $user_id ) {
        return '';
    }

    $query = new WP_Query(
        [
            'post_type'      => PD_PROJECT_POST_TYPE,
            'posts_per_page' => 6,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => 'pd_proyecto_colaboradores',
                    'value'   => '"' . $user_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ]
    );

    if ( ! $query->have_posts() ) {
        return '<p>' . esc_html__( 'No hay proyectos asociados todavía.', 'pertenencia-digital' ) . '</p>';
    }

    $output = '<div class="pd-proyectos-relacionados">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $output .= '<article class="pd-proyectos-relacionados__item">';
        if ( has_post_thumbnail() ) {
            $output .= '<a href="' . esc_url( get_permalink() ) . '">' . get_the_post_thumbnail( get_the_ID(), 'medium_large' ) . '</a>';
        }
        $output .= '<h3><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
        $output .= '</article>';
    }
    wp_reset_postdata();
    $output .= '</div>';

    return $output;
}

if ( pd_use_legacy_project_module() ) {
    add_shortcode( 'pd_colaborador_proyectos', 'pd_shortcode_colaborador_proyectos' );
}

/**
 * Renderiza enlaces legales públicos para el footer y la portada.
 *
 * @return string
 */
function pd_shortcode_legal_links(): string {
    $items = [];

    $privacy_page = get_page_by_path( 'politica-de-privacidad' );
    if ( $privacy_page instanceof WP_Post ) {
        $items[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( get_permalink( $privacy_page ) ),
            esc_html__( 'Política de privacidad', 'pertenencia-digital' )
        );
    }

    $terms_page = get_page_by_path( 'terminos-y-condiciones' );
    if ( $terms_page instanceof WP_Post ) {
        $items[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( get_permalink( $terms_page ) ),
            esc_html__( 'Términos y condiciones', 'pertenencia-digital' )
        );
    }

    if ( empty( $items ) ) {
        return '';
    }

    return '<nav class="pd-legal-links" aria-label="' . esc_attr__( 'Enlaces legales', 'pertenencia-digital' ) . '">' . implode( '<span aria-hidden="true"> · </span>', $items ) . '</nav>';
}

add_shortcode( 'pd_legal_links', 'pd_shortcode_legal_links' );

/**
 * Crea o actualiza páginas base del tema cuando hacen falta.
 *
 * @return void
 */
function pd_ensure_theme_pages(): void {
    $parent_pages = [
        'musica' => [
            'title'   => 'Música',
            'content' => '<!-- wp:paragraph --><p>Próximamente encontrarás aquí contenidos, recursos y recorridos dedicados a la música.</p><!-- /wp:paragraph -->',
        ],
        'tecnologias-web' => [
            'title'   => 'Tecnologías y web',
            'content' => '<!-- wp:paragraph --><p>Próximamente encontrarás aquí contenidos, herramientas y publicaciones sobre tecnologías y web.</p><!-- /wp:paragraph -->',
        ],
    ];

    $parent_ids = [];

    foreach ( $parent_pages as $slug => $page ) {
        $existing = get_page_by_path( $slug );

        if ( $existing instanceof WP_Post ) {
            $parent_ids[ $slug ] = (int) $existing->ID;
            continue;
        }

        $parent_ids[ $slug ] = (int) wp_insert_post(
            [
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => $page['title'],
                'post_name'    => $slug,
                'post_content' => $page['content'],
            ]
        );
    }

    $child_pages = [
        'musica' => [
            [
                'title'    => 'Mi pertenencia',
                'slug'     => 'mi-pertenencia',
                'content'  => '<!-- wp:paragraph --><p>Gestiona aquí tu presskit y tus proyectos asociados.</p><!-- /wp:paragraph -->',
                'template' => 'mi-pertenencia',
            ],
            [
                'title'    => 'Press Kit',
                'slug'     => 'presskit',
                'content'  => '<!-- wp:paragraph --><p>Press kit y materiales oficiales.</p><!-- /wp:paragraph -->',
                'template' => 'presskit',
            ],
            [
                'title'   => 'Estudiar repertorio',
                'slug'    => 'estudiar-repertorio',
                'content' => '<!-- wp:paragraph --><p>Espacio para estudiar y trabajar el repertorio armónico.</p><!-- /wp:paragraph -->',
            ],
            [
                'title'    => 'Proyectos',
                'slug'     => 'proyectos',
                'content'  => '<!-- wp:paragraph --><p>Explora proyectos musicales y sus colaboradores.</p><!-- /wp:paragraph -->',
                'template' => 'proyectos-musica',
            ],
            [
                'title'    => 'Ensayos',
                'slug'     => 'ensayos',
                'content'  => '<!-- wp:paragraph --><p>Espacio colaborativo para disponibilidad, votaciones de ensayo y bitácora del proyecto musical.</p><!-- /wp:paragraph -->',
                'template' => 'ensayos',
            ],
        ],
        'tecnologias-web' => [
            [
                'title'   => 'Web',
                'slug'    => 'web',
                'content' => '<!-- wp:paragraph --><p>Servicios de presencia digital, desarrollo web, alojamiento, mantenimiento y WordPress.</p><!-- /wp:paragraph -->',
                'menu_order' => 10,
            ],
            [
                'title'   => 'Tecnologías digitales',
                'slug'    => 'tecnologias-digitales',
                'content' => '<!-- wp:paragraph --><p>Comisiones externas, contenido multimedia, servicio técnico y consultoría de tecnologías digitales.</p><!-- /wp:paragraph -->',
                'menu_order' => 20,
            ],
            [
                'title'   => 'Tickets',
                'slug'    => 'tickets',
                'content' => '<!-- wp:paragraph --><p>Preguntas, solicitudes, cotizaciones y seguimiento para clientes y potenciales clientes.</p><!-- /wp:paragraph -->',
                'menu_order' => 30,
            ],
            [
                'title'   => 'Enfoque tecnológico',
                'slug'    => 'enfoque-tecnologico',
                'content' => '<!-- wp:paragraph --><p>Conoce nuestro enfoque tecnológico y cómo acompañamos procesos digitales.</p><!-- /wp:paragraph -->',
                'menu_order' => 40,
            ],
            [
                'title'   => '¿Quieres tu propio espacio digital?',
                'slug'    => 'quieres-tu-propio-espacio-digital',
                'content' => '<!-- wp:paragraph --><p>Descubre por qué contar con un espacio digital propio potencia tu presencia.</p><!-- /wp:paragraph -->',
                'menu_order' => 50,
            ],
            [
                'title'   => 'Espacio digital compartido',
                'slug'    => 'presencia-basica-colaboracion',
                'content' => '<!-- wp:paragraph --><p>Carta de presentación digital dentro de una página compartida, con herramientas para editar bloques y plantillas básicas.</p><!-- /wp:paragraph -->',
                'menu_order' => 60,
            ],
            [
                'title'   => 'Presencia mínima propia',
                'slug'    => 'presencia-basica',
                'content' => '<!-- wp:paragraph --><p>Sitio propio simple con dominio anual, hosting mensual y administración accesible mediante WordPress.</p><!-- /wp:paragraph -->',
                'menu_order' => 70,
            ],
            [
                'title'   => 'Auxilio WordPress',
                'slug'    => 'auxilio-wordpress',
                'content' => '<!-- wp:paragraph --><p>Soporte, migración garantizada y replanteamiento de funciones para sitios WordPress existentes.</p><!-- /wp:paragraph -->',
                'menu_order' => 80,
            ],
            [
                'title'   => 'Necesito un trabajo multimedia por comisión',
                'slug'    => 'necesito-trabajo-multimedia-por-comision',
                'content' => '<!-- wp:paragraph --><p>Video, foto, audio o canciones: conoce nuestras opciones.</p><!-- /wp:paragraph -->',
                'menu_order' => 90,
            ],
            [
                'title'   => 'Servicio técnico digital',
                'slug'    => 'servicio-tecnico-digital',
                'content' => '<!-- wp:paragraph --><p>Apoyo técnico para revisar, ordenar o destrabar herramientas digitales.</p><!-- /wp:paragraph -->',
                'menu_order' => 100,
            ],
            [
                'title'   => 'Consultoría de tecnologías digitales',
                'slug'    => 'consultoria-tecnologias-digitales',
                'content' => '<!-- wp:paragraph --><p>Acompañamiento para elegir herramientas, ordenar requerimientos y definir honorarios.</p><!-- /wp:paragraph -->',
                'menu_order' => 110,
            ],
            [
                'title'   => 'Sitio profesional',
                'slug'    => 'sitio-profesional',
                'content' => '<!-- wp:paragraph --><p>Base profesional con herramientas de administración, tienda, transacciones y consultorías de requisitación.</p><!-- /wp:paragraph -->',
                'menu_order' => 120,
            ],
            [
                'title'   => 'Sitio profesional · administración',
                'slug'    => 'sitio-profesional-self-admin',
                'content' => '<!-- wp:paragraph --><p>Ruta para configurar herramientas, plugins y flujos de administración dentro de WordPress.</p><!-- /wp:paragraph -->',
                'menu_order' => 130,
            ],
            [
                'title'   => 'Sitio profesional · implementación',
                'slug'    => 'sitio-profesional-implementacion',
                'content' => '<!-- wp:paragraph --><p>Implementación y diseño resueltos por Pertenencia Digital con foco en utilidad, claridad visual y tiempos de salida razonables.</p><!-- /wp:paragraph -->',
                'menu_order' => 140,
            ],
            [
                'title'    => 'Proyectos',
                'slug'     => 'proyectos',
                'content'  => '<!-- wp:paragraph --><p>Explora proyectos tecnológicos y sus colaboradores.</p><!-- /wp:paragraph -->',
                'template' => 'proyectos-tecnologias',
                'menu_order' => 150,
            ],
        ],
    ];

    $has_cancionero = get_page_by_path( 'cancionero' ) instanceof WP_Post;
    $has_ensayar    = get_page_by_path( 'ensayar' ) instanceof WP_Post;
    $has_estudiar   = get_page_by_path( 'estudiar-repertorio' ) instanceof WP_Post;

    foreach ( $child_pages as $parent_slug => $pages ) {
        $parent_id = $parent_ids[ $parent_slug ] ?? 0;

        foreach ( $pages as $page ) {
            if ( 'estudiar-repertorio' === $page['slug'] && ( $has_cancionero || $has_ensayar || $has_estudiar ) ) {
                continue;
            }

            $full_path = $parent_slug . '/' . $page['slug'];
            $existing  = get_page_by_path( $full_path );

            if ( ! $existing instanceof WP_Post && $parent_id > 0 ) {
                $children = get_pages(
                    [
                        'post_type'   => 'page',
                        'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
                        'child_of'    => $parent_id,
                        'parent'      => $parent_id,
                    ]
                );

                foreach ( $children as $child_page ) {
                    if ( $child_page instanceof WP_Post && $page['slug'] === $child_page->post_name ) {
                        $existing = $child_page;
                        break;
                    }
                }
            }

            if ( $existing instanceof WP_Post ) {
                if ( isset( $page['menu_order'] ) && (int) $existing->menu_order !== (int) $page['menu_order'] && ( 'tecnologias-web' === $parent_slug || 0 === (int) $existing->menu_order ) ) {
                    wp_update_post(
                        [
                            'ID'         => (int) $existing->ID,
                            'menu_order' => (int) $page['menu_order'],
                        ]
                    );
                }

                if ( ! empty( $page['template'] ) ) {
                    $current_template = get_post_meta( $existing->ID, '_wp_page_template', true );
                    if ( ! $current_template || 'default' === $current_template ) {
                        update_post_meta( $existing->ID, '_wp_page_template', $page['template'] );
                    }
                }
                continue;
            }

            $page_id = wp_insert_post(
                [
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_content' => $page['content'],
                    'post_parent'  => $parent_id,
                    'menu_order'   => isset( $page['menu_order'] ) ? (int) $page['menu_order'] : 0,
                ]
            );

            if ( ! empty( $page['template'] ) ) {
                update_post_meta( $page_id, '_wp_page_template', $page['template'] );
            }
        }
    }

    $legal_pages = [
        [
            'title'    => 'Acceso',
            'slug'     => 'acceso',
            'content'  => '<!-- wp:paragraph --><p>Pantalla personalizada para iniciar sesion y recuperar contrasena.</p><!-- /wp:paragraph -->',
            'template' => 'acceso',
        ],
        [
            'title'   => 'Política de privacidad',
            'slug'    => 'politica-de-privacidad',
            'content' => '<!-- wp:paragraph --><p>Esta página resume cómo se recopilan, usan y protegen los datos personales dentro de este sitio. Sustituye este texto por la política final de tu proyecto.</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2>Datos que recopilamos</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Detalla aquí formularios, comentarios, cuentas de usuario, archivos y cualquier dato adicional que procese el sitio.</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2>Uso y conservación</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Describe para qué se usan los datos, quién puede acceder a ellos y cuánto tiempo se conservan.</p><!-- /wp:paragraph -->',
        ],
        [
            'title'   => 'Términos y condiciones',
            'slug'    => 'terminos-y-condiciones',
            'content' => '<!-- wp:paragraph --><p>Estos términos regulan el uso público de este sitio y sus servicios. Sustituye este texto por las condiciones finales de tu proyecto.</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2>Uso permitido</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Explica aquí qué usos están permitidos, límites de responsabilidad y condiciones de acceso a contenidos o herramientas.</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2>Propiedad intelectual</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Indica la titularidad del contenido, licencias aplicables y la forma correcta de solicitar permisos.</p><!-- /wp:paragraph -->',
        ],
    ];

    $privacy_page_id = 0;

    foreach ( $legal_pages as $page ) {
        $existing = get_page_by_path( $page['slug'] );

        if ( $existing instanceof WP_Post ) {
            $page_id = (int) $existing->ID;
        } else {
            $page_id = (int) wp_insert_post(
                [
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_content' => $page['content'],
                ]
            );
        }

        if ( ! empty( $page['template'] ) ) {
            $current_template = get_post_meta( $page_id, '_wp_page_template', true );
            if ( ! $current_template || 'default' === $current_template ) {
                update_post_meta( $page_id, '_wp_page_template', $page['template'] );
            }
        }

        if ( 'politica-de-privacidad' === $page['slug'] ) {
            $privacy_page_id = $page_id;
        }
    }

    if ( $privacy_page_id > 0 ) {
        $configured_privacy_page = absint( get_option( 'wp_page_for_privacy_policy', 0 ) );
        if ( $configured_privacy_page <= 0 || ! get_post( $configured_privacy_page ) ) {
            update_option( 'wp_page_for_privacy_policy', $privacy_page_id );
        }
    }

    if ( function_exists( 'wpssb_register_project_area_taxonomy' ) ) {
        wpssb_register_project_area_taxonomy();
    } elseif ( pd_use_legacy_project_module() ) {
        pd_register_proyecto_area_taxonomy();
    }

    if ( taxonomy_exists( PD_PROJECT_AREA_TAX ) && ! term_exists( 'musica', PD_PROJECT_AREA_TAX ) ) {
        wp_insert_term( 'Música', PD_PROJECT_AREA_TAX, [ 'slug' => 'musica' ] );
    }

    if ( taxonomy_exists( PD_PROJECT_AREA_TAX ) && ! term_exists( 'tecnologias-web', PD_PROJECT_AREA_TAX ) ) {
        wp_insert_term( 'Tecnologías y web', PD_PROJECT_AREA_TAX, [ 'slug' => 'tecnologias-web' ] );
    }
}

add_action( 'after_switch_theme', 'pd_ensure_theme_pages' );

add_action(
    'admin_init',
    function () {
        if ( ! current_user_can( 'edit_pages' ) ) {
            return;
        }

        pd_ensure_theme_pages();
    }
);
