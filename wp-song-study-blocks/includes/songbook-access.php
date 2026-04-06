<?php
/**
 * Roles, capacidades y helpers de acceso al cancionero.
 *
 * @package WP_Song_Study_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WPSS_ROLE_COLEGA' ) ) {
    define( 'WPSS_ROLE_COLEGA', 'colega_musical' );
}

if ( ! defined( 'WPSS_ROLE_INVITADO' ) ) {
    define( 'WPSS_ROLE_INVITADO', 'invitado' );
}

if ( ! defined( 'WPSS_CAP_MANAGE' ) ) {
    define( 'WPSS_CAP_MANAGE', 'wpss_manage_songbook' );
}

if ( ! defined( 'WPSS_CAP_READ' ) ) {
    define( 'WPSS_CAP_READ', 'wpss_read_songbook' );
}

/**
 * Registra o actualiza una capacidad en un rol.
 *
 * @param string $role_name Nombre del rol.
 * @param string $cap       Capacidad.
 * @return void
 */
function wpss_add_cap_to_role( $role_name, $cap ) {
    $role = get_role( $role_name );
    if ( $role && ! $role->has_cap( $cap ) ) {
        $role->add_cap( $cap );
    }
}

/**
 * Registra rol colega musical.
 *
 * @return void
 */
function wpss_register_colega_role() {
    if ( null === get_role( WPSS_ROLE_COLEGA ) ) {
        add_role(
            WPSS_ROLE_COLEGA,
            __( 'Colega musical', 'wp-song-study-blocks' ),
            [
                'read'            => true,
                WPSS_CAP_READ     => true,
                WPSS_CAP_MANAGE   => true,
            ]
        );
    }

    wpss_add_cap_to_role( WPSS_ROLE_COLEGA, WPSS_CAP_READ );
    wpss_add_cap_to_role( WPSS_ROLE_COLEGA, WPSS_CAP_MANAGE );
}

/**
 * Registra rol invitado del cancionero.
 *
 * @return void
 */
function wpss_register_invitado_role() {
    if ( null === get_role( WPSS_ROLE_INVITADO ) ) {
        add_role(
            WPSS_ROLE_INVITADO,
            __( 'Invitado del cancionero', 'wp-song-study-blocks' ),
            [
                'read'          => true,
                WPSS_CAP_READ   => true,
            ]
        );
    }

    wpss_add_cap_to_role( WPSS_ROLE_INVITADO, WPSS_CAP_READ );
}

/**
 * Asegura capacidades para administradores.
 *
 * @return void
 */
function wpss_register_songbook_caps_for_admins() {
    wpss_add_cap_to_role( 'administrator', WPSS_CAP_READ );
    wpss_add_cap_to_role( 'administrator', WPSS_CAP_MANAGE );
}

/**
 * Registra el esquema de acceso del cancionero.
 *
 * @return void
 */
function wpss_register_songbook_access() {
    wpss_register_colega_role();
    wpss_register_invitado_role();
    wpss_register_songbook_caps_for_admins();
}
add_action( 'init', 'wpss_register_songbook_access' );

/**
 * Obtiene el objeto usuario objetivo.
 *
 * @param int|null $user_id ID de usuario.
 * @return WP_User|null
 */
function wpss_resolve_access_user( $user_id = null ) {
    if ( null === $user_id ) {
        $user_id = get_current_user_id();
    }

    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return null;
    }

    $user = get_userdata( $user_id );
    return $user instanceof WP_User ? $user : null;
}

/**
 * Indica si un usuario tiene rol específico del cancionero.
 *
 * @param string   $role_name Rol a validar.
 * @param int|null $user_id   ID usuario.
 * @return bool
 */
function wpss_user_has_songbook_role( $role_name, $user_id = null ) {
    $user = wpss_resolve_access_user( $user_id );
    if ( ! $user ) {
        return false;
    }

    $roles = is_array( $user->roles ) ? $user->roles : [];
    return in_array( $role_name, $roles, true );
}

/**
 * Indica si un usuario es colega musical.
 *
 * @param int|null $user_id ID usuario.
 * @return bool
 */
function wpss_user_is_colega_musical( $user_id = null ) {
    return wpss_user_has_songbook_role( WPSS_ROLE_COLEGA, $user_id );
}

/**
 * Indica si un usuario es invitado del cancionero.
 *
 * @param int|null $user_id ID usuario.
 * @return bool
 */
function wpss_user_is_invitado_songbook( $user_id = null ) {
    return wpss_user_has_songbook_role( WPSS_ROLE_INVITADO, $user_id );
}

/**
 * Indica si el usuario puede gestionar el cancionero.
 *
 * @param int|null $user_id ID usuario.
 * @return bool
 */
function wpss_user_can_manage_songbook( $user_id = null ) {
    if ( null === $user_id ) {
        if ( function_exists( 'is_super_admin' ) && is_super_admin() ) {
            return true;
        }

        return current_user_can( 'manage_options' ) || current_user_can( WPSS_CAP_MANAGE );
    }

    $user = wpss_resolve_access_user( $user_id );
    if ( ! $user ) {
        return false;
    }

    return user_can( $user, 'manage_options' ) || user_can( $user, WPSS_CAP_MANAGE );
}

/**
 * Indica si el usuario puede leer el cancionero autenticado.
 *
 * @param int|null $user_id ID usuario.
 * @return bool
 */
function wpss_user_can_read_songbook( $user_id = null ) {
    if ( wpss_user_can_manage_songbook( $user_id ) ) {
        return true;
    }

    if ( null === $user_id ) {
        return current_user_can( WPSS_CAP_READ );
    }

    $user = wpss_resolve_access_user( $user_id );
    return $user ? user_can( $user, WPSS_CAP_READ ) : false;
}
