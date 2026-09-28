<?php
/**
 * PHPUnit bootstrap for CartShare tests.
 *
 * For unit tests we stub the WordPress and WooCommerce functions that the plugin
 * classes call so tests can run without a live WordPress install.
 *
 * For integration tests the bootstrap attempts to load the WordPress test library
 * (wordpress-develop/tests/phpunit/includes/bootstrap.php via WP_TESTS_DIR).
 * Set the WP_TESTS_DIR environment variable to run integration tests against a
 * real WordPress + WooCommerce install (e.g., inside the docker-compose stack
 * defined in docker-compose.yml).
 *
 * @package CartShare\Tests
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
error_reporting( E_ALL );
// phpcs:enable

// Composer autoload.
$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

// -------------------------------------------------------------------------
// Integration path: load WordPress test library when WP_TESTS_DIR is set.
// -------------------------------------------------------------------------
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( $wp_tests_dir ) {
    $wp_tests_bootstrap = rtrim( $wp_tests_dir, '/' ) . '/includes/bootstrap.php';
    if ( ! file_exists( $wp_tests_bootstrap ) ) {
        echo "ERROR: WP_TESTS_DIR is set to '{$wp_tests_dir}' but bootstrap.php was not found.\n";
        exit( 1 );
    }

    // Point WordPress test suite at the plugin.
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

    require_once $wp_tests_bootstrap;

    // Activate WooCommerce dependency for integration tests.
    require_once WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php';

    // Load the plugin itself.
    require_once dirname( __DIR__ ) . '/cartshare.php';

    return; // Integration bootstrap complete — skip unit stubs below.
}

// -------------------------------------------------------------------------
// Unit test path: define minimal WordPress / WooCommerce stubs so classes
// can be loaded and exercised without a live WordPress install.
// -------------------------------------------------------------------------

// Polyfill path for Yoast PHPUnit Polyfills (used by WP test helpers).
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

// Guard constant so plugin files don't exit early.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Plugin constants (normally set by cartshare.php after WordPress loads).
if ( ! defined( 'CARTSHARE_VERSION' ) ) {
    define( 'CARTSHARE_VERSION', '1.0.1' );
}
if ( ! defined( 'CARTSHARE_PATH' ) ) {
    define( 'CARTSHARE_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'CARTSHARE_URL' ) ) {
    define( 'CARTSHARE_URL', 'http://localhost/' );
}

// ------------------------------------------------------------------
// WordPress function stubs (only those actually called by unit-tested
// classes — add more here as new classes are brought under test).
// ------------------------------------------------------------------

if ( ! function_exists( 'wp_generate_password' ) ) {
    /**
     * Stub: generates a random alphanumeric string of the given length.
     *
     * @param int  $length          String length.
     * @param bool $special_chars   Ignored in stub.
     * @param bool $extra_special   Ignored in stub.
     * @return string
     */
    function wp_generate_password( $length = 12, $special_chars = true, $extra_special = false ) {
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max    = strlen( $chars ) - 1;
        $result = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $result .= $chars[ random_int( 0, $max ) ];
        }
        return $result;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    /**
     * Stub: trims and strips tags.
     *
     * @param string $str Input string.
     * @return string
     */
    function sanitize_text_field( $str ) {
        return trim( strip_tags( (string) $str ) );
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    /**
     * Stub: returns the value unchanged (no slash-stripping needed in test context).
     *
     * @param mixed $value Value to unslash.
     * @return mixed
     */
    function wp_unslash( $value ) {
        return $value;
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    /**
     * Stub: lowercases and strips to [a-z0-9_-].
     *
     * @param string $key Input key.
     * @return string
     */
    function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    /**
     * Stub: thin wrapper around json_encode.
     *
     * @param mixed $data  Data to encode.
     * @param int   $flags JSON flags.
     * @param int   $depth Max depth.
     * @return string|false
     */
    function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
        return json_encode( $data, $flags, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
    }
}

if ( ! function_exists( 'current_time' ) ) {
    /**
     * Stub: returns current time as MySQL datetime string (UTC when $gmt is true).
     *
     * @param string $type Format type ('mysql', 'timestamp').
     * @param bool   $gmt  Whether to use GMT.
     * @return string|int
     */
    function current_time( $type, $gmt = false ) {
        if ( 'timestamp' === $type ) {
            return time();
        }
        return gmdate( 'Y-m-d H:i:s' );
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    /**
     * Minimal WP_Error stub.
     */
    class WP_Error {
        /** @var string */
        public $code;
        /** @var string */
        public $message;
        /** @var mixed */
        public $data;

        /**
         * Constructor.
         *
         * @param string $code    Error code.
         * @param string $message Error message.
         * @param mixed  $data    Optional data.
         */
        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        /**
         * Return error code(s).
         *
         * @return string[]
         */
        public function get_error_codes() {
            return [ $this->code ];
        }

        /**
         * Return error message for a given code.
         *
         * @param string $code Error code.
         * @return string
         */
        public function get_error_message( $code = '' ) {
            return $this->message;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    /**
     * Stub: returns true when $thing is a WP_Error instance.
     *
     * @param mixed $thing Value to check.
     * @return bool
     */
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

// ------------------------------------------------------------------
// Additional constants needed by plugin classes.
// ------------------------------------------------------------------

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

// ------------------------------------------------------------------
// Test state tracker — allows tests to inspect side-effects produced
// by WordPress/WooCommerce function stubs without needing globals.
// ------------------------------------------------------------------

if ( ! class_exists( 'CartShare_Test_State' ) ) {
	/**
	 * Static bag for side-effects captured by function stubs.
	 */
	class CartShare_Test_State {
		/** @var string[] SQL strings passed to dbDelta(). */
		public static $dbdelta_sql = array();
		/** @var array[] Events queued via wp_schedule_event(). */
		public static $scheduled_events = array();
		/** @var string[] Hook names passed to wp_clear_scheduled_hook(). */
		public static $cleared_hooks = array();
		/** @var array Key/value store for update_option / get_option. */
		public static $options = array();
		/** @var bool Return value for current_user_can() stub. */
		public static $user_can = true;
		/** @var array[] Calls captured by wp_send_json_error(): [ 'data' => ..., 'status' => ... ] */
		public static $json_error_calls = array();
		/** @var array[] Calls captured by wp_send_json_success(): [ 'data' => ... ] */
		public static $json_success_calls = array();
		/** @var string[] Script handles passed to wp_enqueue_script(). */
		public static $enqueued_scripts = array();
		/** @var string[] Style handles passed to wp_enqueue_style(). */
		public static $enqueued_styles = array();

		/**
		 * Reset all state to empty arrays (call in setUp / tearDown).
		 *
		 * @return void
		 */
		public static function reset() {
			self::$dbdelta_sql       = array();
			self::$scheduled_events  = array();
			self::$cleared_hooks     = array();
			self::$options           = array();
			self::$user_can          = true;
			self::$json_error_calls  = array();
			self::$json_success_calls = array();
			self::$enqueued_scripts  = array();
			self::$enqueued_styles   = array();
		}
	}
}

// ------------------------------------------------------------------
// WooCommerce stub: WC() returns a minimal object with a cart slot.
// ------------------------------------------------------------------

$GLOBALS['cartshare_wc_instance'] = null;

if ( ! class_exists( 'CartShare_Stub_WC' ) ) {
	/**
	 * Minimal WooCommerce application object stub.
	 */
	class CartShare_Stub_WC {
		/** @var object|null Cart object. */
		public $cart = null;
		/** @var object|null Session object. */
		public $session = null;
	}
}

if ( ! function_exists( 'WC' ) ) {
	/**
	 * Stub: returns the global WooCommerce instance.
	 *
	 * @return CartShare_Stub_WC
	 */
	function WC() {
		if ( null === $GLOBALS['cartshare_wc_instance'] ) {
			$GLOBALS['cartshare_wc_instance'] = new CartShare_Stub_WC();
		}
		return $GLOBALS['cartshare_wc_instance'];
	}
}

if ( ! function_exists( 'wc_load_cart' ) ) {
	/**
	 * Stub: no-op — cart is already available in test context.
	 *
	 * @return void
	 */
	function wc_load_cart() {}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub: returns the original string unchanged.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (ignored).
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * Stub: records the SQL and returns an empty result array.
	 *
	 * @param string $sql     SQL statement(s).
	 * @param bool   $execute Ignored in stub.
	 * @return array
	 */
	function dbDelta( $sql = '', $execute = true ) {
		CartShare_Test_State::$dbdelta_sql[] = $sql;
		return array();
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Stub: checks whether a hook is already in the scheduled-events list.
	 *
	 * @param string $hook Action hook name.
	 * @return int|false Timestamp if found, false otherwise.
	 */
	function wp_next_scheduled( $hook ) {
		foreach ( CartShare_Test_State::$scheduled_events as $event ) {
			if ( $event['hook'] === $hook ) {
				return $event['timestamp'];
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * Stub: appends the event to the scheduled-events list.
	 *
	 * @param int    $timestamp  Unix timestamp for first run.
	 * @param string $recurrence How often the event should recur.
	 * @param string $hook       Action hook name.
	 * @return void
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook ) {
		CartShare_Test_State::$scheduled_events[] = array(
			'timestamp'  => $timestamp,
			'recurrence' => $recurrence,
			'hook'       => $hook,
		);
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Stub: records the cleared hook and removes matching scheduled events.
	 *
	 * @param string $hook Action hook name.
	 * @return void
	 */
	function wp_clear_scheduled_hook( $hook ) {
		CartShare_Test_State::$cleared_hooks[] = $hook;
		CartShare_Test_State::$scheduled_events = array_values(
			array_filter(
				CartShare_Test_State::$scheduled_events,
				function ( $event ) use ( $hook ) {
					return $event['hook'] !== $hook;
				}
			)
		);
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	/**
	 * Stub: no-op.
	 *
	 * @return void
	 */
	function flush_rewrite_rules() {}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Stub: stores a value in the options bag.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return bool
	 */
	function update_option( $option, $value ) {
		CartShare_Test_State::$options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub: retrieves a value from the options bag.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value when option is absent.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		if ( array_key_exists( $option, CartShare_Test_State::$options ) ) {
			return CartShare_Test_State::$options[ $option ];
		}
		return $default;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	/**
	 * Stub: returns a deterministic REST API base URL.
	 *
	 * @param string $path REST path.
	 * @return string
	 */
	function rest_url( $path = '' ) {
		return 'http://example.org/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Stub: returns a deterministic nonce.
	 *
	 * @param string $action Nonce action.
	 * @return string
	 */
	function wp_create_nonce( $action = -1 ) {
		return 'test-nonce-' . $action;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Stub: returns a deterministic site URL.
	 *
	 * @param string $path Optional path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'http://example.org/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Stub: returns untranslated text.
	 *
	 * @param string $text Text.
	 * @param string $domain Translation domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Stub: escapes HTML text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Stub: returns the supplied URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	/**
	 * Stub: returns valid hexadecimal colors unchanged.
	 *
	 * @param string $color Color value.
	 * @return string|null
	 */
	function sanitize_hex_color( $color ) {
		return preg_match( '/^#[a-f0-9]{6}$/i', (string) $color ) ? $color : null;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Stub: converts a value to a non-negative integer.
	 *
	 * @param mixed $n Value to convert.
	 * @return int
	 */
	function absint( $n ) {
		return (int) abs( $n );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stub: lowercases and strips non-alphanumeric/hyphen/underscore characters.
	 *
	 * @param string $key Input key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Stub: always returns 0 (no logged-in user in unit tests).
	 *
	 * @return int
	 */
	function get_current_user_id() {
		return 0;
	}
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Stub: appends a single key=value pair to a URL.
	 *
	 * @param string $key   Query parameter key.
	 * @param string $value Query parameter value.
	 * @param string $url   Base URL.
	 * @return string
	 */
	function add_query_arg( $key, $value, $url ) {
		$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		return $url . $sep . urlencode( $key ) . '=' . urlencode( $value );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Stub: returns the base URL with an optional path suffix.
	 *
	 * @param string $path Optional path to append.
	 * @return string
	 */
	function home_url( $path = '/' ) {
		return 'http://localhost' . $path;
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal WP_REST_Response stub.
	 */
	class WP_REST_Response {
		/** @var mixed Response data. */
		public $data;

		/**
		 * Constructor.
		 *
		 * @param mixed $data Response data.
		 */
		public function __construct( $data = null ) {
			$this->data = $data;
		}

		/**
		 * Return the response data.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	/**
	 * Stub: wraps data in a WP_REST_Response if not already one.
	 *
	 * @param mixed $response Response data or existing WP_REST_Response.
	 * @return WP_REST_Response
	 */
	function rest_ensure_response( $response ) {
		if ( $response instanceof WP_REST_Response ) {
			return $response;
		}
		return new WP_REST_Response( $response );
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal WP_REST_Request stub.
	 *
	 * Supports get_param() and get_header() for unit testing REST handlers
	 * without a live WordPress REST infrastructure.
	 */
	class WP_REST_Request {
		/** @var array Request parameters (merged body + query for unit tests). */
		protected $params = array();

		/** @var array Request headers. */
		protected $headers = array();

		/**
		 * Retrieve a request parameter by key.
		 *
		 * @param string $key Parameter name.
		 * @return mixed|null
		 */
		public function get_param( $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}

		/**
		 * Set a request parameter (test helper).
		 *
		 * @param string $key   Parameter name.
		 * @param mixed  $value Parameter value.
		 * @return void
		 */
		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		/**
		 * Retrieve a request header value.
		 *
		 * @param string $name Header name.
		 * @return string|null
		 */
		public function get_header( $name ) {
			return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : null;
		}

		/**
		 * Set a request header (test helper).
		 *
		 * @param string $name  Header name.
		 * @param string $value Header value.
		 * @return void
		 */
		public function set_header( $name, $value ) {
			$this->headers[ $name ] = $value;
		}
	}
}

if ( ! class_exists( 'CartShare_Test_Json_Die' ) ) {
	/**
	 * Exception thrown by wp_send_json_error / wp_send_json_success stubs to
	 * simulate the wp_die() call that WordPress makes after sending JSON output.
	 * Tests that trigger a JSON response should catch this exception.
	 */
	class CartShare_Test_Json_Die extends \RuntimeException {}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	/**
	 * Stub: no-op — always returns true in unit tests.
	 *
	 * @param string     $action    Expected nonce action.
	 * @param string|int $query_arg Query variable key to check.
	 * @param bool       $die       Whether to die on failure (ignored in stub).
	 * @return int
	 */
	function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
		return 1;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Stub: returns CartShare_Test_State::$user_can.
	 *
	 * @param string $capability Capability to check.
	 * @param mixed  ...$args    Extra args (ignored).
	 * @return bool
	 */
	function current_user_can( $capability, ...$args ) {
		return CartShare_Test_State::$user_can;
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	/**
	 * Stub: records the call in CartShare_Test_State::$json_error_calls, then
	 * throws CartShare_Test_Json_Die to simulate wp_die() — the real WordPress
	 * function terminates execution after sending the response.
	 *
	 * @param mixed $data        Response data.
	 * @param int   $status_code HTTP status code.
	 * @param int   $flags       JSON encode flags (ignored).
	 * @return void
	 * @throws CartShare_Test_Json_Die Always thrown after recording.
	 */
	function wp_send_json_error( $data = null, $status_code = null, $flags = 0 ) {
		CartShare_Test_State::$json_error_calls[] = array(
			'data'   => $data,
			'status' => $status_code,
		);
		throw new CartShare_Test_Json_Die( 'wp_send_json_error' );
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	/**
	 * Stub: records the call in CartShare_Test_State::$json_success_calls, then
	 * throws CartShare_Test_Json_Die to simulate wp_die() — the real WordPress
	 * function terminates execution after sending the response.
	 *
	 * @param mixed $data        Response data.
	 * @param int   $status_code HTTP status code (ignored in stub).
	 * @param int   $flags       JSON encode flags (ignored).
	 * @return void
	 * @throws CartShare_Test_Json_Die Always thrown after recording.
	 */
	function wp_send_json_success( $data = null, $status_code = null, $flags = 0 ) {
		CartShare_Test_State::$json_success_calls[] = array(
			'data' => $data,
		);
		throw new CartShare_Test_Json_Die( 'wp_send_json_success' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Stub: records the handle in CartShare_Test_State::$enqueued_scripts.
	 *
	 * @param string           $handle    Script handle.
	 * @param string           $src       Script URL.
	 * @param string[]         $deps      Dependencies.
	 * @param string|bool|null $ver       Version string.
	 * @param bool             $in_footer Whether to enqueue in footer.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		CartShare_Test_State::$enqueued_scripts[] = $handle;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Stub: records the handle in CartShare_Test_State::$enqueued_styles.
	 *
	 * @param string           $handle Style handle.
	 * @param string           $src    Stylesheet URL.
	 * @param string[]         $deps   Dependencies.
	 * @param string|bool|null $ver    Version string.
	 * @param string           $media  Media type.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		CartShare_Test_State::$enqueued_styles[] = $handle;
	}
}

if ( ! function_exists( 'is_cart' ) ) {
    /**
     * Stub: returns false — no cart page in unit test context.
     *
     * @return bool
     */
    function is_cart() {
        return false;
    }
}

if ( ! function_exists( 'is_checkout' ) ) {
    /**
     * Stub: returns false — no checkout page in unit test context.
     *
     * @return bool
     */
    function is_checkout() {
        return false;
    }
}

if ( ! function_exists( 'is_account_page' ) ) {
    /**
     * Stub: returns false — no account page in unit test context.
     *
     * @return bool
     */
    function is_account_page() {
        return false;
    }
}

// ------------------------------------------------------------------
// Load the plugin's include files for unit testing.
// (Integration tests load everything via the WordPress bootstrap.)
// ------------------------------------------------------------------
require_once CARTSHARE_PATH . 'includes/class-cartshare-token.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-db.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-cart.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-activator.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-deactivator.php';
if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-rest.php' ) ) {
	require_once CARTSHARE_PATH . 'includes/class-cartshare-rest.php';
}
if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-admin.php' ) ) {
	require_once CARTSHARE_PATH . 'includes/class-cartshare-admin.php';
}
if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-frontend.php' ) ) {
	require_once CARTSHARE_PATH . 'includes/class-cartshare-frontend.php';
}
if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-cart-builder.php' ) ) {
	require_once CARTSHARE_PATH . 'includes/class-cartshare-cart-builder.php';
}
if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-analytics.php' ) ) {
	require_once CARTSHARE_PATH . 'includes/class-cartshare-analytics.php';
}
