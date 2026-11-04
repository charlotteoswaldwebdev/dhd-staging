<?php
/**
 * The visitor reference cookie.
 *
 * Stores the anonymous reference that ties saved rooms to a visitor, so the
 * calculator works for logged out users as well as logged in ones.
 *
 * @link       https://wordpress-1657598-6759210.cloudwaysapps.com
 * @since      1.0.0
 *
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/includes
 */

/**
 * The visitor reference cookie.
 *
 * @since      1.0.0
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/includes
 * @author     Charlotte Oswald <web@charlotteoswald.com>
 */
class Heat_Loss_Calculator_Cookie {

	/**
	 * Cookie name.
	 *
	 * Used for both reading and writing so the two can never drift apart.
	 *
	 * @var string
	 */
	const COOKIE_NAME = 'hlc_ref';

	/**
	 * Cookie lifetime in seconds.
	 *
	 * @var int
	 */
	const COOKIE_LIFETIME = YEAR_IN_SECONDS;

	/**
	 * Cookie Domain
	 *
	 * @var string
	 */
	public $cookie_domain;

	/**
	 * Construct
	 */
	public function __construct() {
		$this->set_default_cookie_domain();
	}

	/**
	 * Set the cookie domain.
	 *
	 * Derived from the site host rather than by string replacement on the full
	 * URL, so a port, a path or a `www.` prefix cannot corrupt it. A leading
	 * `www.` is dropped and the cookie is set on the parent domain so the same
	 * reference is seen on both `example.com` and `www.example.com`. Hosts that
	 * cannot take a domain cookie (an IP address, or a single label such as
	 * `localhost`) fall back to a host only cookie.
	 *
	 * @return string
	 */
	public function set_default_cookie_domain() {
		if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) {
			$this->cookie_domain = COOKIE_DOMAIN;
			return $this->cookie_domain;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( empty( $host ) ) {
			$this->cookie_domain = '';
			return $this->cookie_domain;
		}

		$host = strtolower( $host );
		$host = preg_replace( '/^www\./', '', $host );

		// An IP address or a single label host cannot take a domain cookie.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) || false === strpos( $host, '.' ) ) {
			$this->cookie_domain = '';
			return $this->cookie_domain;
		}

		$this->cookie_domain = '.' . $host;

		return $this->cookie_domain;
	}

	/**
	 * Set cookie
	 *
	 * Also assigns the value into $_COOKIE so the rest of the current request
	 * can read back the reference it has just minted.
	 *
	 * @param string $value Uuid.
	 *
	 * @return bool True when the Set-Cookie header was sent.
	 */
	public function set_cookie( string $value ) {
		// Make the value readable for the remainder of this request regardless
		// of whether the header can still be sent.
		$_COOKIE[ self::COOKIE_NAME ] = $value;

		if ( headers_sent( $file, $line ) ) {
			hlc_log(
				sprintf( 'set_cookie: headers already sent by %1$s:%2$d, cookie not written.', $file, $line )
			);
			return false;
		}

		setcookie(
			self::COOKIE_NAME,
			$value,
			array(
				'expires'  => time() + self::COOKIE_LIFETIME,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => $this->cookie_domain,
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);

		return true;
	}

	/**
	 * Get cookie
	 *
	 * Returns an empty string when there is no usable reference. The cookie is
	 * not refreshed here: reads happen during rendering, when it is too late to
	 * send headers. Refreshing is done by get_or_create() on `init`.
	 *
	 * @return string
	 */
	public function get_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		// Only ever trust a well formed UUID. sanitize_key() was previously used
		// here, which silently lower cased and rewrote anything else into a
		// plausible looking value.
		if ( ! wp_is_uuid( $value ) ) {
			hlc_log( 'get_cookie: discarding malformed hlc_ref cookie.' );
			return '';
		}

		return $value;
	}

	/**
	 * Get the reference, creating and storing one when absent.
	 *
	 * Call this on `init` so the Set-Cookie header can still be sent.
	 *
	 * @return string
	 */
	public function get_or_create() {
		$value = $this->get_cookie();

		if ( '' !== $value ) {
			return $value;
		}

		$value = wp_generate_uuid4();
		$this->set_cookie( $value );

		return $value;
	}

}
