<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSU_License {

	const OPTION_KEY   = 'dsu_license_key';
	const OPTION_DATA  = 'dsu_license_data';
	const TRANSIENT    = 'dsu_license_status';
	const TRANSIENT_TTL = 43200; // 12 hours

	// ── Public API ──────────────────────────────────────────────

	public static function get_key() {
		return get_option( self::OPTION_KEY, '' );
	}

	public static function get_status() {
		$cached = get_transient( self::TRANSIENT );
		if ( $cached !== false ) {
			return $cached;
		}
		return self::refresh_status();
	}

	public static function is_active() {
		return self::get_status() === 'active';
	}

	/** Activate the saved license key for this site. */
	public static function activate( $key = null ) {
		$key = $key ? sanitize_text_field( $key ) : self::get_key();
		if ( ! $key ) {
			return [ 'success' => false, 'message' => 'No license key set.' ];
		}

		$response = self::remote_post( 'activate', [
			'license'        => $key,
			'site_url'       => home_url(),
			'plugin_version' => DSU_VERSION,
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		if ( ! empty( $response['success'] ) ) {
			update_option( self::OPTION_KEY, strtoupper( $key ) );
			update_option( self::OPTION_DATA, $response );
			set_transient( self::TRANSIENT, 'active', self::TRANSIENT_TTL );
			return [ 'success' => true, 'message' => $response['message'] ?? 'Activated.' ];
		}

		$msg = $response['message'] ?? $response['error'] ?? 'Activation failed.';
		return [ 'success' => false, 'message' => $msg ];
	}

	/** Deactivate this site from the saved license. */
	public static function deactivate() {
		$key = self::get_key();
		if ( ! $key ) {
			return [ 'success' => false, 'message' => 'No license key set.' ];
		}

		$response = self::remote_post( 'deactivate', [
			'license'  => $key,
			'site_url' => home_url(),
		] );

		delete_transient( self::TRANSIENT );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		delete_option( self::OPTION_DATA );
		return [ 'success' => true, 'message' => $response['message'] ?? 'Deactivated.' ];
	}

	// ── Internals ────────────────────────────────────────────────

	private static function refresh_status() {
		$key = self::get_key();
		if ( ! $key ) {
			return 'unlicensed';
		}

		$url = add_query_arg( [
			'license'         => $key,
			'site_url'        => home_url(),
			'current_version' => DSU_VERSION,
		], DSU_LICENSE_SERVER . '/api/check-update' );

		$result = wp_remote_get( $url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $result ) ) {
			// Network failure — don't lock them out, keep last known state
			$last = get_option( self::OPTION_DATA, [] );
			$status = ! empty( $last ) ? 'active' : 'unknown';
			set_transient( self::TRANSIENT, $status, 3600 );
			return $status;
		}

		$code = wp_remote_retrieve_response_code( $result );
		if ( $code === 200 ) {
			set_transient( self::TRANSIENT, 'active', self::TRANSIENT_TTL );
			return 'active';
		}

		set_transient( self::TRANSIENT, 'invalid', self::TRANSIENT_TTL );
		return 'invalid';
	}

	private static function remote_post( $endpoint, $body ) {
		$result = wp_remote_post( DSU_LICENSE_SERVER . '/api/' . $endpoint, [
			'timeout' => 15,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return json_decode( wp_remote_retrieve_body( $result ), true ) ?: [];
	}
}
