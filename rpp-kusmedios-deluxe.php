<?php
/**
 * Plugin Name: RPP Kusmedios Deluxe - Stream Platform Selector
 * Description: Extiende Radio Player Page con selector de plataforma (Azuracast, ZenoFM, SonicPanel, Shoutcast, Icecast) y proxy Now Playing para estacionkusmedios.org
 * Version: 2.0.0
 * Author: Kusmedios
 * Author URI: https://estacionkusmedios.org
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * Text Domain: rpp-kusmedios
 */

defined( 'ABSPATH' ) || exit;

define( 'RPKUS_VERSION', '2.0.0' );
define( 'RPKUS_FILE',    __FILE__ );
define( 'RPKUS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'RPKUS_URL',     plugin_dir_url( __FILE__ ) );

/**
 * Get all supported streaming platforms with their labels.
 *
 * @return array Key => label mapping. Empty string is the default/placeholder option.
 */
function rpkus_get_platforms(): array {
	return [
		''          => __( '\u2014 Selecciona plataforma \u2014', 'rpp-kusmedios' ),
		'azuracast' => 'Azuracast',
		'zenofm'    => 'ZenoFM',
		'sonicpanel'=> 'SonicPanel',
		'shoutcast' => 'Shoutcast v2',
		'icecast'   => 'Icecast',
		'manual'    => __( 'URL Manual / Otro', 'rpp-kusmedios' ),
	];
}

/**
 * Get platform-specific default values.
 *
 * @param string $platform Platform key.
 * @return array Default meta values for the given platform.
 */
function rpkus_get_defaults( string $platform ): array {
	switch ( $platform ) {
		case 'azuracast':
			return [
				'_rpkus_azura_station_id' => '1',
				'_rpkus_azura_mount'      => '/radio.mp3',
				'_rpkus_api_key'          => '',
			];
		case 'zenofm':
			return [
				'_rpkus_zeno_station_id' => '',
				'_rpkus_api_key'         => '',
			];
		case 'sonicpanel':
			return [
				'_rpkus_sonic_port' => '8000',
				'_rpkus_api_key'    => '',
			];
		case 'shoutcast':
			return [
				'_rpkus_sc_sid' => '1',
				'_rpkus_api_key'=> '',
			];
		case 'icecast':
			return [
				'_rpkus_ic_mount' => '/stream',
				'_rpkus_api_key'  => '',
			];
		case 'manual':
		default:
			return [];
	}
}

// =========================================================
// ENQUEUE ADMIN SCRIPTS
// =========================================================
add_action( 'admin_enqueue_scripts', function( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'radplapag_station' ) return;
	wp_enqueue_style( 'rpkus-admin', RPKUS_URL . 'assets/admin.css', [ 'radplapag-admin' ], RPKUS_VERSION );
	wp_enqueue_script( 'rpkus-admin-js', RPKUS_URL . 'assets/admin.js', [ 'jquery' ], RPKUS_VERSION, true );
	wp_localize_script( 'rpkus-admin-js', 'rpkusData', [
		'nonce'    => wp_create_nonce( 'rpkus_np_nonce' ),
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'strings'  => [
			'test_ok'      => __( 'Conexion exitosa \u2713', 'rpp-kusmedios' ),
			'test_fail'    => __( 'Error al conectar. Revisa la URL.', 'rpp-kusmedios' ),
			'testing'      => __( 'Probando...', 'rpp-kusmedios' ),
			'copy_success' => __( 'Copiado!', 'rpp-kusmedios' ),
			'apply_success'=> __( 'URL aplicada al player!', 'rpp-kusmedios' ),
			'error_nonce'  => __( 'Error de seguridad (nonce).', 'rpp-kusmedios' ),
		],
	] );
} );

// =========================================================
// META BOX
// =========================================================
add_action( 'add_meta_boxes', function() {
	add_meta_box(
		'rpkus_platform_box',
		'\U0001f399\ufe0f ' . __( 'Plataforma de Streaming - Kusmedios Deluxe', 'rpp-kusmedios' ),
		'rpkus_platform_meta_box_html',
		'radplapag_station',
		'normal',
		'high'
	);
} );

