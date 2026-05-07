<?php
/**
 * Plugin Name: On Building Page
 * Plugin URI: https://tusitio.local/
 * Description: Permite marcar páginas y presskits públicos como en construcción, en mantenimiento o fuera de servicio para visitantes y usuarios no administradores, conservando la navegación pública del sitio.
 * Version: 0.1.0
 * Author: Sergio Mendoza
 * License: GPL-2.0+
 * Text Domain: on-building-page
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OBP_VERSION', '0.1.0' );
define( 'OBP_META_STATE', '_obp_page_state' );
define( 'OBP_META_EYEBROW', '_obp_page_state_eyebrow' );
define( 'OBP_META_TITLE', '_obp_page_state_title' );
define( 'OBP_META_MESSAGE', '_obp_page_state_message' );

/**
 * Devuelve la definicion de estados disponibles.
 *
 * @return array<string, array<string, string>>
 */
function obp_get_page_states() {
	return [
		'construction' => [
			'label'   => __( 'En construcción', 'on-building-page' ),
			'eyebrow' => __( 'En construcción', 'on-building-page' ),
			'message' => __( 'Esta página ya forma parte del recorrido del sitio, pero todavía estamos construyendo su contenido público. La intención de esta sección ya existe y volverá con contenido completo en cuanto esté lista.', 'on-building-page' ),
		],
		'maintenance'  => [
			'label'   => __( 'En mantenimiento', 'on-building-page' ),
			'eyebrow' => __( 'En mantenimiento', 'on-building-page' ),
			'message' => __( 'Esta página está temporalmente en mantenimiento. Su lugar dentro del sitio sigue siendo válido, pero retiramos su contenido público mientras hacemos ajustes, correcciones o mejoras.', 'on-building-page' ),
		],
		'offline'      => [
			'label'   => __( 'Fuera de servicio', 'on-building-page' ),
			'eyebrow' => __( 'Fuera de servicio', 'on-building-page' ),
			'message' => __( 'Esta página está fuera de servicio por ahora. Su recorrido y su intención siguen visibles dentro del sitio, pero su contenido público no está disponible temporalmente.', 'on-building-page' ),
		],
	];
}

/**
 * Devuelve los tipos de contenido soportados por el plugin.
 *
 * @return string[]
 */
function obp_get_supported_post_types() {
	$post_types = [ 'page', 'presskit' ];

	return array_values(
		array_filter(
			array_unique(
				(array) apply_filters( 'obp_supported_post_types', $post_types )
			),
			'post_type_exists'
		)
	);
}

/**
 * Indica si un post type es soportado por el plugin.
 *
 * @param string $post_type Post type a validar.
 */
function obp_is_supported_post_type( $post_type ) {
	return in_array( $post_type, obp_get_supported_post_types(), true );
}

/**
 * Devuelve el estado activo de una pagina.
 *
 * @param int $post_id ID del post.
 */
function obp_get_page_state( $post_id ) {
	$state  = sanitize_key( (string) get_post_meta( $post_id, OBP_META_STATE, true ) );
	$states = obp_get_page_states();

	return isset( $states[ $state ] ) ? $state : '';
}

/**
 * Determina si el usuario actual puede saltarse la mascara publica.
 */
function obp_current_user_can_bypass() {
	$capability = apply_filters( 'obp_bypass_capability', 'manage_options' );

	return current_user_can( $capability );
}

/**
 * Indica si la peticion actual debe recibir mascara publica.
 */
