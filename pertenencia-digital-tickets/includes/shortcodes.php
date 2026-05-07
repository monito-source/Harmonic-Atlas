<?php
/**
 * Shortcodes públicos del sistema de tickets.
 *
 * @package PertenenciaDigitalTickets
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'pd_technology_ticket_form', 'pdt_render_ticket_form_shortcode' );
add_shortcode( 'pd_technology_ticket_portal', 'pdt_render_ticket_portal_shortcode' );
add_shortcode( 'pd_technology_services', 'pdt_render_services_shortcode' );

/**
 * Devuelve opciones base del formulario.
 *
 * @return array<string, string>
 */
function pdt_get_area_options(): array {
    return [
        'web'                    => __( 'Web: sitio, WordPress, alojamiento o mantenimiento', 'pertenencia-digital-tickets' ),
        'tecnologias-digitales'  => __( 'Tecnologías digitales: consultoría, servicio técnico o herramientas', 'pertenencia-digital-tickets' ),
        'multimedia'             => __( 'Multimedia: video, foto, audio o música por comisión', 'pertenencia-digital-tickets' ),
        'no-estoy-seguro'        => __( 'No estoy seguro, necesito orientación', 'pertenencia-digital-tickets' ),
    ];
}

/**
 * Devuelve intenciones comunes.
 *
 * @return array<string, string>
 */
function pdt_get_intent_options(): array {
    return [
        'pregunta'       => __( 'Tengo una pregunta', 'pertenencia-digital-tickets' ),
        'cotizacion'     => __( 'Quiero cotizar o contratar', 'pertenencia-digital-tickets' ),
        'diagnostico'    => __( 'Necesito diagnóstico', 'pertenencia-digital-tickets' ),
        'mantenimiento'  => __( 'Busco alojamiento o mantenimiento', 'pertenencia-digital-tickets' ),
        'honorarios'     => __( 'Quiero definir honorarios', 'pertenencia-digital-tickets' ),
    ];
}

/**
 * Renderiza mensajes de estado después de enviar el formulario.
 */
function pdt_render_ticket_status_notice(): string {
    $status = isset( $_GET['ticket_estado'] ) ? sanitize_key( wp_unslash( $_GET['ticket_estado'] ) ) : '';

    if ( '' === $status ) {
        return '';
    }

    $messages = [
        'recibido'   => __( 'Tu solicitud fue recibida. Revisaré el contexto y responderé por correo.', 'pertenencia-digital-tickets' ),
        'incompleto' => __( 'Faltan datos obligatorios o el correo no es válido. Revisa el formulario e inténtalo de nuevo.', 'pertenencia-digital-tickets' ),
        'nonce'      => __( 'La sesión del formulario venció. Recarga la página e inténtalo otra vez.', 'pertenencia-digital-tickets' ),
        'error'      => __( 'No se pudo guardar el ticket. Inténtalo nuevamente o escribe por otro medio.', 'pertenencia-digital-tickets' ),
    ];

    if ( ! isset( $messages[ $status ] ) ) {
        return '';
    }

    $class = 'recibido' === $status ? 'pdt-ticket-notice pdt-ticket-notice--success' : 'pdt-ticket-notice pdt-ticket-notice--error';

    return '<div class="' . esc_attr( $class ) . '">' . esc_html( $messages[ $status ] ) . '</div>';
}

/**
 * Obtiene servicios contratables para el selector.
 *
 * @return array<int, WP_Post>
 */
function pdt_get_service_products(): array {
    $services = get_posts(
        [
            'post_type'      => PDT_SERVICE_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
        ]
    );

    return is_array( $services ) ? array_values( array_filter( $services, static fn ( $service ) => $service instanceof WP_Post ) ) : [];
}

/**
 * Renderiza el formulario de tickets.
 *
 * @param array<string, mixed> $atts Atributos del shortcode.
 */