function rpkus_platform_meta_box_html( WP_Post $post ): void {
	$pid        = $post->ID;
	$platform   = get_post_meta( $pid, '_rpkus_platform', true )          ?: '';
	$base_url   = rtrim( (string) get_post_meta( $pid, '_rpkus_platform_base_url', true ), '/' );
	$meta       = rpkus_get_all_meta( $pid );
	$defaults   = rpkus_get_defaults( $platform );

	wp_nonce_field( 'rpkus_save_platform', 'rpkus_nonce' );
	?>
	<div id="rpkus-platform-wrapper" class="rpkus-box">
		<div class="rpkus-row">
			<label for="rpkus_platform" class="rpkus-label">
				<strong><?php esc_html_e( 'Plataforma', 'rpp-kusmedios' ); ?></strong>
			</label>
			<select id="rpkus_platform" name="rpkus_platform" class="rpkus-select">
				<?php foreach ( rpkus_get_platforms() as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $platform, $val ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="rpkus-row rpkus-panel rpkus-show-on-platform" id="rpkus-field-base-url">
			<label for="rpkus_base_url" class="rpkus-label">
				<strong><?php esc_html_e( 'URL Base del Servidor', 'rpp-kusmedios' ); ?></strong>
				<span class="rpkus-hint" id="rpkus-base-url-hint"></span>
			</label>
			<div class="rpkus-input-row">
				<input type="url" id="rpkus_base_url" name="rpkus_base_url"
					class="rpkus-input" value="<?php echo esc_attr( $base_url ); ?>"
					placeholder="https://radio.ejemplo.com" />
				<button type="button" id="rpkus-test-btn" class="button rpkus-test-btn">
					<?php esc_html_e( 'Probar conexion', 'rpp-kusmedios' ); ?>
				</button>
			</div>
			<span class="rpkus-test-result" id="rpkus-test-result"></span>
		</div>

		<!-- AZURACAST FIELDS -->
		<div class="rpkus-panel rpkus-platform-fields" data-platform="azuracast">
			<div class="rpkus-row">
				<label for="rpkus_azura_station_id" class="rpkus-label">
					<strong><?php esc_html_e( 'Station ID (Azuracast)', 'rpp-kusmedios' ); ?></strong>
					<span class="rpkus-hint"><?php esc_html_e( 'Numero en la URL del panel: /station/1', 'rpp-kusmedios' ); ?></span>
				</label>
				<input type="number" id="rpkus_azura_station_id" name="rpkus_azura_station_id"
					class="rpkus-input-short" value="<?php echo esc_attr( $meta['azura_station_id'] ?? $defaults['azura_station_id'] ?? '1' ); ?>" min="1" />
			</div>
			<div class="rpkus-row">
				<label for="rpkus_azura_mount" class="rpkus-label">
					<strong><?php esc_html_e( 'Mount Point', 'rpp-kusmedios' ); ?></strong>
					<span class="rpkus-hint"><?php esc_html_e( 'Ej: /radio.mp3  (vacio = default)', 'rpp-kusmedios' ); ?></span>
				</label>
				<input type="text" id="rpkus_azura_mount" name="rpkus_azura_mount"
					class="rpkus-input" value="<?php echo esc_attr( $meta['azura_mount'] ?? $defaults['azura_mount'] ?? '' ); ?>" placeholder="/radio.mp3" />
			</div>
			<div class="rpkus-row">
				<label for="rpkus_api_key" class="rpkus-label">
					<strong><?php esc_html_e( 'API Key (opcional)', 'rpp-kusmedios' ); ?></strong>
				</label>
				<input type="password" id="rpkus_api_key" name="rpkus_api_key"
					class="rpkus-input" value="<?php echo esc_attr( $meta['api_key'] ?? '' ); ?>" />
			</div>
			<div class="rpkus-info-box">
				<strong>Endpoint Now Playing:</strong><br>
				<code id="rpkus-azura-endpoint"></code>
			</div>
		</div>

		<!-- ZENOFM FIELDS -->
		<div class="rpkus-panel rpkus-platform-fields" data-platform="zenofm">
			<div class="rpkus-row">
				<label for="rpkus_zeno_id" class="rpkus-label">
					<strong><?php esc_html_e( 'Station ID de ZenoFM', 'rpp-kusmedios' ); ?></strong>
					<span class="rpkus-hint"><?php esc_html_e( 'En Dashboard > Station Settings', 'rpp-kusmedios' ); ?></span>
				</label>
				<input type="text" id="rpkus_zeno_id" name="rpkus_zeno_station_id"
					class="rpkus-input" value="<?php echo esc_attr( $meta['zeno_station_id'] ?? '' ); ?>" placeholder="abc123" />
			</div>
			<div class="rpkus-info-box">
				<strong>Stream URL:</strong>
				<code>https://stream.zeno.fm/<?php echo esc_html( $meta['zeno_station_id'] ?? '' ?: 'TU-STATION-ID' ); ?></code>
			</div>
		</div>

		<!-- SONICPANEL FIELDS -->
		<div class="rpkus-panel rpkus-platform-fields" data-platform="sonicpanel">
			<div class="rpkus-row">
				<label for="rpkus_sonic_port" class="rpkus-label">
					<strong><?php esc_html_e( 'Puerto del stream', 'rpp-kusmedios' ); ?></strong>
				</label>
				<input type="number" id="rpkus_sonic_port" name="rpkus_sonic_port"
					class="rpkus-input-short" value="<?php echo esc_attr( $meta['sonic_port'] ?? $defaults['sonic_port'] ?? '8000' ); ?>" min="1" max="65535" />
			</div>
			<div class="rpkus-info-box">
				<strong>Stream URL:</strong>
				<code id="rpkus-sonic-stream"></code>
			</div>
		</div>

		<!-- SHOUTCAST FIELDS -->
		<div class="rpkus-panel rpkus-platform-fields" data-platform="shoutcast">
			<div class="rpkus-row">
				<label for="rpkus_sc_sid" class="rpkus-label">
					<strong><?php esc_html_e( 'Stream ID (SID)', 'rpp-kusmedios' ); ?></strong>
					<span class="rpkus-hint"><?php esc_html_e( 'Generalmente 1', 'rpp-kusmedios' ); ?></span>
				</label>
				<input type="number" id="rpkus_sc_sid" name="rpkus_sc_sid"
					class="rpkus-input-short" value="<?php echo esc_attr( $meta['sc_sid'] ?? $defaults['sc_sid'] ?? '1' ); ?>" min="1" />
			</div>
			<div class="rpkus-info-box">
				<strong>Stream:</strong>
				<code><?php echo esc_html( ($meta['base_url'] ?? 'https://tu-servidor') . ';stream.mp3' ); ?></code>
			</div>
		</div>

		<!-- ICECAST FIELDS -->
		<div class="rpkus-panel rpkus-platform-fields" data-platform="icecast">
			<div class="rpkus-row">
				<label for="rpkus_ic_mount" class="rpkus-label">
					<strong><?php esc_html_e( 'Mount Point', 'rpp-kusmedios' ); ?></strong>
					<span class="rpkus-hint"><?php esc_html_e( 'Ej: /stream  o  /radio.mp3', 'rpp-kusmedios' ); ?></span>
				</label>
				<input type="text" id="rpkus_ic_mount" name="rpkus_ic_mount"
					class="rpkus-input" value="<?php echo esc_attr( $meta['ic_mount'] ?? $defaults['ic_mount'] ?? '/stream' ); ?>" placeholder="/stream" />
			</div>
			<div class="rpkus-info-box">
				<strong>Stream URL:</strong>
				<code><?php echo esc_html( ($meta['base_url'] ?? 'https://tu-servidor') . ($meta['ic_mount'] ?? '/stream') ); ?></code>
			</div>
		</div>

		<!-- SYNC OPTIONS -->
		<div class="rpkus-panel rpkus-show-on-platform rpkus-sync-section" id="rpkus-sync-section">
			<h4 class="rpkus-section-title">\u26a1 <?php esc_html_e( 'Sincronizacion Automatica', 'rpp-kusmedios' ); ?></h4>
			<label class="rpkus-toggle-label">
				<input type="checkbox" name="rpkus_sync_metadata" value="1" <?php checked( $meta['sync_metadata'] ?? false ); ?> />
				<span><?php esc_html_e( 'Sincronizar metadatos Now Playing (cancion, artista, artwork)', 'rpp-kusmedios' ); ?></span>
			</label>
			<label class="rpkus-toggle-label rpkus-azura-only">
				<input type="checkbox" name="rpkus_sync_schedule" value="1" <?php checked( $meta['sync_schedule'] ?? false ); ?> />
				<span><?php esc_html_e( 'Sincronizar programacion desde Azuracast (solo Azuracast)', 'rpp-kusmedios' ); ?></span>
			</label>
			<p class="rpkus-note"><?php esc_html_e( 'Los metadatos se actualizan cada 15 segundos via REST API. No requiere servidor externo.', 'rpp-kusmedios' ); ?></p>
		</div>

		<!-- GENERATED STREAM URL -->
		<div class="rpkus-panel rpkus-show-on-platform" id="rpkus-generated-stream">
			<h4 class="rpkus-section-title">\U0001f517 <?php esc_html_e( 'Stream URL generada', 'rpp-kusmedios' ); ?></h4>
			<div class="rpkus-input-row">
				<input type="text" id="rpkus-stream-preview" class="rpkus-input" readonly value="" />
				<button type="button" id="rpkus-copy-stream" class="button">\U0001f4cb <?php esc_html_e( 'Copiar', 'rpp-kusmedios' ); ?></button>
				<button type="button" id="rpkus-apply-stream" class="button button-primary">
					\u2705 <?php esc_html_e( 'Aplicar al Stream URL del player', 'rpp-kusmedios' ); ?>
				</button>
			</div>
			<p class="rpkus-note"><?php esc_html_e( 'Aplica para rellenar automaticamente el campo Streaming URL del plugin RPP.', 'rpp-kusmedios' ); ?></p>
		</div>
	</div>
	<?php
}

// Helper: get all platform meta for a post in one call.
function rpkus_get_all_meta( int $post_id ): array {
	$keys = [
		'azura_station_id', 'azura_mount', 'api_key',
		'zeno_station_id', 'sonic_port', 'sc_sid', 'ic_mount',
		'sync_metadata', 'sync_schedule',
		'platform_base_url',
	];
	$output = [];
	foreach ( $keys as $k ) {
		$output[ $k ] = get_post_meta( $post_id, '_rpkus_' . $k, true ) ?: '';
	}
	return $output;
}

// =========================================================
// SAVE META BOX
// =========================================================
add_action( 'save_post_radplapag_station', function( int $post_id ): void {
	if ( ! isset( $_POST['rpkus_nonce'] ) || ! wp_verify_nonce( $_POST['rpkus_nonce'], 'rpkus_save_platform' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'manage_options' ) ) return;

	// Re-generate defaults if platform changed
	$new_platform = sanitize_text_field( $_POST['rpkus_platform'] ?? '' );
	$old_platform = get_post_meta( $post_id, '_rpkus_platform', true );
	if ( $new_platform && $new_platform !== $old_platform ) {
		$defs = rpkus_get_defaults( $new_platform );
		foreach ( $defs as $meta => $val ) {
			update_post_meta( $post_id, '_rpkus_' . $meta, $val );
		}
	}

	$text_fields = [
		'_rpkus_platform'          => 'rpkus_platform',
		'_rpkus_platform_base_url' => 'rpkus_base_url',
		'_rpkus_azura_mount'       => 'rpkus_azura_mount',
		'_rpkus_azura_station_id'  => 'rpkus_azura_station_id',
		'_rpkus_api_key'           => 'rpkus_api_key',
		'_rpkus_zeno_station_id'   => 'rpkus_zeno_station_id',
		'_rpkus_sonic_port'        => 'rpkus_sonic_port',
		'_rpkus_sc_sid'            => 'rpkus_sc_sid',
		'_rpkus_ic_mount'          => 'rpkus_ic_mount',
		'sync_metadata'            => 'rpkus_sync_metadata',
		'sync_schedule'            => 'rpkus_sync_schedule',
	];
	foreach ( $text_fields as $meta => $post_key ) {
		if ( isset( $_POST[ $post_key ] ) ) {
			$val = $_POST[ $post_key ];
			if ( 'sync_metadata' === $meta || 'sync_schedule' === $meta ) {
				update_post_meta( $post_id, $meta, ! empty( $val ) ? '1' : '' );
			} else {
				update_post_meta( $post_id, $meta, sanitize_text_field( wp_unslash( $val ) ) );
			}
		}
	}
} );

// =========================================================
// REST ENDPOINT: /wp-json/rpkus/v1/nowplaying/{id}
// =========================================================
add_action( 'rest_api_init', function() {
	register_rest_route( 'rpkus/v1', '/nowplaying/(?P<id>\d+)', [
		'methods'             => 'GET',
		'callback'            => 'rpkus_rest_nowplaying',
		'permission_callback' => 'rpkus_rest_permission',
		'args'                => [
			'id' => [ 'validate_callback' => function( $val, $req, $key ) { return is_numeric( $val ); } ],
		],
	] );
} );

function rpkus_rest_permission(): bool {
	return current_user_can( 'manage_options' );
}

function rpkus_rest_nowplaying( WP_REST_Request $req ): WP_REST_Response {
	$post_id  = (int) $req->get_param( 'id' );
	$platform = get_post_meta( $post_id, '_rpkus_platform', true );
	$base_url = rtrim( (string) get_post_meta( $post_id, '_rpkus_platform_base_url', true ), '/' );
	$api_key  = (string) get_post_meta( $post_id, '_rpkus_api_key', true );

	if ( ! $platform || ! $base_url ) {
		return new WP_REST_Response( [ 'error' => 'not_configured' ], 404 );
	}

	$endpoint = '';
	$headers  = [];
	switch ( $platform ) {
		case 'azuracast':
			$stid     = (string) ( get_post_meta( $post_id, '_rpkus_azura_station_id', true ) ?: '1' );
			$endpoint = $base_url . '/api/nowplaying/' . $stid;
			if ( $api_key ) $headers['X-API-Key'] = $api_key;
			break;
		case 'zenofm':
			$zid      = (string) get_post_meta( $post_id, '_rpkus_zeno_station_id', true );
			$endpoint = 'https://api.zeno.fm/mounts/icestats/sub/' . rawurlencode( $zid ) . '/current';
			break;
		case 'sonicpanel':
			$port     = (string) ( get_post_meta( $post_id, '_rpkus_sonic_port', true ) ?: '8000' );
			$endpoint = $base_url . ':' . $port . '/stats?json=1';
			if ( $api_key ) $headers['Authorization'] = 'Bearer ' . $api_key;
			break;
		case 'shoutcast':
			$sc_sid   = (string) ( get_post_meta( $post_id, '_rpkus_sc_sid', true ) ?: '1' );
			$endpoint = $base_url . '/statistics?json=1&sid=' . $sc_sid;
			break;
		case 'icecast':
			$endpoint = $base_url . '/status-json.xsl';
			break;
		default:
			return new WP_REST_Response( [ 'error' => 'unsupported_platform' ], 400 );
	}

	$cache_key = 'rpkus_np_' . md5( $endpoint );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) return new WP_REST_Response( $cached, 200 );

	$resp = wp_remote_get( $endpoint, [ 'timeout' => 8, 'headers' => $headers ] );
	if ( is_wp_error( $resp ) ) return new WP_REST_Response( [ 'error' => $resp->get_error_message() ], 502 );

	$code = wp_remote_retrieve_response_code( $resp );
	if ( $code < 200 || $code >= 300 ) return new WP_REST_Response( [ 'error' => 'upstream_error', 'code' => $code ], 502 );

	$data   = json_decode( wp_remote_retrieve_body( $resp ), true );
	$result = rpkus_normalize_nowplaying( $platform, $data );
	set_transient( $cache_key, $result, 14 );
	return new WP_REST_Response( $result, 200 );
}

