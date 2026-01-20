<?php
// Estilos del tema + fuentes
function laboratorio_enqueue_styles() {
    // Fuente: Libre Baskerville
    wp_enqueue_style(
        'laboratorio-fonts',
        'https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap',
        false
    );

    // Tu estilo principal
    wp_enqueue_style('laboratorio-style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'laboratorio_enqueue_styles');

// Carga el textdomain para traducciones.
add_action('after_setup_theme', function() {
    load_theme_textdomain('laboratorio-digital', get_template_directory() . '/languages');
});

// Título dinámico
add_theme_support('title-tag');

// Registro del menú
add_action('after_setup_theme', function() {
    register_nav_menus([
        'menu_principal' => 'Menú Principal',
    ]);

    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
});
?>
