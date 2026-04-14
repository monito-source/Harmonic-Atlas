<?php
add_action(
    'after_setup_theme',
    function () {
        load_theme_textdomain( 'pertenencia-digital', get_template_directory() . '/languages' );
        add_theme_support( 'title-tag' );
        add_theme_support( 'wp-block-styles' );
        add_theme_support( 'responsive-embeds' );
        add_theme_support( 'editor-styles' );
        add_editor_style( 'style.css' );
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
 * Devuelve las plantillas de página personalizadas del tema.
 *
 * @return array<string, string>
 */
function pd_get_custom_page_templates(): array {
    return [
        'acceso'                 => __( 'Acceso', 'pertenencia-digital' ),
        'inicio'                 => __( 'Inicio / Landing', 'pertenencia-digital' ),
        'presskit'               => __( 'Press Kit', 'pertenencia-digital' ),
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

add_action(
    'wp_enqueue_scripts',
    function () {
        $theme       = wp_get_theme();
        $style_path  = get_stylesheet_directory() . '/style.css';
        $script_path = get_template_directory() . '/assets/js/account-access.js';
        $nav_path    = get_template_directory() . '/assets/js/site-navigation.js';
        $version     = file_exists( $style_path ) ? (string) filemtime( $style_path ) : $theme->get( 'Version' );

        wp_enqueue_style(
            'pertenencia-digital-fonts',
            'https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'pertenencia-digital-style',
            get_stylesheet_uri(),
            [ 'pertenencia-digital-fonts' ],
            $version
        );

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
        [ 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n' ],
        (string) filemtime( $script_path ),
        true
    );
}
add_action( 'init', 'pd_register_theme_block_editor_script', 5 );

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
    if ( ! pd_should_use_frontend_login( $redirect ) ) {
        return $login_url;
    }

    return pd_get_login_page_url( $redirect );
}
add_filter( 'login_url', 'pd_filter_login_url', 10, 3 );

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
        ]
    );

    $feedback       = pd_get_auth_feedback();
    $current_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login';
    $current_action = 'lostpassword' === $current_action ? 'lostpassword' : 'login';
    $redirect_to    = is_string( $args['redirect_to'] ) && '' !== $args['redirect_to']
        ? wp_validate_redirect( (string) $args['redirect_to'], pd_get_default_membership_url() )
        : ( isset( $_GET['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), pd_get_default_membership_url() ) : pd_get_default_membership_url() );
    $login_url      = pd_get_login_page_url( $redirect_to );
    $recover_url    = pd_get_login_page_url( '', 'lostpassword' );
    $membership_url = pd_get_default_membership_url();
    $logout_url     = wp_logout_url( $login_url );

    $output  = '<section class="pd-auth-shell">';
    $output .= '<div class="pd-auth-shell__intro">';
    $output .= '<p class="pd-auth-shell__eyebrow">' . esc_html__( 'Acceso', 'pertenencia-digital' ) . '</p>';
    $output .= '<h1 class="pd-auth-shell__title">' . esc_html( (string) $args['title'] ) . '</h1>';
    $output .= '<p class="pd-auth-shell__lead">' . esc_html( (string) $args['intro'] ) . '</p>';
    $output .= '<ul class="pd-auth-shell__list">';
    $output .= '<li>' . esc_html__( 'Formulario con mejor jerarquia visual y lectura mas clara.', 'pertenencia-digital' ) . '</li>';
    $output .= '<li>' . esc_html__( 'Recuperacion de contrasena disponible sin entrar al escritorio.', 'pertenencia-digital' ) . '</li>';
    $output .= '<li>' . esc_html__( 'Acceso administrativo nativo preservado para no romper wp-admin.', 'pertenencia-digital' ) . '</li>';
    $output .= '</ul>';
    $output .= '</div>';
    $output .= '<div class="pd-auth-card">';

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
        $output .= '<p class="pd-auth-card__alt"><a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Volver al inicio de sesion', 'pertenencia-digital' ) . '</a></p>';
    } else {
        $output .= '<h2 class="pd-auth-card__title">' . esc_html__( 'Iniciar sesion', 'pertenencia-digital' ) . '</h2>';
        $output .= '<form class="pd-auth-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $output .= '<input type="hidden" name="action" value="pd_frontend_login" />';
        $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $redirect_to ) . '" />';
        $output .= wp_nonce_field( 'pd_frontend_login', 'pd_auth_nonce', true, false );
        $output .= '<label><span>' . esc_html__( 'Usuario o correo electronico', 'pertenencia-digital' ) . '</span><input type="text" name="log" autocomplete="username" required /></label>';
        $output .= '<label><span>' . esc_html__( 'Contrasena', 'pertenencia-digital' ) . '</span><input type="password" name="pwd" autocomplete="current-password" required /></label>';
        $output .= '<label class="pd-auth-form__checkbox"><input type="checkbox" name="rememberme" value="forever" /><span>' . esc_html__( 'Mantener sesion iniciada', 'pertenencia-digital' ) . '</span></label>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button">' . esc_html__( 'Entrar a mi espacio', 'pertenencia-digital' ) . '</button>';
        $output .= '</form>';
        $output .= '<div class="pd-auth-card__links">';
        $output .= '<a href="' . esc_url( $recover_url ) . '">' . esc_html__( 'Olvide mi contrasena', 'pertenencia-digital' ) . '</a>';
        $output .= '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Volver al sitio', 'pertenencia-digital' ) . '</a>';
        $output .= '</div>';
    }

    $output .= '</div>';
    $output .= '</section>';

    return $output;
}

