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

if ( ! defined( 'WPSSB_PROJECT_AREA_TAX' ) ) {
    define( 'WPSSB_PROJECT_AREA_TAX', 'area_proyecto' );
}

if ( ! defined( 'WPSSB_COLLABORATOR_TARGET_META' ) ) {
    define( 'WPSSB_COLLABORATOR_TARGET_META', '_wpssb_collaborator_user_id' );
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

    if ( function_exists( 'wpss_add_cap_to_role' ) ) {
        wpss_add_cap_to_role( 'pd_colaborador', WPSSB_COLLABORATOR_CAP );

        if ( defined( 'WPSS_ROLE_COLEGA' ) ) {
            wpss_add_cap_to_role( WPSS_ROLE_COLEGA, WPSSB_COLLABORATOR_CAP );
            wpss_add_cap_to_role( WPSS_ROLE_COLEGA, 'upload_files' );
        }

        wpss_add_cap_to_role( 'administrator', WPSSB_COLLABORATOR_CAP );
    }
}
add_action( 'init', 'wpssb_register_collaborator_role' );

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

    add_meta_box(
        'wpssb-project-contact',
        __( 'Contacto del proyecto', 'wp-song-study-blocks' ),
        'wpssb_render_project_contact_meta_box',
        WPSSB_PROJECT_POST_TYPE,
        'normal',
        'default'
    );

    add_meta_box(
        'wpssb-project-gallery',
        __( 'Galería del proyecto', 'wp-song-study-blocks' ),
        'wpssb_render_project_gallery_meta_box',
        WPSSB_PROJECT_POST_TYPE,
        'normal',
        'default'
    );

    add_meta_box(
        'wpssb-project-presskit',
        __( 'Presskit del proyecto', 'wp-song-study-blocks' ),
        'wpssb_render_project_presskit_meta_box',
        WPSSB_PROJECT_POST_TYPE,
        'normal',
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
    add_meta_box(
        'wpssb-collaborator-target',
        __( 'Usuario objetivo del presskit', 'wp-song-study-blocks' ),
        'wpssb_render_collaborator_target_meta_box',
        'page',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes_page', 'wpssb_add_collaborator_target_meta_box' );

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
    $gallery       = isset( $_POST['pd_proyecto_galeria'] ) ? wpssb_sanitize_id_list( wp_unslash( $_POST['pd_proyecto_galeria'] ) ) : [];
    $contact       = isset( $_POST['pd_proyecto_contacto'] ) ? wp_kses_post( wp_unslash( $_POST['pd_proyecto_contacto'] ) ) : '';
    $tagline       = isset( $_POST['pd_proyecto_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_proyecto_tagline'] ) ) : '';
    $presskit      = isset( $_POST['pd_proyecto_presskit'] ) ? wp_kses_post( wp_unslash( $_POST['pd_proyecto_presskit'] ) ) : '';
    $links         = isset( $_POST['pd_proyecto_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pd_proyecto_links'] ) ) : '';

    update_post_meta( $post_id, 'pd_proyecto_colaboradores', $collaborators );
    update_post_meta( $post_id, 'pd_proyecto_galeria', $gallery );
    update_post_meta( $post_id, 'pd_proyecto_contacto', $contact );
    update_post_meta( $post_id, 'pd_proyecto_tagline', $tagline );
    update_post_meta( $post_id, 'pd_proyecto_presskit', $presskit );
    update_post_meta( $post_id, 'pd_proyecto_links', $links );
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

    if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
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
        return;
    }

    delete_post_meta( $post_id, WPSSB_COLLABORATOR_TARGET_META );
}
add_action( 'save_post_page', 'wpssb_save_collaborator_target_meta', 10, 2 );

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
 * Carga assets admin para proyectos y perfiles.
 *
 * @param string $hook Hook actual.
 * @return void
 */
