<?php
/**
 * Acciones para crear tickets desde formularios públicos.
 *
 * @package PertenenciaDigitalTickets
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const PDT_TICKET_NONCE_ACTION = 'pdt_create_ticket';
const PDT_TICKET_NONCE_NAME   = 'pdt_ticket_nonce';

add_action( 'admin_post_pdt_create_ticket', 'pdt_handle_ticket_submission' );
add_action( 'admin_post_nopriv_pdt_create_ticket', 'pdt_handle_ticket_submission' );

/**
 * Sanitiza un campo simple enviado desde el formulario.
 */
function pdt_get_posted_string( string $key, int $max_length = 0 ): string {
    $value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
    $value = is_string( $value ) ? sanitize_text_field( $value ) : '';

    if ( $max_length > 0 && function_exists( 'mb_substr' ) ) {
        return mb_substr( $value, 0, $max_length );
    }

    if ( $max_length > 0 ) {
        return substr( $value, 0, $max_length );
    }

    return $value;
}

/**
 * Procesa un ticket público.
 */
function pdt_handle_ticket_submission(): void {
    $redirect_url = wp_get_referer();

    if ( ! is_string( $redirect_url ) || '' === $redirect_url ) {
        $redirect_url = home_url( '/tecnologias-web/tickets/' );
    }

    if ( ! isset( $_POST[ PDT_TICKET_NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ PDT_TICKET_NONCE_NAME ] ) ), PDT_TICKET_NONCE_ACTION ) ) {
        wp_safe_redirect( add_query_arg( 'ticket_estado', 'nonce', $redirect_url ) );
        exit;
    }

    $name       = pdt_get_posted_string( 'pdt_name', 120 );
    $email      = pdt_get_posted_string( 'pdt_email', 160 );
    $area       = pdt_get_posted_string( 'pdt_area', 80 );
    $intent     = pdt_get_posted_string( 'pdt_intent', 80 );
    $budget     = pdt_get_posted_string( 'pdt_budget', 80 );
    $service_id = absint( $_POST['pdt_service_id'] ?? 0 );
    $message    = isset( $_POST['pdt_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pdt_message'] ) ) : '';

    if ( '' === $name || '' === $email || '' === $message || ! is_email( $email ) ) {
        wp_safe_redirect( add_query_arg( 'ticket_estado', 'incompleto', $redirect_url ) );
        exit;
    }

    $ticket_title = sprintf(
        /* translators: 1: intent, 2: person name */
        __( '%1$s · %2$s', 'pertenencia-digital-tickets' ),
        '' !== $intent ? $intent : __( 'Solicitud', 'pertenencia-digital-tickets' ),
        $name
    );

    $ticket_id = wp_insert_post(
        [
            'post_type'    => PDT_TICKET_POST_TYPE,
            'post_status'  => 'private',
            'post_title'   => $ticket_title,
            'post_content' => $message,
            'meta_input'   => [
                '_pdt_name'       => $name,
                '_pdt_email'      => sanitize_email( $email ),
                '_pdt_area'       => $area,
                '_pdt_intent'     => $intent,
                '_pdt_budget'     => $budget,
                '_pdt_service_id' => $service_id,
                '_pdt_status'     => 'nuevo',
            ],
        ],
        true
    );

    if ( is_wp_error( $ticket_id ) ) {
        wp_safe_redirect( add_query_arg( 'ticket_estado', 'error', $redirect_url ) );
        exit;
    }

    if ( '' !== $area && taxonomy_exists( PDT_AREA_TAXONOMY ) ) {
        wp_set_object_terms( (int) $ticket_id, $area, PDT_AREA_TAXONOMY, false );
    }

    pdt_notify_admin_of_ticket( (int) $ticket_id );

    wp_safe_redirect( add_query_arg( 'ticket_estado', 'recibido', $redirect_url ) );
    exit;
}

/**
 * Envía una notificación básica al administrador.
 */
function pdt_notify_admin_of_ticket( int $ticket_id ): void {
    $admin_email = get_option( 'admin_email' );

    if ( ! is_string( $admin_email ) || '' === $admin_email ) {
        return;
    }

    $ticket = get_post( $ticket_id );

    if ( ! $ticket instanceof WP_Post ) {
        return;
    }

    $name  = (string) get_post_meta( $ticket_id, '_pdt_name', true );
    $email = (string) get_post_meta( $ticket_id, '_pdt_email', true );
    $area  = (string) get_post_meta( $ticket_id, '_pdt_area', true );

    $body = sprintf(
        "Nuevo ticket recibido.\n\nNombre: %s\nCorreo: %s\nÁrea: %s\n\nRevisar: %s",
        $name,
        $email,
        $area,
        admin_url( 'post.php?post=' . $ticket_id . '&action=edit' )
    );

    wp_mail(
        $admin_email,
        sprintf( '[%s] Nuevo ticket de Tecnologías y Web', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
        $body
    );
}
