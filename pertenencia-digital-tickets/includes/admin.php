<?php
/**
 * Administración básica de tickets y servicios.
 *
 * @package PertenenciaDigitalTickets
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_meta_boxes', 'pdt_register_meta_boxes' );
add_action( 'save_post_' . PDT_TICKET_POST_TYPE, 'pdt_save_ticket_meta' );
add_filter( 'manage_' . PDT_TICKET_POST_TYPE . '_posts_columns', 'pdt_ticket_columns' );
add_action( 'manage_' . PDT_TICKET_POST_TYPE . '_posts_custom_column', 'pdt_render_ticket_column', 10, 2 );

/**
 * Registra cajas de metadatos.
 */
function pdt_register_meta_boxes(): void {
    add_meta_box(
        'pdt-ticket-details',
        __( 'Datos del ticket', 'pertenencia-digital-tickets' ),
        'pdt_render_ticket_details_meta_box',
        PDT_TICKET_POST_TYPE,
        'side',
        'high'
    );
}

/**
 * Renderiza datos editables del ticket.
 */
function pdt_render_ticket_details_meta_box( WP_Post $post ): void {
    wp_nonce_field( 'pdt_save_ticket_meta', 'pdt_ticket_meta_nonce' );

    $fields = [
        '_pdt_name'   => __( 'Nombre', 'pertenencia-digital-tickets' ),
        '_pdt_email'  => __( 'Correo', 'pertenencia-digital-tickets' ),
        '_pdt_area'   => __( 'Área', 'pertenencia-digital-tickets' ),
        '_pdt_intent' => __( 'Intención', 'pertenencia-digital-tickets' ),
        '_pdt_budget' => __( 'Presupuesto', 'pertenencia-digital-tickets' ),
    ];

    foreach ( $fields as $key => $label ) {
        $value = (string) get_post_meta( $post->ID, $key, true );
        ?>
        <p>
            <label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
            <input class="widefat" type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
        </p>
        <?php
    }

    $status = (string) get_post_meta( $post->ID, '_pdt_status', true );
    ?>
    <p>
        <label for="_pdt_status"><strong><?php esc_html_e( 'Estado', 'pertenencia-digital-tickets' ); ?></strong></label>
        <select class="widefat" id="_pdt_status" name="_pdt_status">
            <?php foreach ( pdt_get_ticket_statuses() as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

/**
 * Estados internos del ticket.
 *
 * @return array<string, string>
 */
function pdt_get_ticket_statuses(): array {
    return [
        'nuevo'        => __( 'Nuevo', 'pertenencia-digital-tickets' ),
        'en_revision'  => __( 'En revisión', 'pertenencia-digital-tickets' ),
        'cotizado'     => __( 'Cotizado', 'pertenencia-digital-tickets' ),
        'contratado'   => __( 'Contratado', 'pertenencia-digital-tickets' ),
        'cerrado'      => __( 'Cerrado', 'pertenencia-digital-tickets' ),
    ];
}

/**
 * Guarda metadatos del ticket.
 */
function pdt_save_ticket_meta( int $post_id ): void {
    if ( ! isset( $_POST['pdt_ticket_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pdt_ticket_meta_nonce'] ) ), 'pdt_save_ticket_meta' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    foreach ( [ '_pdt_name', '_pdt_email', '_pdt_area', '_pdt_intent', '_pdt_budget', '_pdt_status' ] as $key ) {
        $value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
        update_post_meta( $post_id, $key, $value );
    }
}

/**
 * Columnas del listado de tickets.
 *
 * @param array<string, string> $columns Columnas actuales.
 * @return array<string, string>
 */
function pdt_ticket_columns( array $columns ): array {
    $columns['pdt_contact'] = __( 'Contacto', 'pertenencia-digital-tickets' );
    $columns['pdt_area']    = __( 'Área', 'pertenencia-digital-tickets' );
    $columns['pdt_status']  = __( 'Estado', 'pertenencia-digital-tickets' );

    return $columns;
}

/**
 * Renderiza columnas personalizadas.
 */
function pdt_render_ticket_column( string $column, int $post_id ): void {
    if ( 'pdt_contact' === $column ) {
        $name  = (string) get_post_meta( $post_id, '_pdt_name', true );
        $email = (string) get_post_meta( $post_id, '_pdt_email', true );
        echo esc_html( $name );
        if ( '' !== $email ) {
            echo '<br><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
        }
    }

    if ( 'pdt_area' === $column ) {
        echo esc_html( (string) get_post_meta( $post_id, '_pdt_area', true ) );
    }

    if ( 'pdt_status' === $column ) {
        $status   = (string) get_post_meta( $post_id, '_pdt_status', true );
        $statuses = pdt_get_ticket_statuses();
        echo esc_html( $statuses[ $status ] ?? $status );
    }
}
