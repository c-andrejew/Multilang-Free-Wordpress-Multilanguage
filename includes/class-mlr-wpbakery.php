<?php
/**
 * WPBakery Page Builder integration.
 *
 * Registers three elements (all are also plain shortcodes, so they render fine
 * even without WPBakery installed):
 *
 *   [ml_text  tag="div" text_de="…"  text_en="…"]   – multiline text per language
 *   [ml_title tag="h2"  title_de="…" title_en="…"]  – heading per language (+ link)
 *   [ml_acf   field_name="subtitle"]                 – outputs a translated ACF field
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MLR_WPBakery.
 */
class MLR_WPBakery {

	/**
	 * Allowed wrapper tags.
	 *
	 * @var string[]
	 */
	private $allowed_tags = array( 'div', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

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
		add_shortcode( 'ml_text', array( $this, 'render' ) );
		add_shortcode( 'ml_title', array( $this, 'render_title' ) );
		add_shortcode( 'ml_acf', array( $this, 'render_acf' ) );
		add_action( 'vc_before_init', array( $this, 'map_elements' ) );
	}

	/* --------------------------------------------------------------------- *
	 * WPBakery mapping
	 * --------------------------------------------------------------------- */

	/**
	 * Map all elements into WPBakery.
	 */
	public function map_elements() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}
		$this->map_text();
		$this->map_title();
		$this->map_acf();
	}

	/**
	 * "Multilang Text": one textarea per language.
	 */
	private function map_text() {
		$params = array( $this->tag_param( 'div' ) );

		foreach ( MLR_Languages::all() as $lang ) {
			$params[] = array(
				'type'        => 'textarea',
				/* translators: %s: language name. */
				'heading'     => sprintf( __( 'Text (%s)', 'multilang' ), $lang['name'] ),
				'param_name'  => 'text_' . $lang['code'],
				'description' => strtoupper( $lang['code'] ),
			);
		}

		$params = array_merge( $params, $this->design_params() );

		vc_map(
			array(
				'name'        => __( 'Multilang Text', 'multilang' ),
				'base'        => 'ml_text',
				'icon'        => 'icon-wpb-layer-shape',
				'category'    => __( 'Multilang', 'multilang' ),
				'description' => __( 'Text with a field per configured language.', 'multilang' ),
				'params'      => $params,
			)
		);
	}

	/**
	 * "Multilang Title": one single-line field per language, with tag, alignment and link.
	 */
	private function map_title() {
		$params = array( $this->tag_param( 'h2' ) );

		foreach ( MLR_Languages::all() as $lang ) {
			$params[] = array(
				'type'        => 'textfield',
				/* translators: %s: language name. */
				'heading'     => sprintf( __( 'Title (%s)', 'multilang' ), $lang['name'] ),
				'param_name'  => 'title_' . $lang['code'],
				'description' => strtoupper( $lang['code'] ),
			);
		}

		$params[] = array(
			'type'       => 'dropdown',
			'heading'    => __( 'Alignment', 'multilang' ),
			'param_name' => 'align',
			'value'      => array(
				__( 'Inherit', 'multilang' ) => '',
				__( 'Left', 'multilang' )    => 'left',
				__( 'Center', 'multilang' )  => 'center',
				__( 'Right', 'multilang' )   => 'right',
			),
			'std'        => '',
		);
		$params[] = array(
			'type'        => 'vc_link',
			'heading'     => __( 'Link', 'multilang' ),
			'param_name'  => 'link',
			'description' => __( 'Optional link wrapped around the title.', 'multilang' ),
		);

		$params = array_merge( $params, $this->design_params() );

		vc_map(
			array(
				'name'        => __( 'Multilang Title', 'multilang' ),
				'base'        => 'ml_title',
				'icon'        => 'icon-wpb-layer-shape',
				'category'    => __( 'Multilang', 'multilang' ),
				'description' => __( 'Heading with a field per configured language.', 'multilang' ),
				'params'      => $params,
			)
		);
	}

	/**
	 * "Multilang ACF": output a (token-translated) ACF field.
	 */
	private function map_acf() {
		$params = array(
			array(
				'type'        => 'textfield',
				'heading'     => __( 'ACF field name or key', 'multilang' ),
				'param_name'  => 'field_name',
				'description' => __( 'The field name (e.g. subtitle) or field key (field_xxx).', 'multilang' ),
				'admin_label' => true,
			),
			array(
				'type'        => 'textfield',
				'heading'     => __( 'Object ID', 'multilang' ),
				'param_name'  => 'post_id',
				'description' => __( 'Leave empty for the current post. Use e.g. "option" for options pages, "123", "user_5", "term_8".', 'multilang' ),
			),
			$this->tag_param( 'div' ),
			array(
				'type'       => 'checkbox',
				'heading'    => __( 'Auto-paragraphs', 'multilang' ),
				'param_name' => 'autop',
				'value'      => array( __( 'Apply wpautop() to the value', 'multilang' ) => 'yes' ),
			),
		);

		$params = array_merge( $params, $this->design_params() );

		vc_map(
			array(
				'name'        => __( 'Multilang ACF', 'multilang' ),
				'base'        => 'ml_acf',
				'icon'        => 'icon-wpb-layer-shape',
				'category'    => __( 'Multilang', 'multilang' ),
				'description' => __( 'Output an ACF field; inline translations resolve automatically.', 'multilang' ),
				'params'      => $params,
			)
		);
	}

	/**
	 * Shared "tag" dropdown param.
	 *
	 * @param string $std Default tag.
	 * @return array
	 */
	private function tag_param( $std ) {
		$values = array();
		foreach ( $this->allowed_tags as $tag ) {
			$values[ $tag ] = $tag;
		}
		return array(
			'type'        => 'dropdown',
			'heading'     => __( 'Wrapper tag', 'multilang' ),
			'param_name'  => 'tag',
			'value'       => $values,
			'std'         => $std,
			'description' => __( 'HTML tag used to wrap the output.', 'multilang' ),
		);
	}

	/**
	 * Shared design params (extra class + CSS box).
	 *
	 * @return array
	 */
	private function design_params() {
		return array(
			array(
				'type'       => 'textfield',
				'heading'    => __( 'Extra CSS class', 'multilang' ),
				'param_name' => 'el_class',
				'group'      => __( 'Design', 'multilang' ),
			),
			array(
				'type'       => 'css_editor',
				'heading'    => __( 'CSS box', 'multilang' ),
				'param_name' => 'css',
				'group'      => __( 'Design', 'multilang' ),
			),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Rendering
	 * --------------------------------------------------------------------- */

	/**
	 * Render "Multilang Text".
	 *
	 * @param array  $atts    Attributes.
	 * @param string $content Ignored.
	 * @return string
	 */
	public function render( $atts, $content = '' ) {
		$atts  = is_array( $atts ) ? $atts : array();
		$value = $this->value_for_language( $atts, 'text_' );

		if ( '' === trim( $value ) ) {
			return '';
		}

		$tag   = $this->resolve_tag( $atts, 'div' );
		$class = $this->resolve_class( $atts, 'mlr-text' );

		$value = wpautop( do_shortcode( wp_kses_post( $value ) ) );

		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tag, esc_attr( $class ), $value );
	}

	/**
	 * Render "Multilang Title".
	 *
	 * @param array  $atts    Attributes.
	 * @param string $content Ignored.
	 * @return string
	 */
	public function render_title( $atts, $content = '' ) {
		$atts  = is_array( $atts ) ? $atts : array();
		$value = $this->value_for_language( $atts, 'title_' );

		if ( '' === trim( wp_strip_all_tags( $value ) ) ) {
			return '';
		}

		$tag   = $this->resolve_tag( $atts, 'h2' );
		$class = $this->resolve_class( $atts, 'mlr-title' );

		$style = '';
		$align = isset( $atts['align'] ) ? $atts['align'] : '';
		if ( in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$style = ' style="text-align:' . esc_attr( $align ) . '"';
		}

		$inner = esc_html( $value );
		$inner = $this->maybe_wrap_link( $inner, $atts );

		return sprintf( '<%1$s class="%2$s"%3$s>%4$s</%1$s>', $tag, esc_attr( $class ), $style, $inner );
	}

	/**
	 * Render "Multilang ACF".
	 *
	 * @param array  $atts    Attributes.
	 * @param string $content Ignored.
	 * @return string
	 */
	public function render_acf( $atts, $content = '' ) {
		$atts = is_array( $atts ) ? $atts : array();

		if ( ! function_exists( 'get_field' ) ) {
			return '';
		}

		$field = isset( $atts['field_name'] ) ? sanitize_text_field( $atts['field_name'] ) : '';
		if ( '' === $field ) {
			return '';
		}

		$object_id = $this->sanitize_acf_object_id( isset( $atts['post_id'] ) ? $atts['post_id'] : '' );

		// Value is already token-translated by MLR_ACF via acf/format_value.
		$value = get_field( $field, $object_id );

		if ( is_array( $value ) ) {
			$scalars = array();
			array_walk_recursive(
				$value,
				static function ( $item ) use ( &$scalars ) {
					if ( is_scalar( $item ) ) {
						$scalars[] = (string) $item;
					}
				}
			);
			$value = implode( ', ', $scalars );
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = (string) $value;
		if ( '' === trim( $value ) ) {
			return '';
		}

		$value = wp_kses_post( $value );
		if ( isset( $atts['autop'] ) && 'yes' === $atts['autop'] ) {
			$value = wpautop( $value );
		}

		$tag   = $this->resolve_tag( $atts, 'div' );
		$class = $this->resolve_class( $atts, 'mlr-acf' );

		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tag, esc_attr( $class ), $value );
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Get the active-language value for a field prefix, with main fallback.
	 *
	 * @param array  $atts   Attributes.
	 * @param string $prefix Field prefix ("text_" / "title_").
	 * @return string
	 */
	private function value_for_language( $atts, $prefix ) {
		$code = '' !== $this->router->current ? $this->router->current : MLR_Languages::main_code();
		$main = MLR_Languages::main_code();

		if ( isset( $atts[ $prefix . $code ] ) && '' !== $atts[ $prefix . $code ] ) {
			return (string) $atts[ $prefix . $code ];
		}
		if ( isset( $atts[ $prefix . $main ] ) ) {
			return (string) $atts[ $prefix . $main ];
		}
		return '';
	}

	/**
	 * Resolve a safe wrapper tag.
	 *
	 * @param array  $atts    Attributes.
	 * @param string $default Default tag.
	 * @return string
	 */
	private function resolve_tag( $atts, $default ) {
		$tag = isset( $atts['tag'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( $atts['tag'] ) ) : $default;
		return in_array( $tag, $this->allowed_tags, true ) ? $tag : $default;
	}

	/**
	 * Build the element class string (base + el_class + WPBakery CSS box).
	 *
	 * @param array  $atts Attributes.
	 * @param string $base Base class.
	 * @return string
	 */
	private function resolve_class( $atts, $base ) {
		$classes = array( $base );
		if ( ! empty( $atts['el_class'] ) ) {
			$classes[] = preg_replace( '/[^A-Za-z0-9_ -]/', '', $atts['el_class'] );
		}
		if ( ! empty( $atts['css'] ) && function_exists( 'vc_shortcode_custom_css_class' ) ) {
			$classes[] = vc_shortcode_custom_css_class( $atts['css'] );
		}
		return trim( implode( ' ', array_filter( $classes ) ) );
	}

	/**
	 * Optionally wrap inner markup in a link built from the vc_link attribute.
	 *
	 * @param string $inner Inner markup (already escaped).
	 * @param array  $atts  Attributes.
	 * @return string
	 */
	private function maybe_wrap_link( $inner, $atts ) {
		if ( empty( $atts['link'] ) ) {
			return $inner;
		}

		$url    = '';
		$title  = '';
		$target = '';
		$rel    = '';

		if ( function_exists( 'vc_build_link' ) ) {
			$link   = vc_build_link( $atts['link'] );
			$url    = isset( $link['url'] ) ? $link['url'] : '';
			$title  = isset( $link['title'] ) ? $link['title'] : '';
			$target = isset( $link['target'] ) ? trim( $link['target'] ) : '';
			$rel    = isset( $link['rel'] ) ? trim( $link['rel'] ) : '';
		} else {
			// Fallback: treat the attribute as a plain URL.
			$url = $atts['link'];
		}

		$url = esc_url( $url );
		if ( '' === $url ) {
			return $inner;
		}

		$attr = ' href="' . $url . '"';
		if ( '' !== $title ) {
			$attr .= ' title="' . esc_attr( $title ) . '"';
		}
		if ( '_blank' === $target ) {
			$attr .= ' target="_blank"';
			$rel   = trim( $rel . ' noopener' );
		}
		if ( '' !== $rel ) {
			$attr .= ' rel="' . esc_attr( $rel ) . '"';
		}

		return '<a' . $attr . '>' . $inner . '</a>';
	}

	/**
	 * Sanitize the ACF object id (post id, "option", "user_5", "term_8"…).
	 *
	 * @param string $raw Raw value.
	 * @return int|string|false
	 */
	private function sanitize_acf_object_id( $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( '' === $raw ) {
			return false; // ACF uses the current object.
		}
		if ( ctype_digit( $raw ) ) {
			return (int) $raw;
		}
		return preg_replace( '/[^a-z0-9_]/', '', strtolower( $raw ) );
	}
}