function rpkus_normalize_nowplaying( string $platform, ?array $data ): array {
	$empty = [ 'artist' => '', 'title' => '', 'artwork' => '', 'listeners' => 0, 'is_live' => false ];
	if ( ! $data ) return $empty;

	switch ( $platform ) {
		case 'azuracast':
			$np   = $data['now_playing'] ?? [];
			$song = $np['song'] ?? [];
			return [
				'artist'    => (string) ( $song['artist'] ?? '' ),
				'title'     => (string) ( $song['title']  ?? '' ),
				'artwork'   => (string) ( $song['art']    ?? '' ),
				'listeners' => (int)    ( $data['listeners']['current'] ?? 0 ),
				'is_live'   => ! empty( $data['live']['is_live'] ),
				'dj_name'   => (string) ( $data['live']['streamer_name'] ?? '' ),
				'show_name' => (string) ( $np['playlist'] ?? '' ),
				'next_song' => (string) ( $data['playing_next']['song']['text'] ?? '' ),
			];
		case 'zenofm':
			$src   = $data['icestats']['source'] ?? [];
			$title = (string) ( $src['title'] ?? '' );
			[$a, $t] = array_pad( explode( ' - ', $title, 2 ), 2, '' );
			return array_merge( $empty, [ 'artist' => $a, 'title' => $t ?: $title, 'listeners' => (int)( $src['listeners'] ?? 0 ) ] );
		case 'shoutcast':
			$title = (string) ( $data['songtitle'] ?? '' );
			[$a, $t] = array_pad( explode( ' - ', $title, 2 ), 2, '' );
			return array_merge( $empty, [ 'artist' => $a, 'title' => $t ?: $title, 'listeners' => (int)( $data['currentlisteners'] ?? 0 ) ] );
		case 'icecast':
			$src   = $data['icestats']['source'] ?? [];
			$src   = isset( $src[0] ) ? $src[0] : $src;
			$title = (string) ( $src['title'] ?? '' );
			[$a, $t] = array_pad( explode( ' - ', $title, 2 ), 2, '' );
			return array_merge( $empty, [ 'artist' => $a, 'title' => $t ?: $title, 'listeners' => (int)( $src['listeners'] ?? 0 ) ] );
		default:
			return $empty;
	}
}