function obp_should_mask_current_page() {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || is_preview() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( ! is_singular( obp_get_supported_post_types() ) ) {
		return false;
	}

	if ( obp_current_user_can_bypass() ) {
		return false;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return '' !== obp_get_page_state( $post->ID );
}

/**
 * Devuelve una etiqueta legible para el tipo de contenido.
 *
 * @param string $post_type Slug del post type.
 */
function obp_get_post_type_label( $post_type ) {
	$object = get_post_type_object( $post_type );

	if ( $object && ! empty( $object->labels->singular_name ) ) {
		return $object->labels->singular_name;
	}

	return ucfirst( (string) $post_type );
}

/**
 * Devuelve una etiqueta de layout para la pantalla de ajustes.
 *
 * @param WP_Post $post Post actual.
 */
function obp_get_content_layout_label( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$template_slug = 'page' === $post->post_type ? get_page_template_slug( $post->ID ) : '';

	if ( $template_slug ) {
		return $template_slug;
	}

	$object = get_post_type_object( $post->post_type );

	if ( $object && ! empty( $object->labels->singular_name ) ) {
		return sprintf(
			/* translators: %s: singular post type label. */
			__( 'Single de %s', 'on-building-page' ),
			$object->labels->singular_name
		);
	}

	return __( 'Por defecto', 'on-building-page' );
}

/**
 * Devuelve el contexto publico de una pagina en estado especial.
 *
 * @param int $post_id ID del post.
 * @return array<string, string>
 */
function obp_get_page_state_context( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return [];
	}

	$state = obp_get_page_state( $post_id );

	if ( '' === $state ) {
		return [];
	}

	$states          = obp_get_page_states();
	$definition      = $states[ $state ];
	$eyebrow_override = trim( (string) get_post_meta( $post_id, OBP_META_EYEBROW, true ) );
	$title_override   = trim( (string) get_post_meta( $post_id, OBP_META_TITLE, true ) );
	$message_override = trim( (string) get_post_meta( $post_id, OBP_META_MESSAGE, true ) );

	return [
		'state'        => $state,
		'label'        => $definition['label'],
		'eyebrow'      => '' !== $eyebrow_override ? $eyebrow_override : $definition['eyebrow'],
		'title'        => '' !== $title_override ? $title_override : get_the_title( $post ),
		'message'      => '' !== $message_override ? $message_override : $definition['message'],
		'back_url'     => home_url( '/' ),
		'back_label'   => __( 'Volver al inicio', 'on-building-page' ),
		'browse_url'   => home_url( '/' ),
		'browse_label' => __( 'Seguir navegando', 'on-building-page' ),
	];
}

/**
 * Renderiza el aviso publico.
 *
 * @param int $post_id ID del post.
 */
function obp_render_public_notice( $post_id ) {
	$context = obp_get_page_state_context( $post_id );

	if ( empty( $context ) ) {
		return '';
	}

	$classes = [
		'obp-page-state',
		'obp-page-state--' . $context['state'],
		'pd-editorial-shell',
		'pd-editorial-shell--page',
	];

	$output  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
	$output .= '<section class="obp-page-state__hero wp-block-group alignwide pd-editorial-hero">';
	$output .= '<p class="obp-page-state__eyebrow pd-eyebrow">' . esc_html( $context['eyebrow'] ) . '</p>';
	$output .= '<h1 class="obp-page-state__title wp-block-heading">' . esc_html( $context['title'] ) . '</h1>';
	$output .= '<p class="obp-page-state__message">' . esc_html( $context['message'] ) . '</p>';
	$output .= '</section>';
	$output .= '<section class="obp-page-state__surface wp-block-group alignwide pd-editorial-surface pd-editorial-surface--body">';
	$output .= '<p class="obp-page-state__support">' . esc_html__( 'La sección sigue visible dentro del recorrido del sitio, pero su contenido público está reemplazado temporalmente por este aviso.', 'on-building-page' ) . '</p>';
	$output .= '<div class="obp-page-state__actions wp-block-buttons">';
	$output .= '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $context['back_url'] ) . '">' . esc_html( $context['back_label'] ) . '</a></div>';
	$output .= '<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $context['browse_url'] ) . '">' . esc_html( $context['browse_label'] ) . '</a></div>';
	$output .= '</div>';
	$output .= '</section>';
	$output .= '</div>';

	return $output;
}

/**
 * Encola estilos publicos del plugin solo cuando hacen falta.
 */
