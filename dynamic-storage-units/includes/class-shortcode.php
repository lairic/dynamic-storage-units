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

		if ( ! DSU_License::is_active() ) {
			return '';
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

		// Per-unit data (class + isRentable). Returns [] when the endpoint is unavailable,
		// which makes every consumer below fail open rather than blanking the display.
		$class_map = $this->build_unit_class_map( $api, $facility_code );

		// Hide groups with no rentable unit. The v2 unit-groups feed already excludes these
		// (it only returns groups with >=1 vacant AND rentable unit), so this is a backstop
		// for facilities where that does not hold. Groups absent from $class_map are kept.
		$groups_before_rentable = count( $groups );
		if ( ! empty( $class_map ) ) {
			$groups = array_values( array_filter( $groups, function ( $g ) use ( $class_map ) {
				$gid = $g['id'] ?? '';
				if ( ! isset( $class_map[ $gid ] ) ) {
					return true;
				}
				return ! empty( $class_map[ $gid ]['has_rentable'] );
			} ) );
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
		$display_units = $this->build_display_units( $groups, $group_map, $class_map, $config, $api_settings );

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
			$debug_out .= 'Groups before rentable gate: <strong>' . $groups_before_rentable . '</strong> &nbsp; Before filter: <strong>' . $groups_before_filter . '</strong> &nbsp; After filter/sort: <strong>' . count( $groups ) . '</strong> &nbsp; Display cards: <strong>' . count( $display_units ) . '</strong><br>';
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

			// ---- UNIT CLASS BREAKDOWN ----
			$debug_out .= '<p style="' . $h_style . '">UNIT CLASS BREAKDOWN (vacant + rentable units per class)</p>';
			$debug_out .= 'class_grouping_enabled: <code>' . ( ! empty( $api_settings['class_grouping_enabled'] ) ? '1' : '0' ) . '</code> &nbsp; ';
			$debug_out .= 'unit records: <code>' . count( $class_map ) . ' groups</code> &nbsp; ';
			$debug_out .= 'unit deep-link param: <code>' . esc_html( apply_filters( 'dsu_unit_url_param', defined( 'DSU_UNIT_URL_PARAM' ) ? DSU_UNIT_URL_PARAM : '' ) ?: '(disabled)' ) . '</code>';
			if ( empty( $class_map ) ) {
				$debug_out .= '<p><em>No unit-level data — /units returned an error or is outside the scope of this API client. Class grouping and the rentable gate are both inactive.</em></p>';
			} else {
				$debug_out .= '<table style="border-collapse:collapse;font-size:11px;width:100%;">';
				$debug_out .= '<tr style="background:#555;color:#fff;"><th style="' . $td_style . '">Group</th><th style="' . $td_style . '">Rentable?</th><th style="' . $td_style . '">Classes w/ vacancy</th><th style="' . $td_style . '">Would group?</th></tr>';
				foreach ( ( is_wp_error( $diag_v2_all ) || ! is_array( $diag_v2_all ) ) ? [] : $diag_v2_all as $g2c ) {
					$cid     = $g2c['id'] ?? '';
					$centry  = $class_map[ $cid ] ?? null;
					$cls     = $centry ? ( $centry['classes'] ?? [] ) : [];
					$cls_txt = [];
					foreach ( $cls as $cname => $cinfo ) {
						$cls_txt[] = $cname . ' &times;' . (int) $cinfo['count'] . ' @ $' . number_format( (float) $cinfo['rate'], 2 );
					}
					$would = count( $cls ) > 1
						? '<span style="color:green;font-weight:bold;">yes</span>'
						: '<span style="color:#888;">no</span>';
					$rent = $centry
						? ( ! empty( $centry['has_rentable'] ) ? '<span style="color:green;">&#x2713;</span>' : '<span style="color:red;font-weight:bold;">&#x2717; hidden</span>' )
						: '<em>no data</em>';
					$debug_out .= '<tr><td style="' . $td_style . '">' . esc_html( $g2c['label'] ?? '—' ) . '</td><td style="' . $td_style . 'text-align:center;">' . $rent . '</td><td style="' . $td_style . '">' . ( $cls_txt ? implode( ' &nbsp;|&nbsp; ', $cls_txt ) : '<em>none vacant</em>' ) . '</td><td style="' . $td_style . 'text-align:center;">' . $would . '</td></tr>';
				}
				$debug_out .= '</table>';
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
					'before_rentable_gate' => $groups_before_rentable,
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
				'unit_class_map'          => $class_map,
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

	private function build_display_units( $groups, $group_map, $class_map, $config, $api_settings ) {
		$tier_labels = [
			sanitize_text_field( $api_settings['good_label']   ?? '' ) ?: 'Good',
			sanitize_text_field( $api_settings['better_label'] ?? '' ) ?: 'Better',
			sanitize_text_field( $api_settings['best_label']   ?? '' ) ?: 'Best',
		];
		$tier_classes     = [ 'dsu-tier-col--good', 'dsu-tier-col--better', 'dsu-tier-col--best' ];
		$grouped_cta      = sanitize_text_field( $api_settings['grouped_cta_text'] ?? '' ) ?: 'Choose Your Space';
		$soldout_handling = $config['soldout_handling'] ?? 'hide';
		$class_grouping   = ! empty( $api_settings['class_grouping_enabled'] );

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

				// Class grouping: one unit group holding vacant units in more than one class.
				// Only reached when the group was not already bundled by name — name-based
				// grouping takes precedence, and nesting both would exceed three columns.
				if ( $class_grouping && $is_avail ) {
					$class_tiers = $this->build_class_tiers( $group, $class_map, $group_map, $api_settings );
					if ( count( $class_tiers ) > 1 ) {
						$first = $class_tiers[0];
						$display_units[] = [
							'type'            => 'grouped',
							'name'            => $name,
							'modal_id'        => 'dsu-class-modal-' . sanitize_title( $name ),
							'tiers'           => $class_tiers,
							'tier_labels'     => wp_list_pluck( $class_tiers, '_class_label' ),
							'tier_classes'    => $tier_classes,
							'had_overflow'    => false,
							'from_price'      => (float) ( $first['_special_price'] > 0 ? $first['_special_price'] : $first['_price'] ),
							'from_is_special' => $first['_special_price'] > 0,
							'from_regular'    => (float) $first['_price'],
							'grouped_cta'     => $grouped_cta,
							'special_banner'  => $this->tiers_shared_special_label( $class_tiers ),
							'soldout_handling'=> $soldout_handling,
						];
						continue;
					}
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

				// Normalise each tier onto the same keys the class tiers use, so the modal
				// template reads one shape regardless of which grouping produced it.
				foreach ( $tiers as &$t_norm ) {
					$tid              = $t_norm['id'] ?? '';
					$twp              = $group_map[ $tid ] ?? [];
					$t_norm['_price']         = (float) ( $twp['v1_price'] ?? $t_norm['streetRate'] ?? 0 );
					$t_norm['_special_price'] = (float) ( $twp['v1_special_price'] ?? 0 );
					$t_norm['_special_label'] = (string) ( $twp['v1_special_label'] ?? '' );
					$t_norm['_display_name']  = ( $twp['v1_name'] ?? '' ) ?: ( $t_norm['label'] ?? '' );
					$t_norm['_features']      = is_array( $twp['features'] ?? null ) ? $twp['features'] : [];
				}
				unset( $t_norm );

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
					'special_banner'  => $this->tiers_shared_special_label( $tiers ),
					'soldout_handling'=> $soldout_handling,
				];
			}
		}

		return $display_units;
	}

	/**
	 * Build per-group unit data from the v2 /units endpoint — the only place the API exposes
	 * attributes.class and the per-unit isRentable flag.
	 *
	 * Returns [ groupId => [ 'has_rentable' => bool, 'classes' => [ class => [...] ] ] ].
	 * Only vacant, rentable, undamaged units are counted into 'classes'; 'has_rentable' also
	 * counts occupied ones, since a fully-rented group is still rentable, just not right now.
	 * Returns [] on any API error so callers fail open.
	 */
	private function build_unit_class_map( $api, $facility_code ) {
		$units = $api->get_units( $facility_code );
		if ( is_wp_error( $units ) || empty( $units ) || ! is_array( $units ) ) {
			return [];
		}

		$map = [];
		foreach ( $units as $unit ) {
			// Units join to unit groups on unitType.id — the unit-group endpoints take this
			// same value as their {unitTypeId} path parameter.
			$gid = $unit['unitType']['id'] ?? '';
			if ( empty( $gid ) ) {
				continue;
			}

			if ( ! isset( $map[ $gid ] ) ) {
				$map[ $gid ] = [ 'has_rentable' => false, 'classes' => [] ];
			}

			$rentable = ! empty( $unit['isRentable'] ) && ! empty( $unit['isActive'] ) && empty( $unit['isDamaged'] );
			if ( $rentable ) {
				$map[ $gid ]['has_rentable'] = true;
			}

			if ( ! $rentable || ( $unit['availabilityStatus'] ?? '' ) !== 'Vacant' ) {
				continue;
			}

			$class = sanitize_text_field( (string) ( $unit['attributes']['class'] ?? '' ) );
			if ( $class === '' ) {
				continue;
			}

			// webRate is the online rate; it matches streetRate unless the facility prices
			// its online channel separately.
			$rate = (float) ( $unit['webRate'] ?? $unit['streetRate'] ?? 0 );

			if ( ! isset( $map[ $gid ]['classes'][ $class ] ) ) {
				$map[ $gid ]['classes'][ $class ] = [ 'count' => 0, 'rate' => 0.0, 'unit_id' => '', 'unit_number' => '', 'special_ids' => [] ];
			}

			$map[ $gid ]['classes'][ $class ]['count']++;

			// Track the cheapest unit in the class — that is the rate the class is advertised
			// at, and the unit a per-unit deep link should point to.
			if ( $rate > 0 && ( $map[ $gid ]['classes'][ $class ]['rate'] <= 0 || $rate < $map[ $gid ]['classes'][ $class ]['rate'] ) ) {
				$map[ $gid ]['classes'][ $class ]['rate']        = $rate;
				$map[ $gid ]['classes'][ $class ]['unit_id']     = sanitize_text_field( (string) ( $unit['id'] ?? '' ) );
				$map[ $gid ]['classes'][ $class ]['unit_number'] = sanitize_text_field( (string) ( $unit['number'] ?? '' ) );

				// Specials are attached per unit. Recording them for the unit we link to means
				// the price shown is the price that unit actually gets.
				$sids = [];
				foreach ( (array) ( $unit['specials'] ?? [] ) as $sp ) {
					$sid = sanitize_text_field( (string) ( $sp['id'] ?? '' ) );
					if ( $sid !== '' ) {
						$sids[] = $sid;
					}
				}
				$map[ $gid ]['classes'][ $class ]['special_ids'] = $sids;
			}
		}

		return $map;
	}

	/**
	 * Build tier columns for a single unit group holding vacant units in more than one class.
	 * Returns [] when fewer than two classes have vacancy, so the caller falls back to a normal
	 * single card. Shaped to match the name-based tiers the modal already renders.
	 */
	private function build_class_tiers( $group, $class_map, $group_map, $api_settings ) {
		$gid     = $group['id'] ?? '';
		$classes = $class_map[ $gid ]['classes'] ?? [];
		if ( count( $classes ) < 2 ) {
			return [];
		}

		$labels = [
			'Economy'  => sanitize_text_field( $api_settings['economy_label']  ?? '' ) ?: 'Economy',
			'Standard' => sanitize_text_field( $api_settings['standard_label'] ?? '' ) ?: 'Standard',
			'Premium'  => sanitize_text_field( $api_settings['premium_label']  ?? '' ) ?: 'Premium',
		];
		// Tie-break order when two classes carry the same price.
		$rank = [ 'Economy' => 0, 'Standard' => 1, 'Premium' => 2 ];

		$wp            = $group_map[ $gid ] ?? [];
		$group_rate    = (float) ( $wp['v1_price']          ?? $group['streetRate'] ?? 0 );
		$special_price = (float) ( $wp['v1_special_price']  ?? 0 );
		$special_label = (string) ( $wp['v1_special_label'] ?? '' );
		$special_id    = (string) ( $wp['v1_special_id']    ?? '' );
		$base_features = is_array( $wp['features'] ?? null ) ? $wp['features'] : [];
		$class_extra   = is_array( $wp['class_features'] ?? null ) ? $wp['class_features'] : [];

		// The API prices a special against the group rate only. Every special this API returns
		// is a percentage discount, so the same ratio carries to the dearer classes: a 50% off
		// special is 135 on a 270 unit and 152.50 on a 305 one.
		$ratio = ( $group_rate > 0 && $special_price > 0 ) ? ( $special_price / $group_rate ) : 0.0;

		$tiers = [];
		foreach ( $classes as $class => $info ) {
			$rate = (float) $info['rate'];
			if ( $rate <= 0 ) {
				$rate = $group_rate;
			}

			// Whether this class gets the special is decided by the unit we deep-link to:
			// its specials[] either carries the group special id or it does not. Falls back to
			// matching on rate when the id is unavailable.
			if ( $special_id !== '' && $ratio > 0 && $ratio < 1 ) {
				$special_applies = in_array( $special_id, (array) ( $info['special_ids'] ?? [] ), true );
				$class_special   = $special_applies ? round( $rate * $ratio, 2 ) : 0.0;
			} else {
				$special_applies = $special_price > 0 && abs( $rate - $group_rate ) < 0.01;
				$class_special   = $special_applies ? $special_price : 0.0;
			}

			$feats = array_merge( $base_features, (array) ( $class_extra[ $class ] ?? [] ) );

			$tiers[] = [
				'id'               => $gid,
				'label'            => $group['label'] ?? '',
				'availableTotal'   => (int) $info['count'],
				'availableSpecial' => $special_applies ? ( $group['availableSpecial'] ?? null ) : null,
				'_move_in_url'     => $this->class_cta_url( $group['_move_in_url'] ?? '', $info, $special_applies ),
				'_reserve_url'     => $this->class_cta_url( $group['_reserve_url'] ?? '', $info, $special_applies ),
				'_price'           => $rate,
				'_special_price'   => $class_special,
				'_special_label'   => $special_applies ? $special_label : '',
				'_features'        => array_values( array_unique( $feats ) ),
				'_display_name'    => ( $wp['v1_name'] ?? '' ) ?: ( $group['label'] ?? '' ),
				'_class_label'     => $labels[ $class ] ?? $class,
				'_class'           => $class,
			];
		}

		usort( $tiers, function ( $a, $b ) use ( $rank ) {
			$ae = $a['_special_price'] > 0 ? $a['_special_price'] : $a['_price'];
			$be = $b['_special_price'] > 0 ? $b['_special_price'] : $b['_price'];
			if ( abs( $ae - $be ) > 0.001 ) {
				return $ae <=> $be;
			}
			return ( $rank[ $a['_class'] ] ?? 99 ) <=> ( $rank[ $b['_class'] ] ?? 99 );
		} );

		// Three columns is what the tier modal's CSS grid is built for.
		return array_slice( $tiers, 0, 3 );
	}

	/**
	 * When every column carries the same special, the modal shows it once as a banner
	 * instead of repeating the callout box in each column. Returns the label, or ''.
	 */
	private function tiers_shared_special_label( $tiers ) {
		$labels = [];
		foreach ( $tiers as $t ) {
			if ( (float) ( $t['_special_price'] ?? 0 ) <= 0 ) {
				return '';
			}
			$labels[ (string) ( $t['_special_label'] ?? '' ) ] = true;
		}
		if ( count( $labels ) !== 1 ) {
			return '';
		}
		$label = key( $labels );
		return $label !== '' ? $label : '';
	}

	/**
	 * Adapt a group-level CTA URL for one class column.
	 */
	private function class_cta_url( $url, $class_info, $special_applies ) {
		if ( empty( $url ) ) {
			return '';
		}

		// The group deep link carries that group's own price and specialId. For a class which
		// does not qualify, leaving them on would quote a price the customer cannot get, so
		// drop them and let the portal price the unit itself.
		if ( ! $special_applies ) {
			$url = remove_query_arg( [ 'price', 'specialId' ], $url );
		}

		// Preselect the specific unit. 'unitId' + the unit UUID is undocumented but confirmed
		// working against the live portal; 'unitNumber' was tested and is ignored by it.
		// Define DSU_UNIT_URL_PARAM as '' (or filter it) to fall back to group-level links.
		$param = apply_filters( 'dsu_unit_url_param', defined( 'DSU_UNIT_URL_PARAM' ) ? DSU_UNIT_URL_PARAM : '' );
		if ( ! empty( $param ) ) {
			$value = ( $param === 'unitNumber' )
				? ( $class_info['unit_number'] ?? '' )
				: ( $class_info['unit_id'] ?? '' );
			if ( $value !== '' ) {
				$url = add_query_arg( $param, $value, $url );
			}
		}

		return esc_url_raw( $url );
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
			$v1_special_id      = is_array( $v1_special ) ? sanitize_text_field( $v1_special['specialId'] ?? '' ) : '';
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
				'v1_special_id'      => $v1_special_id,
			];
		}

		return $data_map;
	}
}
