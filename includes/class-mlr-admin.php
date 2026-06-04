<?php
/**
 * Admin: the Languages settings screen.
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MLR_Admin.
 */
class MLR_Admin {

	const PAGE_SLUG = 'mlr-languages';
	const NONCE     = 'mlr_save_languages';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_mlr_save_languages', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . MLR_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_notices', array( $this, 'config_notice' ) );
	}

	/**
	 * Add the top-level menu.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Languages', 'multilang' ),
			__( 'Languages', 'multilang' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-translation',
			76
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'multilang' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Enqueue admin assets only on our screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'mlr-admin', MLR_URL . 'assets/css/admin.css', array(), MLR_VERSION );
		wp_enqueue_script( 'mlr-admin', MLR_URL . 'assets/js/admin.js', array( 'jquery' ), MLR_VERSION, true );
		wp_localize_script(
			'mlr-admin',
			'MLR_ADMIN',
			array(
				'choose' => __( 'Choose flag image', 'multilang' ),
				'use'    => __( 'Use this flag', 'multilang' ),
			)
		);
	}

	/**
	 * Notice prompting configuration when no language exists yet.
	 */
	public function config_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'toplevel_page_' . self::PAGE_SLUG === $screen->id ) {
			return; // Don't nag on the settings screen itself.
		}
		if ( ! empty( MLR_Languages::all() ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Multilang: no languages configured yet.', 'multilang' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Add your languages', 'multilang' )
		);
	}

	/**
	 * Handle the settings form submission.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'multilang' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$input  = isset( $_POST['mlr'] ) ? wp_unslash( $_POST['mlr'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitized -- sanitized field by field below.
		$codes  = isset( $input['code'] ) && is_array( $input['code'] ) ? $input['code'] : array();
		$names  = isset( $input['name'] ) && is_array( $input['name'] ) ? $input['name'] : array();
		$locs   = isset( $input['locale'] ) && is_array( $input['locale'] ) ? $input['locale'] : array();
		$flags  = isset( $input['flag'] ) && is_array( $input['flag'] ) ? $input['flag'] : array();
		$main_i = isset( $_POST['mlr_main'] ) ? (int) $_POST['mlr_main'] : -1;

		$languages = array();
		$used      = array();

		foreach ( $codes as $i => $raw_code ) {
			$code = preg_replace( '/[^a-z]/', '', strtolower( sanitize_key( $raw_code ) ) );
			if ( strlen( $code ) < 2 || strlen( $code ) > 5 ) {
				continue;
			}
			if ( in_array( $code, $used, true ) ) {
				continue; // Skip duplicate codes.
			}
			$used[] = $code;

			$languages[] = array(
				'code'   => $code,
				'name'   => isset( $names[ $i ] ) ? sanitize_text_field( $names[ $i ] ) : $code,
				'locale' => isset( $locs[ $i ] ) ? preg_replace( '/[^A-Za-z_]/', '', $locs[ $i ] ) : '',
				'flag'   => isset( $flags[ $i ] ) ? absint( $flags[ $i ] ) : 0,
				'order'  => count( $languages ),
				'main'   => ( (int) $i === $main_i ) ? 1 : 0,
			);
		}

		// Guarantee exactly one main language.
		$has_main = false;
		foreach ( $languages as $lang ) {
			if ( $lang['main'] ) {
				$has_main = true;
				break;
			}
		}
		if ( ! $has_main && ! empty( $languages ) ) {
			$languages[0]['main'] = 1;
		}

		$settings = array(
			'redirect'       => ! empty( $_POST['mlr_settings']['redirect'] ) ? 1 : 0,
			'browser_detect' => ! empty( $_POST['mlr_settings']['browser_detect'] ) ? 1 : 0,
		);

		MLR_Languages::save( $languages, $settings );

		// Language codes changed -> rebuild prefixed rewrite rules.
		flush_rewrite_rules();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$languages = MLR_Languages::all();
		$settings  = MLR_Languages::settings();
		if ( empty( $languages ) ) {
			// Seed one blank row so the screen is usable immediately.
			$languages = array(
				array( 'code' => '', 'name' => '', 'locale' => '', 'flag' => 0, 'main' => 1 ),
			);
		}

		$updated = isset( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap mlr-wrap">
			<h1><?php esc_html_e( 'Languages', 'multilang' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Languages saved.', 'multilang' ); ?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Configure your languages. The main language defines the default URL, e.g. example.com/de/. Use the shortcode [multilang_switcher] to display the language switcher.', 'multilang' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mlr_save_languages" />
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="widefat mlr-table" id="mlr-languages-table">
					<thead>
						<tr>
							<th class="mlr-col-flag"><?php esc_html_e( 'Flag', 'multilang' ); ?></th>
							<th><?php esc_html_e( 'Language name', 'multilang' ); ?></th>
							<th class="mlr-col-code"><?php esc_html_e( 'Code (URL)', 'multilang' ); ?></th>
							<th class="mlr-col-locale"><?php esc_html_e( 'WP locale', 'multilang' ); ?></th>
							<th class="mlr-col-main"><?php esc_html_e( 'Main', 'multilang' ); ?></th>
							<th class="mlr-col-actions"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_values( $languages ) as $i => $lang ) : ?>
							<?php $this->render_row( $i, $lang ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button" id="mlr-add-language">
						<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle"></span>
						<?php esc_html_e( 'Add language', 'multilang' ); ?>
					</button>
				</p>

				<h2><?php esc_html_e( 'Settings', 'multilang' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Redirect', 'multilang' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="mlr_settings[redirect]" value="1" <?php checked( ! empty( $settings['redirect'] ) ); ?> />
								<?php esc_html_e( 'Redirect URLs without a language prefix to the active language (recommended).', 'multilang' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-detect', 'multilang' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="mlr_settings[browser_detect]" value="1" <?php checked( ! empty( $settings['browser_detect'] ) ); ?> />
								<?php esc_html_e( 'Pick the default language from the visitor cookie / browser settings (otherwise always use the main language).', 'multilang' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save changes', 'multilang' ) ); ?>
			</form>

			<div class="mlr-help card">
				<h2><?php esc_html_e( 'How to translate content', 'multilang' ); ?></h2>
				<ul>
					<li><strong><?php esc_html_e( 'Switcher:', 'multilang' ); ?></strong> <code>[multilang_switcher]</code></li>
					<li><strong><?php esc_html_e( 'Inline (titles, ACF, menus):', 'multilang' ); ?></strong> <code>[:de]Hallo[:en]Hello[:]</code></li>
					<li><strong><?php esc_html_e( 'Short string:', 'multilang' ); ?></strong> <code>[ml de="Hallo" en="Hello"]</code></li>
					<li><strong><?php esc_html_e( 'WPBakery:', 'multilang' ); ?></strong> <?php esc_html_e( 'add the "Multilang Text", "Multilang Title" or "Multilang ACF" element.', 'multilang' ); ?></li>
				</ul>
			</div>
		</div>

		<script type="text/html" id="mlr-row-template">
			<?php $this->render_row( '__INDEX__', array( 'code' => '', 'name' => '', 'locale' => '', 'flag' => 0, 'main' => 0 ) ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single table row.
	 *
	 * @param int|string $index Row index (or "__INDEX__" for the JS template).
	 * @param array      $lang  Language data.
	 */
	private function render_row( $index, $lang ) {
		$flag_id  = isset( $lang['flag'] ) ? (int) $lang['flag'] : 0;
		$flag_img = $flag_id ? wp_get_attachment_image( $flag_id, array( 32, 24 ) ) : '';
		$is_main  = ! empty( $lang['main'] );
		$radio_val = is_int( $index ) ? $index : 0;
		?>
		<tr class="mlr-row">
			<td class="mlr-flag-cell">
				<div class="mlr-flag-preview"><?php echo $flag_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image returns safe markup. ?></div>
				<input type="hidden" class="mlr-flag-input" name="mlr[flag][]" value="<?php echo esc_attr( $flag_id ); ?>" />
				<button type="button" class="button mlr-flag-choose"><?php esc_html_e( 'Choose', 'multilang' ); ?></button>
				<button type="button" class="button-link mlr-flag-remove"><?php esc_html_e( 'Remove', 'multilang' ); ?></button>
			</td>
			<td>
				<input type="text" class="regular-text" name="mlr[name][]" value="<?php echo esc_attr( $lang['name'] ); ?>" placeholder="Deutsch" />
			</td>
			<td>
				<input type="text" class="mlr-code-input" name="mlr[code][]" value="<?php echo esc_attr( $lang['code'] ); ?>" placeholder="de" maxlength="5" pattern="[A-Za-z]{2,5}" />
			</td>
			<td>
				<input type="text" name="mlr[locale][]" value="<?php echo esc_attr( $lang['locale'] ); ?>" placeholder="de_DE" />
			</td>
			<td class="mlr-main-cell">
				<input type="radio" class="mlr-main-radio" name="mlr_main" value="<?php echo esc_attr( $radio_val ); ?>" <?php checked( $is_main ); ?> />
			</td>
			<td class="mlr-actions-cell">
				<button type="button" class="button-link mlr-remove-row" aria-label="<?php esc_attr_e( 'Remove language', 'multilang' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</td>
		</tr>
		<?php
	}
}