/**
 * Renderiza el acceso compacto del header.
 */
function pd_render_account_access_menu(): string {
    $current_url = '';

    if ( ! is_admin() ) {
        $current_url = home_url( add_query_arg( [] ) );
    }

    if ( is_user_logged_in() ) {
        $current_user   = wp_get_current_user();
        $membership_url = pd_get_default_membership_url();
        $logout_url     = wp_logout_url( $current_url ? $current_url : home_url( '/' ) );
        $menu_id        = wp_unique_id( 'pd-account-menu-' );
        $avatar         = get_avatar(
            $current_user->ID,
            40,
            '',
            $current_user->display_name,
            [
                'class'   => 'pd-account-menu__avatar-image',
                'loading' => 'lazy',
            ]
        );

        $output  = '<div class="pd-account-menu" data-account-menu>';
        $output .= '<button type="button" class="pd-account-menu__trigger" data-account-menu-trigger aria-expanded="false" aria-haspopup="true" aria-controls="' . esc_attr( $menu_id ) . '" aria-label="' . esc_attr__( 'Abrir opciones de usuario', 'pertenencia-digital' ) . '">';
        $output .= '<span class="pd-account-menu__avatar">' . $avatar . '</span>';
        $output .= '<span class="pd-account-menu__caret" aria-hidden="true"></span>';
        $output .= '</button>';
        $output .= '<div id="' . esc_attr( $menu_id ) . '" class="pd-account-menu__panel" data-account-menu-panel hidden>';
        $output .= '<p class="pd-account-menu__identity">';
        $output .= '<strong>' . esc_html( $current_user->display_name ) . '</strong><span>' . esc_html( $current_user->user_email ) . '</span>';
        $output .= '</p>';
        $output .= '<a class="pd-account-menu__link" href="' . esc_url( $membership_url ) . '">' . esc_html__( 'Mi pertenencia', 'pertenencia-digital' ) . '</a>';
        $output .= '<a class="pd-account-menu__link" href="' . esc_url( $logout_url ) . '">' . esc_html__( 'Cerrar sesion', 'pertenencia-digital' ) . '</a>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    $login_url = pd_get_login_page_url( $current_url ? $current_url : pd_get_default_membership_url() );

    return '<a class="pd-account-access__login" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Acceso', 'pertenencia-digital' ) . '</a>';
}

/**
 * Render callback del bloque de acceso compacto.
 */
function pd_render_block_account_access( array $attributes = [], string $content = '', ?WP_Block $block = null ): string {
    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'class' => 'pd-account-access-block',
            ]
        )
        : 'class="pd-account-access-block"';

    return '<div ' . $wrapper_attributes . '>' . pd_render_account_access_menu() . '</div>';
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
    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'class' => 'pd-site-navigation-block',
            ]
        )
        : 'class="pd-site-navigation-block"';

    $output  = '<div ' . $wrapper_attributes . '>';
    $output .= '<nav class="pd-site-navigation" data-site-navigation aria-label="' . esc_attr__( 'Navegacion principal', 'pertenencia-digital' ) . '">';
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
 * Obtiene la pagina raiz de la seccion Musica.
 */
function pd_get_music_root_page(): ?WP_Post {
    $music_page = get_page_by_path( 'musica' );

    return $music_page instanceof WP_Post ? $music_page : null;
}

/**
 * Obtiene las paginas hijas publicadas de Musica.
 *
 * @param string $parent_path Slug base de la seccion.
 * @return array<int, WP_Post>
 */
