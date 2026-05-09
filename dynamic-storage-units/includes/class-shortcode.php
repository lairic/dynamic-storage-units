<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSU_Shortcode {

	public function __construct() {
		add_shortcode( 'storage_units', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets() {
		wp_register_style( 'dsu-frontend', DSU_PLUGIN_URL . 'assets/css/frontend.css', [], DSU_VERSION );
		wp_register_script( 'dsu-frontend', DSU_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], DSU_VERSION, true );
		wp_localize_script( 'dsu-frontend', 'dsuData', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'dsu_waitlist_nonce' ),
		] );
	}

	public function render( $atts ) {
		// Never run API calls during REST or AJAX context — prevents JSON corruption on page save.
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
			return '<div class="dsu-placeholder">[' . esc_html__( 'Storage units display', 'dynamic-storage-units' ) . ']</div>';
		}

		$atts = shortcode_atts( [ 'config' => '', 'debug' => '0', 'show_promo_bar' => '' ], $atts, 'storage_units' );

		$config_name = sanitize_text_field( $atts['config'] );
		$debug       = current_user_can( 'manage_options' ) && $atts['debug'] === '1';
		$config      = $this->get_config( $config_name );

		if ( ! $config ) {
			return '<p>' . esc_html__( 'Storage units configuration not found.', 'dynamic-storage-units' ) . '</p>';
		}

		wp_enqueue_style( 'dsu-frontend' );
		wp_enqueue_script( 'dsu-frontend' );

		// Inject custom button colors as CSS custom properties
		$api_settings   = get_option( DSU_OPTION_API, [] );
		$primary        = sanitize_hex_color( $api_settings['primary_color'] ?? '' );
		$secondary      = sanitize_hex_color( $api_settings['secondary_color'] ?? '' );
		$primary_text   = sanitize_hex_color( $api_settings['primary_text_color'] ?? '' );
		$secondary_text = sanitize_hex_color( $api_settings['secondary_text_color'] ?? '' );
		$promo_color    = sanitize_hex_color( $api_settings['promo_bar_color'] ?? '' );
		if ( $primary || $secondary || $primary_text || $secondary_text || $promo_color ) {
			$css = ':root{';
			if ( $primary )        $css .= '--dsu-primary:' . $primary . ';';
			if ( $secondary )      $css .= '--dsu-secondary:' . $secondary . ';';
			if ( $primary_text )   $css .= '--dsu-primary-text:' . $primary_text . ';';
			if ( $secondary_text ) $css .= '--dsu-secondary-text:' . $secondary_text . ';';
			if ( $promo_color ) {
				// Derive background tints and a darker hover/text shade from the accent color.
				$hex = ltrim( $promo_color, '#' );
				$r   = hexdec( substr( $hex, 0, 2 ) );
				$g   = hexdec( substr( $hex, 2, 2 ) );
				$b   = hexdec( substr( $hex, 4, 2 ) );
				// Backgrounds: mix accent with white at increasing saturation
				$bg        = sprintf( '#%02x%02x%02x', (int) round( $r*.10+255*.90 ), (int) round( $g*.10+255*.90 ), (int) round( $b*.10+255*.90 ) );
				$bg_hover  = sprintf( '#%02x%02x%02x', (int) round( $r*.16+255*.84 ), (int) round( $g*.16+255*.84 ), (int) round( $b*.16+255*.84 ) );
				$bg_active = sprintf( '#%02x%02x%02x', (int) round( $r*.24+255*.76 ), (int) round( $g*.24+255*.76 ), (int) round( $b*.24+255*.76 ) );
				// Hover/text border: darken accent by subtracting a fixed offset per channel
				$border_hover = sprintf( '#%02x%02x%02x', max( 0, $r - 45 ), max( 0, $g - 45 ), max( 0, $b - 45 ) );
				// Text: darken to ~55% of original for readability on light background
				$text = sprintf( '#%02x%02x%02x', (int) round( $r*.55 ), (int) round( $g*.55 ), (int) round( $b*.55 ) );
				$css .= '--dsu-promo-border:'     . $promo_color . ';';
				$css .= '--dsu-promo-border-hover:' . $border_hover . ';';
				$css .= '--dsu-promo-bg:'         . $bg . ';';
				$css .= '--dsu-promo-bg-hover:'   . $bg_hover . ';';
				$css .= '--dsu-promo-bg-active:'  . $bg_active . ';';
				$css .= '--dsu-promo-text:'       . $text . ';';
			}
			$css .= '}';
			wp_add_inline_style( 'dsu-frontend', $css );
		}

		$unit_types            = dsu_get_unit_types();
		$show_unit_type_filter = ! empty( $config['show_unit_type_filter'] );

		// Fall back to the global facility code from API settings if config doesn't specify one
		$api_settings  = get_option( DSU_OPTION_API, [] );
		$facility_code = sanitize_text_field(
			! empty( $config['facility_code'] ) ? $config['facility_code'] : ( $api_settings['facility_code'] ?? '' )
		);
		$api = new DSU_API();

		// Fetch unit groups
		$groups = $api->get_unit_groups( $facility_code );

		if ( is_wp_error( $groups ) ) {
			$out = '';
			if ( $debug ) {
				$out .= '<div style="background:#f8f8f8;border:2px solid #e00;padding:16px;margin:16px 0;font-family:monospace;font-size:12px;">';
				$out .= '<strong>DSU Debug v2.0.5 — API Error</strong><br>';
				$out .= 'Facility Code: <code>' . esc_html( $facility_code ) . '</code><br>';
				$out .= 'Error: <code>' . esc_html( $groups->get_error_message() ) . '</code>';
				$out .= '</div>';
			}
			$out .= '<p>' . esc_html__( 'Unable to load storage units at this time.', 'dynamic-storage-units' ) . '</p>';
			return $out;
		}

		// WordPress-managed data + v1 overlay — must run before CTA loop
		$group_map = get_option( DSU_OPTION_IMAGES, [] );
		$v1_data   = $this->build_v1_feature_map( $api, $facility_code );
		foreach ( $v1_data as $gid => $v1_group ) {
			if ( ! isset( $group_map[ $gid ] ) ) {
				$group_map[ $gid ] = [];
			}
			foreach ( $v1_group as $key => $value ) {
				// Preserve admin-set unit_type — v1 API uses different casing/values
				// that won't match the lowercase slugs used by the filter buttons.
				if ( $key === 'unit_type' ) {
					if ( empty( $group_map[ $gid ]['unit_type'] ) ) {
						$group_map[ $gid ]['unit_type'] = strtolower( $value );
					}
					continue;
				}
				$group_map[ $gid ][ $key ] = $value;
			}
		}

		// Fetch CTA URLs via v2 — these endpoints return an error when rent/reserve is disabled,
		// which is the correct availability gate. v1 onlineMoveInUrl is always populated.
		foreach ( $groups as &$group ) {
			$gid = $group['id'] ?? '';
			if ( ! $gid ) {
				continue;
			}
			$move_in_url           = $api->get_move_in_url( $facility_code, $gid );
			$group['_move_in_url'] = is_wp_error( $move_in_url ) ? '' : $move_in_url;
			$reserve_url           = $api->get_reserve_url( $facility_code, $gid );
			$group['_reserve_url'] = is_wp_error( $reserve_url ) ? '' : $reserve_url;
		}
		unset( $group );

		// Apply filter → sort (limit is applied to display_units below)
		$groups_before_filter = count( $groups );
		$groups = $this->apply_filters_config( $groups, $config );
		$groups = $this->apply_sorting( $groups, $config );

		// Build display units (single cards + grouped tier cards)
		$display_units = $this->build_display_units( $groups, $group_map, $config, $api_settings );

		// Apply limit to displayed cards
		$max = isset( $config['max_units'] ) ? absint( $config['max_units'] ) : 0;
		if ( $max > 0 ) {
			$display_units = array_slice( $display_units, 0, $max );
		}

		// Promo bar: enabled via shortcode attribute OR display config checkbox
		$show_promo_bar = $atts['show_promo_bar'] === 'true' || ! empty( $config['show_promo_bar'] );
		$promo_data     = $show_promo_bar ? $this->build_promo_bar_data( $groups ) : null;
		$promo_label    = $promo_data ? $promo_data['label'] : '';

		if ( $debug ) {
			$pre_style = 'overflow:auto;max-height:300px;background:#fff;border:1px solid #ddd;padding:8px;margin:4px 0 0;font-size:11px;white-space:pre;';
			$h_style   = 'margin:14px 0 4px;font-weight:bold;font-size:12px;border-bottom:1px solid #c00;padding-bottom:2px;color:#c00;';
			$td_style  = 'padding:3px 6px;border-bottom:1px solid #eee;vertical-align:top;';

			// Fetch all endpoints (all are cached — no extra HTTP cost)
			$diag_v2_all    = $api->get_unit_groups( $facility_code );
			$diag_v1_groups = $api->get_v1_unit_groups( $facility_code );
			$diag_v1_fac    = $api->get_facility_info( $facility_code );
			$diag_lead_src  = $api->get_lead_sources( $facility_code );
			$diag_res_set   = $api->get_reservation_settings( $facility_code );

			// Build v1 lookup by unitGroupId for matching table
			$v1_diag_lookup = [];
			if ( ! is_wp_error( $diag_v1_groups ) && is_array( $diag_v1_groups ) ) {
				foreach ( $diag_v1_groups as $v1g ) {
					$vid = $v1g['unitGroupId'] ?? $v1g['id'] ?? '';
					if ( $vid ) {
						$v1_diag_lookup[ $vid ] = $v1g;
					}
				}
			}

			$debug_out  = '<div id="dsu-debug-wrap" style="background:#f8f8f8;border:2px solid #e00;padding:16px;margin:16px 0;font-family:monospace;font-size:12px;box-sizing:border-box;">';
			$debug_out .= '<strong style="font-size:14px;">DSU Debug v2.0.5</strong> &nbsp; Facility: <code>' . esc_html( $facility_code ) . '</code><br>';
			$debug_out .= '<button onclick="(function(){var el=document.getElementById(\'dsu-diag-json\');if(!el){alert(\'DSU: diagnostic element not found\');return;}var j=el.textContent||el.innerText;var b=new Blob([j],{type:\'application/json\'});var a=document.createElement(\'a\');a.href=URL.createObjectURL(b);a.download=\'dsu-diagnostic.json\';document.body.appendChild(a);a.click();document.body.removeChild(a);})();" style="margin:8px 0 4px;padding:4px 12px;background:#c00;color:#fff;border:none;cursor:pointer;font-family:monospace;font-size:12px;">&#x2B07; Download JSON Diagnostic</button>';

			// ---- ENDPOINT INVENTORY ----
			$debug_out .= '<p style="' . $h_style . '">ENDPOINT INVENTORY</p>';
			$endpoints = [
				[ 'POST', '/api/v2/login',                                                              'OAuth2 bearer token (clientId, clientSecret)' ],
				[ 'GET',  '/api/v1/companies/{co}/facilities/{fac}',                                    'Facility info — name, address, phone, amenities (Appendix A flags)' ],
				[ 'GET',  '/api/v2/companies/{co}/facilities/{fac}/unit-groups',                        'v2 unit groups — id, label, streetRate, availableTotal, availableSpecial' ],
				[ 'GET',  '/api/v1/companies/{co}/facilities/{fac}/unit-groups',                        'v1 unit groups — unitGroupId, name, features{PascalCase}, featuredFeatures[], areaInSquareFeet' ],
				[ 'GET',  '/api/v2/companies/{co}/facilities/{fac}/unit-groups/{id}/move-in-url',       'Move-in deep link URL' ],
				[ 'GET',  '/api/v2/companies/{co}/facilities/{fac}/unit-groups/{id}/reserve-url',       'Reservation deep link URL' ],
				[ 'GET',  '/api/v2/companies/{co}/facilities/{fac}/unit-groups/{id}/move-in-cost',      'Total due at move-in (rent + fees + deposits)' ],
				[ 'GET',  '/api/v1/companies/{co}/facilities/{fac}/lead-sources',                       'Available lead/referral sources' ],
				[ 'GET',  '/api/v1/companies/{co}/facilities/{fac}/unit-groups/reservations/settings',  'Reservation configuration' ],
			];
			$debug_out .= '<table style="border-collapse:collapse;font-size:11px;width:100%;">';
			$debug_out .= '<tr style="background:#555;color:#fff;"><th style="' . $td_style . '">Method</th><th style="' . $td_style . '">Endpoint</th><th style="' . $td_style . '">Notes</th></tr>';
			foreach ( $endpoints as $ep ) {
				$debug_out .= '<tr><td style="' . $td_style . '">' . esc_html( $ep[0] ) . '</td><td style="' . $td_style . '"><code>' . esc_html( $ep[1] ) . '</code></td><td style="' . $td_style . '">' . esc_html( $ep[2] ) . '</td></tr>';
			}
			$debug_out .= '</table>';

			// ---- FILTER / SORT STATS ----
			$debug_out .= '<p style="' . $h_style . '">FILTER / SORT / LIMIT</p>';
			$debug_out .= 'Before filter: <strong>' . $groups_before_filter . '</strong> &nbsp; After filter/sort: <strong>' . count( $groups ) . '</strong> &nbsp; Display cards: <strong>' . count( $display_units ) . '</strong><br>';
			$debug_out .= 'soldout_handling: <code>' . esc_html( $config['soldout_handling'] ?? 'hide' ) . '</code> &nbsp; ';
			$debug_out .= 'filter_label: <code>' . esc_html( $config['filter_label'] ?? '(none)' ) . '</code> &nbsp; ';
			$debug_out .= 'filter_has_special: <code>' . esc_html( $config['filter_has_special'] ?? '0' ) . '</code> &nbsp; ';
			$debug_out .= 'max_units: <code>' . esc_html( $config['max_units'] ?? '0' ) . '</code>';

			// ---- GROUP ID MATCHING TABLE ----
			$debug_out .= '<p style="' . $h_style . '">GROUP ID MATCHING (v2 id &#x21D4; v1 unitGroupId)</p>';
			if ( ! is_wp_error( $diag_v2_all ) && is_array( $diag_v2_all ) ) {
				$debug_out .= '<table style="border-collapse:collapse;font-size:11px;width:100%;">';
				$debug_out .= '<tr style="background:#555;color:#fff;"><th style="' . $td_style . '">v2 Label</th><th style="' . $td_style . '">v2 id</th><th style="' . $td_style . '">v1?</th><th style="' . $td_style . '">v1 Name</th><th style="' . $td_style . '">featuredFeatures</th><th style="' . $td_style . '">Plugin Features</th></tr>';
				foreach ( $diag_v2_all as $g2 ) {
					$gid   = $g2['id'] ?? '';
					$v1g   = $v1_diag_lookup[ $gid ] ?? null;
					$match = $v1g
						? '<span style="color:green;font-weight:bold;">&#x2713;</span>'
						: '<span style="color:red;font-weight:bold;">&#x2717;</span>';
					$v1name = $v1g ? esc_html( $v1g['name'] ?? '—' ) : '—';
					$ff     = ( $v1g && ! empty( $v1g['featuredFeatures'] ) && is_array( $v1g['featuredFeatures'] ) )
						? esc_html( implode( ', ', $v1g['featuredFeatures'] ) )
						: '';
					$plugin_feats = ( isset( $group_map[ $gid ]['features'] ) && is_array( $group_map[ $gid ]['features'] ) )
						? esc_html( implode( ', ', $group_map[ $gid ]['features'] ) )
						: '';
					$row_bg = $v1g ? '' : 'background:#fff0f0;';
					$debug_out .= '<tr style="' . $row_bg . '"><td style="' . $td_style . '">' . esc_html( $g2['label'] ?? '—' ) . '</td><td style="' . $td_style . 'font-size:10px;">' . esc_html( $gid ) . '</td><td style="' . $td_style . 'text-align:center;">' . $match . '</td><td style="' . $td_style . '">' . $v1name . '</td><td style="' . $td_style . '">' . $ff . '</td><td style="' . $td_style . '">' . $plugin_feats . '</td></tr>';
				}
				$debug_out .= '</table>';
			} else {
				$debug_out .= '<em>v2 groups unavailable.</em>';
			}

			// ---- RAW RESPONSE PANELS ----
			$debug_out .= '<p style="' . $h_style . '">v1 FACILITY INFO</p>';
			$debug_out .= is_wp_error( $diag_v1_fac )
				? '<em style="color:red;">Error: ' . esc_html( $diag_v1_fac->get_error_message() ) . '</em>'
				: '<pre style="' . $pre_style . '">' . esc_html( json_encode( $diag_v1_fac, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';

			$v2_count = is_wp_error( $diag_v2_all ) ? 'error' : count( $diag_v2_all ) . ' groups';
			$debug_out .= '<p style="' . $h_style . '">v2 UNIT GROUPS (' . esc_html( $v2_count ) . ')</p>';
			$debug_out .= is_wp_error( $diag_v2_all )
				? '<em style="color:red;">Error: ' . esc_html( $diag_v2_all->get_error_message() ) . '</em>'
				: '<pre style="' . $pre_style . '">' . esc_html( json_encode( $diag_v2_all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';

			$v1g_count = is_wp_error( $diag_v1_groups ) ? 'error' : count( $diag_v1_groups ) . ' groups';
			$debug_out .= '<p style="' . $h_style . '">v1 UNIT GROUPS (' . esc_html( $v1g_count ) . ')</p>';
			$debug_out .= is_wp_error( $diag_v1_groups )
				? '<em style="color:red;">Error: ' . esc_html( $diag_v1_groups->get_error_message() ) . '</em>'
				: '<pre style="' . $pre_style . '">' . esc_html( json_encode( $diag_v1_groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';

			$debug_out .= '<p style="' . $h_style . '">v1 LEAD SOURCES</p>';
			$debug_out .= is_wp_error( $diag_lead_src )
				? '<em style="color:red;">Error: ' . esc_html( $diag_lead_src->get_error_message() ) . '</em>'
				: '<pre style="' . $pre_style . '">' . esc_html( json_encode( $diag_lead_src, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';

			$debug_out .= '<p style="' . $h_style . '">v1 RESERVATION SETTINGS</p>';
			$debug_out .= is_wp_error( $diag_res_set )
				? '<em style="color:red;">Error: ' . esc_html( $diag_res_set->get_error_message() ) . '</em>'
				: '<pre style="' . $pre_style . '">' . esc_html( json_encode( $diag_res_set, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';

			// ---- BUILD FULL DIAGNOSTIC JSON (hidden — used by download button) ----
			$diag_json = [
				'plugin_version'          => DSU_VERSION,
				'facility_code'           => $facility_code,
				'generated_at'            => current_time( 'c' ),
				'endpoint_inventory'      => array_map( fn( $ep ) => [ 'method' => $ep[0], 'path' => $ep[1], 'notes' => $ep[2] ], $endpoints ),
				'filter_stats'            => [
					'before_filter'     => $groups_before_filter,
					'after_filter'      => count( $groups ),
					'soldout_handling'  => $config['soldout_handling'] ?? 'hide',
					'filter_label'      => $config['filter_label'] ?? '',
					'filter_has_special'=> $config['filter_has_special'] ?? '0',
					'max_units'         => $config['max_units'] ?? '0',
				],
				'group_id_matching'       => [],
				'v1_facility_info'        => is_wp_error( $diag_v1_fac )    ? [ 'error' => $diag_v1_fac->get_error_message() ]    : $diag_v1_fac,
				'v2_unit_groups'          => is_wp_error( $diag_v2_all )    ? [ 'error' => $diag_v2_all->get_error_message() ]    : $diag_v2_all,
				'v1_unit_groups'          => is_wp_error( $diag_v1_groups ) ? [ 'error' => $diag_v1_groups->get_error_message() ] : $diag_v1_groups,
				'v1_lead_sources'         => is_wp_error( $diag_lead_src )  ? [ 'error' => $diag_lead_src->get_error_message() ]  : $diag_lead_src,
				'v1_reservation_settings' => is_wp_error( $diag_res_set )   ? [ 'error' => $diag_res_set->get_error_message() ]   : $diag_res_set,
				'plugin_group_map'        => $group_map,
				'active_config'           => $config,
			];

			if ( ! is_wp_error( $diag_v2_all ) && is_array( $diag_v2_all ) ) {
				foreach ( $diag_v2_all as $g2 ) {
					$gid = $g2['id'] ?? '';
					$v1g = $v1_diag_lookup[ $gid ] ?? null;
					$diag_json['group_id_matching'][] = [
						'v2_id'            => $gid,
						'v2_label'         => $g2['label'] ?? '',
						'v2_street_rate'   => $g2['streetRate'] ?? null,
						'v2_avail_total'   => $g2['availableTotal'] ?? null,
						'v1_matched'       => $v1g !== null,
						'v1_unitGroupId'   => $v1g ? ( $v1g['unitGroupId'] ?? null ) : null,
						'v1_name'          => $v1g ? ( $v1g['name'] ?? null ) : null,
						'v1_areaInSqFt'    => $v1g ? ( $v1g['areaInSquareFeet'] ?? null ) : null,
						'featuredFeatures' => ( $v1g && is_array( $v1g['featuredFeatures'] ?? null ) ) ? $v1g['featuredFeatures'] : [],
						'features_object'  => ( $v1g && is_array( $v1g['features'] ?? null ) ) ? $v1g['features'] : [],
						'plugin_features'  => $group_map[ $gid ]['features'] ?? [],
						'plugin_sqft'      => $group_map[ $gid ]['sqft'] ?? null,
					];
				}
			}

			$debug_out .= '<pre id="dsu-diag-json" style="display:none;">' . esc_html( json_encode( $diag_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
			$debug_out .= '</div>';
		}

		ob_start();
		if ( $debug ) {
			echo $debug_out; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		// Root wrapper prevents theme flex/grid containers from treating our elements as siblings
		// Build size modifier classes from config
		$img_size      = $config['img_size']      ?? 'md';
		$title_size    = $config['title_size']    ?? 'md';
		$special_size  = $config['special_size']  ?? 'md';
		$price_size    = $config['price_size']    ?? 'md';
		$scarcity_size = $config['scarcity_size'] ?? 'md';
		$utf_size      = $config['unit_type_filter_size'] ?? 'md';
		$wrap_classes  = 'dsu-wrap';
		if ( $img_size      !== 'md' ) { $wrap_classes .= ' dsu-img-'      . $img_size; }
		if ( $title_size    !== 'md' ) { $wrap_classes .= ' dsu-title-'    . $title_size; }
		if ( $special_size  !== 'md' ) { $wrap_classes .= ' dsu-special-'  . $special_size; }
		if ( $price_size    !== 'md' ) { $wrap_classes .= ' dsu-price-'    . $price_size; }
		if ( $scarcity_size !== 'md' ) { $wrap_classes .= ' dsu-scarcity-' . $scarcity_size; }
		if ( $utf_size      !== 'md' ) { $wrap_classes .= ' dsu-utf-'      . $utf_size; }
		echo '<div class="' . esc_attr( $wrap_classes ) . '">';

		// Unit type filter — optional compact type selector above tiles/grid
		if ( $show_unit_type_filter && ! empty( $unit_types ) ) {
			$all_label       = $config['unit_type_all_label'] ?? '';
			$hide_utf_mobile = ! empty( $config['hide_utf_mobile'] );
			include DSU_PLUGIN_DIR . 'templates/unit-type-filter.php';
		}

		// Category tiles — toggled per display configuration
		if ( ! empty( $config['show_size_tiles'] ) ) {
			$api_settings_tl      = get_option( DSU_OPTION_API, [] );
			$categories           = dsu_get_size_categories();
			$unavailable_handling = $config['unavailable_tile_handling']
				?? ( $api_settings_tl['unavailable_tile_handling'] ?? 'dim' );
			$tiles_alignment   = $config['tiles_alignment'] ?? 'left';
			$hide_tiles_mobile = ! empty( $config['hide_tiles_mobile'] );
			$tiles         = $this->build_category_tile_data( $categories, $groups, $group_map, $api, $facility_code );
			$tiles         = $this->sort_category_tiles( $tiles, $config );
			$hide_all_tile = ! empty( $config['hide_all_tile'] );
			include DSU_PLUGIN_DIR . 'templates/category-tiles.php';
		}

		// Promo bar — only rendered when enabled and a qualifying promotion exists
		if ( $promo_data ) {
			$hide_promo_mobile = ! empty( $config['hide_promo_mobile'] );
			include DSU_PLUGIN_DIR . 'templates/promo-bar.php';
		}

		include DSU_PLUGIN_DIR . 'templates/unit-grid.php';
		echo '</div><!-- .dsu-wrap -->';
		return ob_get_clean();
	}

	private function get_config( $name ) {
		if ( empty( $name ) ) {
			return null;
		}
		$configs = get_option( DSU_OPTION_CONFIGS, [] );
		foreach ( $configs as $config ) {
			if ( isset( $config['name'] ) && $config['name'] === $name ) {
				return $config;
			}
		}
		return null;
	}

	private function apply_filters_config( $groups, $config ) {
		$filtered = [];

		foreach ( $groups as $group ) {
			// Label/name contains filter
			if ( ! empty( $config['filter_label'] ) ) {
				$search = strtolower( $config['filter_label'] );
				$label  = strtolower( $group['label'] ?? '' );
				if ( strpos( $label, $search ) === false ) {
					continue;
				}
			}

			// Only show groups with a special/promotion
			if ( ! empty( $config['filter_has_special'] ) ) {
				if ( empty( $group['availableSpecial'] ) ) {
					continue;
				}
			}

			$filtered[] = $group;
		}

		return $filtered;
	}

	private function apply_sorting( $groups, $config ) {
		$sort = $config['sort'] ?? '';

		usort( $groups, function( $a, $b ) use ( $sort ) {
			switch ( $sort ) {
				case 'price_asc':
					return ( $a['streetRate'] ?? 0 ) <=> ( $b['streetRate'] ?? 0 );
				case 'price_desc':
					return ( $b['streetRate'] ?? 0 ) <=> ( $a['streetRate'] ?? 0 );
				case 'availability':
					return ( $b['availableTotal'] ?? 0 ) <=> ( $a['availableTotal'] ?? 0 );
				case 'label':
					return strcmp( $a['label'] ?? '', $b['label'] ?? '' );
				default:
					return 0;
			}
		} );

		return $groups;
	}

	private function build_category_tile_data( $categories, $groups, $group_map, $api, $facility_code ) {
		// Build slug → [ group_ids ] from the saved category assignments
		$cat_group_ids = [];
		foreach ( $group_map as $gid => $wp_data ) {
			$slug = $wp_data['size_category'] ?? '';
			if ( $slug ) {
				$cat_group_ids[ $slug ][] = $gid;
			}
		}

		// Build API group lookup by id
		$api_lookup = [];
		foreach ( $groups as $group ) {
			$gid = $group['id'] ?? '';
			if ( $gid ) {
				$api_lookup[ $gid ] = $group;
			}
		}

		$tiles = [];
		foreach ( $categories as $cat ) {
			$slug = $cat['slug'] ?? '';
			if ( empty( $slug ) ) {
				continue;
			}

			$assigned              = $cat_group_ids[ $slug ] ?? [];
			$from_price            = PHP_FLOAT_MAX;
			$from_price_is_special = false;
			$best_gid              = '';
			$has_avail             = false;

			foreach ( $assigned as $gid ) {
				$api_group = $api_lookup[ $gid ] ?? null;
				if ( ! $api_group ) {
					continue;
				}
				$avail = (int) ( $api_group['availableTotal'] ?? 0 );
				if ( $avail <= 0 ) {
					continue;
				}
				$has_avail  = true;
				$rate       = (float) ( $api_group['streetRate'] ?? 0 );
				$is_special = false;

				// Use special rate when lower
				$special = $api_group['availableSpecial'] ?? null;
				if ( is_array( $special ) && ! empty( $special ) ) {
					$sr = (float) ( $special['specialRate'] ?? $special['rate'] ?? 0 );
					if ( $sr > 0 && $sr < $rate ) {
						$rate       = $sr;
						$is_special = true;
					}
				}

				if ( $rate > 0 && $rate < $from_price ) {
					$from_price            = $rate;
					$from_price_is_special = $is_special;
					$best_gid              = $gid;
				}
			}

			// Try to get move-in cost for the cheapest group in this category
			$move_in_total = 0;
			if ( $has_avail && $best_gid ) {
				$cost = $api->get_move_in_cost( $facility_code, $best_gid );
				if ( ! is_wp_error( $cost ) && ! empty( $cost['total'] ) ) {
					$move_in_total = (float) $cost['total'];
				}
			}

			$tiles[] = [
				'slug'                  => $slug,
				'label'                 => $cat['label'] ?? $slug,
				'description'           => $cat['description'] ?? '',
				'unit_type'             => $cat['unit_type'] ?? '',
				'available'             => $has_avail,
				'from_price'            => $has_avail && $from_price < PHP_FLOAT_MAX ? $from_price : 0,
				'from_price_is_special' => $has_avail ? $from_price_is_special : false,
				'move_in_total'         => $move_in_total,
			];
		}

		return $tiles;
	}

	private function build_promo_bar_data( $groups ) {
		/*
		 * Selects the single promotion to feature in the promo bar.
		 *
		 * Algorithm:
		 *   1. For each available unit group with an active special, record the special
		 *      label, how many units share that label, and the max discount amount seen
		 *      (street_rate − special_rate) for that label.
		 *   2. Pick the label with the most units. Ties broken by highest max discount.
		 *
		 * To change this selection logic, update the comparator in the "Pick winner" loop.
		 */
		$promos = [];

		foreach ( $groups as $group ) {
			$avail = (int) ( $group['availableTotal'] ?? 0 );
			if ( $avail <= 0 ) {
				continue;
			}
			$special = $group['availableSpecial'] ?? null;
			if ( ! is_array( $special ) || empty( $special ) ) {
				continue;
			}
			$label = sanitize_text_field( $special['label'] ?? $special['promotionName'] ?? '' );
			if ( empty( $label ) ) {
				continue;
			}

			$street_rate  = (float) ( $group['streetRate'] ?? 0 );
			$special_rate = (float) ( $special['specialRate'] ?? $special['rate'] ?? 0 );
			$discount     = ( $street_rate > 0 && $special_rate > 0 && $special_rate < $street_rate )
				? ( $street_rate - $special_rate )
				: 0;

			if ( ! isset( $promos[ $label ] ) ) {
				$promos[ $label ] = [ 'count' => 0, 'max_discount' => 0 ];
			}
			$promos[ $label ]['count']++;
			if ( $discount > $promos[ $label ]['max_discount'] ) {
				$promos[ $label ]['max_discount'] = $discount;
			}
		}

		if ( empty( $promos ) ) {
			return null;
		}

		// Pick winner: most units, ties broken by highest discount
		$winner_label    = '';
		$winner_count    = 0;
		$winner_discount = 0;

		foreach ( $promos as $label => $data ) {
			if (
				$data['count'] > $winner_count ||
				( $data['count'] === $winner_count && $data['max_discount'] > $winner_discount )
			) {
				$winner_label    = $label;
				$winner_count    = $data['count'];
				$winner_discount = $data['max_discount'];
			}
		}

		return $winner_label ? [ 'label' => $winner_label, 'count' => $winner_count ] : null;
	}

	private function sort_category_tiles( $tiles, $config ) {
		$sort = $config['sort'] ?? '';
		if ( empty( $sort ) ) {
			return $tiles;
		}
		usort( $tiles, function( $a, $b ) use ( $sort ) {
			// Available tiles always sort before unavailable ones
			if ( $a['available'] !== $b['available'] ) {
				return $a['available'] ? -1 : 1;
			}
			switch ( $sort ) {
				case 'price_asc':
					$ap = $a['from_price'] > 0 ? $a['from_price'] : PHP_FLOAT_MAX;
					$bp = $b['from_price'] > 0 ? $b['from_price'] : PHP_FLOAT_MAX;
					return $ap <=> $bp;
				case 'price_desc':
					return $b['from_price'] <=> $a['from_price'];
				case 'label':
					return strcmp( $a['label'], $b['label'] );
				default:
					return 0;
			}
		} );
		return $tiles;
	}

	private function build_display_units( $groups, $group_map, $config, $api_settings ) {
		$tier_labels = [
			sanitize_text_field( $api_settings['good_label']   ?? '' ) ?: 'Good',
			sanitize_text_field( $api_settings['better_label'] ?? '' ) ?: 'Better',
			sanitize_text_field( $api_settings['best_label']   ?? '' ) ?: 'Best',
		];
		$tier_classes     = [ 'dsu-tier-col--good', 'dsu-tier-col--better', 'dsu-tier-col--best' ];
		$grouped_cta      = sanitize_text_field( $api_settings['grouped_cta_text'] ?? '' ) ?: 'Choose Your Space';
		$soldout_handling = $config['soldout_handling'] ?? 'hide';

		// Group by v1_name (falls back to v2 label)
		$buckets = [];
		foreach ( $groups as $group ) {
			$gid  = $group['id'] ?? '';
			$name = $group_map[ $gid ]['v1_name'] ?? $group['label'] ?? '';
			if ( ! $name ) {
				continue;
			}
			$buckets[ $name ][] = $group;
		}

		$display_units = [];
		foreach ( $buckets as $name => $bucket ) {
			if ( count( $bucket ) === 1 ) {
				$group    = $bucket[0];
				$is_avail = (int) ( $group['availableTotal'] ?? 0 ) > 0;
				if ( ! $is_avail && $soldout_handling === 'hide' ) {
					continue;
				}
				$display_units[] = [ 'type' => 'single', 'group' => $group ];
			} else {
				$had_overflow = count( $bucket ) > 3;

				// Sort tiers by effective price (cheapest = Good)
				usort( $bucket, function ( $a, $b ) use ( $group_map ) {
					$aid  = $a['id'] ?? '';
					$bid  = $b['id'] ?? '';
					$ap   = (float) ( $group_map[ $aid ]['v1_price']         ?? $a['streetRate'] ?? 0 );
					$as_  = (float) ( $group_map[ $aid ]['v1_special_price'] ?? 0 );
					$bp   = (float) ( $group_map[ $bid ]['v1_price']         ?? $b['streetRate'] ?? 0 );
					$bs_  = (float) ( $group_map[ $bid ]['v1_special_price'] ?? 0 );
					$aeff = ( $as_ > 0 && $as_ < $ap ) ? $as_ : $ap;
					$beff = ( $bs_ > 0 && $bs_ < $bp ) ? $bs_ : $bp;
					return $aeff <=> $beff;
				} );

				$tiers    = array_slice( $bucket, 0, 3 );
				$has_any  = false;
				foreach ( $tiers as $t ) {
					if ( (int) ( $t['availableTotal'] ?? 0 ) > 0 ) {
						$has_any = true;
						break;
					}
				}
				if ( ! $has_any && $soldout_handling === 'hide' ) {
					continue;
				}

				// from_price = lowest effective price among available tiers
				$from_price      = PHP_FLOAT_MAX;
				$from_is_special = false;
				$from_regular    = 0.0;
				foreach ( $tiers as $tier ) {
					if ( ! (int) ( $tier['availableTotal'] ?? 0 ) ) {
						continue;
					}
					$gid  = $tier['id'] ?? '';
					$vp   = (float) ( $group_map[ $gid ]['v1_price']         ?? $tier['streetRate'] ?? 0 );
					$vs   = (float) ( $group_map[ $gid ]['v1_special_price'] ?? 0 );
					$eff  = ( $vs > 0 && $vs < $vp ) ? $vs : $vp;
					if ( $eff > 0 && $eff < $from_price ) {
						$from_price      = $eff;
						$from_is_special = ( $vs > 0 && $vs < $vp );
						$from_regular    = $vp;
					}
				}
				// Fallback: all sold out — use cheapest tier's price
				if ( $from_price >= PHP_FLOAT_MAX ) {
					$gid          = $tiers[0]['id'] ?? '';
					$vp           = (float) ( $group_map[ $gid ]['v1_price']         ?? $tiers[0]['streetRate'] ?? 0 );
					$vs           = (float) ( $group_map[ $gid ]['v1_special_price'] ?? 0 );
					$from_price   = ( $vs > 0 && $vs < $vp ) ? $vs : $vp;
					$from_is_special = ( $vs > 0 && $vs < $vp );
					$from_regular    = $vp;
				}

				$display_units[] = [
					'type'            => 'grouped',
					'name'            => $name,
					'modal_id'        => 'dsu-tier-modal-' . sanitize_title( $name ),
					'tiers'           => $tiers,
					'tier_labels'     => $tier_labels,
					'tier_classes'    => $tier_classes,
					'had_overflow'    => $had_overflow,
					'from_price'      => $from_price < PHP_FLOAT_MAX ? $from_price : 0.0,
					'from_is_special' => $from_is_special,
					'from_regular'    => $from_regular,
					'grouped_cta'     => $grouped_cta,
					'soldout_handling'=> $soldout_handling,
				];
			}
		}

		return $display_units;
	}

	/**
	 * Fetch v1 unit groups and return structured data per group including features,
	 * dimensions, unit type, sqft, and v1 display name.
	 * Returns [ unitGroupId => [ 'features' => [...], 'sqft' => N, 'width' => N, ... ] ]
	 */
	private function build_v1_feature_map( $api, $facility_code ) {
		$v1_groups = $api->get_v1_unit_groups( $facility_code );
		if ( is_wp_error( $v1_groups ) || empty( $v1_groups ) ) {
			return [];
		}

		// Boolean flags inside the `features` nested object (Appendix B, PascalCase keys)
		$bool_flags = [
			'HasDriveUpAccess'   => 'Drive-Up Access',
			'HasElevatorAccess'  => 'Elevator Access',
			'HasLiftAccess'      => 'Lift Access',
			'HasStairAccess'     => 'Stair Access',
			'HasAlarm'           => 'Alarm',
			'HasElectricOutlets' => 'Electric Outlets',
			'HasLighting'        => 'Lighting',
			'Has24HourAccess'    => '24-Hour Access',
			'HasShelves'         => 'Shelves',
			'HumidityControlled' => 'Humidity Controlled',
			'IsAdaAccessible'    => 'ADA Accessible',
			'IsPremiumUnit'      => 'Premium Unit',
			'IsSkybox'           => 'Skybox',
		];

		$data_map = [];
		foreach ( $v1_groups as $group ) {
			// v1 uses 'unitGroupId' — not 'id'
			$gid = $group['unitGroupId'] ?? $group['id'] ?? '';
			if ( empty( $gid ) ) {
				continue;
			}

			$features  = [];
			$feats_obj = is_array( $group['features'] ?? null ) ? $group['features'] : [];

			// Primary source: API-provided featuredFeatures (human-readable, no mapping needed)
			if ( ! empty( $group['featuredFeatures'] ) && is_array( $group['featuredFeatures'] ) ) {
				foreach ( $group['featuredFeatures'] as $feat ) {
					$feat = sanitize_text_field( (string) $feat );
					if ( $feat !== '' ) {
						$features[] = $feat;
					}
				}
			}

			// ClimateControlled is a string in Appendix B ('Full', 'Partial', 'None', etc.)
			if ( ! empty( $feats_obj['ClimateControlled'] ) ) {
				$cc      = (string) $feats_obj['ClimateControlled'];
				$not_cc  = [ '', 'None', 'No', '0', 'false', 'False' ];
				if ( ! in_array( $cc, $not_cc, true ) ) {
					$cc_label       = ( in_array( $cc, [ 'true', '1', 'Full' ], true ) )
						? 'Climate Controlled'
						: 'Climate Controlled (' . $cc . ')';
					$already_has_cc = false;
					foreach ( $features as $f ) {
						if ( stripos( $f, 'Climate' ) !== false ) {
							$already_has_cc = true;
							break;
						}
					}
					if ( ! $already_has_cc ) {
						$features[] = $cc_label;
					}
				}
			}

			// Boolean feature flags from nested features object.
			// The API returns these as strings ("true"/"false"), so filter_var is required —
			// ! empty("false") is true in PHP, which would incorrectly add every flag.
			foreach ( $bool_flags as $key => $label ) {
				$val = $feats_obj[ $key ] ?? null;
				if ( $val !== null && filter_var( $val, FILTER_VALIDATE_BOOLEAN ) && ! in_array( $label, $features, true ) ) {
					$features[] = $label;
				}
			}

			// Dimensions and metadata
			$width    = (float) ( $feats_obj['Width']  ?? 0 );
			$depth    = (float) ( $feats_obj['Depth']  ?? 0 );
			$height   = (float) ( $feats_obj['Height'] ?? 0 );
			$sqft     = (float) ( $group['areaInSquareFeet'] ?? ( $width > 0 && $depth > 0 ? round( $width * $depth ) : 0 ) );
			$unit_type = sanitize_text_field( (string) ( $feats_obj['UnitType'] ?? '' ) );
			$door_type = sanitize_text_field( (string) ( $feats_obj['DoorType'] ?? '' ) );
			$v1_name   = sanitize_text_field( (string) ( $group['name'] ?? '' ) );

			$v1_price           = (float) ( $group['regularPrice'] ?? 0 );
			$v1_special         = $group['availableSpecial'] ?? null;
			$v1_special_price   = is_array( $v1_special ) ? (float) ( $v1_special['specialPrice'] ?? 0 ) : 0;
			$v1_special_label   = is_array( $v1_special ) ? sanitize_text_field( $v1_special['specialLabel'] ?? '' ) : '';
			$online_move_in_url = esc_url_raw( $group['onlineMoveInUrl'] ?? '' );

			$data_map[ $gid ] = [
				'features'           => $features,
				'sqft'               => $sqft,
				'width'              => $width,
				'depth'              => $depth,
				'height'             => $height,
				'unit_type'          => $unit_type,
				'door_type'          => $door_type,
				'v1_name'            => $v1_name,
				'online_move_in_url' => $online_move_in_url,
				'v1_price'           => $v1_price,
				'v1_special_price'   => $v1_special_price,
				'v1_special_label'   => $v1_special_label,
			];
		}

		return $data_map;
	}
}