function wpssb_enqueue_project_admin_assets( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php', 'profile.php', 'user-edit.php' ], true ) ) {
        return;
    }

    if ( in_array( $hook, [ 'profile.php', 'user-edit.php' ], true ) ) {
        wp_enqueue_media();
        wp_enqueue_script(
            'wpssb-collaborator-meta',
            WPSSB_URL . 'assets/project-admin/collaborator-meta.js',
            [ 'jquery' ],
            WPSSB_VERSION,
            true
        );
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || WPSSB_PROJECT_POST_TYPE !== $screen->post_type ) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'wpssb-project-meta',
        WPSSB_URL . 'assets/project-admin/project-meta.js',
        [ 'jquery' ],
        WPSSB_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'wpssb_enqueue_project_admin_assets' );

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
    $presskit = get_user_meta( $user->ID, 'pd_colaborador_presskit', true );
    $links    = get_user_meta( $user->ID, 'pd_colaborador_links', true );
    $contact  = get_user_meta( $user->ID, 'pd_colaborador_contacto', true );
    $gallery  = wpssb_sanitize_id_list( get_user_meta( $user->ID, 'pd_colaborador_galeria', true ) );

    echo '<h2>' . esc_html__( 'Presskit del colaborador', 'wp-song-study-blocks' ) . '</h2>';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th><label for="pd_colaborador_tagline">' . esc_html__( 'Tagline', 'wp-song-study-blocks' ) . '</label></th>';
    echo '<td><input type="text" name="pd_colaborador_tagline" id="pd_colaborador_tagline" value="' . esc_attr( (string) $tagline ) . '" class="regular-text" /></td></tr>';

    echo '<tr><th><label for="pd_colaborador_presskit">' . esc_html__( 'Descripción / Presskit', 'wp-song-study-blocks' ) . '</label></th>';
    echo '<td><textarea name="pd_colaborador_presskit" id="pd_colaborador_presskit" rows="4" class="large-text">' . esc_textarea( (string) $presskit ) . '</textarea></td></tr>';

    echo '<tr><th><label for="pd_colaborador_links">' . esc_html__( 'Links (uno por línea)', 'wp-song-study-blocks' ) . '</label></th>';
    echo '<td><textarea name="pd_colaborador_links" id="pd_colaborador_links" rows="3" class="large-text">' . esc_textarea( (string) $links ) . '</textarea></td></tr>';

    echo '<tr><th><label for="pd_colaborador_contacto">' . esc_html__( 'Contacto', 'wp-song-study-blocks' ) . '</label></th>';
    echo '<td><textarea name="pd_colaborador_contacto" id="pd_colaborador_contacto" rows="3" class="large-text">' . esc_textarea( (string) $contact ) . '</textarea></td></tr>';

    echo '<tr><th>' . esc_html__( 'Galería', 'wp-song-study-blocks' ) . '</th><td>';
    echo '<div class="wpssb-collaborator-gallery-meta" data-initial="' . esc_attr( implode( ',', $gallery ) ) . '">';
    echo '<input type="hidden" name="pd_colaborador_galeria" value="' . esc_attr( implode( ',', $gallery ) ) . '" />';
    echo '<button type="button" class="button wpssb-collaborator-gallery-select">' . esc_html__( 'Elegir imágenes', 'wp-song-study-blocks' ) . '</button>';
    echo '<button type="button" class="button wpssb-collaborator-gallery-clear" style="margin-left:6px;">' . esc_html__( 'Limpiar galería', 'wp-song-study-blocks' ) . '</button>';
    echo '<div class="wpssb-collaborator-gallery-preview" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;"></div>';
    echo '</div>';
    echo '</td></tr>';
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

    update_user_meta( $user_id, 'pd_colaborador_tagline', isset( $_POST['pd_colaborador_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_colaborador_tagline'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_presskit', isset( $_POST['pd_colaborador_presskit'] ) ? wp_kses_post( wp_unslash( $_POST['pd_colaborador_presskit'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_links', isset( $_POST['pd_colaborador_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pd_colaborador_links'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_contacto', isset( $_POST['pd_colaborador_contacto'] ) ? wp_kses_post( wp_unslash( $_POST['pd_colaborador_contacto'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_galeria', isset( $_POST['pd_colaborador_galeria'] ) ? wpssb_sanitize_id_list( wp_unslash( $_POST['pd_colaborador_galeria'] ) ) : [] );
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

    return $user_id > 0 && current_user_can( 'edit_user', $user_id );
}

/**
 * Indica si el usuario actual puede gestionar el presskit de un usuario objetivo.
 *
 * @param int $user_id Usuario objetivo.
 * @return bool
 */
function wpssb_current_user_can_manage_presskit_user( $user_id ) {
    $user_id = absint( $user_id );

    return $user_id > 0 && current_user_can( 'edit_user', $user_id );
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

    update_user_meta( $user_id, 'pd_colaborador_tagline', isset( $_POST['pd_colaborador_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_colaborador_tagline'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_presskit', isset( $_POST['pd_colaborador_presskit'] ) ? wp_kses_post( wp_unslash( $_POST['pd_colaborador_presskit'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_links', isset( $_POST['pd_colaborador_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pd_colaborador_links'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_contacto', isset( $_POST['pd_colaborador_contacto'] ) ? wp_kses_post( wp_unslash( $_POST['pd_colaborador_contacto'] ) ) : '' );
    update_user_meta( $user_id, 'pd_colaborador_galeria', isset( $_POST['pd_colaborador_galeria'] ) ? wpssb_sanitize_id_list( wp_unslash( $_POST['pd_colaborador_galeria'] ) ) : [] );

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
        'updated'         => [ 'type' => 'success', 'message' => __( 'El presskit se actualizó correctamente.', 'wp-song-study-blocks' ) ],
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

    $avatar  = get_avatar( $user->ID, 96, '', $user->display_name, [ 'class' => 'pd-colaborador-avatar' ] );
    $bio     = get_user_meta( $user->ID, 'description', true );
    $tagline = get_user_meta( $user->ID, 'pd_colaborador_tagline', true );
    $url     = $user->user_url ? esc_url( $user->user_url ) : '';
    $author  = get_author_posts_url( $user->ID );

    $output  = '<article class="pd-colaborador-card">';
    $output .= '<div class="pd-colaborador-card__header">';

    if ( ! empty( $settings['show_avatar'] ) && $avatar ) {
        $output .= '<div class="pd-colaborador-card__avatar">' . $avatar . '</div>';
    }

    $output .= '<h3 class="pd-colaborador-card__name"><a href="' . esc_url( $author ) . '">' . esc_html( $user->display_name ) . '</a></h3>';
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

        $query_args['meta_query'] = [
            [
                'key'     => 'pd_proyecto_colaboradores',
                'value'   => '"' . $membership_user_id . '"',
                'compare' => 'LIKE',
            ],
        ];
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
            'meta_query'     => [
                [
                    'key'     => 'pd_proyecto_colaboradores',
                    'value'   => '"' . $user_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ]
    );

    return array_map( 'intval', (array) $query->posts );
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

    if ( ! $tagline && ! $presskit && ! $links ) {
        return '<p>' . esc_html__( 'No hay información de presskit definida.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-proyecto-presskit">';

    if ( $tagline ) {
        $output .= '<p class="pd-proyecto-presskit__tagline">' . esc_html( $tagline ) . '</p>';
    }

    if ( $presskit ) {
        $output .= '<div class="pd-proyecto-presskit__text">' . wpautop( wp_kses_post( $presskit ) ) . '</div>';
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

    $output .= wpssb_render_presskit_links_markup( $links, 'pd-colaborador-presskit__links' );
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

    $query = new WP_Query(
        [
            'post_type'      => WPSSB_PROJECT_POST_TYPE,
            'posts_per_page' => $posts_per_page,
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
    $author   = get_author_posts_url( $user_id );
    $current_url = get_permalink() ? get_permalink() : home_url( '/' );
    $gallery_ids = wpssb_sanitize_id_list( get_user_meta( $user_id, 'pd_colaborador_galeria', true ) );

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
    $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $author ) . '">' . esc_html__( 'Ver perfil público', 'wp-song-study-blocks' ) . '</a>';
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

    if ( $can_manage_target ) {
        wpssb_enqueue_frontend_membership_assets();
    }

    $output .= '<div class="pd-membership-shell__grid">';
    $output .= '<div class="pd-membership-editor">';
    $output .= '<h2>' . esc_html( $is_admin_override ? __( 'Editar presskit del perfil seleccionado', 'wp-song-study-blocks' ) : __( 'Editar mi presskit', 'wp-song-study-blocks' ) ) . '</h2>';
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
    $output .= '<label><span>' . esc_html__( 'Presskit', 'wp-song-study-blocks' ) . '</span><textarea name="pd_colaborador_presskit" rows="7">' . esc_textarea( (string) get_user_meta( $user_id, 'pd_colaborador_presskit', true ) ) . '</textarea></label>';
    $output .= '<label><span>' . esc_html__( 'Links', 'wp-song-study-blocks' ) . '</span><textarea name="pd_colaborador_links" rows="4" placeholder="https://...&#10;https://...">' . esc_textarea( (string) get_user_meta( $user_id, 'pd_colaborador_links', true ) ) . '</textarea></label>';
    $output .= '<label><span>' . esc_html__( 'Contacto', 'wp-song-study-blocks' ) . '</span><textarea name="pd_colaborador_contacto" rows="4">' . esc_textarea( (string) get_user_meta( $user_id, 'pd_colaborador_contacto', true ) ) . '</textarea></label>';
    $output .= '<label><span>' . esc_html__( 'Sitio / portafolio principal', 'wp-song-study-blocks' ) . '</span><input type="url" name="pd_colaborador_user_url" value="' . esc_attr( (string) $user->user_url ) . '" placeholder="https://..." /></label>';
    $output .= '<div class="pd-membership-gallery-field" data-initial="' . esc_attr( implode( ',', $gallery_ids ) ) . '">';
    $output .= '<input type="hidden" name="pd_colaborador_galeria" value="' . esc_attr( implode( ',', $gallery_ids ) ) . '" />';
    $output .= '<div class="pd-membership-gallery-field__header">';
    $output .= '<span>' . esc_html__( 'Galería de presskit', 'wp-song-study-blocks' ) . '</span>';
    $output .= '<div class="pd-membership-gallery-field__actions">';
    $output .= '<button type="button" class="button pd-membership-gallery-select">' . esc_html__( 'Elegir imágenes', 'wp-song-study-blocks' ) . '</button>';
    $output .= '<button type="button" class="button pd-membership-gallery-clear">' . esc_html__( 'Limpiar', 'wp-song-study-blocks' ) . '</button>';
    $output .= '</div></div>';
    $output .= '<div class="pd-membership-gallery-preview"></div>';
    $output .= '</div>';
    $output .= '<p class="pd-membership-form__hint">' . esc_html__( 'Puedes elegir imágenes existentes o subir nuevas desde la biblioteca multimedia.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<button type="submit" class="wp-block-button__link wp-element-button">' . esc_html( $is_admin_override ? __( 'Guardar perfil seleccionado', 'wp-song-study-blocks' ) : __( 'Guardar mi presskit', 'wp-song-study-blocks' ) ) . '</button>';
    $output .= '</form>';
    $output .= '</div>';
    }

    $output .= '<div class="pd-membership-sidebar">';
    if ( ! empty( $settings['show_preview'] ) ) {
        $output .= '<div class="pd-membership-panel">';
        $output .= '<h2>' . esc_html__( 'Vista pública actual', 'wp-song-study-blocks' ) . '</h2>';
        $output .= wpssb_render_collaborator_presskit_markup( $user_id );
        $output .= '<div class="pd-membership-panel__gallery">';
        $output .= '<h3>' . esc_html__( 'Galería pública actual', 'wp-song-study-blocks' ) . '</h3>';
        $output .= wpssb_render_collaborator_gallery_markup( $user_id );
        $output .= '</div>';
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
    $output .= '</div></div></section>';

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
    return wpssb_render_current_membership_markup(
        [
            'show_projects'   => ! isset( $attributes['showProjects'] ) || (bool) $attributes['showProjects'],
            'show_preview'    => ! isset( $attributes['showPreview'] ) || (bool) $attributes['showPreview'],
            'show_admin_link' => ! isset( $attributes['showAdminLink'] ) || (bool) $attributes['showAdminLink'],
            'target_user_id'  => isset( $attributes['targetUserId'] ) ? absint( $attributes['targetUserId'] ) : 0,
            'login_message'   => isset( $attributes['loginMessage'] ) ? sanitize_text_field( $attributes['loginMessage'] ) : __( 'Inicia sesión para gestionar tu presskit y revisar tus proyectos.', 'wp-song-study-blocks' ),
        ]
    );
}
