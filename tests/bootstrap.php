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

		/**
		 * Reset all state to empty arrays (call in setUp / tearDown).
		 *
		 * @return void
		 */
		public static function reset() {
			self::$dbdelta_sql      = array();
			self::$scheduled_events = array();
			self::$cleared_hooks    = array();
			self::$options          = array();
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

// ------------------------------------------------------------------
// Load the plugin's include files for unit testing.
// (Integration tests load everything via the WordPress bootstrap.)
// ------------------------------------------------------------------
require_once CARTSHARE_PATH . 'includes/class-cartshare-token.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-db.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-cart.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-activator.php';
require_once CARTSHARE_PATH . 'includes/class-cartshare-deactivator.php';