function obp_enqueue_public_styles() {
	if ( ! obp_should_mask_current_page() ) {
		return;
	}

	$style_path = plugin_dir_path( __FILE__ ) . 'assets/on-building-page.css';

	wp_enqueue_style(
		'on-building-page',
		plugin_dir_url( __FILE__ ) . 'assets/on-building-page.css',
		[],
		file_exists( $style_path ) ? (string) filemtime( $style_path ) : OBP_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'obp_enqueue_public_styles' );

/**
 * Agrega clases de estado al body.
 *
 * @param string[] $classes Clases actuales.
 * @return string[]
 */
function obp_filter_body_class( $classes ) {
	if ( ! obp_should_mask_current_page() ) {
		return $classes;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return $classes;
	}

	$state = obp_get_page_state( $post->ID );

	if ( '' === $state ) {
		return $classes;
	}

	$classes[] = 'obp-is-masked-page';
	$classes[] = 'obp-state-' . $state;

	return $classes;
}
add_filter( 'body_class', 'obp_filter_body_class' );

/**
 * Evita indexacion de paginas en estados temporales.
 *
 * @param array<string, bool> $robots Robots actuales.
 * @return array<string, bool>
 */
function obp_filter_wp_robots( $robots ) {
	if ( ! obp_should_mask_current_page() ) {
		return $robots;
	}

	$robots['noindex'] = true;

	return $robots;
}
add_filter( 'wp_robots', 'obp_filter_wp_robots' );

/**
 * Inicia el buffer de salida para reemplazar el main publico.
 */
function obp_start_template_buffer() {
	if ( ! obp_should_mask_current_page() ) {
		return;
	}

	ob_start( 'obp_replace_public_main_markup' );
}
add_action( 'template_redirect', 'obp_start_template_buffer', 0 );

/**
 * Reemplaza el contenido del primer <main> publico.
 *
 * @param string $html HTML completo de salida.
 * @return string
 */
function obp_replace_public_main_markup( $html ) {
	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return $html;
	}

	$notice = obp_render_public_notice( $post->ID );

	if ( '' === $notice ) {
		return $html;
	}

	$pattern = '/<main\b([^>]*)>.*?<\/main>/is';

	if ( ! preg_match( $pattern, $html ) ) {
		return $html;
	}

	$replaced = false;

	return preg_replace_callback(
		$pattern,
		static function ( $matches ) use ( $notice, &$replaced ) {
			if ( $replaced ) {
				return $matches[0];
			}

			$replaced = true;

			return '<main' . $matches[1] . '>' . $notice . '</main>';
		},
		$html,
		1
	);
}

/**
 * Registra la metabox en los contenidos soportados.
 */