function pd_get_music_child_pages( string $parent_path = 'musica' ): array {
    $music_root = 'musica' === $parent_path ? pd_get_music_root_page() : get_page_by_path( $parent_path );

    if ( ! $music_root instanceof WP_Post ) {
        return [];
    }

    $pages = get_pages(
        [
            'child_of'    => 0,
            'parent'      => (int) $music_root->ID,
            'sort_column' => 'menu_order,post_title',
            'sort_order'  => 'ASC',
            'post_status' => 'publish',
        ]
    );

    return is_array( $pages ) ? array_values( array_filter( $pages, static fn ( $page ) => $page instanceof WP_Post ) ) : [];
}

/**
 * Render callback del submenu horizontal de la seccion Musica.
 *
 * @param array<string, mixed> $attributes Atributos del bloque.
 */
function pd_render_block_music_subnavigation( array $attributes = [], string $content = '', ?WP_Block $block = null ): string {
    $parent_path = isset( $attributes['parentPath'] ) && '' !== (string) $attributes['parentPath'] ? (string) $attributes['parentPath'] : 'musica';
    $pages       = pd_get_music_child_pages( $parent_path );

    if ( empty( $pages ) ) {
        if ( current_user_can( 'edit_theme_options' ) ) {
            $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
                ? get_block_wrapper_attributes(
                    [
                        'class' => 'pd-music-subnav-block pd-music-subnav-block--empty',
                    ]
                )
                : 'class="pd-music-subnav-block pd-music-subnav-block--empty"';

            return '<div ' . $wrapper_attributes . '><div class="pd-music-subnav pd-music-subnav--empty">' . esc_html__( 'No hay paginas hijas publicadas en la seccion Musica.', 'pertenencia-digital' ) . '</div></div>';
        }

        return '';
    }

    $current_id = get_queried_object_id();
    $ancestors  = $current_id > 0 ? array_map( 'intval', get_post_ancestors( $current_id ) ) : [];
    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'class' => 'pd-music-subnav-block',
            ]
        )
        : 'class="pd-music-subnav-block"';
    $output     = '<div ' . $wrapper_attributes . '>';
    $output    .= '<nav class="pd-music-subnav" aria-label="' . esc_attr__( 'Submenu de Musica', 'pertenencia-digital' ) . '">';
    $output    .= '<ul class="pd-music-subnav__list">';

    foreach ( $pages as $page ) {
        $page_url = get_permalink( $page );

        if ( ! is_string( $page_url ) || '' === $page_url ) {
            continue;
        }

        $is_current = (int) $page->ID === $current_id || in_array( (int) $page->ID, $ancestors, true );
        $classes    = 'pd-music-subnav__item' . ( $is_current ? ' is-current' : '' );

        $output .= '<li class="' . esc_attr( $classes ) . '">';
        $output .= '<a class="pd-music-subnav__link" href="' . esc_url( $page_url ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>' . esc_html( get_the_title( $page ) ) . '</a>';
        $output .= '</li>';
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
    $author = get_author_posts_url( $user->ID );

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
        ],
        'tecnologias-web' => [
            [
                'title'   => 'Enfoque tecnológico',
                'slug'    => 'enfoque-tecnologico',
                'content' => '<!-- wp:paragraph --><p>Conoce nuestro enfoque tecnológico y cómo acompañamos procesos digitales.</p><!-- /wp:paragraph -->',
            ],
            [
                'title'   => '¿Quieres tu propio espacio digital?',
                'slug'    => 'quieres-tu-propio-espacio-digital',
                'content' => '<!-- wp:paragraph --><p>Descubre por qué contar con un espacio digital propio potencia tu presencia.</p><!-- /wp:paragraph -->',
            ],
            [
                'title'   => 'Auxilio WordPress',
                'slug'    => 'auxilio-wordpress',
                'content' => '<!-- wp:paragraph --><p>Servicios y apoyo para sitios WordPress.</p><!-- /wp:paragraph -->',
            ],
            [
                'title'   => 'Necesito un trabajo multimedia por comisión',
                'slug'    => 'necesito-trabajo-multimedia-por-comision',
                'content' => '<!-- wp:paragraph --><p>Video, foto, audio o canciones: conoce nuestras opciones.</p><!-- /wp:paragraph -->',
            ],
            [
                'title'    => 'Proyectos',
                'slug'     => 'proyectos',
                'content'  => '<!-- wp:paragraph --><p>Explora proyectos tecnológicos y sus colaboradores.</p><!-- /wp:paragraph -->',
                'template' => 'proyectos-tecnologias',
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
