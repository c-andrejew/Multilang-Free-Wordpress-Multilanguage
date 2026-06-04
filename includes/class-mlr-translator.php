<?php
/**
 * String / inline translation.
 *
 * Two complementary syntaxes are supported:
 *
 *  1. Inline tokens (qTranslate style), great for titles, menus and ACF fields:
 *         [:de]Hallo Welt[:en]Hello world[:]
 *     The trailing [:] terminator lets several blocks live inside one string and
 *     keeps surrounding text intact, e.g.  Foo [:de]A[:en]B[:] bar.
 *     A value made up entirely of tokens may omit the terminator.
 *
 *  2. The [ml] shortcode for short strings inside the content/page builder:
 *         [ml de="Hallo" en="Hello"]
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MLR_Translator.
 */
class MLR_Translator {

	/**
	 * Router reference (for the active language).
	 *
	 * @var MLR_Router
	 */
	private $router;

	/**
	 * Constructor.
	 *
	 * @param MLR_Router $router Router.
	 */
	public function __construct( MLR_Router $router ) {
		$this->router = $router;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_shortcode( 'ml', array( $this, 'shortcode_ml' ) );

		add_filter( 'the_content', array( $this, 'translate_inline' ), 9 );
		add_filter( 'the_title', array( $this, 'translate_title' ), 9 );
		add_filter( 'the_excerpt', array( $this, 'translate_inline' ), 9 );
		add_filter( 'widget_text', array( $this, 'translate_inline' ), 9 );
		add_filter( 'widget_block_content', array( $this, 'translate_inline' ), 9 );
		add_filter( 'nav_menu_item_title', array( $this, 'translate_inline' ), 9 );
		add_filter( 'wp_nav_menu_items', array( $this, 'translate_inline' ), 9 );
		add_filter( 'get_the_archive_title', array( $this, 'translate_inline' ), 9 );
		add_filter( 'term_description', array( $this, 'translate_inline' ), 9 );
		add_filter( 'single_post_title', array( $this, 'translate_inline' ), 9 );
		add_filter( 'document_title_parts', array( $this, 'translate_title_parts' ) );
	}

	/**
	 * [ml de="..." en="..."] shortcode.
	 *
	 * @param array  $atts    Attributes keyed by language code.
	 * @param string $content Ignored.
	 * @return string
	 */
	public function shortcode_ml( $atts, $content = '' ) {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}
		$atts = array_change_key_case( $atts, CASE_LOWER );

		$code = $this->router->current;
		$main = MLR_Languages::main_code();

		if ( '' !== $code && isset( $atts[ $code ] ) ) {
			return wp_kses_post( $atts[ $code ] );
		}
		if ( '' !== $main && isset( $atts[ $main ] ) ) {
			return wp_kses_post( $atts[ $main ] );
		}
		foreach ( $atts as $key => $value ) {
			if ( ctype_alpha( (string) $key ) && strlen( (string) $key ) <= 5 ) {
				return wp_kses_post( $value );
			}
		}
		return '';
	}

	/**
	 * Resolve inline [:xx]...[:] tokens for the active language.
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	public function translate_inline( $text ) {
		if ( ! is_string( $text ) || false === strpos( $text, '[:' ) ) {
			return $text;
		}

		// 1) Replace every terminated run: [:de]A[:en]B[:]
		$text = preg_replace_callback(
			'/((?:\[:[a-z]{2,5}\].*?)+)\[:\]/su',
			array( $this, 'pick_from_run' ),
			$text
		);

		if ( false === strpos( $text, '[:' ) ) {
			return $text;
		}

		// 2) Whole-value token set without a terminator (e.g. a title or ACF field).
		//    Only treat it as a translation block if nothing but tokens remains.
		$remainder = preg_replace( '/\[:[a-z]{2,5}\].*?(?=\[:[a-z]{2,5}\]|$)/su', '', $text );
		if ( '' === trim( $remainder ) ) {
			return $this->pick_from_run( array( 1 => $text ) );
		}

		return $text;
	}

	/**
	 * Pick the active-language chunk from a token run.
	 *
	 * @param array $matches preg matches; index 1 holds the run.
	 * @return string
	 */
	public function pick_from_run( $matches ) {
		$run = isset( $matches[1] ) ? $matches[1] : '';

		if ( ! preg_match_all( '/\[:([a-z]{2,5})\](.*?)(?=\[:[a-z]{2,5}\]|\[:\]|$)/su', $run, $found, PREG_SET_ORDER ) ) {
			return $run;
		}

		$map = array();
		foreach ( $found as $item ) {
			$map[ strtolower( $item[1] ) ] = $item[2];
		}
		if ( empty( $map ) ) {
			return $run;
		}

		$code = $this->router->current;
		$main = MLR_Languages::main_code();

		if ( '' !== $code && isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}
		if ( '' !== $main && isset( $map[ $main ] ) ) {
			return $map[ $main ];
		}
		return reset( $map );
	}

	/**
	 * Translate a title, but never inside the admin editor.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public function translate_title( $title ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $title;
		}
		return $this->translate_inline( $title );
	}

	/**
	 * Translate the <title> document parts.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function translate_title_parts( $parts ) {
		foreach ( $parts as $key => $value ) {
			if ( is_string( $value ) ) {
				$parts[ $key ] = $this->translate_inline( $value );
			}
		}
		return $parts;
	}
}
