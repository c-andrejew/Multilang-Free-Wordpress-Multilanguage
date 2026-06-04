<?php
/**
 * Template helper functions for theme / plugin developers.
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mlr_current_language' ) ) {
	/**
	 * Active language code (e.g. "de").
	 *
	 * @return string
	 */
	function mlr_current_language() {
		return MLR()->current_code();
	}
}

if ( ! function_exists( 'mlr_main_language' ) ) {
	/**
	 * Main language code.
	 *
	 * @return string
	 */
	function mlr_main_language() {
		return MLR()->main_code();
	}
}

if ( ! function_exists( 'mlr_languages' ) ) {
	/**
	 * All configured languages.
	 *
	 * @return array
	 */
	function mlr_languages() {
		return MLR_Languages::all();
	}
}

if ( ! function_exists( 'mlr_url_in_language' ) ) {
	/**
	 * Build a URL in a specific language.
	 *
	 * @param string      $code Language code.
	 * @param string|null $url  URL or null for the current request.
	 * @return string
	 */
	function mlr_url_in_language( $code, $url = null ) {
		return MLR()->router->url_in_language( $code, $url );
	}
}

if ( ! function_exists( 'mlr_t' ) ) {
	/**
	 * Translate from an array keyed by language code.
	 *
	 * Example: echo mlr_t( array( 'de' => 'Hallo', 'en' => 'Hello' ) );
	 *
	 * @param array  $translations Map of code => string.
	 * @param string $fallback     Fallback when nothing matches.
	 * @return string
	 */
	function mlr_t( $translations, $fallback = '' ) {
		if ( ! is_array( $translations ) || empty( $translations ) ) {
			return $fallback;
		}
		$code = mlr_current_language();
		$main = mlr_main_language();

		if ( isset( $translations[ $code ] ) ) {
			return $translations[ $code ];
		}
		if ( isset( $translations[ $main ] ) ) {
			return $translations[ $main ];
		}
		$first = reset( $translations );
		return false !== $first ? $first : $fallback;
	}
}

if ( ! function_exists( 'mlr__' ) ) {
	/**
	 * Resolve inline [:xx]...[:] tokens in a string.
	 *
	 * @param string $text Text containing tokens.
	 * @return string
	 */
	function mlr__( $text ) {
		return MLR()->translator->translate_inline( $text );
	}
}

if ( ! function_exists( 'mlr_switcher' ) ) {
	/**
	 * Render the language switcher (for use in theme templates).
	 *
	 * @param array $atts Same attributes as the [multilang_switcher] shortcode.
	 * @return string
	 */
	function mlr_switcher( $atts = array() ) {
		return MLR()->switcher->render( $atts );
	}
}

if ( ! function_exists( 'mlr_is_current' ) ) {
	/**
	 * Whether the given code is the active language.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	function mlr_is_current( $code ) {
		return mlr_current_language() === $code;
	}
}
