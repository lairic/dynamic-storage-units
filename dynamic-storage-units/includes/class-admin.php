<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSU_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_dsu_fetch_unit_groups', [ $this, 'ajax_fetch_unit_groups' ] );
		add_action( 'wp_ajax_dsu_bust_cache', [ $this, 'ajax_bust_cache' ] );
		add_action( 'wp_ajax_dsu_create_configs', [ $this, 'ajax_create_configs' ] );
		add_action( 'wp_ajax_dsu_update_config', [ $this, 'ajax_update_config' ] );
		add_action( 'wp_ajax_dsu_delete_config', [ $this, 'ajax_delete_config' ] );
		add_action( 'wp_ajax_dsu_fetch_facility_info', [ $this, 'ajax_fetch_facility_info' ] );
		add_action( 'wp_ajax_dsu_test_api_key', [ $this, 'ajax_test_api_key' ] );
		add_action( 'wp_ajax_dsu_test_v1_oauth', [ $this, 'ajax_test_v1_oauth' ] );
		add_action( 'wp_ajax_dsu_fetch_featured_features', [ $this, 'ajax_fetch_featured_features' ] );
		add_action( 'wp_ajax_dsu_set_default_config', [ $this, 'ajax_set_default_config' ] );
		add_action( 'wp_ajax_dsu_fetch_lead_sources', [ $this, 'ajax_fetch_lead_sources' ] );
		add_action( 'wp_ajax_dsu_activate_license',   [ $this, 'ajax_activate_license' ] );
		add_action( 'wp_ajax_dsu_deactivate_license', [ $this, 'ajax_deactivate_license' ] );
	}

	public function register_menu() {
		add_options_page(
			__( 'Dynamic Storage Units', 'dynamic-storage-units' ),
			__( 'Storage Units', 'dynamic-storage-units' ),
			'manage_options',
			'dynamic-storage-units',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'dsu_api_group', DSU_OPTION_API, [ $this, 'sanitize_api_settings' ] );
		register_setting( 'dsu_images_group', DSU_OPTION_IMAGES, [ $this, 'sanitize_group_mappings' ] );
		register_setting( 'dsu_configs_group', DSU_OPTION_CONFIGS, [ $this, 'sanitize_display_configs' ] );
		register_setting( 'dsu_categories_group', DSU_OPTION_CATEGORIES, [ $this, 'sanitize_size_categories' ] );
		register_setting( 'dsu_schema_group', DSU_OPTION_SCHEMA, [ $this, 'sanitize_schema_settings' ] );
		register_setting( 'dsu_feature_icons_group', DSU_OPTION_FEATURE_ICONS, [ $this, 'sanitize_feature_icons' ] );
		register_setting( 'dsu_unit_types_group', DSU_OPTION_UNIT_TYPES, [ $this, 'sanitize_unit_types' ] );
		register_setting( 'dsu_source_map_group', DSU_OPTION_SOURCE_MAP, [ $this, 'sanitize_source_map' ] );
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_dynamic-storage-units' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'dsu-frontend', DSU_PLUGIN_URL . 'assets/css/frontend.css', [], DSU_VERSION );
		wp_enqueue_style( 'dsu-admin', DSU_PLUGIN_URL . 'assets/css/admin.css', [ 'wp-color-picker', 'dsu-frontend' ], DSU_VERSION );
		wp_enqueue_script( 'dsu-admin', DSU_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery', 'media-upload', 'wp-color-picker' ], DSU_VERSION, true );
		$api_settings = get_option( DSU_OPTION_API, [] );
		wp_localize_script( 'dsu-admin', 'dsuAdmin', [
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'dsu_admin_nonce' ),
			'facilityCode' => sanitize_text_field( $api_settings['facility_code'] ?? '' ),
			'pluginUrl'    => DSU_PLUGIN_URL,
			'iconMap'      => DSU_Feature_Icons::js_icon_map(),
			'categories'   => dsu_get_size_categories(),
			'featureIcons' => get_option( DSU_OPTION_FEATURE_ICONS, [] ),
			'unitTypes'     => dsu_get_unit_types(),
			'defaultConfig' => get_option( DSU_OPTION_DEFAULT_CONFIG, '' ),
			'configs'       => get_option( DSU_OPTION_CONFIGS, [] ),
			'colors'        => [
				'primary'       => sanitize_hex_color( $api_settings['primary_color']       ?? '' ) ?: '#1a73e8',
				'primaryText'   => sanitize_hex_color( $api_settings['primary_text_color']  ?? '' ) ?: '#ffffff',
				'secondary'     => sanitize_hex_color( $api_settings['secondary_color']      ?? '' ) ?: '#1a73e8',
				'secondaryText' => sanitize_hex_color( $api_settings['secondary_text_color'] ?? '' ) ?: '#ffffff',
				'promoBar'      => sanitize_hex_color( $api_settings['promo_bar_color']      ?? '' ) ?: '',
			],
			'strings'      => [
				'selectImage'    => __( 'Select Image', 'dynamic-storage-units' ),
				'useImage'       => __( 'Use This Image', 'dynamic-storage-units' ),
				'confirmDelete'  => __( 'Delete this configuration?', 'dynamic-storage-units' ),
				'fetchingGroups' => __( 'Fetching groups…', 'dynamic-storage-units' ),
				'noGroups'       => __( 'No groups found.', 'dynamic-storage-units' ),
				'cacheBusted'    => __( 'Cache cleared.', 'dynamic-storage-units' ),
			],
		] );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = sanitize_key( $_GET['tab'] ?? 'api' );
		$tabs = [
			'api'            => __( 'Settings', 'dynamic-storage-units' ),
			'features'       => __( 'Feature Icons', 'dynamic-storage-units' ),
			'categories'     => __( 'Size Categories', 'dynamic-storage-units' ),
			'images'         => __( 'Unit Group Mapping', 'dynamic-storage-units' ),
			'configs'        => __( 'Display Configuration Builder', 'dynamic-storage-units' ),
			'saved'          => __( 'Saved Displays', 'dynamic-storage-units' ),
			'source-mapping' => __( 'Source Mapping', 'dynamic-storage-units' ),
			'schema'         => __( 'Schema Variables', 'dynamic-storage-units' ),
			'license'        => __( 'License', 'dynamic-storage-units' ),
		];
		?>
		<div class="wrap dsu-admin-wrap">
			<h1><?php esc_html_e( 'Dynamic Storage Units', 'dynamic-storage-units' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $tab ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="dsu-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'images':
						include DSU_PLUGIN_DIR . 'admin/views/settings-images.php';
						break;
					case 'configs':
						include DSU_PLUGIN_DIR . 'admin/views/settings-configs.php';
						break;
					case 'categories':
						include DSU_PLUGIN_DIR . 'admin/views/settings-categories.php';
						break;
					case 'saved':
						include DSU_PLUGIN_DIR . 'admin/views/saved-displays.php';
						break;
					case 'schema':
						include DSU_PLUGIN_DIR . 'admin/views/settings-schema.php';
						break;
					case 'features':
						include DSU_PLUGIN_DIR . 'admin/views/settings-features.php';
						break;
					case 'source-mapping':
						include DSU_PLUGIN_DIR . 'admin/views/settings-source-mapping.php';
						break;
					case 'license':
						include DSU_PLUGIN_DIR . 'admin/views/settings-license.php';
						break;
					default:
						include DSU_PLUGIN_DIR . 'admin/views/settings-api.php';
				}
				?>
			</div>
		</div>
		<?php
	}

	public function sanitize_api_settings( $input ) {
		DSU_Cache::clear_access_token();

		$primary        = sanitize_hex_color( $input['primary_color'] ?? '' );
		$secondary      = sanitize_hex_color( $input['secondary_color'] ?? '' );
		$primary_text   = sanitize_hex_color( $input['primary_text_color'] ?? '' );
		$secondary_text = sanitize_hex_color( $input['secondary_text_color'] ?? '' );
		$promo_bar      = sanitize_hex_color( $input['promo_bar_color'] ?? '' );

		return [
			'base_url'                 => esc_url_raw( $input['base_url'] ?? '' ),
			'company_code'             => sanitize_text_field( $input['company_code'] ?? '' ),
			'facility_code'            => sanitize_text_field( $input['facility_code'] ?? '' ),
			'client_id'                => sanitize_text_field( $input['client_id'] ?? '' ),
			'client_secret'            => sanitize_text_field( $input['client_secret'] ?? '' ),
			'api_key'                  => sanitize_text_field( $input['api_key'] ?? '' ),
			'cache_duration'           => absint( $input['cache_duration'] ?? 15 ),
			'primary_color'            => $primary ?: '',
			'primary_text_color'       => $primary_text ?: '',
			'secondary_color'          => $secondary ?: '',
			'secondary_text_color'     => $secondary_text ?: '',
			'promo_bar_color'          => $promo_bar ?: '',
			'show_size_tiles'          => ! empty( $input['show_size_tiles'] ) ? '1' : '',
			'unavailable_tile_handling'=> in_array( $input['unavailable_tile_handling'] ?? '', [ 'dim', 'hide' ], true )
				? $input['unavailable_tile_handling']
				: 'dim',
			'grouped_cta_text'         => sanitize_text_field( $input['grouped_cta_text'] ?? '' ),
			'good_label'               => sanitize_text_field( $input['good_label']       ?? '' ),
			'better_label'             => sanitize_text_field( $input['better_label']     ?? '' ),
			'best_label'               => sanitize_text_field( $input['best_label']       ?? '' ),
		];
	}

	/**
	 * Stores: { "group-uuid": { "label": "...", "image_url": "...", "features": [...] } }
	 */
	public function sanitize_group_mappings( $input ) {
		if ( ! is_array( $input ) ) {
			return [];
		}
		$clean = [];
		foreach ( $input as $group_id => $data ) {
			$gid = sanitize_text_field( $group_id );
			if ( empty( $gid ) ) {
				continue;
			}
			$clean[ $gid ] = [
				'label'         => sanitize_text_field( $data['label'] ?? '' ),
				'image_url'     => esc_url_raw( $data['image_url'] ?? '' ),
				'size_category' => sanitize_key( $data['size_category'] ?? '' ),
				'unit_type'     => sanitize_key( $data['unit_type'] ?? '' ),
			];
		}
		return $clean;
	}

	public function sanitize_display_configs( $input ) {
		if ( ! is_array( $input ) ) {
			return [];
		}
		$clean = [];
		foreach ( $input as $config ) {
			if ( empty( $config['name'] ) ) {
				continue;
			}
			$clean[] = [
				'name'              => sanitize_text_field( $config['name'] ),
				'facility_code'     => sanitize_text_field( $config['facility_code'] ?? '' ),
				'filter_label'      => sanitize_text_field( $config['filter_label'] ?? '' ),
				'filter_has_special' => ! empty( $config['filter_has_special'] ) ? 1 : 0,
				'max_units'          => absint( $config['max_units'] ?? 0 ),
				'sort'               => sanitize_key( $config['sort'] ?? '' ),
				'display_format'           => in_array( $config['display_format'] ?? '', [ 'grid', 'list' ], true ) ? $config['display_format'] : 'grid',
				'show_size_tiles'          => ! empty( $config['show_size_tiles'] ) ? 1 : 0,
				'unavailable_tile_handling'=> in_array( $config['unavailable_tile_handling'] ?? '', [ 'dim', 'hide' ], true ) ? $config['unavailable_tile_handling'] : 'dim',
				'tiles_alignment'          => in_array( $config['tiles_alignment'] ?? '', [ 'left', 'center' ], true ) ? $config['tiles_alignment'] : 'left',
				'hide_tiles_mobile'        => ! empty( $config['hide_tiles_mobile'] ) ? 1 : 0,
				'feature_tag_size'         => in_array( $config['feature_tag_size'] ?? '', [ 'sm', 'md', 'lg' ], true ) ? $config['feature_tag_size'] : 'sm',
				'show_promo_bar'           => ! empty( $config['show_promo_bar'] ) ? 1 : 0,
				'hide_promo_mobile'        => ! empty( $config['hide_promo_mobile'] ) ? 1 : 0,
				'soldout_handling'         => in_array( $config['soldout_handling'] ?? '', [ 'hide', 'waitlist' ], true )
					? $config['soldout_handling']
					: 'hide',
				'waitlist_email'    => sanitize_email( $config['waitlist_email'] ?? '' ),
				'waitlist_subject'  => sanitize_text_field( $config['waitlist_subject'] ?? '' ),
				'waitlist_message'  => wp_kses_post( $config['waitlist_message'] ?? '' ),
				'show_unit_type_filter' => ! empty( $config['show_unit_type_filter'] ) ? 1 : 0,
				'unit_type_filter_size' => in_array( $config['unit_type_filter_size'] ?? 'md', [ 'sm', 'md', 'lg' ], true ) ? $config['unit_type_filter_size'] : 'md',
				'unit_type_all_label'   => sanitize_text_field( $config['unit_type_all_label'] ?? '' ),
				'hide_utf_mobile'       => ! empty( $config['hide_utf_mobile'] ) ? 1 : 0,
				'hide_all_tile'         => ! empty( $config['hide_all_tile'] ) ? 1 : 0,
				'img_size'      => in_array( $config['img_size']      ?? 'md', [ 'sm', 'md', 'lg' ], true ) ? $config['img_size']      : 'md',
				'title_size'    => in_array( $config['title_size']    ?? 'md', [ 'sm', 'md', 'lg' ], true ) ? $config['title_size']    : 'md',
				'special_size'  => in_array( $config['special_size']  ?? 'md', [ 'sm', 'md', 'lg' ], true ) ? $config['special_size']  : 'md',
				'price_size'    => in_array( $config['price_size']    ?? 'md', [ 'sm', 'md', 'lg' ], true ) ? $config['price_size']    : 'md',
				'scarcity_size' => in_array( $config['scarcity_size'] ?? 'md', [ 'sm', 'md', 'lg' ], true ) ? $config['scarcity_size'] : 'md',
			];
		}
		return $clean;
	}

	public function sanitize_size_categories( $input ) {
		if ( ! is_array( $input ) ) {
			return [];
		}
		$clean = [];
		$i     = 0;
		foreach ( $input as $cat ) {
			$slug = sanitize_key( $cat['slug'] ?? '' );
			if ( empty( $slug ) ) {
				continue;
			}
			$clean[] = [
				'slug'        => $slug,
				'label'       => sanitize_text_field( $cat['label'] ?? '' ),
				'min_sqft'    => absint( $cat['min_sqft'] ?? 0 ),
				'max_sqft'    => absint( $cat['max_sqft'] ?? 0 ),
				'description' => sanitize_text_field( $cat['description'] ?? '' ),
				'unit_type'   => sanitize_key( $cat['unit_type'] ?? '' ),
				'sort_order'  => absint( $cat['sort_order'] ?? $i ),
			];
			$i++;
		}
		usort( $clean, function ( $a, $b ) {
			$cmp = $a['sort_order'] <=> $b['sort_order'];
			return $cmp !== 0 ? $cmp : ( $a['min_sqft'] <=> $b['min_sqft'] );
		} );
		return $clean;
	}

	public function ajax_fetch_unit_groups() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$facility_code = sanitize_text_field( $_POST['facility_code'] ?? '' );
		if ( empty( $facility_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Facility Code is required.', 'dynamic-storage-units' ) ] );
		}

		$api    = new DSU_API();
		$groups = $api->get_unit_groups( $facility_code );

		if ( is_wp_error( $groups ) ) {
			wp_send_json_error( [ 'message' => $groups->get_error_message() ] );
		}

		$v1_groups = $api->get_v1_unit_groups( $facility_code );
		$v1_lookup = [];
		if ( ! is_wp_error( $v1_groups ) && is_array( $v1_groups ) ) {
			foreach ( $v1_groups as $v1g ) {
				$vid = $v1g['unitGroupId'] ?? $v1g['id'] ?? '';
				if ( $vid ) {
					$v1_lookup[ $vid ] = $v1g;
				}
			}
		}

		wp_send_json_success( [ 'groups' => $groups, 'v1_lookup' => $v1_lookup ] );
	}

	public function ajax_bust_cache() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$settings      = get_option( DSU_OPTION_API, [] );
		$company_code  = sanitize_text_field( $settings['company_code'] ?? '' );
		$facility_code = sanitize_text_field( $_POST['facility_code'] ?? '' );

		if ( $company_code && $facility_code ) {
			DSU_Cache::bust_facility( $company_code, $facility_code );
		}

		wp_send_json_success();
	}

	/** Create one or more new configs (from the builder). */
	public function ajax_create_configs() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$raw     = $_POST[ DSU_OPTION_CONFIGS ] ?? [];
		$new     = $this->sanitize_display_configs( $raw );
		$existing = get_option( DSU_OPTION_CONFIGS, [] );

		$existing_names = array_column( $existing, 'name' );
		$added = 0;
		foreach ( $new as $cfg ) {
			if ( ! in_array( $cfg['name'], $existing_names, true ) ) {
				$existing[]       = $cfg;
				$existing_names[] = $cfg['name'];
				$added++;
			}
		}

		if ( $added === 0 ) {
			wp_send_json_error( [ 'message' => __( 'No new configurations to save (name already exists or is blank).', 'dynamic-storage-units' ) ] );
		}

		update_option( DSU_OPTION_CONFIGS, $existing );
		wp_send_json_success( [ 'message' => sprintf( _n( '%d configuration created.', '%d configurations created.', $added, 'dynamic-storage-units' ), $added ) ] );
	}

	/** Update a single existing config (from Saved Displays inline edit). */
	public function ajax_update_config() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$original_name = sanitize_text_field( $_POST['original_name'] ?? '' );
		$raw           = $_POST['dsu_edit_config'] ?? [];
		$updated       = $this->sanitize_display_configs( [ $raw ] );

		if ( empty( $updated[0] ) || empty( $updated[0]['name'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid configuration data.', 'dynamic-storage-units' ) ] );
		}

		$all  = get_option( DSU_OPTION_CONFIGS, [] );
		$found = false;
		foreach ( $all as &$cfg ) {
			if ( $cfg['name'] === $original_name ) {
				$cfg   = $updated[0];
				$found = true;
				break;
			}
		}
		unset( $cfg );

		if ( ! $found ) {
			wp_send_json_error( [ 'message' => __( 'Configuration not found.', 'dynamic-storage-units' ) ] );
		}

		update_option( DSU_OPTION_CONFIGS, $all );
		wp_send_json_success( [ 'message' => __( 'Configuration saved.', 'dynamic-storage-units' ), 'new_name' => $updated[0]['name'] ] );
	}

	public function sanitize_schema_settings( $input ) {
		$days  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
		$hours = [];
		foreach ( $days as $day ) {
			$day_data       = $input['hours'][ $day ] ?? [];
			$hours[ $day ] = [
				'enabled' => ! empty( $day_data['enabled'] ) ? 1 : 0,
				'opens'   => sanitize_text_field( $day_data['opens']  ?? '09:00' ),
				'closes'  => sanitize_text_field( $day_data['closes'] ?? '18:00' ),
			];
		}

		$payment_options  = [ 'Cash', 'Check', 'Credit Card', 'Debit Card', 'ACH Transfer', 'Money Order' ];
		$payment_accepted = [];
		foreach ( (array) ( $input['payment_accepted'] ?? [] ) as $p ) {
			if ( in_array( $p, $payment_options, true ) ) {
				$payment_accepted[] = $p;
			}
		}

		return [
			'enabled'          => ! empty( $input['enabled'] ) ? '1' : '',
			'name'             => sanitize_text_field( $input['name'] ?? '' ),
			'telephone'        => sanitize_text_field( $input['telephone'] ?? '' ),
			'url'              => esc_url_raw( $input['url'] ?? '' ),
			'description'      => sanitize_textarea_field( $input['description'] ?? '' ),
			'image_url'        => esc_url_raw( $input['image_url'] ?? '' ),
			'street_address'   => sanitize_text_field( $input['street_address'] ?? '' ),
			'address_locality' => sanitize_text_field( $input['address_locality'] ?? '' ),
			'address_region'   => sanitize_text_field( $input['address_region'] ?? '' ),
			'postal_code'      => sanitize_text_field( $input['postal_code'] ?? '' ),
			'address_country'  => sanitize_text_field( $input['address_country'] ?? 'US' ),
			'latitude'         => sanitize_text_field( $input['latitude'] ?? '' ),
			'longitude'        => sanitize_text_field( $input['longitude'] ?? '' ),
			'hours'            => $hours,
			'payment_accepted' => $payment_accepted,
			'schema_id'        => esc_url_raw( $input['schema_id'] ?? '' ),
		];
	}

	public function ajax_fetch_facility_info() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$api_settings  = get_option( DSU_OPTION_API, [] );
		$facility_code = sanitize_text_field( $api_settings['facility_code'] ?? '' );

		if ( empty( $facility_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Facility code is not configured in API Settings.', 'dynamic-storage-units' ) ] );
		}

		$api    = new DSU_API();
		$result = $api->get_facility_info( $facility_code );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// v1 address uses street1; lat/lng nested inside address object
		$address = $result['address'] ?? $result['physicalAddress'] ?? $result['mailingAddress'] ?? [];

		// v1 phoneNumber is an object: { countryCode: '+1', number: '9075551234' }
		$phone_obj = $result['phoneNumber'] ?? null;
		$telephone = '';
		if ( is_array( $phone_obj ) ) {
			$cc        = trim( $phone_obj['countryCode'] ?? '' );
			$num       = trim( $phone_obj['number'] ?? '' );
			$telephone = $cc ? $cc . $num : $num;
		} elseif ( is_string( $phone_obj ) ) {
			$telephone = $phone_obj;
		}
		if ( empty( $telephone ) ) {
			$telephone = $result['phone'] ?? $result['telephone'] ?? '';
		}

		// Geo — v1 nests latitude/longitude inside the address object
		$latitude  = $address['latitude']  ?? $address['lat']  ?? $result['latitude']  ?? '';
		$longitude = $address['longitude'] ?? $address['lng']  ?? $result['longitude'] ?? '';

		// Amenity boolean flags → human-readable labels (Appendix A, exact camelCase property names)
		$amenity_flag_map = [
			// Access & security
			'electronicGateAccess'             => 'Electronic Gate Access',
			'fencedAndLighted'                 => 'Fenced and Lighted',
			'videoCamerasOnSite'               => 'Video Cameras on Site',
			'is24HourAccessAvailable'          => '24-Hour Access',
			'is24HourKioskAvailable'           => '24-Hour Kiosk',
			'is24HourManagerAvailable'         => '24-Hour Manager',
			// Loading & facility
			'hasElevator'                      => 'Elevator',
			'hasLoadingDock'                   => 'Loading Dock',
			'availableHandcartsOrDollies'      => 'Handcarts / Dollies',
			'mailOrPackagesAcceptedForTenants' => 'Mail / Package Acceptance',
			// Moving
			'availableTruckRental'             => 'Truck Rental',
			'availableFreeTruckRental'         => 'Free Truck Rental',
			'movingSuppliesForSale'            => 'Moving Supplies for Sale',
			// Billing & discounts
			'emailInvoicingAvailable'          => 'Email Invoicing',
			'automaticPaymentsAvailable'       => 'Automatic Payments',
			'paperworkCanBeDoneRemotely'       => 'Remote Paperwork',
			'militaryDiscountsOffered'         => 'Military Discount',
			'seniorDiscountOffered'            => 'Senior Discount',
			'studentDiscountOffered'           => 'Student Discount',
			// Insurance
			'insuranceAvailable'               => 'Insurance Available',
			'protectionPlanAvailable'          => 'Protection Plan Available',
			// Vehicle-specific
			'washStationAvailable'             => 'Wash Station',
			'dumpStationAvailable'             => 'Dump Station',
			'maintenanceAllowedOnProperty'     => 'Maintenance Allowed',
			'hasAirPump'                       => 'Air Pump',
			'hasVacuumStation'                 => 'Vacuum Station',
			'hasIceMachine'                    => 'Ice Machine',
			'hoseOrSpigotAvailable'            => 'Hose / Spigot',
			'propane'                          => 'Propane',
			'dieselAndGas'                     => 'Diesel & Gas',
			'generalMaintenance'               => 'General Maintenance',
			'doesStateInspections'             => 'State Inspections',
			'autoCleaningOrDetailing'          => 'Auto Cleaning / Detailing',
			'bandPracticeAllowed'              => 'Band Practice Allowed',
		];
		// Amenity flags live in the nested 'amenities' object (Appendix A)
		$amenity_src = ( ! empty( $result['amenities'] ) && is_array( $result['amenities'] ) )
			? $result['amenities']
			: $result;
		$amenities = [];
		foreach ( $amenity_flag_map as $flag => $label ) {
			if ( ! empty( $amenity_src[ $flag ] ) ) {
				$amenities[] = $label;
			}
		}

		// Payment flags are also inside the amenities object per Appendix A
		$payment_accepted = [];
		if ( ! empty( $amenity_src['cashAccepted'] ) )  $payment_accepted[] = 'Cash';
		if ( ! empty( $amenity_src['checkAccepted'] ) ) $payment_accepted[] = 'Check';
		$cc_types = $amenity_src['creditCardTypesAccepted'] ?? $result['creditCardTypesAccepted'] ?? [];
		if ( is_array( $cc_types ) && ! empty( $cc_types ) ) {
			$payment_accepted[] = 'Credit Card';
		}

		wp_send_json_success( [
			'name'             => sanitize_text_field( $result['name'] ?? $result['facilityName'] ?? '' ),
			'telephone'        => sanitize_text_field( $telephone ),
			'street_address'   => sanitize_text_field( $address['street1'] ?? $address['street'] ?? $address['streetAddress'] ?? $address['address1'] ?? '' ),
			'address_locality' => sanitize_text_field( $address['city'] ?? $address['addressLocality'] ?? '' ),
			'address_region'   => sanitize_text_field( $address['state'] ?? $address['addressRegion'] ?? '' ),
			'postal_code'      => sanitize_text_field( $address['zip'] ?? $address['postalCode'] ?? $address['zipCode'] ?? '' ),
			'address_country'  => sanitize_text_field( $address['country'] ?? $address['countryCode'] ?? 'US' ),
			'latitude'         => (string) $latitude,
			'longitude'        => (string) $longitude,
			'amenities'        => $amenities,
			'payment_accepted' => $payment_accepted,
			'raw'              => $result,
		] );
	}

	public function ajax_test_api_key() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$settings      = get_option( DSU_OPTION_API, [] );
		$base_url      = rtrim( sanitize_text_field( $settings['base_url'] ?? '' ), '/' );
		$company_code  = sanitize_text_field( $settings['company_code'] ?? '' );
		$facility_code = sanitize_text_field( $settings['facility_code'] ?? '' );
		$api_key       = sanitize_text_field( $settings['api_key'] ?? '' );

		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'No API key saved yet. Add it in the Settings tab and save first.', 'dynamic-storage-units' ) ] );
		}
		if ( empty( $base_url ) || empty( $company_code ) || empty( $facility_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Base URL, Company Code, and Facility Code must be configured in the Settings tab.', 'dynamic-storage-units' ) ] );
		}

		$base_endpoint = $base_url . '/api/v1/companies/' . rawurlencode( $company_code )
			. '/facilities/' . rawurlencode( $facility_code );

		// Try every common API key format and report all results so we can see which one works.
		$attempts = [
			[
				'label'   => 'Authorization: Bearer {key}',
				'url'     => $base_endpoint,
				'headers' => [ 'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
			],
			[
				'label'   => 'Authorization: ApiKey {key}',
				'url'     => $base_endpoint,
				'headers' => [ 'Accept' => 'application/json', 'Authorization' => 'ApiKey ' . $api_key ],
			],
			[
				'label'   => 'X-Api-Key: {key} header',
				'url'     => $base_endpoint,
				'headers' => [ 'Accept' => 'application/json', 'X-Api-Key' => $api_key ],
			],
			[
				'label'   => 'Query param ?api_key={key}',
				'url'     => add_query_arg( 'api_key', $api_key, $base_endpoint ),
				'headers' => [ 'Accept' => 'application/json' ],
			],
			[
				'label'   => 'Query param ?apiKey={key}',
				'url'     => add_query_arg( 'apiKey', $api_key, $base_endpoint ),
				'headers' => [ 'Accept' => 'application/json' ],
			],
		];

		$results = [];
		foreach ( $attempts as $attempt ) {
			$response = wp_remote_get( $attempt['url'], [
				'timeout' => 15,
				'headers' => $attempt['headers'],
			] );

			$status  = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
			$body    = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
			$decoded = json_decode( $body, true );
			$display = ( json_last_error() === JSON_ERROR_NONE )
				? json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
				: $body;

			$results[] = [
				'label'   => $attempt['label'],
				'status'  => $status,
				'success' => $status >= 200 && $status < 300,
				'display' => $display,
			];
		}

		wp_send_json_success( [ 'results' => $results ] );
	}

	public function ajax_test_v1_oauth() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$settings      = get_option( DSU_OPTION_API, [] );
		$base_url      = rtrim( sanitize_text_field( $settings['base_url'] ?? '' ), '/' );
		$company_code  = sanitize_text_field( $settings['company_code'] ?? '' );
		$facility_code = sanitize_text_field( $settings['facility_code'] ?? '' );

		if ( empty( $base_url ) || empty( $company_code ) || empty( $facility_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Base URL, Company Code, and Facility Code must be configured in the Settings tab.', 'dynamic-storage-units' ) ] );
		}

		// Reuse the existing OAuth flow — get the same bearer token that already works for v2
		$api   = new DSU_API();
		$token = $api->get_access_token();

		if ( is_wp_error( $token ) ) {
			wp_send_json_error( [ 'message' => $token->get_error_message() ] );
		}

		// Try the same facility endpoint but with v1 instead of v2
		$url      = $base_url . '/api/v1/companies/' . rawurlencode( $company_code )
			. '/facilities/' . rawurlencode( $facility_code );
		$response = wp_remote_get( $url, [
			'timeout' => 15,
			'headers' => [
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			],
		] );

		$status  = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$body    = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );
		$display = ( json_last_error() === JSON_ERROR_NONE )
			? json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			: $body;

		wp_send_json_success( [
			'url'     => $url,
			'status'  => $status,
			'success' => $status >= 200 && $status < 300,
			'display' => $display,
		] );
	}

	public function ajax_fetch_featured_features() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$api_settings  = get_option( DSU_OPTION_API, [] );
		$facility_code = sanitize_text_field( $api_settings['facility_code'] ?? '' );
		if ( empty( $facility_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Facility code is not configured in API Settings.', 'dynamic-storage-units' ) ] );
		}

		// Always fetch fresh data on explicit refresh — bust stale cached v1 groups first
		$company_code = sanitize_text_field( $api_settings['company_code'] ?? '' );
		if ( $company_code && $facility_code ) {
			DSU_Cache::bust_facility( $company_code, $facility_code );
		}

		$api    = new DSU_API();
		$groups = $api->get_v1_unit_groups( $facility_code );
		if ( is_wp_error( $groups ) ) {
			wp_send_json_error( [ 'message' => $groups->get_error_message() ] );
		}

		$all_features = [];
		foreach ( $groups as $group ) {
			$ff = $group['featuredFeatures'] ?? [];
			if ( is_array( $ff ) ) {
				foreach ( $ff as $feat ) {
					$feat = sanitize_text_field( (string) $feat );
					if ( $feat !== '' && ! in_array( $feat, $all_features, true ) ) {
						$all_features[] = $feat;
					}
				}
			}
		}
		sort( $all_features );

		$saved_map = get_option( DSU_OPTION_FEATURE_ICONS, [] );

		wp_send_json_success( [ 'features' => $all_features, 'saved' => $saved_map ] );
	}

	public function sanitize_feature_icons( $input ) {
		if ( ! is_array( $input ) ) {
			return [];
		}
		$clean = [];
		foreach ( $input as $feature => $emoji ) {
			$feature = sanitize_text_field( (string) $feature );
			$emoji   = sanitize_text_field( (string) $emoji );
			if ( $feature !== '' ) {
				$clean[ $feature ] = $emoji;
			}
		}
		return $clean;
	}

	public function ajax_set_default_config() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}
		$name = sanitize_text_field( $_POST['config_name'] ?? '' );
		update_option( DSU_OPTION_DEFAULT_CONFIG, $name );
		wp_send_json_success( [ 'config_name' => $name ] );
	}

	public function sanitize_unit_types( $input ) {
		if ( ! is_array( $input ) ) {
			return dsu_get_unit_types();
		}
		$clean = [];
		foreach ( $input as $ut ) {
			$slug  = sanitize_key( $ut['slug'] ?? '' );
			$label = sanitize_text_field( $ut['label'] ?? '' );
			if ( $slug && $label ) {
				$clean[] = [ 'slug' => $slug, 'label' => $label ];
			}
		}
		return $clean ?: dsu_get_unit_types();
	}

	public function ajax_fetch_lead_sources() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$api_settings  = get_option( DSU_OPTION_API, [] );
		$facility_code = sanitize_text_field( $api_settings['facility_code'] ?? '' );

		if ( empty( $facility_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Facility code is not configured in API Settings.', 'dynamic-storage-units' ) ] );
		}

		$api      = new DSU_API();
		$response = $api->get_lead_sources( $facility_code );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => $response->get_error_message() ] );
		}

		// Handle camelCase and PascalCase response keys
		$raw = $response['results'] ?? $response['Results'] ?? null;

		if ( $raw === null ) {
			// Unexpected structure — report top-level keys so we can diagnose
			$keys = is_array( $response ) ? implode( ', ', array_keys( $response ) ) : gettype( $response );
			wp_send_json_error( [
				'message' => sprintf(
					/* translators: %s: list of JSON keys returned */
					__( 'Unexpected response format from API. Keys received: %s', 'dynamic-storage-units' ),
					$keys
				),
			] );
		}

		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		$sources = [];
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id   = sanitize_text_field( $item['id']   ?? $item['Id']   ?? '' );
			$name = sanitize_text_field( $item['name'] ?? $item['Name'] ?? '' );
			if ( $id && $name ) {
				$sources[] = [ 'id' => $id, 'name' => $name ];
			}
		}

		// If items came back but nothing matched, report the actual keys so we can fix parsing
		if ( empty( $sources ) && ! empty( $raw ) ) {
			$first_keys = is_array( $raw[0] ?? null ) ? implode( ', ', array_keys( $raw[0] ) ) : 'N/A';
			wp_send_json_error( [
				'message' => sprintf(
					/* translators: 1: item count, 2: field names */
					__( 'API returned %1$d item(s) but could not parse them. Field names found: %2$s', 'dynamic-storage-units' ),
					count( $raw ),
					$first_keys
				),
			] );
		}

		// Cache the list in the option so it persists for the dropdown on next page load
		$current                 = get_option( DSU_OPTION_SOURCE_MAP, [] );
		$current['lead_sources'] = $sources;
		update_option( DSU_OPTION_SOURCE_MAP, $current );

		wp_send_json_success( [ 'sources' => $sources ] );
	}

	public function sanitize_source_map( $input ) {
		// Preserve the cached lead_sources list when saving fallback_id via settings form
		$current = get_option( DSU_OPTION_SOURCE_MAP, [] );
		return [
			'fallback_id'  => sanitize_text_field( $input['fallback_id'] ?? '' ),
			'lead_sources' => $current['lead_sources'] ?? [],
		];
	}

	public function ajax_activate_license() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}
		$key = sanitize_text_field( $_POST['license_key'] ?? '' );
		if ( ! $key ) {
			wp_send_json_error( [ 'message' => 'License key is required.' ] );
		}
		$result = DSU_License::activate( $key );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	public function ajax_deactivate_license() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}
		$result = DSU_License::deactivate();
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/** Delete a single config by name. */
	public function ajax_delete_config() {
		check_ajax_referer( 'dsu_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$name = sanitize_text_field( $_POST['config_name'] ?? '' );
		if ( empty( $name ) ) {
			wp_send_json_error( [ 'message' => __( 'No name provided.', 'dynamic-storage-units' ) ] );
		}

		$all     = get_option( DSU_OPTION_CONFIGS, [] );
		$filtered = array_filter( $all, fn( $c ) => ( $c['name'] ?? '' ) !== $name );
		update_option( DSU_OPTION_CONFIGS, array_values( $filtered ) );
		wp_send_json_success();
	}
}
