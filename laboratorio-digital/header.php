<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=0.5, maximum-scale=5, user-scalable=yes">
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
        <button
          class="menu-toggle"
          type="button"
          aria-expanded="false"
          aria-controls="main-navigation"
        >
          <?php esc_html_e( 'Menú', 'laboratorio-digital' ); ?>
        </button>
        <?php
          wp_nav_menu([
            'theme_location'  => 'menu_principal',
            'container'       => 'nav',
            'container_id'    => 'main-navigation',
            'container_class' => 'main-navigation',
            'menu_class'      => 'menu',
            'menu_id'         => 'primary-menu',
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
