<?php
/**
 * Language storage and access.
 *
 * Languages are stored in a single option as an ordered list. Each language:
 *   array(
 *     'code'   => 'de',      // 2-5 lowercase letters, used in the URL (/de/).
 *     'name'   => 'Deutsch', // Full display name.
 *     'locale' => 'de_DE',   // WordPress locale, used for hreflang / get_locale().
 *     'flag'   => 123,       // Attachment ID of the uploaded flag (0 = none).
 *     'order'  => 0,         // Sort order.
 *     'main'   => 1,         // 1 for the main language, else 0.
 *   )
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MLR_Languages.
 */
class MLR_Languages {

	const OPTION   = 'mlr_languages';
	const SETTINGS = 'mlr_settings';

	/**
	 * Runtime cache of the parsed language list.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Get all configured languages, ordered.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$clean = array();
		foreach ( $raw as $lang ) {
			if ( empty( $lang['code'] ) ) {
				continue;
			}
			$clean[] = array(
				'code'   => (string) $lang['code'],
				'name'   => isset( $lang['name'] ) ? (string) $lang['name'] : (string) $lang['code'],
				'locale' => isset( $lang['locale'] ) ? (string) $lang['locale'] : '',
				'flag'   => isset( $lang['flag'] ) ? (int) $lang['flag'] : 0,
				'order'  => isset( $lang['order'] ) ? (int) $lang['order'] : 0,
				'main'   => ! empty( $lang['main'] ) ? 1 : 0,
			);
		}

		usort(
			$clean,
			static function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);

		self::$cache = array_values( $clean );
		return self::$cache;
	}

	/**
	 * Plugin settings with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$defaults = array(
			'redirect'       => 1, // Redirect prefix-less front-end URLs to the active language.
			'browser_detect' => 1, // Use cookie / Accept-Language to pick the default language.
		);

		$saved = get_option( self::SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( $defaults, $saved );
	}

	/**
	 * Get a single language by code.
	 *
	 * @param string $code Language code.
	 * @return array|null
	 */
	public static function get( $code ) {
		foreach ( self::all() as $lang ) {
			if ( $lang['code'] === $code ) {
				return $lang;
			}
		}
		return null;
	}

	/**
	 * Whether a code is configured.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	public static function exists( $code ) {
		return null !== self::get( $code );
	}

	/**
	 * The main language code (falls back to the first configured language).
	 *
	 * @return string
	 */
	public static function main_code() {
		$all = self::all();
		foreach ( $all as $lang ) {
			if ( $lang['main'] ) {
				return $lang['code'];
			}
		}
		return ! empty( $all ) ? $all[0]['code'] : '';
	}

	/**
	 * All configured codes.
	 *
	 * @return string[]
	 */
	public static function codes() {
		return wp_list_pluck( self::all(), 'code' );
	}

	/**
	 * Persist languages and settings, then bust the cache.
	 *
	 * @param array $languages Sanitized language list.
	 * @param array $settings  Sanitized settings.
	 */
	public static function save( $languages, $settings ) {
		update_option( self::OPTION, $languages );
		update_option( self::SETTINGS, $settings );
		self::$cache = null;
	}

	/**
	 * Reset the runtime cache (used after option updates).
	 */
	public static function flush_cache() {
		self::$cache = null;
	}
}
