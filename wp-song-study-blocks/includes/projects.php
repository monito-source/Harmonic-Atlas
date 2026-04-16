<?php
/**
 * Centraliza proyectos, membresías y presskits para plugin y tema.
 *
 * @package WP_Song_Study_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WPSSB_PROJECTS_CENTRALIZED' ) ) {
    define( 'WPSSB_PROJECTS_CENTRALIZED', true );
}

if ( ! defined( 'WPSSB_COLLABORATOR_CAP' ) ) {
    define( 'WPSSB_COLLABORATOR_CAP', 'pd_colaborador' );
}

if ( ! defined( 'WPSSB_PROJECT_POST_TYPE' ) ) {
    define( 'WPSSB_PROJECT_POST_TYPE', 'proyecto' );
}

if ( ! defined( 'WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE' ) ) {
    define( 'WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE', 'presskit' );
}

if ( ! defined( 'WPSSB_PROJECT_AREA_TAX' ) ) {
    define( 'WPSSB_PROJECT_AREA_TAX', 'area_proyecto' );
}

if ( ! defined( 'WPSSB_COLLABORATOR_TARGET_META' ) ) {
    define( 'WPSSB_COLLABORATOR_TARGET_META', '_wpssb_collaborator_user_id' );
}

if ( ! defined( 'WPSSB_COLLABORATOR_PRESSKIT_USER_META' ) ) {
    define( 'WPSSB_COLLABORATOR_PRESSKIT_USER_META', '_wpssb_collaborator_presskit_post_id' );
}

/**
 * Sanitiza listas de IDs.
 *
 * @param mixed $value Valor crudo.
 * @return int[]
 */
