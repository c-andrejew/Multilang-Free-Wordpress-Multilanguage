<?php
/**
 * Core plugin wiring (singleton).
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MLR_Plugin.
 */
final class MLR_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MLR_Plugin|null
	 */
	private static $instance = null;

	/** @var MLR_Router */
	public $router;

	/** @var MLR_Translator */
	public $translator;

	/** @var MLR_Switcher */
	public $switcher;

	/** @var MLR_WPBakery */
	public $wpbakery;

	/** @var MLR_ACF */
	public $acf;

	/** @var MLR_Admin */
	public $admin;

	/**
	 * Get the singleton.
	 *
	 * @return MLR_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: build components and register hooks.
	 */
	private function __construct() {
		$this->router     = new MLR_Router();
		$this->translator = new MLR_Translator( $this->router );
		$this->switcher   = new MLR_Switcher( $this->router );
		$this->wpbakery   = new MLR_WPBakery( $this->router );
		$this->acf        = new MLR_ACF( $this->translator );
		$this->admin      = new MLR_Admin();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Detect the active language as early as possible so generated links are correct.
		add_action( 'plugins_loaded', array( $this->router, 'detect' ), 1 );

		$this->router->register_hooks();
		$this->translator->register_hooks();
		$this->switcher->register_hooks();
		$this->wpbakery->register_hooks();
		$this->acf->register_hooks();

		if ( is_admin() ) {
			$this->admin->register_hooks();
		}
	}

	/**
	 * Load translations for the plugin UI.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'multilang', false, dirname( MLR_BASENAME ) . '/languages' );
	}

	/**
	 * Current language code (e.g. "de").
	 *
	 * @return string
	 */
	public function current_code() {
		return $this->router->current;
	}

	/**
	 * Main language code.
	 *
	 * @return string
	 */
	public function main_code() {
		return MLR_Languages::main_code();
	}
}
