<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
  <div class="header-inner">

    <!-- Título enlazado al home -->
    <h1 class="site-title">
      <a href="<?php echo esc_url(home_url('/')); ?>">
        <span class="linea-1">Laboratorio</span><br>
        <span class="linea-2">Digital</span>
      </a>
    </h1>

    <!-- Menú de navegación -->
    <?php if ( has_nav_menu( 'menu_principal' ) ) : ?>
      <?php
        wp_nav_menu([
          'theme_location'  => 'menu_principal',
          'container'       => 'nav',
          'container_class' => 'main-navigation',
          'menu_class'      => 'menu',
        ]);
      ?>
    <?php endif; ?>

  </div>
</header>
