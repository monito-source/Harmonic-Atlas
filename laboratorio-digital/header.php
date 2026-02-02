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
    <div class="header-left">
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

    <div class="header-actions">
      <?php if ( is_user_logged_in() ) : ?>
        <a class="button button-secondary" href="<?php echo esc_url( get_edit_user_link() ); ?>">
          <?php esc_html_e( 'Mi perfil', 'laboratorio-digital' ); ?>
        </a>
      <?php else : ?>
        <a class="button button-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
          <?php esc_html_e( 'Iniciar sesión', 'laboratorio-digital' ); ?>
        </a>
      <?php endif; ?>
    </div>

  </div>
</header>
