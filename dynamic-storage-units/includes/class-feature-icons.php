<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DSU_Feature_Icons {

	private static $svgs = [
		'drive_up'           => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17H3v-5l2-5h14l2 5v5h-2"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/><path d="M5 12h14"/></svg>',
		'roll_up_door'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="13" x2="21" y2="13"/><line x1="3" y1="17" x2="21" y2="17"/></svg>',
		'access_controlled'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"/></svg>',
		'video_surveillance' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
		'extra_wide'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
		'climate'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>',
		'ground_floor'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/><line x1="3" y1="19" x2="21" y2="19"/></svg>',
		'outdoor'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
		'default'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
	];

	private static $keywords = [
		'drive_up'           => [ 'drive' ],
		'roll_up_door'       => [ 'roll' ],
		'access_controlled'  => [ 'access control', 'keypad', 'gate' ],
		'video_surveillance' => [ 'surveillance', 'camera', 'video', 'cctv' ],
		'extra_wide'         => [ 'extra wide', 'extra-wide', 'premium' ],
		'climate'            => [ 'climate', 'temperature', 'heated', 'cooled' ],
		'ground_floor'       => [ 'ground floor', 'ground-floor', 'ground level' ],
		'outdoor'            => [ 'outdoor', 'exterior', 'boat', 'rv ', 'vehicle' ],
	];

	public static function match( $feature_text ) {
		$lower = strtolower( $feature_text );
		foreach ( self::$keywords as $slug => $words ) {
			foreach ( $words as $kw ) {
				if ( strpos( $lower, $kw ) !== false ) {
					return $slug;
				}
			}
		}
		return 'default';
	}

	public static function get_emoji( $feature_text ) {
		static $cache = null;
		if ( $cache === null ) {
			$cache = get_option( DSU_OPTION_FEATURE_ICONS, [] );
		}
		return is_array( $cache ) ? ( $cache[ $feature_text ] ?? '' ) : '';
	}

	public static function render_tile( $feature_text ) {
		// Detect emoji prefix already in the string: e.g. "🚗 Drive-up access"
		if ( preg_match( '/^(\X{1,3})\s+(.+)$/u', $feature_text, $m )
			&& mb_strlen( $m[1], 'UTF-8' ) < strlen( $m[1] ) ) {
			return '<div class="dsu-feat-tile dsu-feat-tile--emoji" role="listitem">'
				. '<span class="dsu-feat-emoji" aria-hidden="true">' . esc_html( $m[1] ) . '</span>'
				. '<span>' . esc_html( $m[2] ) . '</span>'
				. '</div>';
		}

		// Look up saved emoji for this feature string
		$emoji = self::get_emoji( $feature_text );
		if ( $emoji !== '' ) {
			return '<div class="dsu-feat-tile dsu-feat-tile--emoji" role="listitem">'
				. '<span class="dsu-feat-emoji" aria-hidden="true">' . esc_html( $emoji ) . '</span>'
				. '<span>' . esc_html( $feature_text ) . '</span>'
				. '</div>';
		}

		// Plain text — no icon
		return '<div class="dsu-feat-tile dsu-feat-tile--plain" role="listitem">'
			. '<span>' . esc_html( $feature_text ) . '</span>'
			. '</div>';
	}

	public static function js_icon_map() {
		$map = [];
		foreach ( self::$keywords as $slug => $words ) {
			$map[ $slug ] = [
				'keywords' => $words,
				'svg'      => self::$svgs[ $slug ] ?? self::$svgs['default'],
				'color'    => $slug === 'extra_wide' ? 'gold' : 'blue',
			];
		}
		$map['default'] = [
			'keywords' => [],
			'svg'      => self::$svgs['default'],
			'color'    => 'blue',
		];
		return $map;
	}
}
