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

add_action(
    'wp_enqueue_scripts',
    function () {
        $theme      = wp_get_theme();
        $style_path = get_stylesheet_directory() . '/style.css';
        $version    = file_exists( $style_path ) ? (string) filemtime( $style_path ) : $theme->get( 'Version' );

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
 * Usa una cabecera reducida para páginas hijas de "música".
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

        if ( ! pd_is_child_of_musica_page() ) {
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
