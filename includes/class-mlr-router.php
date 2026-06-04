<?php
/**
 * URL routing and language detection.
 *
 * Strategy:
 *  - Every WordPress rewrite rule is duplicated with a leading language prefix
 *    (^(de|en)/...), so /de/sample-page/ resolves natively just like /sample-page/.
 *  - All generated front-end links are prefixed by filtering home_url().
 *  - Because both the incoming URL and the canonical permalink carry the prefix,
 *    WordPress' canonical redirect does not fight us (no redirect loops).
 *  - Prefix-less front-end URLs are redirected to the active language.
 *
 * @package Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MLR_Router.
 */
class MLR_Router {

	/**
	 * Active language code for this request.
	 *
	 * @var string
	 */
	public $current = '';

	/**
	 * Whether the current request URL carried a language prefix.
	 *
	 * @var bool
	 */
	public $has_prefix = false;

	/**
	 * Cached configured codes.
	 *
	 * @var string[]
	 */
	private $codes = array();

	/**
	 * Home path for sub-directory installs (without surrounding slashes).
	 *
	 * @var string
	 */
	private $home_path = '';

	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks() {
		add_filter( 'rewrite_rules_array', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'wp', array( $this, 'sync_from_query' ) );

		add_filter( 'home_url', array( $this, 'filter_home_url' ), 10, 4 );
		add_filter( 'locale', array( $this, 'filter_locale' ) );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ) );

		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
		add_action( 'wp_head', array( $this, 'render_hreflang' ), 1 );
	}

	/**
	 * Detect the active language from the request URL (runs very early).
	 */
	public function detect() {
		$langs = MLR_Languages::all();
		if ( empty( $langs ) ) {
			return;
		}

		$this->codes     = MLR_Languages::codes();
		$this->home_path = trim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );

		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$parsed = wp_parse_url( $uri );
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';

		$rel = ltrim( $path, '/' );
		if ( '' !== $this->home_path && 0 === strpos( $rel, $this->home_path ) ) {
			$rel = ltrim( substr( $rel, strlen( $this->home_path ) ), '/' );
		}

		$segments = '' === $rel ? array() : explode( '/', $rel );
		$first    = isset( $segments[0] ) ? strtolower( $segments[0] ) : '';

		if ( '' !== $first && in_array( $first, $this->codes, true ) ) {
			$this->current    = $first;
			$this->has_prefix = true;
		} else {
			$this->current    = $this->resolve_default();
			$this->has_prefix = false;
		}

		$this->persist_cookie();
	}

	/**
	 * Resolve the default language from cookie, browser, then the main language.
	 *
	 * @return string
	 */
	private function resolve_default() {
		$settings = MLR_Languages::settings();

		if ( ! empty( $settings['browser_detect'] ) ) {
			if ( isset( $_COOKIE['mlr_lang'] ) ) {
				$cookie = sanitize_key( wp_unslash( $_COOKIE['mlr_lang'] ) );
				if ( in_array( $cookie, $this->codes, true ) ) {
					return $cookie;
				}
			}
			$browser = $this->from_browser();
			if ( '' !== $browser ) {
				return $browser;
			}
		}

		return MLR_Languages::main_code();
	}

	/**
	 * Match the browser Accept-Language header against configured codes.
	 *
	 * @return string
	 */
	private function from_browser() {
		if ( empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return '';
		}

		$accept = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) );
		foreach ( explode( ',', $accept ) as $chunk ) {
			// Drop the optional quality value (";q=0.8") and any region/script
			// subtag ("de-DE" -> "de"), then match the primary subtag against the
			// configured codes. Codes may be 2-5 letters, so don't hard-truncate.
			$chunk   = trim( $chunk );
			$semi    = strpos( $chunk, ';' );
			$chunk   = ( false !== $semi ) ? substr( $chunk, 0, $semi ) : $chunk;
			$primary = explode( '-', trim( $chunk ) )[0];
			if ( '' !== $primary && in_array( $primary, $this->codes, true ) ) {
				return $primary;
			}
		}

		return '';
	}

	/**
	 * Remember the active language in a cookie.
	 */
	private function persist_cookie() {
		if ( '' === $this->current || headers_sent() ) {
			return;
		}
		if ( isset( $_COOKIE['mlr_lang'] ) && sanitize_key( wp_unslash( $_COOKIE['mlr_lang'] ) ) === $this->current ) {
			return;
		}

		$expire = time() + MONTH_IN_SECONDS;
		$path   = COOKIEPATH ? COOKIEPATH : '/';

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				'mlr_lang',
				$this->current,
				array(
					'expires'  => $expire,
					'path'     => $path,
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
		} else {
			setcookie( 'mlr_lang', $this->current, $expire, $path, COOKIE_DOMAIN ? COOKIE_DOMAIN : '', is_ssl(), false );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Rewrite rules
	 * --------------------------------------------------------------------- */

	/**
	 * Duplicate every rewrite rule with a leading language prefix.
	 *
	 * @param array $rules Existing rules.
	 * @return array
	 */
	public function add_rewrite_rules( $rules ) {
		$codes = MLR_Languages::codes();
		if ( empty( $codes ) ) {
			return $rules;
		}

		// Codes are validated to [a-z]{2,5}, so they are safe inside the pattern.
		$slug = implode( '|', array_map( 'preg_quote', $codes ) );

		$prefixed = array();

		// Language root: /de/ -> front page in that language.
		$prefixed[ '^(' . $slug . ')/?$' ] = 'index.php?mlr_lang=$matches[1]';

		foreach ( $rules as $pattern => $query ) {
			// Shift every $matches[n] up by one to make room for the language capture.
			$shifted = preg_replace_callback(
				'/\$matches\[(\d+)\]/',
				static function ( $m ) {
					return '$matches[' . ( (int) $m[1] + 1 ) . ']';
				},
				$query
			);

			$shifted .= ( false !== strpos( $shifted, '?' ) ? '&' : '?' ) . 'mlr_lang=$matches[1]';

			$prefixed[ '^(' . $slug . ')/' . ltrim( $pattern, '^' ) ] = $shifted;
		}

		// Prefixed rules first; keep originals as a fallback for the default language.
		return $prefixed + $rules;
	}

	/**
	 * Whitelist the language query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = 'mlr_lang';
		return $vars;
	}

	/**
	 * Reconcile the detected language with the parsed query (authoritative).
	 */
	public function sync_from_query() {
		$from_query = get_query_var( 'mlr_lang' );
		if ( $from_query && MLR_Languages::exists( $from_query ) ) {
			$this->current    = $from_query;
			$this->has_prefix = true;
		}
	}

	/* --------------------------------------------------------------------- *
	 * Link generation
	 * --------------------------------------------------------------------- */

	/**
	 * Prefix front-end home URLs with the active language.
	 *
	 * @param string      $url    The complete home URL.
	 * @param string      $path   Path relative to the home URL.
	 * @param string|null $scheme Scheme.
	 * @param int|null    $blog_id Blog ID.
	 * @return string
	 */
	public function filter_home_url( $url, $path, $scheme, $blog_id ) {
		if ( '' === $this->current ) {
			return $url;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $url;
		}
		if ( $this->is_excluded_context() ) {
			return $url;
		}
		if ( in_array( $scheme, array( 'rest', 'json' ), true ) ) {
			return $url;
		}

		return $this->url_in_language( $this->current, $url );
	}

	/**
	 * Build the given URL in a specific language (defaults to the current request).
	 *
	 * @param string      $code Target language code.
	 * @param string|null $url  Full URL or null for the current request URL.
	 * @return string
	 */
	public function url_in_language( $code, $url = null ) {
		if ( null === $url ) {
			$url = $this->current_full_url();
		}

		$parsed = wp_parse_url( $url );
		if ( false === $parsed ) {
			return $url;
		}

		$path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		$base = '' !== $this->home_path ? '/' . $this->home_path : '';

		// Path relative to the home base.
		$rel = $path;
		if ( '' !== $base && 0 === strpos( $path, $base ) ) {
			$rel = substr( $path, strlen( $base ) );
		}
		$rel = '/' . ltrim( $rel, '/' );

		// Strip an existing language prefix to avoid doubling it.
		$segments = '/' === $rel ? array() : explode( '/', ltrim( $rel, '/' ) );
		if ( ! empty( $segments ) && in_array( strtolower( $segments[0] ), $this->codes(), true ) ) {
			array_shift( $segments );
			$rel = $segments ? '/' . implode( '/', $segments ) : '/';
		}

		$trailing = ( '/' === substr( $path, -1 ) );
		$new_path = $base . '/' . $code . ( '/' === $rel ? '/' : $rel );
		$new_path = preg_replace( '#//+#', '/', $new_path );
		if ( $trailing && '/' !== substr( $new_path, -1 ) ) {
			$new_path .= '/';
		}

		// Reassemble.
		if ( empty( $parsed['host'] ) ) {
			$out = $new_path;
		} else {
			$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] : ( is_ssl() ? 'https' : 'http' );
			$out    = $scheme . '://' . $parsed['host'];
			if ( isset( $parsed['port'] ) ) {
				$out .= ':' . $parsed['port'];
			}
			$out .= $new_path;
		}
		if ( isset( $parsed['query'] ) && '' !== $parsed['query'] ) {
			$out .= '?' . $parsed['query'];
		}
		if ( isset( $parsed['fragment'] ) && '' !== $parsed['fragment'] ) {
			$out .= '#' . $parsed['fragment'];
		}

		return $out;
	}

	/**
	 * The full URL of the current request.
	 *
	 * @return string
	 */
	private function current_full_url() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return ( is_ssl() ? 'https' : 'http' ) . '://' . $host . $uri;
	}

	/**
	 * Cached configured codes (avoids re-reading the option on every link).
	 *
	 * @return string[]
	 */
	private function codes() {
		if ( empty( $this->codes ) ) {
			$this->codes = MLR_Languages::codes();
		}
		return $this->codes;
	}

	/* --------------------------------------------------------------------- *
	 * Redirects & head output
	 * --------------------------------------------------------------------- */

	/**
	 * Redirect prefix-less front-end requests to the active language.
	 */
	public function maybe_redirect() {
		if ( '' === $this->current || $this->has_prefix ) {
			return;
		}
		$settings = MLR_Languages::settings();
		if ( empty( $settings['redirect'] ) ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || $this->is_excluded_context() ) {
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return;
		}
		if ( is_robots() || is_trackback() ) {
			return;
		}

		$current = $this->current_full_url();
		$target  = $this->url_in_language( $this->current, $current );
		if ( $target === $current ) {
			return;
		}

		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * Output hreflang alternate links for SEO.
	 */
	public function render_hreflang() {
		$langs = MLR_Languages::all();
		if ( count( $langs ) < 2 ) {
			return;
		}

		$current = $this->current_full_url();
		foreach ( $langs as $lang ) {
			$url      = $this->url_in_language( $lang['code'], $current );
			$hreflang = $lang['locale'] ? str_replace( '_', '-', $lang['locale'] ) : $lang['code'];
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
				esc_attr( $hreflang ),
				esc_url( $url )
			);
		}
	}

	/**
	 * Switch the WordPress locale on the front end to the active language.
	 *
	 * @param string $locale Current locale.
	 * @return string
	 */
	public function filter_locale( $locale ) {
		if ( is_admin() || '' === $this->current ) {
			return $locale;
		}
		$lang = MLR_Languages::get( $this->current );
		if ( $lang && ! empty( $lang['locale'] ) ) {
			return $lang['locale'];
		}
		return $locale;
	}

	/**
	 * Set the correct lang="" attribute on <html>.
	 *
	 * @param string $output Existing attributes.
	 * @return string
	 */
	public function filter_language_attributes( $output ) {
		if ( '' === $this->current ) {
			return $output;
		}
		$lang = MLR_Languages::get( $this->current );
		if ( ! $lang ) {
			return $output;
		}
		$value = $lang['locale'] ? str_replace( '_', '-', $lang['locale'] ) : $lang['code'];

		if ( false !== strpos( $output, 'lang=' ) ) {
			return preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $value ) . '"', $output, 1 );
		}
		return $output . ' lang="' . esc_attr( $value ) . '"';
	}

	/**
	 * Contexts where link prefixing / redirects must never happen.
	 *
	 * @return bool
	 */
	private function is_excluded_context() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return false;
	}
}
