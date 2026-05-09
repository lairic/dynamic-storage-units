<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSU_Waitlist {

	public function __construct() {
		add_action( 'wp_ajax_dsu_waitlist_submit', [ $this, 'handle_submit' ] );
		add_action( 'wp_ajax_nopriv_dsu_waitlist_submit', [ $this, 'handle_submit' ] );
	}

	public function handle_submit() {
		check_ajax_referer( 'dsu_waitlist_nonce', 'nonce' );

		$name          = sanitize_text_field( $_POST['name'] ?? '' );
		$email         = sanitize_email( $_POST['email'] ?? '' );
		$phone         = sanitize_text_field( $_POST['phone'] ?? '' );
		$group_label   = sanitize_text_field( $_POST['group_label'] ?? '' );
		$group_id      = sanitize_text_field( $_POST['group_id'] ?? '' );
		$facility_code = sanitize_text_field( $_POST['facility_code'] ?? '' );
		$config_name   = sanitize_text_field( $_POST['config_name'] ?? '' );

		if ( empty( $name ) || ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => __( 'Please provide a valid name and email.', 'dynamic-storage-units' ) ] );
		}

		$config = $this->get_config( $config_name );
		if ( ! $config ) {
			wp_send_json_error( [ 'message' => __( 'Invalid configuration.', 'dynamic-storage-units' ) ] );
		}

		$recipient = sanitize_email( $config['waitlist_email'] ?? get_option( 'admin_email' ) );
		$subject   = sanitize_text_field( $config['waitlist_subject'] ?? __( 'New Waitlist Signup', 'dynamic-storage-units' ) );
		$template  = wp_kses_post( $config['waitlist_message'] ?? '' );

		$replacements = [
			'{name}'          => esc_html( $name ),
			'{email}'         => esc_html( $email ),
			'{phone}'         => esc_html( $phone ),
			'{group_label}'   => esc_html( $group_label ),
			'{group_id}'      => esc_html( $group_id ),
			'{facility_code}' => esc_html( $facility_code ),
		];

		if ( $template ) {
			$body = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
		} else {
			$body  = "New storage unit waitlist signup:\n\n";
			$body .= "Name: {$replacements['{name}']}\n";
			$body .= "Email: {$replacements['{email}']}\n";
			if ( $phone ) {
				$body .= "Phone: {$replacements['{phone}']}\n";
			}
			$body .= "Unit Group: {$replacements['{group_label}']}\n";
			$body .= "Facility Code: {$replacements['{facility_code}']}\n";
		}

		$sent = wp_mail( $recipient, $subject, $body );

		if ( $sent ) {
			wp_send_json_success( [ 'message' => __( "You're on the waitlist! We'll notify you when this unit becomes available.", 'dynamic-storage-units' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not send notification. Please try again.', 'dynamic-storage-units' ) ] );
		}
	}

	private function get_config( $name ) {
		$configs = get_option( DSU_OPTION_CONFIGS, [] );
		foreach ( $configs as $config ) {
			if ( isset( $config['name'] ) && $config['name'] === $name ) {
				return $config;
			}
		}
		return null;
	}
}