function pdt_render_ticket_form_shortcode( array $atts = [] ): string {
    $atts = shortcode_atts(
        [
            'area'       => '',
            'intent'     => '',
            'service_id' => 0,
        ],
        $atts,
        'pd_technology_ticket_form'
    );

    $area_options   = pdt_get_area_options();
    $intent_options = pdt_get_intent_options();
    $services       = pdt_get_service_products();
    $selected_area  = sanitize_key( (string) $atts['area'] );
    $selected_intent = sanitize_key( (string) $atts['intent'] );
    $selected_service_id = absint( $atts['service_id'] );

    ob_start();
    ?>
    <div class="pdt-ticket-panel">
        <?php echo pdt_render_ticket_status_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <form class="pdt-ticket-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="pdt_create_ticket" />
            <?php wp_nonce_field( PDT_TICKET_NONCE_ACTION, PDT_TICKET_NONCE_NAME ); ?>

            <div class="pdt-ticket-form__grid">
                <label>
                    <span><?php esc_html_e( 'Nombre', 'pertenencia-digital-tickets' ); ?></span>
                    <input type="text" name="pdt_name" autocomplete="name" required />
                </label>

                <label>
                    <span><?php esc_html_e( 'Correo', 'pertenencia-digital-tickets' ); ?></span>
                    <input type="email" name="pdt_email" autocomplete="email" required />
                </label>
            </div>

            <div class="pdt-ticket-form__grid">
                <label>
                    <span><?php esc_html_e( 'Área', 'pertenencia-digital-tickets' ); ?></span>
                    <select name="pdt_area" required>
                        <?php foreach ( $area_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_area, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e( 'Intención', 'pertenencia-digital-tickets' ); ?></span>
                    <select name="pdt_intent" required>
                        <?php foreach ( $intent_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_intent, $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="pdt-ticket-form__grid">
                <label>
                    <span><?php esc_html_e( 'Servicio relacionado', 'pertenencia-digital-tickets' ); ?></span>
                    <select name="pdt_service_id">
                        <option value="0"><?php esc_html_e( 'Todavía no sé / no aplica', 'pertenencia-digital-tickets' ); ?></option>
                        <?php foreach ( $services as $service ) : ?>
                            <option value="<?php echo esc_attr( (string) $service->ID ); ?>" <?php selected( $selected_service_id, (int) $service->ID ); ?>><?php echo esc_html( get_the_title( $service ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e( 'Presupuesto o rango', 'pertenencia-digital-tickets' ); ?></span>
                    <input type="text" name="pdt_budget" placeholder="<?php esc_attr_e( 'Opcional, si ya tienes referencia', 'pertenencia-digital-tickets' ); ?>" />
                </label>
            </div>

            <label>
                <span><?php esc_html_e( 'Cuéntame qué necesitas resolver', 'pertenencia-digital-tickets' ); ?></span>
                <textarea name="pdt_message" rows="7" required></textarea>
            </label>

            <button class="pdt-ticket-form__submit" type="submit"><?php esc_html_e( 'Crear ticket', 'pertenencia-digital-tickets' ); ?></button>
        </form>
    </div>
    <?php

    return (string) ob_get_clean();
}

/**
 * Renderiza el portal editorial de tickets.
 */
function pdt_render_ticket_portal_shortcode(): string {
    ob_start();
    ?>
    <div class="pdt-ticket-portal">
        <div class="pdt-ticket-portal__item">
            <strong><?php esc_html_e( '1. Pregunta o duda', 'pertenencia-digital-tickets' ); ?></strong>
            <p><?php esc_html_e( 'Sirve para aclarar si necesitas Web, Tecnologías digitales, multimedia o una combinación.', 'pertenencia-digital-tickets' ); ?></p>
        </div>
        <div class="pdt-ticket-portal__item">
            <strong><?php esc_html_e( '2. Cotización u honorarios', 'pertenencia-digital-tickets' ); ?></strong>
            <p><?php esc_html_e( 'Sirve para convertir una ruta de servicio en una solicitud concreta con alcance inicial.', 'pertenencia-digital-tickets' ); ?></p>
        </div>
        <div class="pdt-ticket-portal__item">
            <strong><?php esc_html_e( '3. Seguimiento operativo', 'pertenencia-digital-tickets' ); ?></strong>
            <p><?php esc_html_e( 'Sirve como base para administrar clientes y prospectos conforme el sistema crezca.', 'pertenencia-digital-tickets' ); ?></p>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

/**
 * Lista servicios contratables.
 */
function pdt_render_services_shortcode(): string {
    $services = pdt_get_service_products();

    if ( empty( $services ) ) {
        return '<p>' . esc_html__( 'Los servicios contratables se agregarán desde el administrador.', 'pertenencia-digital-tickets' ) . '</p>';
    }

    ob_start();
    ?>
    <div class="pdt-services-list">
        <?php foreach ( $services as $service ) : ?>
            <article class="pdt-services-list__item">
                <h3><?php echo esc_html( get_the_title( $service ) ); ?></h3>
                <?php if ( has_excerpt( $service ) ) : ?>
                    <p><?php echo esc_html( get_the_excerpt( $service ) ); ?></p>
                <?php endif; ?>
                <a href="<?php echo esc_url( get_permalink( $service ) ); ?>"><?php esc_html_e( 'Ver servicio', 'pertenencia-digital-tickets' ); ?></a>
            </article>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}
