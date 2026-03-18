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
