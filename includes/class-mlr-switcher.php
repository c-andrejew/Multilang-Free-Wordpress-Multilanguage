<?php
/**
 * Language switcher shortcode: [multilang_switcher].
 *
 * Renders the active language (flag + name) as a button that opens a small,
 * modern dropdown of the other languages. Each entry links to the same page
 * in the target language (the URL only swaps the language prefix).
 *
 * Attributes:
 *   show_flag  "1"|"0"  (default 1)
 *   show_name  "1"|"0"  (default 1)
 *   show_code  "1"|"0"  (default 0)  e.g. "DE"
 *   dropdown   "1"|"0"  (default 1)  0 = render an inline row of links
 *   align      left|right (default left) which edge the menu aligns to
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MLR_Switcher.
 */
class MLR_Switcher {

	/**
	 * Router reference.
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
		add_shortcode( 'multilang_switcher', array( $this, 'render' ) );
		add_shortcode( 'mlr_switcher', array( $this, 'render' ) ); // Alias.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (but do not enqueue) front-end assets.
	 */
	public function register_assets() {
		wp_register_style( 'mlr-switcher', MLR_URL . 'assets/css/switcher.css', array(), MLR_VERSION );
		wp_register_script( 'mlr-switcher', MLR_URL . 'assets/js/switcher.js', array(), MLR_VERSION, true );
	}

	/**
	 * Render the switcher.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$langs = MLR_Languages::all();
		if ( count( $langs ) < 2 ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'show_flag' => '1',
				'show_name' => '1',
				'show_code' => '0',
				'dropdown'  => '1',
				'align'     => 'left',
			),
			$atts,
			'multilang_switcher'
		);

		wp_enqueue_style( 'mlr-switcher' );
		wp_enqueue_script( 'mlr-switcher' );

		$current_code = '' !== $this->router->current ? $this->router->current : MLR_Languages::main_code();
		$current      = MLR_Languages::get( $current_code );
		if ( ! $current ) {
			$current      = $langs[0];
			$current_code = $current['code'];
		}

		$show_flag = '1' === (string) $atts['show_flag'];
		$show_name = '1' === (string) $atts['show_name'];
		$show_code = '1' === (string) $atts['show_code'];
		$dropdown  = '1' === (string) $atts['dropdown'];
		$align     = 'right' === $atts['align'] ? 'right' : 'left';

		$classes = 'mlr-switcher';
		$classes .= $dropdown ? '' : ' mlr-switcher--inline';
		$classes .= ' mlr-switcher--' . $align;

		$out  = '<div class="' . esc_attr( $classes ) . '">';

		if ( $dropdown ) {
			$out .= '<button type="button" class="mlr-switcher__toggle" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr__( 'Select language', 'multilang' ) . '">';
			$out .= $this->label_html( $current, $show_flag, $show_name, $show_code );
			$out .= '<span class="mlr-switcher__caret" aria-hidden="true"></span>';
			$out .= '</button>';

			$out .= '<ul class="mlr-switcher__menu" role="menu">';
			foreach ( $langs as $lang ) {
				$out .= $this->item_html( $lang, $current_code, $show_flag, $show_name, $show_code, 'menuitem' );
			}
			$out .= '</ul>';
		} else {
			$out .= '<ul class="mlr-switcher__list">';
			foreach ( $langs as $lang ) {
				$out .= $this->item_html( $lang, $current_code, $show_flag, $show_name, $show_code, '' );
			}
			$out .= '</ul>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * A single list item.
	 *
	 * @param array  $lang         Language.
	 * @param string $current_code Active code.
	 * @param bool   $show_flag    Show flag.
	 * @param bool   $show_name    Show name.
	 * @param bool   $show_code    Show code.
	 * @param string $role         ARIA role for the link.
	 * @return string
	 */
	private function item_html( $lang, $current_code, $show_flag, $show_name, $show_code, $role ) {
		$url       = $this->router->url_in_language( $lang['code'] );
		$is_active = ( $lang['code'] === $current_code );

		$attrs = ' class="mlr-switcher__link"';
		$attrs .= ' href="' . esc_url( $url ) . '"';
		$attrs .= ' hreflang="' . esc_attr( $lang['code'] ) . '"';
		$attrs .= ' lang="' . esc_attr( $lang['code'] ) . '"';
		if ( $role ) {
			$attrs .= ' role="' . esc_attr( $role ) . '"';
		}
		if ( $is_active ) {
			$attrs .= ' aria-current="true"';
		}

		$li_attr = $role ? ' role="none"' : '';

		return '<li' . $li_attr . '><a' . $attrs . '>'
			. $this->label_html( $lang, $show_flag, $show_name, $show_code )
			. '</a></li>';
	}

	/**
	 * Flag + name label markup.
	 *
	 * @param array $lang      Language.
	 * @param bool  $show_flag Show flag.
	 * @param bool  $show_name Show name.
	 * @param bool  $show_code Show code.
	 * @return string
	 */
	private function label_html( $lang, $show_flag, $show_name, $show_code ) {
		$html = '';
		if ( $show_flag ) {
			$html .= $this->flag_html( $lang );
		}
		if ( $show_name ) {
			$html .= '<span class="mlr-switcher__name">' . esc_html( $lang['name'] ) . '</span>';
		}
		if ( $show_code ) {
			$html .= '<span class="mlr-switcher__code">' . esc_html( strtoupper( $lang['code'] ) ) . '</span>';
		}
		return $html;
	}

	/**
	 * Flag image (or a code badge fallback).
	 *
	 * @param array $lang Language.
	 * @return string
	 */
	private function flag_html( $lang ) {
		if ( ! empty( $lang['flag'] ) ) {
			$img = wp_get_attachment_image(
				(int) $lang['flag'],
				array( 24, 18 ),
				false,
				array(
					'class'   => 'mlr-switcher__flag',
					'alt'     => $lang['name'],
					'loading' => 'lazy',
				)
			);
			if ( $img ) {
				return $img;
			}
		}
		return '<span class="mlr-switcher__flag mlr-switcher__flag--code">' . esc_html( strtoupper( $lang['code'] ) ) . '</span>';
	}
}
