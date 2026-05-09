<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSU_Cache {

	private static function duration() {
		$settings = get_option( DSU_OPTION_API, [] );
		return isset( $settings['cache_duration'] ) ? absint( $settings['cache_duration'] ) : 15;
	}

	private static function base_key( $company_code, $facility_code ) {
		return sanitize_key( $company_code . '_' . $facility_code );
	}

	// Unit groups list
	public static function get_unit_groups( $company_code, $facility_code ) {
		return get_transient( 'dsu_groups_' . self::base_key( $company_code, $facility_code ) );
	}

	public static function set_unit_groups( $company_code, $facility_code, $data ) {
		set_transient(
			'dsu_groups_' . self::base_key( $company_code, $facility_code ),
			$data,
			self::duration() * MINUTE_IN_SECONDS
		);
	}

	// Move-in URL per group
	public static function get_move_in_url( $company_code, $facility_code, $group_id ) {
		return get_transient( 'dsu_movein_' . self::base_key( $company_code, $facility_code ) . '_' . sanitize_key( $group_id ) );
	}

	public static function set_move_in_url( $company_code, $facility_code, $group_id, $url ) {
		set_transient(
			'dsu_movein_' . self::base_key( $company_code, $facility_code ) . '_' . sanitize_key( $group_id ),
			$url,
			self::duration() * MINUTE_IN_SECONDS
		);
	}

	// Reserve URL per group
	public static function get_reserve_url( $company_code, $facility_code, $group_id ) {
		return get_transient( 'dsu_reserve_' . self::base_key( $company_code, $facility_code ) . '_' . sanitize_key( $group_id ) );
	}

	public static function set_reserve_url( $company_code, $facility_code, $group_id, $url ) {
		set_transient(
			'dsu_reserve_' . self::base_key( $company_code, $facility_code ) . '_' . sanitize_key( $group_id ),
			$url,
			self::duration() * MINUTE_IN_SECONDS
		);
	}

	// Move-in cost per group
	public static function get_move_in_cost( $company_code, $facility_code, $group_id ) {
		return get_transient( 'dsu_mic_' . self::base_key( $company_code, $facility_code ) . '_' . sanitize_key( $group_id ) );
	}

	public static function set_move_in_cost( $company_code, $facility_code, $group_id, $data ) {
		set_transient(
			'dsu_mic_' . self::base_key( $company_code, $facility_code ) . '_' . sanitize_key( $group_id ),
			$data,
			self::duration() * MINUTE_IN_SECONDS
		);
	}

	// OAuth access token
	public static function get_access_token() {
		return get_transient( 'dsu_access_token' );
	}

	public static function set_access_token( $token, $expires_in ) {
		// Subtract 60 seconds buffer so we never use an expiring token
		$ttl = max( 60, absint( $expires_in ) - 60 );
		set_transient( 'dsu_access_token', $token, $ttl );
	}

	public static function clear_access_token() {
		delete_transient( 'dsu_access_token' );
	}

	// v1 unit groups (feature flags per group)
	public static function get_v1_unit_groups( $company_code, $facility_code ) {
		return get_transient( 'dsu_v1grp_' . self::base_key( $company_code, $facility_code ) );
	}

	public static function set_v1_unit_groups( $company_code, $facility_code, $data ) {
		set_transient(
			'dsu_v1grp_' . self::base_key( $company_code, $facility_code ),
			$data,
			self::duration() * MINUTE_IN_SECONDS
		);
	}

	// Facility info (name, address, phone from API)
	public static function get_facility_info( $company_code, $facility_code ) {
		return get_transient( 'dsu_facinfo_' . self::base_key( $company_code, $facility_code ) );
	}

	public static function set_facility_info( $company_code, $facility_code, $data ) {
		set_transient(
			'dsu_facinfo_' . self::base_key( $company_code, $facility_code ),
			$data,
			self::duration() * MINUTE_IN_SECONDS
		);
	}

	public static function bust_facility( $company_code, $facility_code ) {
		global $wpdb;
		$key_prefix = 'dsu_' . self::base_key( $company_code, $facility_code );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . $key_prefix . '%',
				'_transient_timeout_' . $key_prefix . '%'
			)
		);
		// Also clear the groups list key
		delete_transient( 'dsu_groups_' . self::base_key( $company_code, $facility_code ) );
	}
}
