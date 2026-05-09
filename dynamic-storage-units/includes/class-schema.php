<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSU_Schema {

	public function __construct() {
		add_action( 'wp_head', [ $this, 'emit_json_ld' ], 20 );
	}

	public function emit_json_ld() {
		$settings = get_option( DSU_OPTION_SCHEMA, [] );

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'storage_units' ) ) {
			return;
		}

		$schema = $this->build_schema( $settings );
		if ( empty( $schema ) ) {
			return;
		}

		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo "\n" . '</script>' . "\n";
	}

	private function build_schema( $settings ) {
		// Pull live facility data from v1 API — used as fallback when manual fields are empty
		$api_settings  = get_option( DSU_OPTION_API, [] );
		$facility_code = sanitize_text_field( $api_settings['facility_code'] ?? '' );
		$api = [];
		if ( $facility_code ) {
			$client  = new DSU_API();
			$fetched = $client->get_facility_info( $facility_code );
			if ( ! is_wp_error( $fetched ) ) {
				$api = $fetched;
			}
		}

		// Parse v1 address and phone from API response
		$api_addr  = $api['address'] ?? $api['physicalAddress'] ?? [];
		$phone_obj = $api['phoneNumber'] ?? null;
		$api_phone = '';
		if ( is_array( $phone_obj ) ) {
			$cc        = trim( $phone_obj['countryCode'] ?? '' );
			$num       = trim( $phone_obj['number'] ?? '' );
			$api_phone = $cc ? $cc . $num : $num;
		} elseif ( is_string( $phone_obj ) ) {
			$api_phone = $phone_obj;
		}
		if ( empty( $api_phone ) ) {
			$api_phone = $api['phone'] ?? $api['telephone'] ?? '';
		}

		$api_name   = $api['name'] ?? $api['facilityName'] ?? '';
		$api_street = $api_addr['street1'] ?? $api_addr['street'] ?? $api_addr['address1'] ?? '';
		$api_city   = $api_addr['city'] ?? '';
		$api_state  = $api_addr['state'] ?? '';
		$api_zip    = $api_addr['postalCode'] ?? $api_addr['zip'] ?? '';
		$api_lat    = $api_addr['latitude']  ?? $api['latitude']  ?? '';
		$api_lng    = $api_addr['longitude'] ?? $api['longitude'] ?? '';

		// Derive amenities from API boolean flags (Appendix A, exact camelCase property names)
		$amenity_flag_map = [
			'electronicGateAccess'             => 'Electronic Gate Access',
			'fencedAndLighted'                 => 'Fenced and Lighted',
			'videoCamerasOnSite'               => 'Video Cameras on Site',
			'is24HourAccessAvailable'          => '24-Hour Access',
			'is24HourKioskAvailable'           => '24-Hour Kiosk',
			'is24HourManagerAvailable'         => '24-Hour Manager',
			'hasElevator'                      => 'Elevator',
			'hasLoadingDock'                   => 'Loading Dock',
			'availableHandcartsOrDollies'      => 'Handcarts / Dollies',
			'mailOrPackagesAcceptedForTenants' => 'Mail / Package Acceptance',
			'availableTruckRental'             => 'Truck Rental',
			'availableFreeTruckRental'         => 'Free Truck Rental',
			'movingSuppliesForSale'            => 'Moving Supplies for Sale',
			'emailInvoicingAvailable'          => 'Email Invoicing',
			'automaticPaymentsAvailable'       => 'Automatic Payments',
			'militaryDiscountsOffered'         => 'Military Discount',
			'seniorDiscountOffered'            => 'Senior Discount',
			'studentDiscountOffered'           => 'Student Discount',
			'insuranceAvailable'               => 'Insurance Available',
			'protectionPlanAvailable'          => 'Protection Plan Available',
			'washStationAvailable'             => 'Wash Station',
			'dumpStationAvailable'             => 'Dump Station',
		];
		$amenity_src   = ( ! empty( $api['amenities'] ) && is_array( $api['amenities'] ) )
			? $api['amenities']
			: $api;
		$api_amenities = [];
		foreach ( $amenity_flag_map as $flag => $label ) {
			if ( ! empty( $amenity_src[ $flag ] ) ) {
				$api_amenities[] = $label;
			}
		}

		// Payment flags are inside the amenities object per Appendix A
		$api_payment = [];
		if ( ! empty( $amenity_src['cashAccepted'] ) )  $api_payment[] = 'Cash';
		if ( ! empty( $amenity_src['checkAccepted'] ) ) $api_payment[] = 'Check';
		$cc_types = $amenity_src['creditCardTypesAccepted'] ?? $api['creditCardTypesAccepted'] ?? [];
		if ( is_array( $cc_types ) && ! empty( $cc_types ) ) {
			$api_payment[] = 'Credit Card';
		}

		// Build schema — manual settings override API values; amenities/payment are merged
		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'SelfStorage',
		];

		if ( ! empty( $settings['schema_id'] ) ) {
			$schema['@id'] = esc_url_raw( $settings['schema_id'] );
		}

		$name = ! empty( $settings['name'] ) ? $settings['name'] : $api_name;
		if ( $name ) {
			$schema['name'] = sanitize_text_field( $name );
		}

		$telephone = ! empty( $settings['telephone'] ) ? $settings['telephone'] : $api_phone;
		if ( $telephone ) {
			$schema['telephone'] = sanitize_text_field( $telephone );
		}

		$schema['url'] = ! empty( $settings['url'] ) ? esc_url_raw( $settings['url'] ) : get_site_url();

		if ( ! empty( $settings['description'] ) ) {
			$schema['description'] = sanitize_textarea_field( $settings['description'] );
		}
		if ( ! empty( $settings['image_url'] ) ) {
			$schema['image'] = esc_url_raw( $settings['image_url'] );
		}

		// Address: per-field fallback from API when manual field is empty
		$addr_merged = [
			'street_address'   => ! empty( $settings['street_address'] )   ? $settings['street_address']   : $api_street,
			'address_locality' => ! empty( $settings['address_locality'] )  ? $settings['address_locality'] : $api_city,
			'address_region'   => ! empty( $settings['address_region'] )    ? $settings['address_region']   : $api_state,
			'postal_code'      => ! empty( $settings['postal_code'] )        ? $settings['postal_code']      : $api_zip,
			'address_country'  => ! empty( $settings['address_country'] )   ? $settings['address_country']  : 'US',
		];
		$addr = $this->build_address( $addr_merged );
		if ( $addr ) {
			$schema['address'] = $addr;
		}

		$lat = ! empty( $settings['latitude'] )  ? $settings['latitude']  : $api_lat;
		$lng = ! empty( $settings['longitude'] ) ? $settings['longitude'] : $api_lng;
		if ( $lat && $lng ) {
			$schema['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			];
		}

		$hours = $this->build_opening_hours( $settings['hours'] ?? [] );
		if ( ! empty( $hours ) ) {
			$schema['openingHoursSpecification'] = $hours;
		}

		// Payment: API-derived values merged with manual selections
		$all_payment = array_values( array_unique( array_merge( $api_payment, (array) ( $settings['payment_accepted'] ?? [] ) ) ) );
		if ( ! empty( $all_payment ) ) {
			$schema['paymentAccepted'] = implode( ', ', $all_payment );
		}

		// Amenities sourced entirely from API boolean flags
		$amenity_specs = $this->build_amenities( $api_amenities );
		if ( ! empty( $amenity_specs ) ) {
			$schema['amenityFeature'] = $amenity_specs;
		}

		$catalog = $this->build_offer_catalog( $settings );
		if ( $catalog ) {
			$schema['hasOfferCatalog'] = $catalog;
		}

		return $schema;
	}

	private function build_address( $settings ) {
		$field_map = [
			'street_address'   => 'streetAddress',
			'address_locality' => 'addressLocality',
			'address_region'   => 'addressRegion',
			'postal_code'      => 'postalCode',
			'address_country'  => 'addressCountry',
		];

		$addr = [ '@type' => 'PostalAddress' ];
		$has  = false;

		foreach ( $field_map as $key => $prop ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$addr[ $prop ] = sanitize_text_field( $settings[ $key ] );
				$has           = true;
			}
		}

		return $has ? $addr : null;
	}

	private function build_opening_hours( $hours_settings ) {
		if ( empty( $hours_settings ) || ! is_array( $hours_settings ) ) {
			return [];
		}

		$day_uris = [
			'Monday'    => 'https://schema.org/Monday',
			'Tuesday'   => 'https://schema.org/Tuesday',
			'Wednesday' => 'https://schema.org/Wednesday',
			'Thursday'  => 'https://schema.org/Thursday',
			'Friday'    => 'https://schema.org/Friday',
			'Saturday'  => 'https://schema.org/Saturday',
			'Sunday'    => 'https://schema.org/Sunday',
		];

		$specs = [];
		foreach ( $day_uris as $day => $uri ) {
			$day_data = $hours_settings[ $day ] ?? [];
			if ( empty( $day_data['enabled'] ) ) {
				continue;
			}
			$specs[] = [
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $uri,
				'opens'     => sanitize_text_field( $day_data['opens']  ?? '00:00' ),
				'closes'    => sanitize_text_field( $day_data['closes'] ?? '23:59' ),
			];
		}

		return $specs;
	}

	private function build_amenities( $amenities ) {
		if ( empty( $amenities ) || ! is_array( $amenities ) ) {
			return [];
		}

		return array_map( function ( $amenity ) {
			return [
				'@type' => 'LocationFeatureSpecification',
				'name'  => sanitize_text_field( $amenity ),
				'value' => true,
			];
		}, $amenities );
	}

	private function build_offer_catalog( $settings ) {
		$api_settings  = get_option( DSU_OPTION_API, [] );
		$facility_code = sanitize_text_field( $api_settings['facility_code'] ?? '' );

		if ( empty( $facility_code ) ) {
			return null;
		}

		$api    = new DSU_API();
		$groups = $api->get_unit_groups( $facility_code );

		if ( is_wp_error( $groups ) || empty( $groups ) ) {
			return null;
		}

		// Build a v1 lookup by unitGroupId for dimensions and features
		$v1_lookup = [];
		$v1_raw    = $api->get_v1_unit_groups( $facility_code );
		if ( ! is_wp_error( $v1_raw ) && is_array( $v1_raw ) ) {
			foreach ( $v1_raw as $v1g ) {
				$v1gid = $v1g['unitGroupId'] ?? $v1g['id'] ?? '';
				if ( $v1gid ) {
					$v1_lookup[ $v1gid ] = $v1g;
				}
			}
		}

		$items = [];

		foreach ( $groups as $group ) {
			$group_id    = $group['id'] ?? '';
			$label       = sanitize_text_field( $group['label'] ?? '' );
			$street_rate = (float) ( $group['streetRate'] ?? 0 );
			$avail_total = (int) ( $group['availableTotal'] ?? 0 );

			// Dimensions and features come from v1
			$v1g       = $v1_lookup[ $group_id ] ?? [];
			$feats_obj = is_array( $v1g['features'] ?? null ) ? $v1g['features'] : [];
			$width     = (float) ( $feats_obj['Width'] ?? 0 );
			$depth     = (float) ( $feats_obj['Depth'] ?? 0 );
			$sqft      = (float) ( $v1g['areaInSquareFeet'] ?? ( $width > 0 && $depth > 0 ? round( $width * $depth ) : 0 ) );
			$features  = is_array( $v1g['featuredFeatures'] ?? null ) ? $v1g['featuredFeatures'] : [];

			if ( empty( $label ) ) {
				continue;
			}

			$desc_parts = [];
			if ( $sqft > 0 ) {
				$desc_parts[] = number_format( $sqft, 0 ) . ' sq ft';
			}
			if ( ! empty( $features ) ) {
				$desc_parts[] = implode( ', ', array_map( 'sanitize_text_field', (array) $features ) );
			}

			$v1_price    = (float) ( $v1g['regularPrice'] ?? 0 );
			$v1_sp_obj   = $v1g['availableSpecial'] ?? null;
			$v1_sp_price = is_array( $v1_sp_obj ) ? (float) ( $v1_sp_obj['specialPrice'] ?? 0 ) : 0;
			if ( $v1_price > 0 ) {
				$street_rate = $v1_price;
			}
			$offer_price = ( $v1_sp_price > 0 && $v1_sp_price < $street_rate ) ? $v1_sp_price : $street_rate;

			$item = [
				'@type'         => 'Offer',
				'itemOffered'   => [
					'@type' => 'Product',
					'name'  => $label,
				],
				'price'         => number_format( $offer_price, 2, '.', '' ),
				'priceCurrency' => 'USD',
				'availability'  => $avail_total > 0
					? 'https://schema.org/InStock'
					: 'https://schema.org/OutOfStock',
			];

			if ( ! empty( $desc_parts ) ) {
				$item['itemOffered']['description'] = implode( '. ', $desc_parts );
			}

			$items[] = $item;
		}

		if ( empty( $items ) ) {
			return null;
		}

		$facility_name = sanitize_text_field( $settings['name'] ?? '' );

		return [
			'@type'           => 'OfferCatalog',
			'name'            => $facility_name ? $facility_name . ' Storage Units' : 'Storage Units',
			'itemListElement' => $items,
		];
	}
}
