<?php
/**
 * Advanced Custom Fields integration.
 *
 * Any ACF field whose value contains inline [:xx]...[:] tokens is resolved to
 * the active language automatically when read with get_field()/the_field().
 * No extra configuration is required — just enter the translations into the
 * normal ACF field, e.g.:  [:de]Hallo[:en]Hello[:]
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MLR_ACF.
 */
class MLR_ACF {

	/**
	 * Translator reference.
	 *
	 * @var MLR_Translator
	 */
	private $translator;

	/**
	 * Constructor.
	 *
	 * @param MLR_Translator $translator Translator.
	 */
	public function __construct( MLR_Translator $translator ) {
		$this->translator = $translator;
	}

	/**
	 * Register hooks. The acf/format_value filter is simply never fired when ACF
	 * is not active, so it is safe to register unconditionally (and ACF may load
	 * after this runs).
	 */
	public function register_hooks() {
		add_filter( 'acf/format_value', array( $this, 'translate_value' ), 20, 3 );
	}

	/**
	 * Resolve inline tokens in formatted ACF values.
	 *
	 * @param mixed  $value    Field value.
	 * @param mixed  $post_id  Post ID.
	 * @param array  $field    Field settings.
	 * @return mixed
	 */
	public function translate_value( $value, $post_id, $field ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return $this->translator->translate_inline( $value );
		}

		if ( is_array( $value ) ) {
			$translator = $this->translator;
			array_walk_recursive(
				$value,
				static function ( &$item ) use ( $translator ) {
					if ( is_string( $item ) ) {
						$item = $translator->translate_inline( $item );
					}
				}
			);
		}

		return $value;
	}
}