function wpssb_sanitize_id_list( $value ) {
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
 * Registra el rol reutilizable para colaboradores.
 *
 * @return void
 */
function wpssb_register_collaborator_role() {
    if ( null === get_role( 'pd_colaborador' ) ) {
        add_role(
            'pd_colaborador',
            __( 'Colaborador digital', 'wp-song-study-blocks' ),
            [
                'read'                 => true,
                'upload_files'         => true,
                WPSSB_COLLABORATOR_CAP => true,
            ]
        );
    }

    $role = get_role( 'pd_colaborador' );
    if ( $role && ! $role->has_cap( 'upload_files' ) ) {
        $role->add_cap( 'upload_files' );
    }

    $collaborator_presskit_caps = [
        'read_presskit',
        'edit_presskit',
        'delete_presskit',
        'edit_presskits',
        'publish_presskits',
        'delete_presskits',
        'delete_published_presskits',
        'edit_published_presskits',
    ];

    $admin_presskit_caps = array_merge(
        $collaborator_presskit_caps,
        [
            'edit_others_presskits',
            'read_private_presskits',
            'delete_private_presskits',
            'delete_others_presskits',
            'edit_private_presskits',
        ]
    );

    if ( $role ) {
        foreach ( $collaborator_presskit_caps as $cap ) {
            if ( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }

        foreach ( array_diff( $admin_presskit_caps, $collaborator_presskit_caps ) as $cap ) {
            if ( $role->has_cap( $cap ) ) {
                $role->remove_cap( $cap );
            }
        }
    }

    if ( function_exists( 'wpss_add_cap_to_role' ) ) {
        wpss_add_cap_to_role( 'pd_colaborador', WPSSB_COLLABORATOR_CAP );

        if ( defined( 'WPSS_ROLE_COLEGA' ) ) {
            wpss_add_cap_to_role( WPSS_ROLE_COLEGA, WPSSB_COLLABORATOR_CAP );
            wpss_add_cap_to_role( WPSS_ROLE_COLEGA, 'upload_files' );
            foreach ( $collaborator_presskit_caps as $cap ) {
                wpss_add_cap_to_role( WPSS_ROLE_COLEGA, $cap );
            }

            $colega_role = get_role( WPSS_ROLE_COLEGA );

            if ( $colega_role ) {
                foreach ( array_diff( $admin_presskit_caps, $collaborator_presskit_caps ) as $cap ) {
                    if ( $colega_role->has_cap( $cap ) ) {
                        $colega_role->remove_cap( $cap );
                    }
                }
            }
        }

        wpss_add_cap_to_role( 'administrator', WPSSB_COLLABORATOR_CAP );
        foreach ( $admin_presskit_caps as $cap ) {
            wpss_add_cap_to_role( 'administrator', $cap );
        }
    }

    $admin_role = get_role( 'administrator' );

    if ( $admin_role ) {
        foreach ( $admin_presskit_caps as $cap ) {
            if ( ! $admin_role->has_cap( $cap ) ) {
                $admin_role->add_cap( $cap );
            }
        }
    }
}
add_action( 'init', 'wpssb_register_collaborator_role' );

/**
 * Registra el CPT editable de presskits personales.
 *
 * @return void
 */
function wpssb_register_collaborator_presskit_post_type() {
    if ( post_type_exists( WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE ) ) {
        return;
    }

    $labels = [
        'name'               => __( 'Presskits personales', 'wp-song-study-blocks' ),
        'singular_name'      => __( 'Presskit personal', 'wp-song-study-blocks' ),
        'add_new'            => __( 'Añadir nuevo', 'wp-song-study-blocks' ),
        'add_new_item'       => __( 'Añadir nuevo presskit personal', 'wp-song-study-blocks' ),
        'edit_item'          => __( 'Editar presskit personal', 'wp-song-study-blocks' ),
        'new_item'           => __( 'Nuevo presskit personal', 'wp-song-study-blocks' ),
        'view_item'          => __( 'Ver presskit personal', 'wp-song-study-blocks' ),
        'search_items'       => __( 'Buscar presskits personales', 'wp-song-study-blocks' ),
        'not_found'          => __( 'No se encontraron presskits personales', 'wp-song-study-blocks' ),
        'not_found_in_trash' => __( 'No hay presskits personales en la papelera', 'wp-song-study-blocks' ),
        'all_items'          => __( 'Todos los presskits personales', 'wp-song-study-blocks' ),
    ];

    register_post_type(
        WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE,
        [
            'labels'          => $labels,
            'public'          => true,
            'show_in_rest'    => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-id-alt',
            'supports'        => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ],
            'has_archive'     => false,
            'rewrite'         => [
                'slug' => 'presskit',
            ],
            'map_meta_cap'    => true,
            'capability_type' => [ 'presskit', 'presskits' ],
        ]
    );
}
add_action( 'init', 'wpssb_register_collaborator_presskit_post_type' );

/**
 * Registra el CPT de proyectos.
 *
 * @return void
 */
function wpssb_register_project_post_type() {
    if ( post_type_exists( WPSSB_PROJECT_POST_TYPE ) ) {
        return;
    }

    $labels = [
        'name'               => __( 'Proyectos', 'wp-song-study-blocks' ),
        'singular_name'      => __( 'Proyecto', 'wp-song-study-blocks' ),
        'add_new'            => __( 'Añadir nuevo', 'wp-song-study-blocks' ),
        'add_new_item'       => __( 'Añadir nuevo proyecto', 'wp-song-study-blocks' ),
        'edit_item'          => __( 'Editar proyecto', 'wp-song-study-blocks' ),
        'new_item'           => __( 'Nuevo proyecto', 'wp-song-study-blocks' ),
        'view_item'          => __( 'Ver proyecto', 'wp-song-study-blocks' ),
        'search_items'       => __( 'Buscar proyectos', 'wp-song-study-blocks' ),
        'not_found'          => __( 'No se encontraron proyectos', 'wp-song-study-blocks' ),
        'not_found_in_trash' => __( 'No hay proyectos en la papelera', 'wp-song-study-blocks' ),
        'all_items'          => __( 'Todos los proyectos', 'wp-song-study-blocks' ),
    ];

    register_post_type(
        WPSSB_PROJECT_POST_TYPE,
        [
            'labels'       => $labels,
            'public'       => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-networking',
            'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'has_archive'  => true,
            'rewrite'      => [
                'slug' => 'proyecto',
            ],
        ]
    );
}
add_action( 'init', 'wpssb_register_project_post_type' );

/**
 * Registra la taxonomía de áreas de proyecto.
 *
 * @return void
 */
function wpssb_register_project_area_taxonomy() {
    if ( taxonomy_exists( WPSSB_PROJECT_AREA_TAX ) ) {
        return;
    }

    $labels = [
        'name'          => __( 'Áreas del proyecto', 'wp-song-study-blocks' ),
        'singular_name' => __( 'Área del proyecto', 'wp-song-study-blocks' ),
        'search_items'  => __( 'Buscar áreas', 'wp-song-study-blocks' ),
        'all_items'     => __( 'Todas las áreas', 'wp-song-study-blocks' ),
        'edit_item'     => __( 'Editar área', 'wp-song-study-blocks' ),
        'update_item'   => __( 'Actualizar área', 'wp-song-study-blocks' ),
        'add_new_item'  => __( 'Añadir nueva área', 'wp-song-study-blocks' ),
        'new_item_name' => __( 'Nuevo nombre de área', 'wp-song-study-blocks' ),
        'menu_name'     => __( 'Áreas', 'wp-song-study-blocks' ),
    ];

    register_taxonomy(
        WPSSB_PROJECT_AREA_TAX,
        [ WPSSB_PROJECT_POST_TYPE ],
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
add_action( 'init', 'wpssb_register_project_area_taxonomy' );

/**
 * Registra meta del proyecto.
 *
 * @return void
 */
function wpssb_register_project_meta() {
    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_colaboradores',
        [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => 'wpssb_sanitize_id_list',
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
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_galeria',
        [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => 'wpssb_sanitize_id_list',
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
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_contacto',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_tagline',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_presskit',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_links',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'show_in_rest'      => true,
        ]
    );
}
add_action( 'init', 'wpssb_register_project_meta' );

/**
 * Registra meta de usuario para presskits.
 *
 * @return void
 */
function wpssb_register_collaborator_meta() {
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
            'sanitize_callback' => 'wpssb_sanitize_id_list',
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
add_action( 'init', 'wpssb_register_collaborator_meta' );

/**
 * Obtiene colaboradores ordenados para administración y render.
 *
 * @return WP_User[]
 */
function wpssb_user_is_project_collaborator_candidate( $user ) {
    if ( ! $user instanceof WP_User ) {
        return false;
    }

    if ( user_can( $user, WPSSB_COLLABORATOR_CAP ) ) {
        return true;
    }

    if ( defined( 'WPSS_CAP_MANAGE' ) && user_can( $user, WPSS_CAP_MANAGE ) ) {
        return true;
    }

    if ( defined( 'WPSS_ROLE_COLEGA' ) && in_array( WPSS_ROLE_COLEGA, (array) $user->roles, true ) ) {
        return true;
    }

    return false;
}

/**
 * Obtiene usuarios elegibles como colaboradores de proyecto.
 *
 * @return WP_User[]
 */
function wpssb_get_collaborators() {
    $users = get_users(
        [
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ]
    );

    return array_values(
        array_filter(
            $users,
            'wpssb_user_is_project_collaborator_candidate'
        )
    );
}

/**
 * Meta boxes de proyecto.
 *
 * @return void
 */
function wpssb_add_project_meta_boxes() {
    add_meta_box(
        'wpssb-project-collaborators',
        __( 'Colaboradores', 'wp-song-study-blocks' ),
        'wpssb_render_project_collaborators_meta_box',
        WPSSB_PROJECT_POST_TYPE,
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'wpssb_add_project_meta_boxes' );

/**
 * Registra el meta box para elegir el usuario objetivo de una página pública.
 *
 * @return void
 */
function wpssb_add_collaborator_target_meta_box() {
    foreach ( [ 'page', WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE ] as $screen ) {
        add_meta_box(
            'wpssb-collaborator-target',
            __( 'Usuario objetivo del presskit', 'wp-song-study-blocks' ),
            'wpssb_render_collaborator_target_meta_box',
            $screen,
            'side',
            'default'
        );
    }
}
add_action( 'add_meta_boxes', 'wpssb_add_collaborator_target_meta_box' );

/**
 * Renderiza el selector de usuario objetivo en páginas.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_collaborator_target_meta_box( WP_Post $post ) {
    wp_nonce_field( 'wpssb_save_collaborator_target_meta', 'wpssb_collaborator_target_nonce' );

    $selected_user_id = absint( get_post_meta( $post->ID, WPSSB_COLLABORATOR_TARGET_META, true ) );
    $page_template    = (string) get_page_template_slug( $post->ID );
    $users            = get_users(
        [
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ]
    );

    echo '<p>' . esc_html__( 'Elige qué colaborador, colega músico o administrador representa esta página. Los bloques públicos de presskit, galería, contacto y proyectos usarán este usuario antes de tomar el autor de la página.', 'wp-song-study-blocks' ) . '</p>';

    if ( 'presskit' === $page_template ) {
        echo '<p><strong>' . esc_html__( 'La plantilla actual es Press Kit.', 'wp-song-study-blocks' ) . '</strong></p>';
    }

    echo '<label class="screen-reader-text" for="wpssb_collaborator_target_user">' . esc_html__( 'Usuario objetivo', 'wp-song-study-blocks' ) . '</label>';
    echo '<select id="wpssb_collaborator_target_user" name="wpssb_collaborator_target_user" class="widefat">';
    echo '<option value="0">' . esc_html__( 'Autor de la página (por defecto)', 'wp-song-study-blocks' ) . '</option>';

    foreach ( $users as $user ) {
        if ( ! $user instanceof WP_User ) {
            continue;
        }

        printf(
            '<option value="%1$d" %2$s>%3$s</option>',
            (int) $user->ID,
            selected( $selected_user_id, (int) $user->ID, false ),
            esc_html( $user->display_name . ' · ' . $user->user_email )
        );
    }

    echo '</select>';
}

/**
 * Obtiene el ID del presskit personal vinculado a un usuario.
 *
 * @param int $user_id Usuario objetivo.
 * @return int
 */
function wpssb_get_collaborator_presskit_post_id( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return 0;
    }

    $stored_post_id = absint( get_user_meta( $user_id, WPSSB_COLLABORATOR_PRESSKIT_USER_META, true ) );

    if ( $stored_post_id > 0 && WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE === get_post_type( $stored_post_id ) ) {
        wpssb_sync_collaborator_presskit_post_slug( $stored_post_id, $user_id );
        return $stored_post_id;
    }

    $posts = get_posts(
        [
            'post_type'      => WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => WPSSB_COLLABORATOR_TARGET_META,
                    'value' => $user_id,
                ],
            ],
        ]
    );

    $post_id = ! empty( $posts[0] ) ? (int) $posts[0] : 0;

    if ( $post_id > 0 ) {
        update_user_meta( $user_id, WPSSB_COLLABORATOR_PRESSKIT_USER_META, $post_id );
        wpssb_sync_collaborator_presskit_post_slug( $post_id, $user_id );
    }

    return $post_id;
}

/**
 * Devuelve el URL público preferido para un colaborador.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_get_collaborator_public_url( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return home_url( '/' );
    }

    $presskit_post_id = wpssb_get_collaborator_presskit_post_id( $user_id );

    if ( $presskit_post_id > 0 ) {
        $permalink = get_permalink( $presskit_post_id );

        if ( is_string( $permalink ) && '' !== $permalink ) {
            return $permalink;
        }
    }

    return get_author_posts_url( $user_id );
}

/**
 * Redirige el archivo de autor al presskit personal cuando exista.
 *
 * @return void
 */
function wpssb_redirect_author_archive_to_presskit() {
    if ( is_admin() || ! is_author() || is_feed() || is_preview() ) {
        return;
    }

    if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    $user_id = (int) get_queried_object_id();

    if ( $user_id <= 0 ) {
        return;
    }

    $presskit_post_id = wpssb_get_collaborator_presskit_post_id( $user_id );

    if ( $presskit_post_id <= 0 ) {
        return;
    }

    $target_url = get_permalink( $presskit_post_id );

    if ( ! is_string( $target_url ) || '' === $target_url ) {
        return;
    }

    wp_safe_redirect( $target_url, 302, 'WP Song Study Blocks' );
    exit;
}
add_action( 'template_redirect', 'wpssb_redirect_author_archive_to_presskit' );

/**
 * Redirige slugs legacy o adivinados de presskit al permalink real del usuario.
 *
 * Ejemplo: `/presskit/sergiomendoza/` aunque el post se haya creado con otro slug.
 *
 * @return void
 */
function wpssb_redirect_guessed_presskit_slug() {
    if ( is_admin() || ! is_404() || is_feed() || is_preview() ) {
        return;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
    $request_path = trim( $request_path, '/' );

    if ( '' === $request_path ) {
        return;
    }

    $home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

    if ( '' !== $home_path && str_starts_with( $request_path, $home_path . '/' ) ) {
        $request_path = substr( $request_path, strlen( $home_path ) + 1 );
    }

    if ( ! preg_match( '#^presskit/([^/]+)$#', $request_path, $matches ) ) {
        return;
    }

    $candidate = sanitize_title( $matches[1] );

    if ( '' === $candidate ) {
        return;
    }

    $user = get_user_by( 'slug', $candidate );

    if ( ! $user instanceof WP_User ) {
        $user = get_user_by( 'login', $candidate );
    }

    if ( ! $user instanceof WP_User ) {
        return;
    }

    $presskit_post_id = wpssb_get_collaborator_presskit_post_id( (int) $user->ID );

    if ( $presskit_post_id <= 0 ) {
        return;
    }

    $target_url = get_permalink( $presskit_post_id );

    if ( ! $target_url ) {
        return;
    }

    wp_safe_redirect( $target_url, 301, 'WP Song Study Blocks' );
    exit;
}
add_action( 'template_redirect', 'wpssb_redirect_guessed_presskit_slug', 1 );

/**
 * Construye el contenido inicial editable del presskit personal.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_get_default_collaborator_presskit_content( $user_id = 0 ) {
    return <<<'HTML'
<!-- wp:group {"align":"wide","className":"pd-presskit__surface pd-presskit__surface--hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__surface pd-presskit__surface--hero">
  <!-- wp:wp-song-study/collaborator-presskit /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">
  <!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->
  <p class="pd-eyebrow has-x-small-font-size">Historia, enfoque y materiales</p>
  <!-- /wp:paragraph -->

  <!-- wp:heading {"level":2} -->
  <h2 class="wp-block-heading">Contenido editorial</h2>
  <!-- /wp:heading -->

  <!-- wp:paragraph -->
  <p>Usa este espacio como un lienzo abierto: biografía extensa, statement artístico, embebidos, prensa, citas, agenda, dossier o cualquier composición hecha con bloques.</p>
  <!-- /wp:paragraph -->
  <!-- wp:paragraph -->
  <p>Los bloques del plugin siguen disponibles para reutilizar datos base, pero ya no limitan la forma final de tu presskit.</p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">
  <!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->
  <p class="pd-eyebrow has-x-small-font-size">Trayectoria</p>
  <!-- /wp:paragraph -->

  <!-- wp:heading {"level":2} -->
  <h2 class="wp-block-heading">Proyectos</h2>
  <!-- /wp:heading -->

  <!-- wp:wp-song-study/collaborator-projects /-->
</div>
<!-- /wp:group -->
HTML;
}

/**
 * Construye contenido migrado para un presskit personal legacy.
 *
 * Si el nuevo CPT existe pero llega vacío, intenta sembrarlo con la información
 * mínima que antes vivía en user meta para no dejar el documento en blanco.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_get_migrated_collaborator_presskit_content( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return '';
    }

    $user = get_user_by( 'id', $user_id );

    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $tagline       = trim( (string) get_user_meta( $user_id, 'pd_colaborador_tagline', true ) );
    $legacy_text   = trim( (string) get_user_meta( $user_id, 'pd_colaborador_presskit', true ) );
    $description   = trim( (string) $user->description );
    $fallback_text = '' !== $legacy_text ? $legacy_text : $description;

    if ( '' === $tagline && '' === $fallback_text ) {
        return '';
    }

    $content = [];

    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Presentación', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":2} -->';
    $content[] = '<h2 class="wp-block-heading">' . esc_html( $user->display_name ) . '</h2>';
    $content[] = '<!-- /wp:heading -->';

    if ( '' !== $tagline ) {
        $content[] = '<!-- wp:paragraph {"fontSize":"large"} -->';
        $content[] = '<p class="has-large-font-size">' . esc_html( $tagline ) . '</p>';
        $content[] = '<!-- /wp:paragraph -->';
    }

    if ( '' !== $fallback_text ) {
        foreach ( preg_split( "/\n\s*\n/", $fallback_text ) as $paragraph ) {
            $paragraph = trim( wp_strip_all_tags( $paragraph ) );

            if ( '' === $paragraph ) {
                continue;
            }

            $content[] = '<!-- wp:paragraph -->';
            $content[] = '<p>' . esc_html( $paragraph ) . '</p>';
            $content[] = '<!-- /wp:paragraph -->';
        }
    }

    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';
    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Trayectoria', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":2} -->';
    $content[] = '<h2 class="wp-block-heading">' . esc_html__( 'Proyectos', 'wp-song-study-blocks' ) . '</h2>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:wp-song-study/collaborator-projects /-->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';

    return implode( "\n", $content );
}

/**
 * Devuelve contenido efectivo para un documento editable de presskit/proyecto.
 *
 * @param WP_Post $post Post objetivo.
 * @return string
 */
function wpssb_get_effective_presskit_document_content( WP_Post $post ) {
    $content = trim( (string) $post->post_content );

    if ( '' !== $content ) {
        return (string) $post->post_content;
    }

    if ( WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE === $post->post_type ) {
        $user_id = wpssb_get_explicit_collaborator_target_user_id( $post->ID );

        if ( ! $user_id ) {
            $user_id = (int) ( $post->post_author ?: get_current_user_id() );
        }

        $migrated = wpssb_get_migrated_collaborator_presskit_content( $user_id );

        return '' !== $migrated ? $migrated : wpssb_get_default_collaborator_presskit_content( $user_id );
    }

    if ( WPSSB_PROJECT_POST_TYPE === $post->post_type ) {
        return wpssb_get_default_project_presskit_content( (int) $post->ID );
    }

    return '';
}

/**
 * Construye el contenido inicial editable del proyecto.
 *
 * @param int $post_id Proyecto objetivo.
 * @return string
 */
function wpssb_get_default_project_presskit_content( $post_id = 0 ) {
    return <<<'HTML'
<!-- wp:group {"align":"wide","className":"pd-presskit__surface pd-presskit__surface--hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__surface pd-presskit__surface--hero">
  <!-- wp:wp-song-study/project-presskit /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">
  <!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->
  <p class="pd-eyebrow has-x-small-font-size">Narrativa del proyecto</p>
  <!-- /wp:paragraph -->

  <!-- wp:heading {"level":2} -->
  <h2 class="wp-block-heading">Contenido editorial</h2>
  <!-- /wp:heading -->

  <!-- wp:paragraph -->
  <p>Este documento ya es libre para componer el presskit del proyecto con bloques: contexto, manifiesto, hitos, embeds, dossier, agenda, prensa o cualquier estructura editorial.</p>
  <!-- /wp:paragraph -->
  <!-- wp:paragraph -->
  <p>Si quieres empezar con una base visual, inserta un patrón del tema y ajústalo libremente en el editor.</p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">
  <!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->
  <p class="pd-eyebrow has-x-small-font-size">Equipo</p>
  <!-- /wp:paragraph -->

  <!-- wp:heading {"level":2} -->
  <h2 class="wp-block-heading">Integrantes y colaboradores</h2>
  <!-- /wp:heading -->

  <!-- wp:wp-song-study/project-collaborators /-->
</div>
<!-- /wp:group -->
HTML;
}

/**
 * Aplica contenido inicial al editor para presskits personales y proyectos.
 *
 * @param string  $content Contenido actual.
 * @param WP_Post $post    Post actual.
 * @return string
 */
function wpssb_filter_default_presskit_content( $content, $post ) {
    if ( ! $post instanceof WP_Post || '' !== trim( (string) $content ) ) {
        return $content;
    }

    if ( WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE === $post->post_type ) {
        $user_id = wpssb_get_explicit_collaborator_target_user_id( $post->ID );

        if ( ! $user_id ) {
            $user_id = (int) ( $post->post_author ?: get_current_user_id() );
        }

        return wpssb_get_default_collaborator_presskit_content( $user_id );
    }

    if ( WPSSB_PROJECT_POST_TYPE === $post->post_type ) {
        return wpssb_get_default_project_presskit_content( (int) $post->ID );
    }

    return $content;
}
add_filter( 'default_content', 'wpssb_filter_default_presskit_content', 10, 2 );

/**
 * Garantiza que un colaborador tenga un presskit editable vinculado.
 *
 * @param int $user_id Usuario objetivo.
 * @return int
 */
function wpssb_ensure_collaborator_presskit_post( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return 0;
    }

    $existing_post_id = wpssb_get_collaborator_presskit_post_id( $user_id );

    if ( $existing_post_id > 0 ) {
        $existing_post = get_post( $existing_post_id );

        if ( $existing_post instanceof WP_Post && '' === trim( (string) $existing_post->post_content ) ) {
            $effective_content = wpssb_get_effective_presskit_document_content( $existing_post );

            if ( '' !== trim( $effective_content ) ) {
                wp_update_post(
                    [
                        'ID'           => $existing_post_id,
                        'post_content' => $effective_content,
                    ]
                );
            }
        }

        return $existing_post_id;
    }

    $user = get_user_by( 'id', $user_id );

    if ( ! $user instanceof WP_User ) {
        return 0;
    }

    $post_id = wp_insert_post(
        [
            'post_type'    => WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $user->display_name,
            'post_author'  => $user_id,
            'post_name'    => wpssb_get_preferred_collaborator_presskit_slug( $user ),
            'post_content' => wpssb_get_default_collaborator_presskit_content( $user_id ),
            'meta_input'   => [
                WPSSB_COLLABORATOR_TARGET_META => $user_id,
            ],
        ],
        true
    );

    if ( is_wp_error( $post_id ) ) {
        return 0;
    }

    update_user_meta( $user_id, WPSSB_COLLABORATOR_PRESSKIT_USER_META, (int) $post_id );
    wpssb_sync_collaborator_presskit_post_slug( (int) $post_id, $user_id );

    return (int) $post_id;
}

/**
 * Render del meta box de miembros.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_project_collaborators_meta_box( WP_Post $post ) {
    wp_nonce_field( 'wpssb_save_project_meta', 'wpssb_project_meta_nonce' );

    $selected = wpssb_sanitize_id_list( get_post_meta( $post->ID, 'pd_proyecto_colaboradores', true ) );
    $users    = wpssb_get_collaborators();

    if ( empty( $users ) ) {
        echo '<p>' . esc_html__( 'No hay colaboradores disponibles. Asigna el rol o capability primero.', 'wp-song-study-blocks' ) . '</p>';
        return;
    }

    echo '<div class="wpssb-project-collaborators-meta">';

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

/**
 * Render del meta box de contacto.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_project_contact_meta_box( WP_Post $post ) {
    $contact = get_post_meta( $post->ID, 'pd_proyecto_contacto', true );

    echo '<p>' . esc_html__( 'Cómo contactar al proyecto: email, teléfono, formulario o redes.', 'wp-song-study-blocks' ) . '</p>';
    printf(
        '<textarea name="pd_proyecto_contacto" rows="4" style="width:100%%;">%s</textarea>',
        esc_textarea( (string) $contact )
    );
}

/**
 * Render del meta box de galería.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_project_gallery_meta_box( WP_Post $post ) {
    $gallery = wpssb_sanitize_id_list( get_post_meta( $post->ID, 'pd_proyecto_galeria', true ) );

    echo '<div class="wpssb-project-gallery-meta" data-initial="' . esc_attr( implode( ',', $gallery ) ) . '">';
    echo '<p>' . esc_html__( 'Selecciona imágenes para la galería del proyecto.', 'wp-song-study-blocks' ) . '</p>';
    echo '<input type="hidden" name="pd_proyecto_galeria" value="' . esc_attr( implode( ',', $gallery ) ) . '" />';
    echo '<button type="button" class="button wpssb-project-gallery-select">' . esc_html__( 'Elegir imágenes', 'wp-song-study-blocks' ) . '</button>';
    echo '<button type="button" class="button wpssb-project-gallery-clear" style="margin-left:6px;">' . esc_html__( 'Limpiar galería', 'wp-song-study-blocks' ) . '</button>';
    echo '<div class="wpssb-project-gallery-preview" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;"></div>';
    echo '</div>';
}

/**
 * Render del meta box de presskit.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_project_presskit_meta_box( WP_Post $post ) {
    $tagline  = get_post_meta( $post->ID, 'pd_proyecto_tagline', true );
    $presskit = get_post_meta( $post->ID, 'pd_proyecto_presskit', true );
    $links    = get_post_meta( $post->ID, 'pd_proyecto_links', true );

    echo '<p>' . esc_html__( 'Resume el proyecto como presskit: tagline, descripción breve y enlaces.', 'wp-song-study-blocks' ) . '</p>';
    printf(
        '<label style="display:block;margin-bottom:10px;"><strong>%s</strong><br/><input type="text" name="pd_proyecto_tagline" value="%s" style="width:100%%;" /></label>',
        esc_html__( 'Tagline', 'wp-song-study-blocks' ),
        esc_attr( (string) $tagline )
    );

    printf(
        '<label style="display:block;margin-bottom:10px;"><strong>%s</strong><br/><textarea name="pd_proyecto_presskit" rows="4" style="width:100%%;">%s</textarea></label>',
        esc_html__( 'Descripción / Presskit', 'wp-song-study-blocks' ),
        esc_textarea( (string) $presskit )
    );

    printf(
        '<label style="display:block;"><strong>%s</strong><br/><textarea name="pd_proyecto_links" rows="3" style="width:100%%;">%s</textarea></label>',
        esc_html__( 'Links (uno por línea)', 'wp-song-study-blocks' ),
        esc_textarea( (string) $links )
    );
}

/**
 * Guarda meta del proyecto.
 *
 * @param int     $post_id ID del post.
 * @param WP_Post $post    Post actual.
 * @return void
 */
function wpssb_save_project_meta( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! $post instanceof WP_Post || WPSSB_PROJECT_POST_TYPE !== $post->post_type ) {
        return;
    }

    if ( ! isset( $_POST['wpssb_project_meta_nonce'] ) || ! wp_verify_nonce( $_POST['wpssb_project_meta_nonce'], 'wpssb_save_project_meta' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $collaborators = isset( $_POST['pd_proyecto_colaboradores'] ) ? wpssb_sanitize_id_list( wp_unslash( $_POST['pd_proyecto_colaboradores'] ) ) : [];
    update_post_meta( $post_id, 'pd_proyecto_colaboradores', $collaborators );

    if ( isset( $_POST['pd_proyecto_galeria'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_galeria', wpssb_sanitize_id_list( wp_unslash( $_POST['pd_proyecto_galeria'] ) ) );
    }

    if ( isset( $_POST['pd_proyecto_contacto'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_contacto', wp_kses_post( wp_unslash( $_POST['pd_proyecto_contacto'] ) ) );
    }

    if ( isset( $_POST['pd_proyecto_tagline'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_tagline', sanitize_text_field( wp_unslash( $_POST['pd_proyecto_tagline'] ) ) );
    }

    if ( isset( $_POST['pd_proyecto_presskit'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_presskit', wp_kses_post( wp_unslash( $_POST['pd_proyecto_presskit'] ) ) );
    }

    if ( isset( $_POST['pd_proyecto_links'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_links', sanitize_textarea_field( wp_unslash( $_POST['pd_proyecto_links'] ) ) );
    }
}
add_action( 'save_post_' . WPSSB_PROJECT_POST_TYPE, 'wpssb_save_project_meta', 10, 2 );

/**
 * Guarda el usuario objetivo vinculado a una página pública.
 *
 * @param int $post_id ID del post.
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_save_collaborator_target_meta( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, [ 'page', WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE ], true ) ) {
        return;
    }

    if ( ! isset( $_POST['wpssb_collaborator_target_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wpssb_collaborator_target_nonce'] ), 'wpssb_save_collaborator_target_meta' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $user_id = isset( $_POST['wpssb_collaborator_target_user'] ) ? absint( wp_unslash( $_POST['wpssb_collaborator_target_user'] ) ) : 0;

    if ( $user_id > 0 ) {
        update_post_meta( $post_id, WPSSB_COLLABORATOR_TARGET_META, $user_id );
        if ( WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE === $post->post_type ) {
            update_user_meta( $user_id, WPSSB_COLLABORATOR_PRESSKIT_USER_META, $post_id );
        }
        return;
    }

    delete_post_meta( $post_id, WPSSB_COLLABORATOR_TARGET_META );
}
add_action( 'save_post', 'wpssb_save_collaborator_target_meta', 10, 2 );

/**
 * Obtiene el usuario objetivo explícito configurado para una página o post.
 *
 * @param int $post_id ID del post.
 * @return int
 */
function wpssb_get_explicit_collaborator_target_user_id( $post_id ) {
    $post_id = absint( $post_id );

    if ( ! $post_id ) {
        return 0;
    }

    return absint( get_post_meta( $post_id, WPSSB_COLLABORATOR_TARGET_META, true ) );
}

/**
 * Devuelve el usuario owner principal del sitio.
 *
 * Prioriza el usuario cuyo correo coincide con `admin_email` y, si no existe,
 * usa el primer administrador disponible.
 *
 * @return int
 */
function wpssb_get_primary_site_owner_user_id() {
    $admin_email = sanitize_email( (string) get_option( 'admin_email' ) );

    if ( '' !== $admin_email ) {
        $user = get_user_by( 'email', $admin_email );

        if ( $user instanceof WP_User ) {
            return (int) $user->ID;
        }
    }

    $admins = get_users(
        [
            'role'    => 'administrator',
            'orderby' => 'ID',
            'order'   => 'ASC',
            'number'  => 1,
            'fields'  => 'ID',
        ]
    );

    return ! empty( $admins[0] ) ? (int) $admins[0] : 0;
}

/**
 * Devuelve el slug preferido del presskit público de un usuario.
 *
 * @param WP_User $user Usuario objetivo.
 * @return string
 */
function wpssb_get_preferred_collaborator_presskit_slug( WP_User $user ) {
    $candidate = sanitize_title( (string) $user->user_nicename );

    if ( '' === $candidate ) {
        $candidate = sanitize_title( (string) $user->user_login );
    }

    if ( '' === $candidate ) {
        $candidate = 'presskit-' . (int) $user->ID;
    }

    return $candidate;
}

/**
 * Sincroniza el slug del presskit con el identificador público del usuario.
 *
 * @param int $post_id ID del presskit.
 * @param int $user_id ID del usuario.
 * @return void
 */
function wpssb_sync_collaborator_presskit_post_slug( $post_id, $user_id ) {
    $post_id = absint( $post_id );
    $user_id = absint( $user_id );

    if ( $post_id <= 0 || $user_id <= 0 ) {
        return;
    }

    $post = get_post( $post_id );
    $user = get_user_by( 'id', $user_id );

    if ( ! $post instanceof WP_Post || ! $user instanceof WP_User ) {
        return;
    }

    $preferred_slug = wp_unique_post_slug(
        wpssb_get_preferred_collaborator_presskit_slug( $user ),
        $post_id,
        $post->post_status,
        $post->post_type,
        (int) $post->post_parent
    );

    if ( $preferred_slug === $post->post_name ) {
        return;
    }

    wp_update_post(
        [
            'ID'        => $post_id,
            'post_name' => $preferred_slug,
        ]
    );
}

/**
 * Resuelve el usuario objetivo de una página pública de presskit.
 *
 * @param int $post_id ID de la página.
 * @return int
 */
function wpssb_resolve_presskit_page_target_user_id( $post_id ) {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 ) {
        return 0;
    }

    $user_id = wpssb_get_explicit_collaborator_target_user_id( $post_id );

    if ( $user_id > 0 ) {
        return $user_id;
    }

    $owner_user_id = wpssb_get_primary_site_owner_user_id();

    if ( $owner_user_id > 0 ) {
        return $owner_user_id;
    }

    return (int) get_post_field( 'post_author', $post_id );
}

/**
 * Redirige páginas con template `presskit` al documento personal real del usuario objetivo.
 *
 * @return void
 */
function wpssb_redirect_presskit_page_to_personal_presskit() {
    if ( is_admin() || ! is_page() || is_feed() || is_preview() ) {
        return;
    }

    if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    $page_id = (int) get_queried_object_id();

    if ( $page_id <= 0 || 'presskit' !== get_page_template_slug( $page_id ) ) {
        return;
    }

    $user_id = wpssb_resolve_presskit_page_target_user_id( $page_id );

    if ( $user_id <= 0 ) {
        return;
    }

    $target_url = wpssb_get_collaborator_public_url( $user_id );
    $current_url = get_permalink( $page_id );

    if ( ! $target_url || ! $current_url || untrailingslashit( $target_url ) === untrailingslashit( $current_url ) ) {
        return;
    }

    wp_safe_redirect( $target_url, 302, 'WP Song Study Blocks' );
    exit;
}
add_action( 'template_redirect', 'wpssb_redirect_presskit_page_to_personal_presskit', 11 );

/**
 * Carga assets admin para proyectos y perfiles.
 *
 * @param string $hook Hook actual.
 * @return void
 */
function wpssb_enqueue_project_admin_assets( $hook ) {
    unset( $hook );

    return;
}
add_action( 'admin_enqueue_scripts', 'wpssb_enqueue_project_admin_assets' );

/**
 * Renderiza contenido de un presskit con contexto correcto de post.
 *
 * @param int         $post_id           ID del presskit.
 * @param string|null $content_override  Contenido alterno.
 * @return string
 */
function wpssb_render_saved_presskit_post_content( $post_id, $content_override = null ) {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 ) {
        return '';
    }

    $post = get_post( $post_id );

    if ( ! $post instanceof WP_Post ) {
        return '';
    }

    $content       = is_string( $content_override ) ? $content_override : wpssb_get_effective_presskit_document_content( $post );
    $previous_post = isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ? $GLOBALS['post'] : null;
    $GLOBALS['post'] = $post;
    setup_postdata( $post );

    $context_filter = static function ( $context ) use ( $post_id, $post ) {
        if ( empty( $context['postId'] ) ) {
            $context['postId'] = $post_id;
        }

        if ( empty( $context['postType'] ) ) {
            $context['postType'] = $post->post_type;
        }

        return $context;
    };

    add_filter( 'render_block_context', $context_filter, 10, 1 );
    $rendered = apply_filters( 'the_content', $content );
    remove_filter( 'render_block_context', $context_filter, 10 );

    if ( $previous_post instanceof WP_Post ) {
        $GLOBALS['post'] = $previous_post;
        setup_postdata( $previous_post );
    } else {
        wp_reset_postdata();
    }

    return (string) $rendered;
}

/**
 * REST: renderiza preview del presskit personal para edición frontal.
 *
 * @return void
 */
function wpssb_register_presskit_preview_rest_route() {
    register_rest_route(
        'wpssb/v1',
        '/presskit-preview/(?P<id>\d+)',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => static function ( WP_REST_Request $request ) {
                return current_user_can( 'edit_post', (int) $request['id'] );
            },
            'callback'            => static function ( WP_REST_Request $request ) {
                $post_id = (int) $request['id'];
                $content = $request->get_param( 'content' );

                return rest_ensure_response(
                    [
                        'html' => wpssb_render_saved_presskit_post_content(
                            $post_id,
                            is_string( $content ) ? $content : null
                        ),
                    ]
                );
            },
        ]
    );
}
add_action( 'rest_api_init', 'wpssb_register_presskit_preview_rest_route' );

/**
 * Encola scripts y estilos de editor para todos los bloques registrados.
 *
 * Esto permite usar bloques nativos y de terceros dentro del editor frontal
 * sin depender del admin de WordPress.
 *
 * @return void
 */
function wpssb_enqueue_frontend_block_editor_block_assets() {
    static $enqueued = false;

    if ( $enqueued || ! class_exists( 'WP_Block_Type_Registry' ) ) {
        return;
    }

    $enqueued = true;
    $registry = WP_Block_Type_Registry::get_instance()->get_all_registered();

    foreach ( $registry as $block_type ) {
        if ( ! $block_type instanceof WP_Block_Type ) {
            continue;
        }

        foreach ( [ 'editor_script_handles', 'script_handles', 'view_script_handles', 'editor_script', 'script', 'view_script' ] as $property ) {
            if ( empty( $block_type->{$property} ) || ! is_array( $block_type->{$property} ) ) {
                if ( empty( $block_type->{$property} ) || ! is_string( $block_type->{$property} ) ) {
                    continue;
                }

                $handles = [ $block_type->{$property} ];
            } else {
                $handles = $block_type->{$property};
            }

            foreach ( $handles as $handle ) {
                if ( is_string( $handle ) && wp_script_is( $handle, 'registered' ) ) {
                    wp_enqueue_script( $handle );
                }
            }
        }

        foreach ( [ 'editor_style_handles', 'style_handles', 'view_style_handles', 'editor_style', 'style', 'view_style' ] as $property ) {
            if ( empty( $block_type->{$property} ) || ! is_array( $block_type->{$property} ) ) {
                if ( empty( $block_type->{$property} ) || ! is_string( $block_type->{$property} ) ) {
                    continue;
                }

                $handles = [ $block_type->{$property} ];
            } else {
                $handles = $block_type->{$property};
            }

            foreach ( $handles as $handle ) {
                if ( is_string( $handle ) && wp_style_is( $handle, 'registered' ) ) {
                    wp_enqueue_style( $handle );
                }
            }
        }
    }

    $core_editor_script_handles = [
        'wp-block-library',
        'wp-format-library',
        'wp-block-paragraph',
        'wp-block-heading',
        'wp-block-list',
        'wp-block-quote',
        'wp-block-image',
        'wp-block-gallery',
        'wp-block-group',
        'wp-block-columns',
        'wp-block-column',
        'wp-block-buttons',
        'wp-block-button',
        'wp-block-cover',
        'wp-block-media-text',
        'wp-block-file',
        'wp-block-audio',
        'wp-block-video',
        'wp-block-separator',
        'wp-block-spacer',
        'wp-block-pullquote',
        'wp-block-table',
        'wp-block-details',
        'wp-block-code',
        'wp-block-preformatted',
        'wp-block-verse',
        'wp-block-social-links',
        'wp-block-social-link',
        'wp-block-site-logo',
    ];

    foreach ( $core_editor_script_handles as $handle ) {
        if ( wp_script_is( $handle, 'registered' ) ) {
            wp_enqueue_script( $handle );
        }
    }
}

/**
 * Resuelve una URL de asset local a ruta del sistema.
 *
 * @param string $src URL o ruta relativa.
 * @return string
 */
function wpssb_resolve_local_asset_path_from_src( $src ) {
    $src = (string) $src;

    if ( '' === $src ) {
        return '';
    }

    $src = strtok( $src, '?' );

    if ( 0 === strpos( $src, '/' ) && file_exists( ABSPATH . ltrim( $src, '/' ) ) ) {
        return ABSPATH . ltrim( $src, '/' );
    }

    $parsed_path = wp_parse_url( $src, PHP_URL_PATH );

    if ( ! is_string( $parsed_path ) || '' === $parsed_path ) {
        return '';
    }

    $site_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

    if ( '' !== $site_path && 0 === strpos( $parsed_path, $site_path ) ) {
        $parsed_path = substr( $parsed_path, strlen( $site_path ) );
    }

    $parsed_path = ltrim( $parsed_path, '/' );

    if ( '' === $parsed_path ) {
        return '';
    }

    $candidate = ABSPATH . $parsed_path;

    return file_exists( $candidate ) ? $candidate : '';
}

/**
 * Devuelve CSS inline util a inyectar dentro del canvas del editor frontal.
 *
 * @return array<int, array<string, string>>
 */
function wpssb_get_frontend_presskit_editor_iframe_styles() {
    global $wp_styles;

    $styles = [];
    $handles = [
        'wp-block-library',
        'wp-block-library-theme',
        'global-styles',
        'classic-theme-styles',
        'pertenencia-digital-style',
    ];

    if ( isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) {
        foreach ( $wp_styles->queue as $queued_handle ) {
            if ( ! is_string( $queued_handle ) ) {
                continue;
            }

            if ( 0 === strpos( $queued_handle, 'wp-block-' ) || 0 === strpos( $queued_handle, 'core-block-supports' ) ) {
                $handles[] = $queued_handle;
            }
        }
    }

    $handles = array_values( array_unique( $handles ) );

    foreach ( $handles as $handle ) {
        if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
            continue;
        }

        $registered = $wp_styles->registered[ $handle ];
        $src        = isset( $registered->src ) ? (string) $registered->src : '';
        $path       = wpssb_resolve_local_asset_path_from_src( $src );

        if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
            continue;
        }

        $css = file_get_contents( $path );

        if ( ! is_string( $css ) || '' === trim( $css ) ) {
            continue;
        }

        $styles[] = [
            'css' => $css,
        ];

        if ( ! empty( $registered->extra['after'] ) && is_array( $registered->extra['after'] ) ) {
            foreach ( $registered->extra['after'] as $after_css ) {
                if ( is_string( $after_css ) && '' !== trim( $after_css ) ) {
                    $styles[] = [
                        'css' => $after_css,
                    ];
                }
            }
        }
    }

    return $styles;
}

/**
 * Encola el editor de bloques frontal para el presskit personal.
 *
 * @param int $post_id ID del presskit.
 * @return void
 */
function wpssb_enqueue_frontend_presskit_editor_assets( $post_id ) {
    static $enqueued = [];

    $post_id = absint( $post_id );

    if ( $post_id <= 0 || isset( $enqueued[ $post_id ] ) ) {
        return;
    }

    $post = get_post( $post_id );

    if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $editor_settings = [];

    if ( class_exists( 'WP_Block_Editor_Context' ) && function_exists( 'get_block_editor_settings' ) ) {
        $editor_settings = get_block_editor_settings(
            [],
            new WP_Block_Editor_Context(
                [
                    'post' => $post,
                ]
            )
        );
    }

    $editor_settings['allowedBlockTypes'] = true;
    $editor_settings['templateLock']      = false;

    wp_enqueue_media();
    wp_enqueue_style( 'wp-block-library' );
    wp_enqueue_style( 'wp-block-library-theme' );
    wp_enqueue_style( 'wp-components' );
    wp_enqueue_editor_format_library_assets();

    if ( wp_style_is( 'wp-block-editor', 'registered' ) ) {
        wp_enqueue_style( 'wp-block-editor' );
    }

    if ( wp_style_is( 'wp-edit-blocks', 'registered' ) ) {
        wp_enqueue_style( 'wp-edit-blocks' );
    }

    if ( wp_script_is( 'wp-block-library', 'registered' ) ) {
        wp_enqueue_script( 'wp-block-library' );
    }

    wpssb_enqueue_frontend_block_editor_block_assets();

    $editor_settings['styles'] = array_merge(
        isset( $editor_settings['styles'] ) && is_array( $editor_settings['styles'] ) ? $editor_settings['styles'] : [],
        wpssb_get_frontend_presskit_editor_iframe_styles()
    );

    $editor_script_dependencies = [
        'wp-api-fetch',
        'wp-block-editor',
        'wp-blocks',
        'wp-components',
        'wp-compose',
        'wp-core-data',
        'wp-data',
        'wp-dom-ready',
        'wp-editor',
        'wp-element',
        'wp-html-entities',
        'wp-hooks',
        'wp-i18n',
        'wp-primitives',
        'wp-rich-text',
    ];

    if ( wp_script_is( 'wp-media-utils', 'registered' ) && ! in_array( 'wp-media-utils', $editor_script_dependencies, true ) ) {
        $editor_script_dependencies[] = 'wp-media-utils';
    }

    if ( wp_script_is( 'wp-block-library', 'registered' ) ) {
        array_unshift( $editor_script_dependencies, 'wp-block-library' );
    }

    if ( wp_script_is( 'wp-format-library', 'registered' ) && ! in_array( 'wp-format-library', $editor_script_dependencies, true ) ) {
        $editor_script_dependencies[] = 'wp-format-library';
    }

    wp_enqueue_script(
        'wpssb-frontend-presskit-editor',
        WPSSB_URL . 'assets/project-frontend/presskit-editor.js',
        $editor_script_dependencies,
        file_exists( WPSSB_PATH . 'assets/project-frontend/presskit-editor.js' ) ? (string) filemtime( WPSSB_PATH . 'assets/project-frontend/presskit-editor.js' ) : WPSSB_VERSION,
        true
    );

    wp_add_inline_script(
        'wpssb-frontend-presskit-editor',
        'window.wpssbFrontendPresskitEditor = ' . wp_json_encode(
            [
                'postId'        => $post_id,
                'postType'      => $post->post_type,
                'content'       => wpssb_get_effective_presskit_document_content( $post ),
                'previewHtml'   => wpssb_render_saved_presskit_post_content( $post_id ),
                'previewPath'   => '/wpssb/v1/presskit-preview/' . $post_id,
                'settings'      => $editor_settings,
                'restPath'      => '/wp/v2/' . $post->post_type,
                'restNonce'     => wp_create_nonce( 'wp_rest' ),
                'saveLabel'     => __( 'Guardar presskit', 'wp-song-study-blocks' ),
                'savedLabel'    => __( 'Presskit actualizado.', 'wp-song-study-blocks' ),
                'errorLabel'    => __( 'No se pudo guardar el presskit.', 'wp-song-study-blocks' ),
                'editTabLabel'  => __( 'Editar', 'wp-song-study-blocks' ),
                'previewLabel'  => __( 'Preview', 'wp-song-study-blocks' ),
                'inserterLabel' => __( 'Insertar bloque', 'wp-song-study-blocks' ),
            ]
        ) . ';',
        'before'
    );

    $enqueued[ $post_id ] = true;
}

/**
 * Renderiza el panel frontal de edición/preview del presskit personal.
 *
 * @param int $post_id ID del presskit.
 * @return string
 */
function wpssb_render_frontend_presskit_workbench( $post_id ) {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
        return '';
    }

    wpssb_enqueue_frontend_presskit_editor_assets( $post_id );

    $output  = '<section class="pd-membership-panel pd-membership-panel--presskit-workbench">';
    $output .= '<header class="pd-membership-presskit-workbench__header">';
    $output .= '<div>';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Presskit personal', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html__( 'Editar tu documento público con vista en vivo', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Aquí trabajas directamente sobre tu presskit real con bloques. El propio lienzo de edición ya respeta los estilos del tema para que no dependas de un preview aparte.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';
    $output .= '</header>';
    $output .= '<div id="wpssb-frontend-presskit-editor" class="pd-membership-presskit-workbench__app"></div>';
    $output .= '</section>';

    return $output;
}

/**
 * Renderiza campos de presskit para perfiles de usuario.
 *
 * @param WP_User $user Usuario actual.
 * @return void
 */
function wpssb_render_collaborator_presskit_fields( $user ) {
    if ( ! $user instanceof WP_User ) {
        return;
    }

    $tagline  = get_user_meta( $user->ID, 'pd_colaborador_tagline', true );
    $presskit_post_id = wpssb_get_collaborator_presskit_post_id( $user->ID );
    $presskit_edit_url = $presskit_post_id ? wpssb_get_frontend_membership_url( $user->ID, true ) : '';
    $presskit_view_url = wpssb_get_collaborator_public_url( $user->ID );

    echo '<h2>' . esc_html__( 'Presskit del colaborador', 'wp-song-study-blocks' ) . '</h2>';
    echo '<p>' . esc_html__( 'La composición pública del presskit ahora vive en un documento editable con bloques. Aquí solo se conservan datos base mínimos del perfil.', 'wp-song-study-blocks' ) . '</p>';
    echo '<p>';
    if ( $presskit_edit_url ) {
        echo '<a class="button button-secondary" href="' . esc_url( $presskit_edit_url ) . '">' . esc_html__( 'Editar documento del presskit', 'wp-song-study-blocks' ) . '</a> ';
    }
    echo '<a class="button button-secondary" href="' . esc_url( $presskit_view_url ) . '">' . esc_html__( 'Ver página pública', 'wp-song-study-blocks' ) . '</a>';
    echo '</p>';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th><label for="pd_colaborador_tagline">' . esc_html__( 'Tagline', 'wp-song-study-blocks' ) . '</label></th>';
    echo '<td><input type="text" name="pd_colaborador_tagline" id="pd_colaborador_tagline" value="' . esc_attr( (string) $tagline ) . '" class="regular-text" /></td></tr>';
    echo '</table>';
}
add_action( 'show_user_profile', 'wpssb_render_collaborator_presskit_fields' );
add_action( 'edit_user_profile', 'wpssb_render_collaborator_presskit_fields' );

/**
 * Guarda meta de presskit del colaborador.
 *
 * @param int $user_id Usuario a guardar.
 * @return void
 */
function wpssb_save_collaborator_presskit_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    if ( isset( $_POST['pd_colaborador_tagline'] ) ) {
        update_user_meta( $user_id, 'pd_colaborador_tagline', sanitize_text_field( wp_unslash( $_POST['pd_colaborador_tagline'] ) ) );
    }
}
add_action( 'personal_options_update', 'wpssb_save_collaborator_presskit_fields' );
add_action( 'edit_user_profile_update', 'wpssb_save_collaborator_presskit_fields' );

/**
 * Indica si el tema activo expone una template FSE concreta.
 *
 * El slug coincide con el valor guardado en `_wp_page_template` para block themes.
 *
 * @param string $slug Slug de template sin extensión.
 * @return bool
 */
function wpssb_theme_has_block_template_slug( $slug ) {
    $slug = sanitize_title( (string) $slug );

    if ( '' === $slug ) {
        return false;
    }

    $candidate_paths = array_unique(
        array_filter(
            [
                trailingslashit( get_stylesheet_directory() ) . 'templates/' . $slug . '.html',
                trailingslashit( get_template_directory() ) . 'templates/' . $slug . '.html',
            ]
        )
    );

    foreach ( $candidate_paths as $candidate_path ) {
        if ( file_exists( $candidate_path ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Devuelve la template preferida para la página editorial de presskit.
 *
 * La capa visual debe vivir en el tema; el plugin solo la detecta y la utiliza
 * cuando existe para no duplicar markup ni acoplarse a un tema concreto.
 *
 * @return string
 */
function wpssb_get_preferred_presskit_page_template() {
    return wpssb_theme_has_block_template_slug( 'presskit' ) ? 'presskit' : '';
}

/**
 * Asigna la template `presskit` a páginas llamadas `presskit` cuando el tema activo
 * la declara y la página todavía usa la template por defecto.
 *
 * @return void
 */
function wpssb_sync_presskit_page_template_assignment() {
    $template_slug = wpssb_get_preferred_presskit_page_template();

    if ( '' === $template_slug ) {
        return;
    }

    $presskit_pages = get_posts(
        [
            'post_type'      => 'page',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'name'           => 'presskit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]
    );

    foreach ( $presskit_pages as $page_id ) {
        $page_id = (int) $page_id;
        if ( $page_id <= 0 ) {
            continue;
        }

        $current_template = (string) get_post_meta( $page_id, '_wp_page_template', true );

        if ( '' !== $current_template && 'default' !== $current_template ) {
            continue;
        }

        update_post_meta( $page_id, '_wp_page_template', $template_slug );
    }
}
add_action( 'admin_init', 'wpssb_sync_presskit_page_template_assignment' );

/**
 * Indica si el usuario actual puede editar su propio presskit desde frontend.
 *
 * @return bool
 */
function wpssb_current_user_can_manage_own_presskit() {
    $user_id = get_current_user_id();

    return wpssb_current_user_can_manage_presskit_user( $user_id );
}

/**
 * Indica si el usuario actual puede gestionar el presskit de un usuario objetivo.
 *
 * @param int $user_id Usuario objetivo.
 * @return bool
 */
function wpssb_current_user_can_manage_presskit_user( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return false;
    }

    $current_user_id = get_current_user_id();

    if ( $current_user_id <= 0 ) {
        return false;
    }

    if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_user', $user_id ) ) {
        return true;
    }

    return $current_user_id === $user_id && (
        current_user_can( WPSSB_COLLABORATOR_CAP ) ||
        current_user_can( 'edit_presskits' ) ||
        current_user_can( 'upload_files' ) ||
        current_user_can( 'read' )
    );
}

/**
 * Devuelve la URL frontal de "Mi pertenencia digital".
 *
 * @param int  $user_id     Usuario objetivo.
 * @param bool $open_editor Si debe apuntar al editor frontal.
 * @return string
 */
function wpssb_get_frontend_membership_url( $user_id = 0, $open_editor = false ) {
    $user_id = absint( $user_id );
    $page_id = 0;
    $pages   = get_posts(
        [
            'post_type'      => 'page',
            'post_status'    => [ 'publish', 'private' ],
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'mi-pertenencia',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]
    );

    if ( ! empty( $pages[0] ) ) {
        $page_id = (int) $pages[0];
    }

    if ( ! $page_id ) {
        $page = get_page_by_path( 'mi-pertenencia' );

        if ( $page instanceof WP_Post ) {
            $page_id = (int) $page->ID;
        }
    }

    $url = $page_id ? get_permalink( $page_id ) : home_url( '/mi-pertenencia/' );
    $args = [];

    if ( $user_id > 0 && current_user_can( 'manage_options' ) && get_current_user_id() !== $user_id ) {
        $args['membership_user'] = $user_id;
    }

    if ( $open_editor ) {
        $args['membership_view'] = 'editor';
    }

    if ( ! empty( $args ) ) {
        $url = add_query_arg( $args, $url );
    }

    return $open_editor ? $url . '#wpssb-frontend-presskit-editor' : $url;
}

/**
 * Devuelve usuarios gestionables para la vista frontend de pertenencia.
 *
 * @return WP_User[]
 */
function wpssb_get_manageable_membership_users() {
    if ( current_user_can( 'manage_options' ) ) {
        return get_users(
            [
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ]
        );
    }

    $current_user = wp_get_current_user();

    return $current_user instanceof WP_User && $current_user->exists()
        ? [ $current_user ]
        : [];
}

/**
 * Resuelve el usuario objetivo de la vista frontend de pertenencia.
 *
 * @param array $settings Ajustes del bloque.
 * @return int
 */
function wpssb_resolve_membership_target_user_id( $settings = [] ) {
    $target_user_id = 0;

    if ( isset( $settings['target_user_id'] ) ) {
        $target_user_id = absint( $settings['target_user_id'] );
    }

    if ( current_user_can( 'manage_options' ) && isset( $_GET['membership_user'] ) ) {
        $target_user_id = absint( wp_unslash( $_GET['membership_user'] ) );
    }

    if ( ! $target_user_id ) {
        $target_user_id = get_current_user_id();
    }

    if ( ! wpssb_current_user_can_manage_presskit_user( $target_user_id ) ) {
        return get_current_user_id();
    }

    return absint( $target_user_id );
}

/**
 * Redirige al usuario a una URL segura del frontend de pertenencia.
 *
 * @param string $status Estado a reflejar en query args.
 * @return void
 */
function wpssb_redirect_membership_frontend( $status ) {
    $redirect_to = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : wp_get_referer();
    $redirect_to = $redirect_to ? wp_validate_redirect( $redirect_to, home_url( '/' ) ) : home_url( '/' );
    $redirect_to = add_query_arg( 'wpssb_membership_status', sanitize_key( $status ), $redirect_to );

    wp_safe_redirect( $redirect_to );
    exit;
}

/**
 * Encola assets frontend para gestionar la galería de pertenencia.
 *
 * @return void
 */
function wpssb_enqueue_frontend_membership_assets() {
    static $enqueued = false;

    if ( $enqueued ) {
        return;
    }

    $enqueued = true;

    wp_enqueue_media();
    wp_enqueue_script(
        'wpssb-membership-gallery',
        WPSSB_URL . 'assets/project-frontend/membership-gallery.js',
        [ 'jquery' ],
        WPSSB_VERSION,
        true
    );
}

/**
 * Guarda el presskit del usuario actual desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_membership_save() {
    if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
        wpssb_redirect_membership_frontend( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_membership_frontend( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_membership_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wpssb_membership_nonce'] ), 'wpssb_save_my_membership' ) ) {
        wpssb_redirect_membership_frontend( 'invalid_nonce' );
    }

    $user_id = isset( $_POST['target_user_id'] ) ? absint( wp_unslash( $_POST['target_user_id'] ) ) : get_current_user_id();

    if ( ! wpssb_current_user_can_manage_presskit_user( $user_id ) ) {
        wpssb_redirect_membership_frontend( 'forbidden' );
    }

    if ( isset( $_POST['pd_colaborador_tagline'] ) ) {
        update_user_meta( $user_id, 'pd_colaborador_tagline', sanitize_text_field( wp_unslash( $_POST['pd_colaborador_tagline'] ) ) );
    }

    wp_update_user(
        [
            'ID'          => $user_id,
            'user_url'    => isset( $_POST['pd_colaborador_user_url'] ) ? esc_url_raw( wp_unslash( $_POST['pd_colaborador_user_url'] ) ) : '',
            'description' => isset( $_POST['pd_colaborador_descripcion_corta'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pd_colaborador_descripcion_corta'] ) ) : '',
        ]
    );

    wpssb_redirect_membership_frontend( 'updated' );
}
add_action( 'admin_post_wpssb_save_my_membership', 'wpssb_handle_frontend_membership_save' );

/**
 * Devuelve feedback para la vista frontend de pertenencia.
 *
 * @return array|null
 */
function wpssb_get_membership_feedback() {
    $status = isset( $_GET['wpssb_membership_status'] ) ? sanitize_key( wp_unslash( $_GET['wpssb_membership_status'] ) ) : '';

    if ( ! $status ) {
        return null;
    }

    $messages = [
        'updated'         => [ 'type' => 'success', 'message' => __( 'Los datos base del perfil se actualizaron correctamente.', 'wp-song-study-blocks' ) ],
        'login_required'  => [ 'type' => 'error', 'message' => __( 'Necesitas iniciar sesión para gestionar tu pertenencia.', 'wp-song-study-blocks' ) ],
        'forbidden'       => [ 'type' => 'error', 'message' => __( 'No tienes permisos para editar este perfil.', 'wp-song-study-blocks' ) ],
        'invalid_nonce'   => [ 'type' => 'error', 'message' => __( 'La sesión del formulario expiró. Inténtalo de nuevo.', 'wp-song-study-blocks' ) ],
        'invalid_request' => [ 'type' => 'error', 'message' => __( 'La solicitud recibida no es válida.', 'wp-song-study-blocks' ) ],
    ];

    return $messages[ $status ] ?? null;
}

/**
 * Renderiza una tarjeta de colaborador reutilizable.
 *
 * @param WP_User $user      Usuario a renderizar.
 * @param array   $settings  Opciones visuales.
 * @return string
 */
function wpssb_render_collaborator_card( $user, $settings = [] ) {
    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $settings = wp_parse_args(
        $settings,
        [
            'show_avatar' => true,
            'show_bio'    => true,
            'show_link'   => true,
        ]
    );

    $avatar     = get_avatar( $user->ID, 96, '', $user->display_name, [ 'class' => 'pd-colaborador-avatar' ] );
    $bio        = get_user_meta( $user->ID, 'description', true );
    $tagline    = get_user_meta( $user->ID, 'pd_colaborador_tagline', true );
    $url        = $user->user_url ? esc_url( $user->user_url ) : '';
    $public_url = wpssb_get_collaborator_public_url( $user->ID );

    $output  = '<article class="pd-colaborador-card">';
    $output .= '<div class="pd-colaborador-card__header">';

    if ( ! empty( $settings['show_avatar'] ) && $avatar ) {
        $output .= '<div class="pd-colaborador-card__avatar">' . $avatar . '</div>';
    }

    $output .= '<h3 class="pd-colaborador-card__name"><a href="' . esc_url( $public_url ) . '">' . esc_html( $user->display_name ) . '</a></h3>';
    $output .= '</div>';

    if ( ! empty( $settings['show_bio'] ) ) {
        if ( $bio ) {
            $output .= '<p class="pd-colaborador-card__bio">' . esc_html( $bio ) . '</p>';
        } elseif ( $tagline ) {
            $output .= '<p class="pd-colaborador-card__bio">' . esc_html( $tagline ) . '</p>';
        }
    }

    if ( ! empty( $settings['show_link'] ) && $url ) {
        $output .= '<p class="pd-colaborador-card__link"><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sitio / portafolio', 'wp-song-study-blocks' ) . '</a></p>';
    }

    $output .= '</article>';

    return $output;
}

/**
 * Obtiene usuarios vinculados a un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return WP_User[]
 */
function wpssb_get_project_collaborators( $post_id ) {
    $ids = wpssb_sanitize_id_list( get_post_meta( $post_id, 'pd_proyecto_colaboradores', true ) );

    if ( empty( $ids ) ) {
        return [];
    }

    return array_values(
        array_filter(
            array_map(
                static function ( $id ) {
                    return get_user_by( 'id', (int) $id );
                },
                $ids
            )
        )
    );
}

/**
 * Obtiene términos de área de un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return WP_Term[]
 */
function wpssb_get_project_area_terms( $post_id ) {
    $terms = get_the_terms( $post_id, WPSSB_PROJECT_AREA_TAX );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return [];
    }

    return array_values(
        array_filter(
            $terms,
            static function ( $term ) {
                return $term instanceof WP_Term;
            }
        )
    );
}

/**
 * Renderiza una tarjeta de proyecto reutilizable.
 *
 * @param int   $post_id  Proyecto actual.
 * @param array $settings Ajustes visuales.
 * @return string
 */
function wpssb_render_project_card( $post_id, $settings = [] ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return '';
    }

    $settings = wp_parse_args(
        $settings,
        [
            'show_image'         => true,
            'show_excerpt'       => true,
            'show_area'          => true,
            'show_collaborators' => true,
        ]
    );

    $title         = get_the_title( $post_id );
    $permalink     = get_permalink( $post_id );
    $excerpt       = get_the_excerpt( $post_id );
    $area_terms    = wpssb_get_project_area_terms( $post_id );
    $collaborators = wpssb_get_project_collaborators( $post_id );

    $output = '<article class="pd-proyectos-relacionados__item pd-proyecto-card">';

    if ( ! empty( $settings['show_image'] ) && has_post_thumbnail( $post_id ) ) {
        $output .= '<a class="pd-proyecto-card__image" href="' . esc_url( $permalink ) . '">';
        $output .= get_the_post_thumbnail( $post_id, 'medium_large' );
        $output .= '</a>';
    }

    if ( ! empty( $settings['show_area'] ) && ! empty( $area_terms ) ) {
        $output .= '<p class="pd-proyecto-card__areas">';
        $output .= esc_html( implode( ' · ', wp_list_pluck( $area_terms, 'name' ) ) );
        $output .= '</p>';
    }

    $output .= '<h3 class="pd-proyecto-card__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h3>';

    if ( ! empty( $settings['show_excerpt'] ) && $excerpt ) {
        $output .= '<p class="pd-proyecto-card__excerpt">' . esc_html( wp_trim_words( $excerpt, 28 ) ) . '</p>';
    }

    if ( ! empty( $settings['show_collaborators'] ) && ! empty( $collaborators ) ) {
        $output .= '<p class="pd-proyecto-card__collaborators">';
        $output .= esc_html( implode( ' · ', wp_list_pluck( $collaborators, 'display_name' ) ) );
        $output .= '</p>';
    }

    $output .= '</article>';

    return $output;
}

/**
 * Resuelve el slug de área para el directorio de proyectos según el contexto actual.
 *
 * Si el bloque no recibe `areaSlug`, intenta usar el término actual en archivos de
 * la taxonomía `area_proyecto` para que el tema pueda reutilizar el mismo bloque
 * en plantillas FSE sin duplicar queries manuales.
 *
 * @param string $area_slug Slug explícito del bloque.
 * @return string
 */
function wpssb_resolve_project_directory_area_slug( $area_slug ) {
    $area_slug = sanitize_title( (string) $area_slug );

    if ( '' !== $area_slug ) {
        return $area_slug;
    }

    if ( is_tax( WPSSB_PROJECT_AREA_TAX ) ) {
        $term = get_queried_object();

        if ( $term instanceof WP_Term && WPSSB_PROJECT_AREA_TAX === $term->taxonomy ) {
            return sanitize_title( $term->slug );
        }
    }

    return '';
}

/**
 * Renderiza un directorio reusable de proyectos.
 *
 * @param array $settings Ajustes del directorio.
 * @return string
 */
function wpssb_render_project_directory_markup( $settings = [] ) {
    $settings = wp_parse_args(
        $settings,
        [
            'area_slug'          => '',
            'posts_per_page'     => 9,
            'show_image'         => true,
            'show_excerpt'       => true,
            'show_area'          => true,
            'show_collaborators' => true,
            'only_current_user'  => false,
            'user_id'            => 0,
            'empty_message'      => __( 'No hay proyectos publicados todavía.', 'wp-song-study-blocks' ),
            'login_message'      => __( 'Inicia sesión para ver tus proyectos relacionados.', 'wp-song-study-blocks' ),
        ]
    );

    $query_args = [
        'post_type'      => WPSSB_PROJECT_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => max( 1, absint( $settings['posts_per_page'] ) ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    $area_slug = wpssb_resolve_project_directory_area_slug( $settings['area_slug'] );
    if ( $area_slug ) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => WPSSB_PROJECT_AREA_TAX,
                'field'    => 'slug',
                'terms'    => $area_slug,
            ],
        ];
    }

    $membership_user_id = absint( $settings['user_id'] );

    if ( ! $membership_user_id && ! empty( $settings['only_current_user'] ) ) {
        $membership_user_id = get_current_user_id();
    }

    if ( $membership_user_id > 0 ) {
        if ( ! is_user_logged_in() && ! empty( $settings['only_current_user'] ) ) {
            return '<p>' . esc_html( $settings['login_message'] ) . '</p>';
        }

        $project_ids = wpssb_get_user_project_ids( $membership_user_id );

        if ( empty( $project_ids ) ) {
            return '<p>' . esc_html( $settings['empty_message'] ) . '</p>';
        }

        $query_args['post__in'] = $project_ids;
        $query_args['orderby']  = 'post__in';
    }

    $query = new WP_Query( $query_args );

    if ( ! $query->have_posts() ) {
        return '<p>' . esc_html( $settings['empty_message'] ) . '</p>';
    }

    $output = '<div class="pd-proyectos-grid pd-proyectos-grid--directory">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $output .= wpssb_render_project_card(
            get_the_ID(),
            [
                'show_image'         => ! empty( $settings['show_image'] ),
                'show_excerpt'       => ! empty( $settings['show_excerpt'] ),
                'show_area'          => ! empty( $settings['show_area'] ),
                'show_collaborators' => ! empty( $settings['show_collaborators'] ),
            ]
        );
    }

    wp_reset_postdata();

    $output .= '</div>';

    return $output;
}

/**
 * Devuelve IDs de proyectos donde participa un usuario.
 *
 * @param int $user_id Usuario a consultar.
 * @return int[]
 */
function wpssb_get_user_project_ids( $user_id ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return [];
    }

    $query = new WP_Query(
        [
            'post_type'      => WPSSB_PROJECT_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]
    );

    if ( empty( $query->posts ) ) {
        return [];
    }

    $project_ids = [];

    foreach ( (array) $query->posts as $project_id ) {
        $project_id = (int) $project_id;

        if ( $project_id <= 0 ) {
            continue;
        }

        $collaborators = wpssb_sanitize_id_list( get_post_meta( $project_id, 'pd_proyecto_colaboradores', true ) );

        if ( in_array( $user_id, $collaborators, true ) ) {
            $project_ids[] = $project_id;
        }
    }

    return $project_ids;
}

/**
 * Indica si un usuario pertenece a alguno de los proyectos dados.
 *
 * @param int   $user_id     Usuario a consultar.
 * @param int[] $project_ids Proyectos objetivo.
 * @return bool
 */
function wpssb_user_belongs_to_projects( $user_id, $project_ids ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return false;
    }

    $project_ids = array_filter( array_map( 'absint', (array) $project_ids ) );
    if ( empty( $project_ids ) ) {
        return false;
    }

    $user_project_ids = wpssb_get_user_project_ids( $user_id );
    return ! empty( array_intersect( $project_ids, $user_project_ids ) );
}

/**
 * Render común del listado de colaboradores del proyecto.
 *
 * @param int   $post_id     Proyecto actual.
 * @param array $settings    Ajustes visuales.
 * @return string
 */
function wpssb_render_project_collaborators_markup( $post_id, $settings = [] ) {
    $users = wpssb_get_project_collaborators( $post_id );

    if ( empty( $users ) ) {
        return '<p>' . esc_html__( 'Este proyecto no tiene colaboradores asignados todavía.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-colaboradores-grid">';

    foreach ( $users as $user ) {
        $output .= wpssb_render_collaborator_card( $user, $settings );
    }

    $output .= '</div>';

    return $output;
}

/**
 * Shortcode de todos los colaboradores.
 *
 * @return string
 */
function wpssb_shortcode_collaborators() {
    $users = wpssb_get_collaborators();

    if ( empty( $users ) ) {
        return '<p>' . esc_html__( 'Aún no hay colaboradores registrados.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-colaboradores-grid">';

    foreach ( $users as $user ) {
        $output .= wpssb_render_collaborator_card( $user );
    }

    $output .= '</div>';

    return $output;
}
add_shortcode( 'pd_colaboradores', 'wpssb_shortcode_collaborators' );

/**
 * Shortcode de colaboradores del proyecto actual.
 *
 * @return string
 */
function wpssb_shortcode_project_collaborators() {
    $post_id = wpssb_resolve_block_project_post_id();
    if ( ! $post_id ) {
        return '';
    }

    return wpssb_render_project_collaborators_markup( $post_id );
}
add_shortcode( 'pd_proyecto_colaboradores', 'wpssb_shortcode_project_collaborators' );

/**
 * Resuelve el post actual para bloques o shortcodes de proyecto.
 *
 * @param WP_Block|null $block Instancia del bloque.
 * @return int
 */
function wpssb_resolve_block_project_post_id( $block = null ) {
    if ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
        return absint( $block->context['postId'] );
    }

    return absint( get_the_ID() );
}

/**
 * Renderiza un listado de links para presskits.
 *
 * @param string $links_raw  Texto con links separados por línea.
 * @param string $class_name Clase CSS del listado.
 * @return string
 */
function wpssb_render_presskit_links_markup( $links_raw, $class_name ) {
    if ( ! $links_raw ) {
        return '';
    }

    $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $links_raw ) ) );
    if ( empty( $lines ) ) {
        return '';
    }

    $output = '<ul class="' . esc_attr( $class_name ) . '">';

    foreach ( $lines as $line ) {
        $url = esc_url( $line );
        if ( $url ) {
            $output .= '<li><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a></li>';
        } else {
            $output .= '<li>' . esc_html( $line ) . '</li>';
        }
    }

    $output .= '</ul>';

    return $output;
}

/**
 * Render común de la galería del proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return string
 */
function wpssb_render_project_gallery_markup( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return '';
    }

    $ids = wpssb_sanitize_id_list( get_post_meta( $post_id, 'pd_proyecto_galeria', true ) );

    if ( empty( $ids ) ) {
        return '<p>' . esc_html__( 'Aún no hay imágenes en la galería del proyecto.', 'wp-song-study-blocks' ) . '</p>';
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

/**
 * Render común del contacto del proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return string
 */
function wpssb_render_project_contact_markup( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return '';
    }

    $contact = get_post_meta( $post_id, 'pd_proyecto_contacto', true );

    if ( ! $contact ) {
        return '<p>' . esc_html__( 'No hay información de contacto definida.', 'wp-song-study-blocks' ) . '</p>';
    }

    return '<div class="pd-proyecto-contacto">' . wpautop( wp_kses_post( $contact ) ) . '</div>';
}

/**
 * Render común del presskit del proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return string
 */
function wpssb_render_project_presskit_markup( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return '';
    }

    $tagline  = get_post_meta( $post_id, 'pd_proyecto_tagline', true );
    $presskit = get_post_meta( $post_id, 'pd_proyecto_presskit', true );
    $links    = get_post_meta( $post_id, 'pd_proyecto_links', true );
    $excerpt  = get_post_field( 'post_excerpt', $post_id );

    if ( ! $tagline && ! $presskit && ! $links && ! $excerpt ) {
        return '<p>' . esc_html__( 'No hay información de presskit definida.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-proyecto-presskit">';

    if ( $tagline ) {
        $output .= '<p class="pd-proyecto-presskit__tagline">' . esc_html( $tagline ) . '</p>';
    }

    if ( $presskit ) {
        $output .= '<div class="pd-proyecto-presskit__text">' . wpautop( wp_kses_post( $presskit ) ) . '</div>';
    } elseif ( $excerpt ) {
        $output .= '<div class="pd-proyecto-presskit__text">' . wpautop( esc_html( $excerpt ) ) . '</div>';
    }

    $output .= wpssb_render_presskit_links_markup( $links, 'pd-proyecto-presskit__links' );
    $output .= '</div>';

    return $output;
}

/**
 * Shortcode de galería de proyecto.
 *
 * @return string
 */
function wpssb_shortcode_project_gallery() {
    return wpssb_render_project_gallery_markup( wpssb_resolve_block_project_post_id() );
}
add_shortcode( 'pd_proyecto_galeria', 'wpssb_shortcode_project_gallery' );

/**
 * Shortcode de contacto del proyecto.
 *
 * @return string
 */
function wpssb_shortcode_project_contact() {
    return wpssb_render_project_contact_markup( wpssb_resolve_block_project_post_id() );
}
add_shortcode( 'pd_proyecto_contacto', 'wpssb_shortcode_project_contact' );

/**
 * Shortcode de presskit del proyecto.
 *
 * @return string
 */
function wpssb_shortcode_project_presskit() {
    return wpssb_render_project_presskit_markup( wpssb_resolve_block_project_post_id() );
}
add_shortcode( 'pd_proyecto_presskit', 'wpssb_shortcode_project_presskit' );

/**
 * Resuelve un usuario objetivo para shortcodes de autor.
 *
 * @param array $atts Shortcode attrs.
 * @return int
 */
function wpssb_resolve_collaborator_user_id( $atts = [] ) {
    $atts    = shortcode_atts( [ 'id' => 0 ], (array) $atts );
    return wpssb_resolve_block_collaborator_user_id(
        [
            'userId' => isset( $atts['id'] ) ? $atts['id'] : 0,
        ]
    );
}

/**
 * Resuelve el usuario objetivo para bloques o shortcodes de colaborador.
 *
 * @param array         $attributes Atributos del bloque.
 * @param WP_Block|null $block      Instancia del bloque.
 * @return int
 */
function wpssb_resolve_block_collaborator_user_id( $attributes = [], $block = null ) {
    $user_id = isset( $attributes['userId'] ) ? absint( $attributes['userId'] ) : 0;

    if ( ! $user_id && is_page() ) {
        $presskit_page_id = (int) get_queried_object_id();

        if ( $presskit_page_id > 0 && 'presskit' === get_page_template_slug( $presskit_page_id ) ) {
            $user_id = wpssb_resolve_presskit_page_target_user_id( $presskit_page_id );
        }
    }

    if ( ! $user_id ) {
        $queried_object = get_queried_object();
        if ( $queried_object instanceof WP_User ) {
            $user_id = (int) $queried_object->ID;
        }
    }

    if ( ! $user_id && get_query_var( 'author' ) ) {
        $user_id = (int) get_query_var( 'author' );
    }

    if ( ! $user_id && $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
        $post_id = (int) $block->context['postId'];
        $user_id = wpssb_get_explicit_collaborator_target_user_id( $post_id );

        if ( ! $user_id ) {
            $user_id = (int) get_post_field( 'post_author', $post_id );
        }
    }

    if ( ! $user_id ) {
        $post_id = get_the_ID();
        if ( $post_id ) {
            $user_id = wpssb_get_explicit_collaborator_target_user_id( $post_id );

            if ( ! $user_id ) {
                $user_id = (int) get_post_field( 'post_author', $post_id );
            }
        }
    }

    return absint( $user_id );
}

/**
 * Render común del presskit del colaborador.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_render_collaborator_presskit_markup( $user_id ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) {
        return '';
    }

    $user = get_user_by( 'id', $user_id );
    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $tagline      = get_user_meta( $user_id, 'pd_colaborador_tagline', true );
    $presskit     = get_user_meta( $user_id, 'pd_colaborador_presskit', true );
    $links        = get_user_meta( $user_id, 'pd_colaborador_links', true );
    $fallback_url = $user->user_url ? esc_url_raw( $user->user_url ) : '';

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

    $output .= wpssb_render_presskit_links_markup( $links ?: $fallback_url, 'pd-colaborador-presskit__links' );
    $output .= '</section>';

    return $output;
}

/**
 * Render común de la galería del colaborador.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_render_collaborator_gallery_markup( $user_id ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) {
        return '';
    }

    $ids = wpssb_sanitize_id_list( get_user_meta( $user_id, 'pd_colaborador_galeria', true ) );
    if ( empty( $ids ) ) {
        return '<p>' . esc_html__( 'Aún no hay imágenes en la galería del colaborador.', 'wp-song-study-blocks' ) . '</p>';
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

/**
 * Render común del contacto del colaborador.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_render_collaborator_contact_markup( $user_id ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) {
        return '';
    }

    $contact = get_user_meta( $user_id, 'pd_colaborador_contacto', true );
    if ( ! $contact ) {
        return '<p>' . esc_html__( 'No hay información de contacto definida.', 'wp-song-study-blocks' ) . '</p>';
    }

    return '<div class="pd-proyecto-contacto">' . wpautop( wp_kses_post( $contact ) ) . '</div>';
}

/**
 * Render común de proyectos relacionados a un colaborador.
 *
 * @param int $user_id        Usuario objetivo.
 * @param int $posts_per_page Número máximo de proyectos.
 * @return string
 */
function wpssb_render_collaborator_projects_markup( $user_id, $posts_per_page = 6 ) {
    $user_id        = absint( $user_id );
    $posts_per_page = max( 1, absint( $posts_per_page ) );

    if ( ! $user_id ) {
        return '';
    }

    $project_ids = array_slice( wpssb_get_user_project_ids( $user_id ), 0, $posts_per_page );

    if ( empty( $project_ids ) ) {
        return '<p>' . esc_html__( 'No hay proyectos asociados todavía.', 'wp-song-study-blocks' ) . '</p>';
    }

    $query = new WP_Query(
        [
            'post_type'      => WPSSB_PROJECT_POST_TYPE,
            'posts_per_page' => $posts_per_page,
            'orderby'        => 'post__in',
            'post__in'       => $project_ids,
        ]
    );

    if ( ! $query->have_posts() ) {
        return '<p>' . esc_html__( 'No hay proyectos asociados todavía.', 'wp-song-study-blocks' ) . '</p>';
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

/**
 * Shortcode de presskit del colaborador.
 *
 * @param array $atts Atributos del shortcode.
 * @return string
 */
function wpssb_shortcode_collaborator_presskit( $atts = [] ) {
    return wpssb_render_collaborator_presskit_markup( wpssb_resolve_collaborator_user_id( $atts ) );
}
add_shortcode( 'pd_colaborador_presskit', 'wpssb_shortcode_collaborator_presskit' );

/**
 * Shortcode de galería del colaborador.
 *
 * @param array $atts Atributos del shortcode.
 * @return string
 */
function wpssb_shortcode_collaborator_gallery( $atts = [] ) {
    return wpssb_render_collaborator_gallery_markup( wpssb_resolve_collaborator_user_id( $atts ) );
}
add_shortcode( 'pd_colaborador_galeria', 'wpssb_shortcode_collaborator_gallery' );

/**
 * Shortcode de contacto del colaborador.
 *
 * @param array $atts Atributos del shortcode.
 * @return string
 */
function wpssb_shortcode_collaborator_contact( $atts = [] ) {
    return wpssb_render_collaborator_contact_markup( wpssb_resolve_collaborator_user_id( $atts ) );
}
add_shortcode( 'pd_colaborador_contacto', 'wpssb_shortcode_collaborator_contact' );

/**
 * Shortcode de proyectos relacionados a un colaborador.
 *
 * @param array $atts Atributos del shortcode.
 * @return string
 */
function wpssb_shortcode_collaborator_projects( $atts = [] ) {
    return wpssb_render_collaborator_projects_markup( wpssb_resolve_collaborator_user_id( $atts ), 6 );
}
add_shortcode( 'pd_colaborador_proyectos', 'wpssb_shortcode_collaborator_projects' );

/**
 * Render callback del bloque de colaboradores por proyecto.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido del bloque.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_collaborators( $attributes = [], $content = '', $block = null ) {
    $post_id = wpssb_resolve_block_project_post_id( $block );

    if ( ! $post_id ) {
        return '';
    }

    $settings = [
        'show_avatar' => ! isset( $attributes['showAvatar'] ) || (bool) $attributes['showAvatar'],
        'show_bio'    => ! isset( $attributes['showBio'] ) || (bool) $attributes['showBio'],
        'show_link'   => ! isset( $attributes['showLink'] ) || (bool) $attributes['showLink'],
    ];

    return wpssb_render_project_collaborators_markup( $post_id, $settings );
}

/**
 * Render callback del bloque de presskit del proyecto.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_presskit( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_project_presskit_markup( wpssb_resolve_block_project_post_id( $block ) );
}

/**
 * Render callback del bloque de galería del proyecto.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_gallery( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_project_gallery_markup( wpssb_resolve_block_project_post_id( $block ) );
}

/**
 * Render callback del bloque de contacto del proyecto.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_contact( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_project_contact_markup( wpssb_resolve_block_project_post_id( $block ) );
}

/**
 * Render callback del bloque de presskit del colaborador.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_collaborator_presskit( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_collaborator_presskit_markup( wpssb_resolve_block_collaborator_user_id( $attributes, $block ) );
}

/**
 * Render callback del bloque de galería del colaborador.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_collaborator_gallery( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_collaborator_gallery_markup( wpssb_resolve_block_collaborator_user_id( $attributes, $block ) );
}

/**
 * Render callback del bloque de contacto del colaborador.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_collaborator_contact( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_collaborator_contact_markup( wpssb_resolve_block_collaborator_user_id( $attributes, $block ) );
}

/**
 * Render callback del bloque de proyectos del colaborador.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_collaborator_projects( $attributes = [], $content = '', $block = null ) {
    $posts_per_page = isset( $attributes['postsPerPage'] ) ? max( 1, absint( $attributes['postsPerPage'] ) ) : 6;

    return wpssb_render_collaborator_projects_markup(
        wpssb_resolve_block_collaborator_user_id( $attributes, $block ),
        $posts_per_page
    );
}

/**
 * Render callback del bloque de directorio de proyectos.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_directory( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_project_directory_markup(
        [
            'area_slug'          => isset( $attributes['areaSlug'] ) ? sanitize_title( $attributes['areaSlug'] ) : '',
            'posts_per_page'     => isset( $attributes['postsPerPage'] ) ? max( 1, absint( $attributes['postsPerPage'] ) ) : 9,
            'show_image'         => ! isset( $attributes['showImage'] ) || (bool) $attributes['showImage'],
            'show_excerpt'       => ! isset( $attributes['showExcerpt'] ) || (bool) $attributes['showExcerpt'],
            'show_area'          => ! isset( $attributes['showArea'] ) || (bool) $attributes['showArea'],
            'show_collaborators' => ! empty( $attributes['showCollaborators'] ),
            'only_current_user'  => ! empty( $attributes['onlyCurrentUser'] ),
            'empty_message'      => isset( $attributes['emptyMessage'] ) ? sanitize_text_field( $attributes['emptyMessage'] ) : __( 'No hay proyectos publicados todavía.', 'wp-song-study-blocks' ),
            'login_message'      => isset( $attributes['loginMessage'] ) ? sanitize_text_field( $attributes['loginMessage'] ) : __( 'Inicia sesión para ver tus proyectos relacionados.', 'wp-song-study-blocks' ),
        ]
    );
}

/**
 * Renderiza el espacio frontend de pertenencia del usuario actual.
 *
 * @param array $settings Ajustes visuales.
 * @return string
 */
function wpssb_render_current_membership_markup( $settings = [] ) {
    $settings = wp_parse_args(
        $settings,
        [
            'show_projects'   => true,
            'show_preview'    => true,
            'show_admin_link' => true,
            'target_user_id'  => 0,
            'login_message'   => __( 'Inicia sesión para gestionar tu presskit y revisar tus proyectos.', 'wp-song-study-blocks' ),
        ]
    );

    if ( ! is_user_logged_in() ) {
        if ( function_exists( 'pd_render_login_panel' ) ) {
            return pd_render_login_panel(
                [
                    'title'       => __( 'Accede a tu pertenencia digital', 'wp-song-study-blocks' ),
                    'intro'       => $settings['login_message'],
                    'redirect_to' => get_permalink() ? get_permalink() : home_url( '/' ),
                ]
            );
        }

        $login_url = wp_login_url( get_permalink() ? get_permalink() : home_url( '/' ) );

        return '<div class="pd-membership-shell"><p>' . esc_html( $settings['login_message'] ) . '</p><p><a class="wp-block-button__link wp-element-button" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Iniciar sesión', 'wp-song-study-blocks' ) . '</a></p></div>';
    }

    $viewer_id = get_current_user_id();
    $user_id   = wpssb_resolve_membership_target_user_id( $settings );
    $user    = get_user_by( 'id', $user_id );

    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $can_manage_target  = wpssb_current_user_can_manage_presskit_user( $user_id );
    $can_switch_targets = current_user_can( 'manage_options' );
    $is_admin_override  = $can_switch_targets && $viewer_id > 0 && $viewer_id !== $user_id;
    $manageable_users   = $can_switch_targets ? wpssb_get_manageable_membership_users() : [];
    $feedback = wpssb_get_membership_feedback();
    $action   = admin_url( 'admin-post.php' );
    $presskit_post_id = $can_manage_target ? wpssb_ensure_collaborator_presskit_post( $user_id ) : wpssb_get_collaborator_presskit_post_id( $user_id );
    $public_presskit  = wpssb_get_collaborator_public_url( $user_id );
    $edit_presskit    = $presskit_post_id ? wpssb_get_frontend_membership_url( $user_id, true ) : '';
    $current_url = get_permalink() ? get_permalink() : home_url( '/' );
    $output  = '<section class="pd-membership-shell">';
    $output .= '<header class="pd-membership-shell__header">';
    $output .= '<div class="pd-membership-shell__identity">';
    $output .= get_avatar( $user_id, 96, '', $user->display_name, [ 'class' => 'pd-membership-shell__avatar' ] );
    $output .= '<div>';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html( $is_admin_override ? __( 'Administración de pertenencia', 'wp-song-study-blocks' ) : __( 'Mi pertenencia digital', 'wp-song-study-blocks' ) ) . '</p>';
    $output .= '<h1 class="pd-membership-shell__title">' . esc_html( $user->display_name ) . '</h1>';
    $output .= '<p class="pd-membership-shell__meta">' . esc_html( $user->user_email ) . '</p>';
    $output .= '</div></div>';
    $output .= '<div class="pd-membership-shell__actions">';
    $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $public_presskit ) . '">' . esc_html__( 'Ver perfil público', 'wp-song-study-blocks' ) . '</a>';
    if ( ! empty( $settings['show_admin_link'] ) ) {
        $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( admin_url( 'profile.php' ) ) . '">' . esc_html__( 'Abrir perfil de WordPress', 'wp-song-study-blocks' ) . '</a>';
    }
    $output .= '</div></header>';

    if ( $can_switch_targets && ! empty( $manageable_users ) ) {
        $output .= '<div class="pd-membership-panel pd-membership-panel--switcher">';
        $output .= '<form class="pd-membership-switcher" method="get" action="' . esc_url( $current_url ) . '">';
        $output .= '<label><span>' . esc_html__( 'Administrar pertenencia de', 'wp-song-study-blocks' ) . '</span>';
        $output .= '<select name="membership_user">';
        foreach ( $manageable_users as $manageable_user ) {
            if ( ! $manageable_user instanceof WP_User ) {
                continue;
            }
            $selected = selected( $user_id, (int) $manageable_user->ID, false );
            $output .= '<option value="' . (int) $manageable_user->ID . '" ' . $selected . '>' . esc_html( $manageable_user->display_name . ' · ' . $manageable_user->user_email ) . '</option>';
        }
        $output .= '</select></label>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Abrir', 'wp-song-study-blocks' ) . '</button>';
        $output .= '</form>';
        if ( $is_admin_override ) {
            $output .= '<p class="pd-membership-form__hint">' . esc_html__( 'Estás editando este perfil con permisos de administrador.', 'wp-song-study-blocks' ) . '</p>';
        }
        $output .= '</div>';
    }

    if ( is_array( $feedback ) && ! empty( $feedback['message'] ) ) {
        $class   = 'success' === ( $feedback['type'] ?? '' ) ? 'is-success' : 'is-error';
        $output .= '<p class="pd-membership-feedback ' . esc_attr( $class ) . '">' . esc_html( $feedback['message'] ) . '</p>';
    }

    if ( $can_manage_target && $presskit_post_id ) {
        $output .= '<div class="pd-membership-panel pd-membership-panel--builder">';
        $output .= '<h2>' . esc_html__( 'Documento público editable', 'wp-song-study-blocks' ) . '</h2>';
        $output .= '<p>' . esc_html__( 'Tu presskit ahora puede construirse como una página libre con bloques. Aquí debajo sigues manteniendo los datos estructurados que el plugin puede reutilizar dentro de esa composición.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '<div class="pd-membership-shell__actions">';
        if ( $edit_presskit ) {
            $output .= '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $edit_presskit ) . '">' . esc_html__( 'Editar documento del presskit', 'wp-song-study-blocks' ) . '</a>';
        }
        $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $public_presskit ) . '">' . esc_html__( 'Abrir vista pública', 'wp-song-study-blocks' ) . '</a>';
        $output .= '</div>';
        $output .= '</div>';
    }

    $output .= '<div class="pd-membership-shell__grid">';
    $output .= '<div class="pd-membership-editor">';
    $output .= '<h2>' . esc_html( $is_admin_override ? __( 'Editar datos base del perfil seleccionado', 'wp-song-study-blocks' ) : __( 'Editar datos base del perfil', 'wp-song-study-blocks' ) ) . '</h2>';
    if ( ! $can_manage_target ) {
        $output .= '<p>' . esc_html__( 'No tienes permisos para editar este perfil.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '</div>';
    } else {
    $output .= '<form class="pd-membership-form" method="post" action="' . esc_url( $action ) . '">';
    $output .= '<input type="hidden" name="action" value="wpssb_save_my_membership" />';
    $output .= '<input type="hidden" name="target_user_id" value="' . (int) $user_id . '" />';
    $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( add_query_arg( 'membership_user', $user_id, $current_url ) ) . '" />';
    $output .= wp_nonce_field( 'wpssb_save_my_membership', 'wpssb_membership_nonce', true, false );
    $output .= '<label><span>' . esc_html__( 'Tagline', 'wp-song-study-blocks' ) . '</span><input type="text" name="pd_colaborador_tagline" value="' . esc_attr( (string) get_user_meta( $user_id, 'pd_colaborador_tagline', true ) ) . '" /></label>';
    $output .= '<label><span>' . esc_html__( 'Biografía breve', 'wp-song-study-blocks' ) . '</span><textarea name="pd_colaborador_descripcion_corta" rows="3">' . esc_textarea( (string) $user->description ) . '</textarea></label>';
    $output .= '<label><span>' . esc_html__( 'Sitio / portafolio principal', 'wp-song-study-blocks' ) . '</span><input type="url" name="pd_colaborador_user_url" value="' . esc_attr( (string) $user->user_url ) . '" placeholder="https://..." /></label>';
    $output .= '<p class="pd-membership-form__hint">' . esc_html__( 'El contenido rico, galerías, embeds, dossier y contacto principal ahora deben editarse dentro del documento público de bloques.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<button type="submit" class="wp-block-button__link wp-element-button">' . esc_html( $is_admin_override ? __( 'Guardar perfil seleccionado', 'wp-song-study-blocks' ) : __( 'Guardar datos base', 'wp-song-study-blocks' ) ) . '</button>';
    $output .= '</form>';
    $output .= '</div>';
    }

    $output .= '<div class="pd-membership-sidebar">';
    if ( ! empty( $settings['show_preview'] ) && $presskit_post_id ) {
        $output .= '<div class="pd-membership-panel">';
        $output .= '<h2>' . esc_html__( 'Última vista pública guardada', 'wp-song-study-blocks' ) . '</h2>';
        $output .= wpssb_render_saved_presskit_post_content( $presskit_post_id );
        $output .= '</div>';
    }

    if ( ! empty( $settings['show_projects'] ) ) {
        $output .= '<div class="pd-membership-panel">';
        $output .= '<h2>' . esc_html__( 'Mis proyectos', 'wp-song-study-blocks' ) . '</h2>';
        $output .= wpssb_render_project_directory_markup(
            [
                'user_id'            => $user_id,
                'posts_per_page'     => 12,
                'show_image'         => true,
                'show_excerpt'       => true,
                'show_area'          => true,
                'show_collaborators' => true,
                'empty_message'      => $is_admin_override
                    ? __( 'Este usuario todavía no está vinculado a ningún proyecto.', 'wp-song-study-blocks' )
                    : __( 'Todavía no estás vinculado a ningún proyecto.', 'wp-song-study-blocks' ),
                'login_message'      => __( 'Inicia sesión para ver tus proyectos relacionados.', 'wp-song-study-blocks' ),
            ]
        );
        $output .= '</div>';
    }
    $output .= '</div></div>';

    if ( $can_manage_target && $presskit_post_id ) {
        $output .= wpssb_render_frontend_presskit_workbench( $presskit_post_id );
    }

    $output .= '</section>';

    return $output;
}

/**
 * Render callback del bloque de pertenencia del usuario actual.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_current_membership( $attributes = [], $content = '', $block = null ) {
    $settings = [
        'show_projects'   => ! isset( $attributes['showProjects'] ) || (bool) $attributes['showProjects'],
        'show_preview'    => ! isset( $attributes['showPreview'] ) || (bool) $attributes['showPreview'],
        'show_admin_link' => ! isset( $attributes['showAdminLink'] ) || (bool) $attributes['showAdminLink'],
        'target_user_id'  => isset( $attributes['targetUserId'] ) ? absint( $attributes['targetUserId'] ) : 0,
        'login_message'   => isset( $attributes['loginMessage'] ) ? sanitize_text_field( $attributes['loginMessage'] ) : __( 'Inicia sesión para gestionar tu presskit y revisar tus proyectos.', 'wp-song-study-blocks' ),
    ];

    $classes = [ 'pd-membership-block' ];
    $layout_width = isset( $attributes['layoutWidth'] ) ? sanitize_key( $attributes['layoutWidth'] ) : 'immersive';
    $valid_layout_widths = [ 'default', 'wide', 'immersive' ];

    if ( ! in_array( $layout_width, $valid_layout_widths, true ) ) {
        $layout_width = 'immersive';
    }

    $classes[] = 'is-layout-' . $layout_width;

    $style_vars = [];
    $style_attributes = [
        'shellTextColor'       => '--pd-membership-custom-shell-text',
        'headerBackgroundColor'=> '--pd-membership-custom-header-background',
        'headerTextColor'      => '--pd-membership-custom-header-text',
        'panelBackgroundColor' => '--pd-membership-custom-panel-background',
        'panelTextColor'       => '--pd-membership-custom-panel-text',
        'panelBorderColor'     => '--pd-membership-custom-panel-border',
        'fieldBackgroundColor' => '--pd-membership-custom-field-background',
        'fieldTextColor'       => '--pd-membership-custom-field-text',
        'linkColor'            => '--pd-membership-custom-link',
    ];

    foreach ( $style_attributes as $attribute_name => $css_var ) {
        if ( empty( $attributes[ $attribute_name ] ) || ! is_string( $attributes[ $attribute_name ] ) ) {
            continue;
        }

        $value = trim( sanitize_text_field( $attributes[ $attribute_name ] ) );

        if ( '' === $value || ! preg_match( '/^[#(),.%\sA-Za-z0-9_-]+$/', $value ) ) {
            continue;
        }

        $style_vars[] = $css_var . ':' . $value;
    }

    if ( isset( $attributes['editorMinHeight'] ) ) {
        $editor_min_height = max( 560, min( 1200, absint( $attributes['editorMinHeight'] ) ) );
        $style_vars[] = '--pd-membership-editor-min-height:' . $editor_min_height . 'px';
    }

    $wrapper_attributes = get_block_wrapper_attributes(
        [
            'class' => implode( ' ', $classes ),
            'style' => implode( ';', $style_vars ),
        ]
    );

    return sprintf(
        '<div %1$s>%2$s</div>',
        $wrapper_attributes,
        wpssb_render_current_membership_markup( $settings )
    );
}
