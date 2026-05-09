<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSU_API {

	private $base_url;
	private $company_code;
	private $client_id;
	private $client_secret;

	public function __construct() {
		$settings            = get_option( DSU_OPTION_API, [] );
		$this->base_url      = rtrim( sanitize_text_field( $settings['base_url'] ?? '' ), '/' );
		$this->company_code  = sanitize_text_field( $settings['company_code'] ?? '' );
		$this->client_id     = sanitize_text_field( $settings['client_id'] ?? '' );
		$this->client_secret = sanitize_text_field( $settings['client_secret'] ?? '' );
	}

	/**
	 * GET /api/v2/companies/{companyCode}/facilities/{facilityCode}/unit-groups
	 */
	public function get_unit_groups( $facility_code ) {
		$cached = DSU_Cache::get_unit_groups( $this->company_code, $facility_code );
		if ( false !== $cached ) {
			return $cached;
		}

		$path     = $this->facility_path( $facility_code ) . '/unit-groups';
		$response = $this->request( $path, [ 'Page' => '0', 'Size' => '200' ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$groups = $response['results'] ?? $response;
		if ( ! is_array( $groups ) ) {
			$groups = [];
		}

		DSU_Cache::set_unit_groups( $this->company_code, $facility_code, $groups );

		return $groups;
	}

	/**
	 * GET /api/v1/companies/{companyCode}/facilities/{facilityCode}/unit-groups
	 * Returns per-group feature flags (isClimateControlled, hasDriveUpAccess, etc.)
	 * that are not available in the v2 endpoint.
	 */
	public function get_v1_unit_groups( $facility_code ) {
		$cached = DSU_Cache::get_v1_unit_groups( $this->company_code, $facility_code );
		if ( false !== $cached ) {
			return $cached;
		}

		$path     = '/api/v1/companies/' . rawurlencode( $this->company_code )
			. '/facilities/' . rawurlencode( sanitize_text_field( $facility_code ) )
			. '/unit-groups';
		$response = $this->request( $path );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$groups = $response['availableUnits'] ?? $response['results'] ?? $response;
		if ( ! is_array( $groups ) ) {
			$groups = [];
		}

		DSU_Cache::set_v1_unit_groups( $this->company_code, $facility_code, $groups );
		return $groups;
	}

	/**
	 * GET /api/v2/companies/{companyCode}/facilities/{facilityCode}/unit-groups/{groupId}/move-in-url
	 */
	public function get_move_in_url( $facility_code, $group_id ) {
		$cached = DSU_Cache::get_move_in_url( $this->company_code, $facility_code, $group_id );
		if ( false !== $cached ) {
			return $cached;
		}

		$path     = $this->facility_path( $facility_code ) . '/unit-groups/' . rawurlencode( $group_id ) . '/move-in-url';
		$response = $this->request( $path );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$url = esc_url_raw( $response['url'] ?? '' );
		DSU_Cache::set_move_in_url( $this->company_code, $facility_code, $group_id, $url );

		return $url;
	}

	/**
	 * GET /api/v2/companies/{companyCode}/facilities/{facilityCode}/unit-groups/{groupId}/reserve-url
	 */
	public function get_reserve_url( $facility_code, $group_id ) {
		$cached = DSU_Cache::get_reserve_url( $this->company_code, $facility_code, $group_id );
		if ( false !== $cached ) {
			return $cached;
		}

		$path     = $this->facility_path( $facility_code ) . '/unit-groups/' . rawurlencode( $group_id ) . '/reserve-url';
		$response = $this->request( $path );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$url = esc_url_raw( $response['url'] ?? '' );
		DSU_Cache::set_reserve_url( $this->company_code, $facility_code, $group_id, $url );

		return $url;
	}

	/**
	 * GET .../unit-groups/{groupId}/move-in-cost — total due at move-in (rent + fees + deposits).
	 * Returns array with 'total' key, or WP_Error if the endpoint isn't supported.
	 */
	public function get_move_in_cost( $facility_code, $group_id ) {
		$cached = DSU_Cache::get_move_in_cost( $this->company_code, $facility_code, $group_id );
		if ( false !== $cached ) {
			return $cached;
		}

		$move_in_date = current_time( 'Y-m-d' );
		$path         = $this->facility_path( $facility_code ) . '/unit-groups/' . rawurlencode( $group_id ) . '/move-in-cost';
		$response     = $this->request( $path, [ 'moveInDate' => $move_in_date ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$cost = [
			'total' => (float) ( $response['total'] ?? $response['moveInTotal'] ?? $response['amount'] ?? 0 ),
		];

		DSU_Cache::set_move_in_cost( $this->company_code, $facility_code, $group_id, $cost );
		return $cost;
	}

	/**
	 * POST /api/v2/login — exchange client credentials for a bearer token.
	 * Returns token string or WP_Error.
	 */
	public function get_access_token( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = DSU_Cache::get_access_token();
			if ( $cached ) {
				return $cached;
			}
		}

		if ( empty( $this->base_url ) ) {
			return new WP_Error( 'dsu_no_url', __( 'API base URL is not configured.', 'dynamic-storage-units' ) );
		}
		if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
			return new WP_Error( 'dsu_no_creds', __( 'Client ID and Client Secret are required.', 'dynamic-storage-units' ) );
		}

		$response = wp_remote_post( $this->base_url . '/api/v2/login', [
			'timeout' => 15,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'grantType'    => 'Client_Credentials',
				'clientId'     => $this->client_id,
				'clientSecret' => $this->client_secret,
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error(
				'dsu_auth_error',
				sprintf( __( 'Authentication failed (status %d). Check your Client ID and Secret.', 'dynamic-storage-units' ), $code )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['token'] ) ) {
			return new WP_Error( 'dsu_token_missing', __( 'No token returned from login endpoint.', 'dynamic-storage-units' ) );
		}

		$token      = sanitize_text_field( $body['token'] );
		$expires_in = absint( $body['expiresIn'] ?? 3600 );

		DSU_Cache::set_access_token( $token, $expires_in );

		return $token;
	}

	/**
	 * GET /api/v2/companies/{companyCode}/facilities/{facilityCode}
	 */
	public function get_facility_info( $facility_code ) {
		$cached = DSU_Cache::get_facility_info( $this->company_code, $facility_code );
		if ( false !== $cached ) {
			return $cached;
		}

		// v1 returns richer data than v2: amenities, coordinates, phone, full address
		$path     = '/api/v1/companies/' . rawurlencode( $this->company_code )
			. '/facilities/' . rawurlencode( sanitize_text_field( $facility_code ) );
		$response = $this->request( $path );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		DSU_Cache::set_facility_info( $this->company_code, $facility_code, $response );

		return $response;
	}

	/**
	 * GET /api/v1/companies/{companyCode}/facilities/{facilityCode}/lead-sources
	 */
	public function get_lead_sources( $facility_code ) {
		$path = '/api/v1/companies/' . rawurlencode( $this->company_code )
			. '/facilities/' . rawurlencode( sanitize_text_field( $facility_code ) )
			. '/lead-sources';
		return $this->request( $path, [ 'Page' => '0', 'Size' => '200' ] );
	}

	/**
	 * GET /api/v1/companies/{companyCode}/facilities/{facilityCode}/unit-groups/reservations/settings
	 */
	public function get_reservation_settings( $facility_code ) {
		$path = '/api/v1/companies/' . rawurlencode( $this->company_code )
			. '/facilities/' . rawurlencode( sanitize_text_field( $facility_code ) )
			. '/unit-groups/reservations/settings';
		return $this->request( $path );
	}

	private function facility_path( $facility_code ) {
		return '/api/v2/companies/' . rawurlencode( $this->company_code )
			. '/facilities/' . rawurlencode( sanitize_text_field( $facility_code ) );
	}

	private function request( $path, $params = [] ) {
		if ( empty( $this->base_url ) ) {
			return new WP_Error( 'dsu_no_url', __( 'API base URL is not configured.', 'dynamic-storage-units' ) );
		}
		if ( empty( $this->company_code ) ) {
			return new WP_Error( 'dsu_no_company', __( 'Company code is not configured.', 'dynamic-storage-units' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url  = $this->base_url . $path;
		$args = [
			'timeout' => 15,
			'headers' => [
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			],
		];

		if ( ! empty( $params ) ) {
			$url = add_query_arg( array_map( 'sanitize_text_field', $params ), $url );
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Token may have expired — retry once with a fresh token
		if ( $code === 401 ) {
			$token = $this->get_access_token( true );
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$args['headers']['Authorization'] = 'Bearer ' . $token;
			$response = wp_remote_get( $url, $args );
			$code     = wp_remote_retrieve_response_code( $response );
		}

		if ( $code === 404 ) {
			return new WP_Error( 'dsu_not_found', __( 'Resource not found (404).', 'dynamic-storage-units' ) );
		}
		if ( $code !== 200 ) {
			return new WP_Error(
				'dsu_api_error',
				sprintf( __( 'API returned status %d.', 'dynamic-storage-units' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'dsu_json_error', __( 'Invalid JSON from API.', 'dynamic-storage-units' ) );
		}

		return $data;
	}
}
