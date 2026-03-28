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

/**
 * Registra el rol de colaboradores digitales.
 */
function pd_register_collaborator_role(): void {
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

add_action( 'init', 'pd_register_collaborator_role' );

/**
 * Registra el CPT de proyectos.
 */
function pd_register_proyecto_cpt(): void {
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

add_action( 'init', 'pd_register_proyecto_cpt' );

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
}

add_action( 'init', 'pd_register_proyecto_meta' );

/**
 * Meta boxes para proyectos.
 */
function pd_add_proyecto_meta_boxes(): void {
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
}

add_action( 'add_meta_boxes', 'pd_add_proyecto_meta_boxes' );

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

    update_post_meta( $post_id, 'pd_proyecto_colaboradores', $colaboradores );
    update_post_meta( $post_id, 'pd_proyecto_galeria', $galeria );
    update_post_meta( $post_id, 'pd_proyecto_contacto', $contacto );
}

add_action( 'save_post_' . PD_PROJECT_POST_TYPE, 'pd_save_proyecto_meta', 10, 2 );

/**
 * Scripts para meta boxes.
 */
function pd_enqueue_proyecto_meta_assets( string $hook ): void {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
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

add_action( 'admin_enqueue_scripts', 'pd_enqueue_proyecto_meta_assets' );

/**
 * Shortcodes de colaboradores y proyectos.
 */
function pd_render_colaborador_card( WP_User $user ): string {
    $avatar = get_avatar( $user->ID, 96, '', $user->display_name, [ 'class' => 'pd-colaborador-avatar' ] );
    $bio    = get_user_meta( $user->ID, 'description', true );
    $url    = $user->user_url ? esc_url( $user->user_url ) : '';
    $author = get_author_posts_url( $user->ID );

    $output  = '<article class="pd-colaborador-card">';
    $output .= '<div class="pd-colaborador-card__header">';
    $output .= $avatar ? '<div class="pd-colaborador-card__avatar">' . $avatar . '</div>' : '';
    $output .= '<h3 class="pd-colaborador-card__name"><a href="' . esc_url( $author ) . '">' . esc_html( $user->display_name ) . '</a></h3>';
    $output .= '</div>';

    if ( $bio ) {
        $output .= '<p class="pd-colaborador-card__bio">' . esc_html( $bio ) . '</p>';
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

add_shortcode( 'pd_colaboradores', 'pd_shortcode_colaboradores' );

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

add_shortcode( 'pd_proyecto_colaboradores', 'pd_shortcode_proyecto_colaboradores' );

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

add_shortcode( 'pd_proyecto_galeria', 'pd_shortcode_proyecto_galeria' );

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

add_shortcode( 'pd_proyecto_contacto', 'pd_shortcode_proyecto_contacto' );

add_action(
    'after_switch_theme',
    function () {
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
                    'title'   => 'Press Kit',
                    'slug'    => 'presskit',
                    'content' => '<!-- wp:paragraph --><p>Press kit y materiales oficiales.</p><!-- /wp:paragraph -->',
                    'template'=> 'presskit',
                ],
                [
                    'title'   => 'Ensayar',
                    'slug'    => 'ensayar',
                    'content' => '<!-- wp:paragraph --><p>Espacio para el cancionero armónico y ensayos.</p><!-- /wp:paragraph -->',
                ],
                [
                    'title'   => 'Proyectos',
                    'slug'    => 'proyectos',
                    'content' => '<!-- wp:paragraph --><p>Explora proyectos y colegas involucrados.</p><!-- /wp:paragraph -->',
                ],
            ],
            'tecnologias-web' => [
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
                    'title'   => 'Necesito multimedia',
                    'slug'    => 'necesito-multimedia',
                    'content' => '<!-- wp:paragraph --><p>Video, foto, audio o canciones: conoce nuestras opciones.</p><!-- /wp:paragraph -->',
                ],
            ],
        ];

        $has_cancionero = get_page_by_path( 'cancionero' ) instanceof WP_Post;

        foreach ( $child_pages as $parent_slug => $pages ) {
            $parent_id = $parent_ids[ $parent_slug ] ?? 0;

            foreach ( $pages as $page ) {
                if ( 'ensayar' === $page['slug'] && $has_cancionero ) {
                    continue;
                }

                $existing = get_page_by_path( $page['slug'] );

                if ( $existing instanceof WP_Post ) {
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
    }
);