// =========================================================
// AJAX: TEST CONNECTION
// =========================================================
add_action( 'wp_ajax_rpkus_test_connection', function() {
	check_ajax_referer( 'rpkus_np_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

	$platform = sanitize_text_field( $_POST['platform'] ?? '' );
	$base_url = esc_url_raw( $_POST['base_url'] ?? '' );
	$extra    = sanitize_text_field( $_POST['extra'] ?? '' );

	if ( ! $platform || ! $base_url ) {
		wp_send_json_error( [ 'error' => 'missing_params' ] );
		return;
	}

	$endpoint = '';
	switch ( $platform ) {
		case 'azuracast':
			$stid = $extra ?: '1';
			$endpoint = rtrim( $base_url, '/' ) . '/api/nowplaying/' . $stid;
			break;
		case 'zenofm':
			$endpoint = 'https://api.zeno.fm/mounts/icestats/sub/' . rawurlencode( $extra ) . '/current';
			break;
		case 'sonicpanel':
			$port = $extra ?: '8000';
			$endpoint = rtrim( $base_url, '/' ) . ':' . $port . '/stats?json=1';
			break;
		case 'shoutcast':
			$sid = $extra ?: '1';
			$endpoint = rtrim( $base_url, '/' ) . '/statistics?json=1&sid=' . $sid;
			break;
		case 'icecast':
			$endpoint = rtrim( $base_url, '/' ) . '/status-json.xsl';
			break;
		default:
			wp_send_json_error( [ 'error' => 'unsupported_platform' ] );
			return;
	}

	$resp = wp_remote_get( $endpoint, [ 'timeout' => 8 ] );
	if ( is_wp_error( $resp ) ) { wp_send_json_error( [ 'error' => $resp->get_error_message() ] ); return; }

	$code = wp_remote_retrieve_response_code( $resp );
	if ( $code >= 200 && $code < 300 ) {
		wp_send_json_success( [ 'code' => $code, 'endpoint' => $endpoint ] );
	} else {
		wp_send_json_error( [ 'error' => "HTTP {$code}", 'endpoint' => $endpoint ] );
	}
} );

// =========================================================
// AUTO-FILL stream_url on save (only if empty)
// =========================================================
add_action( 'save_post_radplapag_station', function( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'manage_options' ) ) return;

	$platform = (string) get_post_meta( $post_id, '_rpkus_platform', true );
	$base_url = rtrim( (string) get_post_meta( $post_id, '_rpkus_platform_base_url', true ), '/' );
	if ( ! $platform || ! $base_url ) return;

	$existing = (string) get_post_meta( $post_id, 'radplapag_station_stream_url', true );
	if ( $existing ) return;

	$meta = rpkus_get_all_meta( $post_id );
	$url = '';

	switch ( $platform ) {
		case 'azuracast':
			$m   = $meta['azura_mount'] ?: '/radio.mp3';
			$sid = $meta['azura_station_id'] ?: '1';
			$url = $base_url . '/listen/' . $sid . $m;
			break;
		case 'zenofm':
			$zid = $meta['zeno_station_id'] ?: '';
			$url = 'https://stream.zeno.fm/' . $zid;
			break;
		case 'sonicpanel':
			$port = $meta['sonic_port'] ?: '8000';
			$url  = $base_url . ':' . $port . '/stream';
			break;
		case 'shoutcast':
			$url = $base_url . ';stream.mp3';
			break;
		case 'icecast':
			$mount = $meta['ic_mount'] ?: '/stream';
			$url   = $base_url . $mount;
			break;
	}
	if ( $url ) update_post_meta( $post_id, 'radplapag_station_stream_url', esc_url_raw( $url ) );
} );