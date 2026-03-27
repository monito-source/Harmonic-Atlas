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
 * Determina si la página actual es descendiente de la página "musica".
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

    $ancestors = get_post_ancestors( $current_page_id );

    return in_array( $musica_page_id, $ancestors, true );
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

add_action(
    'after_switch_theme',
    function () {
        $pages = [
            [
                'title'   => 'Música',
                'slug'    => 'musica',
                'content' => '<!-- wp:paragraph --><p>Próximamente encontrarás aquí contenidos, recursos y recorridos dedicados a la música.</p><!-- /wp:paragraph -->',
            ],
            [
                'title'   => 'Tecnologías y web',
                'slug'    => 'tecnologias-web',
                'content' => '<!-- wp:paragraph --><p>Próximamente encontrarás aquí contenidos, herramientas y publicaciones sobre tecnologías y web.</p><!-- /wp:paragraph -->',
            ],
        ];

        foreach ( $pages as $page ) {
            $existing = get_page_by_path( $page['slug'] );

            if ( $existing instanceof WP_Post ) {
                continue;
            }

            wp_insert_post(
                [
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_content' => $page['content'],
                ]
            );
        }
    }
);
