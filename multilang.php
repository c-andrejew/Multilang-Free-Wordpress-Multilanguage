<?php
/**
 * Plugin Name:       Multilang
 * Plugin URI:        https://example.com/multilang
 * Description:       Lightweight, fast multilingual system with per-language URL prefixes (e.g. /de/), a modern language switcher shortcode, and WPBakery + ACF compatible translatable text. Configure each language with an uploadable flag, full name, code and a main-language flag.
 * Version:           1.0.0
 * Author:            Christopher Andrejew
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       multilang
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'MLR_VERSION', '1.0.0' );
define( 'MLR_FILE', __FILE__ );
define( 'MLR_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLR_URL', plugin_dir_url( __FILE__ ) );
define( 'MLR_BASENAME', plugin_basename( __FILE__ ) );

require_once MLR_DIR . 'includes/class-mlr-languages.php';
require_once MLR_DIR . 'includes/class-mlr-router.php';
require_once MLR_DIR . 'includes/class-mlr-translator.php';
require_once MLR_DIR . 'includes/class-mlr-switcher.php';
require_once MLR_DIR . 'includes/class-mlr-wpbakery.php';
require_once MLR_DIR . 'includes/class-mlr-acf.php';
require_once MLR_DIR . 'includes/class-mlr-admin.php';
require_once MLR_DIR . 'includes/class-mlr-plugin.php';
require_once MLR_DIR . 'includes/helpers.php';

/**
 * Main accessor for the plugin instance.
 *
 * @return MLR_Plugin
 */
function MLR() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return MLR_Plugin::instance();
}

// Boot.
MLR();

/**
 * Activation: make sure rewrite rules that include the language prefix are written.
 */
register_activation_hook(
	MLR_FILE,
	function () {
		// Default settings on first install.
		if ( false === get_option( MLR_Languages::SETTINGS, false ) ) {
			update_option( MLR_Languages::SETTINGS, array( 'redirect' => 1, 'browser_detect' => 1 ) );
		}
		// Router hooks are already registered by MLR(); flush to bake in prefixed rules.
		flush_rewrite_rules();
	}
);

/**
 * Deactivation: drop the language-prefixed rewrite rules again.
 */
register_deactivation_hook(
	MLR_FILE,
	function () {
		flush_rewrite_rules();
	}
);
