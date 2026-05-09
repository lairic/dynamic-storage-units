<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSU_Updater {

	private $plugin_slug;
	private $plugin_file;

	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = plugin_basename( $plugin_file );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'clear_update_transient' ], 10, 2 );
	}

	/** Injects update data into WordPress's plugin update transient. */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$installed = $transient->checked[ $this->plugin_slug ] ?? DSU_VERSION;
		$update    = $this->fetch_update_data();

		// No data, license error, or server says already current — clear any stale entry.
		if ( ! $update || isset( $update['no_update'] ) ) {
			return $this->mark_current( $transient, $installed );
		}

		// Belt-and-suspenders: verify locally that the server version is actually newer.
		// Guards against stale cached responses that claim the same version is "new".
		if ( ! version_compare( $update['version'], $installed, '>' ) ) {
			return $this->mark_current( $transient, $installed );
		}

		$transient->response[ $this->plugin_slug ] = (object) [
			'slug'         => 'dynamic-storage-units',
			'plugin'       => $this->plugin_slug,
			'new_version'  => $update['version'],
			'url'          => $update['author_homepage'] ?? DSU_LICENSE_SERVER,
			'package'      => $update['download_url'],
			'requires'     => $update['requires']      ?? '6.0',
			'requires_php' => $update['requires_php']  ?? '7.4',
			'tested'       => $update['tested']        ?? $update['version'],
		];

		return $transient;
	}

	/** Provides plugin details for the "View version X.X details" popup. */
	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== 'dynamic-storage-units' ) {
			return $result;
		}

		$update = $this->fetch_update_data();
		if ( ! $update || isset( $update['no_update'] ) ) {
			return $result;
		}

		return (object) [
			'name'          => $update['name']            ?? 'Dynamic Storage Units',
			'slug'          => 'dynamic-storage-units',
			'version'       => $update['version'],
			'author'        => $update['author']          ?? 'Metric Moose',
			'homepage'      => $update['author_homepage'] ?? DSU_LICENSE_SERVER,
			'requires'      => $update['requires']        ?? '6.0',
			'requires_php'  => $update['requires_php']    ?? '7.4',
			'tested'        => $update['tested']          ?? $update['version'],
			'sections'      => [
				'changelog' => nl2br( esc_html( $update['changelog'] ?? '' ) ),
			],
			'download_link' => $update['download_url'],
		];
	}

	/** Clears our cached update response when this plugin is updated. */
	public function clear_update_transient( $upgrader, $hook_extra ) {
		$plugins = $hook_extra['plugins'] ?? [];
		if ( in_array( $this->plugin_slug, (array) $plugins, true ) ) {
			delete_transient( 'dsu_update_check' );
		}
	}

	// ── Internal ─────────────────────────────────────────────────

	/** Marks the plugin as current in the transient, clearing any stale update entry. */
	private function mark_current( $transient, $installed_version ) {
		unset( $transient->response[ $this->plugin_slug ] );

		if ( ! isset( $transient->no_update ) ) {
			$transient->no_update = [];
		}
		$transient->no_update[ $this->plugin_slug ] = (object) [
			'slug'        => 'dynamic-storage-units',
			'plugin'      => $this->plugin_slug,
			'new_version' => $installed_version,
			'url'         => DSU_LICENSE_SERVER,
			'package'     => '',
		];

		return $transient;
	}

	private function fetch_update_data() {
		$transient_key = 'dsu_update_check';
		$cached = get_transient( $transient_key );
		if ( $cached !== false ) {
			return $cached;
		}

		$key = DSU_License::get_key();
		if ( ! $key ) {
			return null;
		}

		$url = add_query_arg( [
			'license'         => $key,
			'site_url'        => home_url(),
			'current_version' => DSU_VERSION,
		], DSU_LICENSE_SERVER . '/api/check-update' );

		$result = wp_remote_get( $url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $result ) || wp_remote_retrieve_response_code( $result ) !== 200 ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $result ), true );
		set_transient( $transient_key, $data, 43200 );
		return $data;
	}
}