function obp_register_page_meta_box() {
	foreach ( obp_get_supported_post_types() as $post_type ) {
		add_meta_box(
			'obp-page-state',
			__( 'On Building Page', 'on-building-page' ),
			'obp_render_page_meta_box',
			$post_type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'obp_register_page_meta_box' );

/**
 * Renderiza la metabox de configuracion por pagina.
 *
 * @param WP_Post $post Post actual.
 */
function obp_render_page_meta_box( $post ) {
	$state   = obp_get_page_state( $post->ID );
	$eyebrow = (string) get_post_meta( $post->ID, OBP_META_EYEBROW, true );
	$title   = (string) get_post_meta( $post->ID, OBP_META_TITLE, true );
	$message = (string) get_post_meta( $post->ID, OBP_META_MESSAGE, true );
	$states  = obp_get_page_states();

	wp_nonce_field( 'obp_save_page_state', 'obp_page_state_nonce' );
	?>
	<p>
		<label for="obp-page-state"><strong><?php esc_html_e( 'Estado público', 'on-building-page' ); ?></strong></label>
		<select id="obp-page-state" name="obp_page_state" class="widefat">
			<option value=""><?php esc_html_e( 'Mostrar contenido normal', 'on-building-page' ); ?></option>
			<?php foreach ( $states as $key => $definition ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $state, $key ); ?>><?php echo esc_html( $definition['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="obp-page-eyebrow"><strong><?php esc_html_e( 'Eyebrow público', 'on-building-page' ); ?></strong></label>
		<input id="obp-page-eyebrow" type="text" name="obp_page_state_eyebrow" class="widefat" value="<?php echo esc_attr( $eyebrow ); ?>" />
	</p>
	<p>
		<label for="obp-page-title"><strong><?php esc_html_e( 'Título público', 'on-building-page' ); ?></strong></label>
		<input id="obp-page-title" type="text" name="obp_page_state_title" class="widefat" value="<?php echo esc_attr( $title ); ?>" />
	</p>
	<p>
		<label for="obp-page-message"><strong><?php esc_html_e( 'Mensaje público', 'on-building-page' ); ?></strong></label>
		<textarea id="obp-page-message" name="obp_page_state_message" class="widefat" rows="5"><?php echo esc_textarea( $message ); ?></textarea>
	</p>
	<p class="description">
		<?php esc_html_e( 'Los administradores siguen viendo el contenido real. Los demás visitantes verán este aviso en lugar del contenido público.', 'on-building-page' ); ?>
	</p>
	<?php
}

/**
 * Guarda la configuracion por contenido.
 *
 * @param int $post_id ID del post.
 */
function obp_save_page_meta_box( $post_id ) {
	if ( ! isset( $_POST['obp_page_state_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['obp_page_state_nonce'] ), 'obp_save_page_state' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post || ! obp_is_supported_post_type( $post->post_type ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$states = obp_get_page_states();
	$state  = isset( $_POST['obp_page_state'] ) ? sanitize_key( wp_unslash( $_POST['obp_page_state'] ) ) : '';
	$state  = isset( $states[ $state ] ) ? $state : '';

	if ( '' === $state ) {
		delete_post_meta( $post_id, OBP_META_STATE );
	} else {
		update_post_meta( $post_id, OBP_META_STATE, $state );
	}

	$map = [
		OBP_META_EYEBROW => isset( $_POST['obp_page_state_eyebrow'] ) ? sanitize_text_field( wp_unslash( $_POST['obp_page_state_eyebrow'] ) ) : '',
		OBP_META_TITLE   => isset( $_POST['obp_page_state_title'] ) ? sanitize_text_field( wp_unslash( $_POST['obp_page_state_title'] ) ) : '',
		OBP_META_MESSAGE => isset( $_POST['obp_page_state_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['obp_page_state_message'] ) ) : '',
	];

	foreach ( $map as $meta_key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}
}
add_action( 'save_post', 'obp_save_page_meta_box' );

/**
 * Agrega la pagina de ajustes del plugin.
 */
function obp_register_settings_page() {
	add_options_page(
		__( 'On Building Page', 'on-building-page' ),
		__( 'On Building Page', 'on-building-page' ),
		'manage_options',
		'on-building-page',
		'obp_render_settings_page'
	);
}
add_action( 'admin_menu', 'obp_register_settings_page' );

/**
 * Devuelve los contenidos para la pantalla de ajustes.
 *
 * @return WP_Post[]
 */
function obp_get_manageable_posts() {
	$posts = get_posts(
		[
			'post_type'      => obp_get_supported_post_types(),
			'post_status'    => [ 'publish', 'private', 'draft', 'pending', 'future' ],
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		]
	);

	usort(
		$posts,
		static function ( $left, $right ) {
			if ( ! $left instanceof WP_Post || ! $right instanceof WP_Post ) {
				return 0;
			}

			$type_compare = strcmp( $left->post_type, $right->post_type );

			if ( 0 !== $type_compare ) {
				return $type_compare;
			}

			$menu_order_compare = (int) $left->menu_order <=> (int) $right->menu_order;

			if ( 0 !== $menu_order_compare ) {
				return $menu_order_compare;
			}

			return strcasecmp( $left->post_title, $right->post_title );
		}
	);

	return $posts;
}

/**
 * Procesa el guardado en lote desde ajustes.
 */
function obp_handle_settings_page_submission() {
	if ( ! isset( $_POST['obp_save_settings'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'obp_save_settings', 'obp_settings_nonce' );

	$states      = obp_get_page_states();
	$submitted   = isset( $_POST['obp_page_state'] ) && is_array( $_POST['obp_page_state'] ) ? wp_unslash( $_POST['obp_page_state'] ) : [];
	$posts       = obp_get_manageable_posts();

	foreach ( $posts as $post ) {
		$raw_state = isset( $submitted[ $post->ID ] ) ? sanitize_key( (string) $submitted[ $post->ID ] ) : '';
		$state     = isset( $states[ $raw_state ] ) ? $raw_state : '';

		if ( '' === $state ) {
			delete_post_meta( $post->ID, OBP_META_STATE );
		} else {
			update_post_meta( $post->ID, OBP_META_STATE, $state );
		}
	}

	wp_safe_redirect(
		add_query_arg(
			[
				'page'    => 'on-building-page',
				'updated' => '1',
			],
			admin_url( 'options-general.php' )
		)
	);
	exit;
}
add_action( 'admin_init', 'obp_handle_settings_page_submission' );

/**
 * Renderiza la pantalla de ajustes.
 */
function obp_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$posts  = obp_get_manageable_posts();
	$states = obp_get_page_states();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'On Building Page', 'on-building-page' ); ?></h1>
		<p><?php esc_html_e( 'Marca páginas y presskits públicos como en construcción, en mantenimiento o fuera de servicio. Los administradores siguen viendo el contenido real; los demás usuarios verán el aviso público.', 'on-building-page' ); ?></p>
		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Estados guardados.', 'on-building-page' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'obp_save_settings', 'obp_settings_nonce' ); ?>
			<input type="hidden" name="obp_save_settings" value="1" />
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Contenido', 'on-building-page' ); ?></th>
						<th><?php esc_html_e( 'Tipo', 'on-building-page' ); ?></th>
						<th><?php esc_html_e( 'Layout', 'on-building-page' ); ?></th>
						<th><?php esc_html_e( 'Estado público', 'on-building-page' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'on-building-page' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $posts as $post ) : ?>
						<?php
						$current_state = obp_get_page_state( $post->ID );
						$layout_label  = obp_get_content_layout_label( $post );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( get_the_title( $post ) ); ?></strong>
								<div><code><?php echo esc_html( $post->post_name ); ?></code></div>
							</td>
							<td><?php echo esc_html( obp_get_post_type_label( $post->post_type ) ); ?></td>
							<td><?php echo esc_html( $layout_label ); ?></td>
							<td>
								<select name="obp_page_state[<?php echo esc_attr( $post->ID ); ?>]">
									<option value=""><?php esc_html_e( 'Mostrar contenido normal', 'on-building-page' ); ?></option>
									<?php foreach ( $states as $state_key => $definition ) : ?>
										<option value="<?php echo esc_attr( $state_key ); ?>" <?php selected( $current_state, $state_key ); ?>><?php echo esc_html( $definition['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Editar contenido', 'on-building-page' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Guardar estados', 'on-building-page' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Agrega una columna de estado en el listado de páginas.
 *
 * @param array<string, string> $columns Columnas actuales.
 * @return array<string, string>
 */
function obp_add_pages_column( $columns ) {
	$offset = array_search( 'date', array_keys( $columns ), true );

	if ( false === $offset ) {
		$columns['obp_page_state'] = __( 'Estado público', 'on-building-page' );
		return $columns;
	}

	$before = array_slice( $columns, 0, $offset, true );
	$after  = array_slice( $columns, $offset, null, true );

	return $before + [ 'obp_page_state' => __( 'Estado público', 'on-building-page' ) ] + $after;
}
add_filter( 'manage_pages_columns', 'obp_add_pages_column' );

/**
 * Renderiza la columna de estado.
 *
 * @param string $column  Nombre de columna.
 * @param int    $post_id ID del post.
 */
function obp_render_pages_column( $column, $post_id ) {
	if ( 'obp_page_state' !== $column ) {
		return;
	}

	$state = obp_get_page_state( $post_id );

	if ( '' === $state ) {
		echo esc_html__( 'Normal', 'on-building-page' );
		return;
	}

	$states = obp_get_page_states();
	echo esc_html( $states[ $state ]['label'] );
}
add_action( 'manage_pages_custom_column', 'obp_render_pages_column', 10, 2 );

/**
 * Agrega una columna de estado en los listados soportados.
 *
 * @param array<string, string> $columns Columnas actuales.
 * @return array<string, string>
 */
function obp_add_posts_column( $columns ) {
	return obp_add_pages_column( $columns );
}
add_filter( 'manage_presskit_posts_columns', 'obp_add_posts_column' );

/**
 * Renderiza la columna de estado en los listados soportados.
 *
 * @param string $column  Nombre de columna.
 * @param int    $post_id ID del post.
 */
function obp_render_posts_column( $column, $post_id ) {
	obp_render_pages_column( $column, $post_id );
}
add_action( 'manage_presskit_posts_custom_column', 'obp_render_posts_column', 10, 2 );

/**
 * Agrega link rapido a ajustes desde la lista de plugins.
 *
 * @param string[] $links Links actuales.
 * @return string[]
 */
function obp_filter_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=on-building-page' ) ) . '">' . esc_html__( 'Ajustes', 'on-building-page' ) . '</a>';

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'obp_filter_plugin_action_links' );
