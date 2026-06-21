<?php
/**
 * Main plugin class — singleton that wires up all CartShare subsystems.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Plugin
 *
 * Singleton orchestrator. Call CartShare_Plugin::instance()->boot() once from
 * the plugins_loaded callback in cartshare.php to initialise every subsystem.
 */
class CartShare_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var CartShare_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * Ensures boot() is idempotent — safe to call multiple times.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * CartShare_DB instance.
	 *
	 * @var CartShare_DB|null
	 */
	public $db = null;

	/**
	 * CartShare_Token instance (stateless utility, stored for completeness).
	 *
	 * @var CartShare_Token|null
	 */
	public $token = null;

	/**
	 * Private constructor — use instance() to obtain the singleton.
	 */
	private function __construct() {}

	/**
	 * Return (and create on first call) the singleton instance.
	 *
	 * @return CartShare_Plugin
	 */
	public static function instance(): CartShare_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot all plugin subsystems.
	 *
	 * Requires dependency class files that already exist and instantiates them.
	 * Files for modules not yet implemented (Cart, REST, Frontend, MyAccount,
	 * Admin, Email, Cron, BlocksIntegration) are required conditionally so that
	 * this method remains forward-compatible — later phases add those files and
	 * this method will pick them up automatically.
	 *
	 * Calling boot() more than once is safe — subsequent calls are no-ops.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->load_dependencies();
		$this->init_subsystems();
	}

	/**
	 * Require all dependency class files.
	 *
	 * Only files that exist on disk are required, so phases can be built
	 * incrementally without breaking earlier phases.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		// Phase 1 — always present after foundation phase.
		require_once CARTSHARE_PATH . 'includes/class-cartshare-db.php';
		require_once CARTSHARE_PATH . 'includes/class-cartshare-token.php';

		// Phase 2 — Cart serialization / restoration.
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-cart.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-cart.php';
		}

		// Phase 3 — REST API controller.
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-rest.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-rest.php';
		}

		// Phase 4 — Frontend (popup, enqueue) + Blocks integration.
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-frontend.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-frontend.php';
		}
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-blocks-integration.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-blocks-integration.php';
		}

		// Phase 5 — My Account saved-carts tab.
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-myaccount.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-myaccount.php';
		}

		// Phase 6 — Admin settings page.
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-admin.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-admin.php';
		}

		// Phase 8 — Admin cart builder.
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-cart-builder.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-cart-builder.php';
		}

		// Phase 7 — Email sender.
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-email.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-email.php';
		}

		// Phase 7 — WP-Cron cleanup job.
		if ( file_exists( CARTSHARE_PATH . 'includes/class-cartshare-cron.php' ) ) {
			require_once CARTSHARE_PATH . 'includes/class-cartshare-cron.php';
		}
	}

	/**
	 * Instantiate subsystems and wire their hooks.
	 *
	 * Modules that do not yet exist are skipped gracefully via class_exists()
	 * checks, mirroring the file-existence guards in load_dependencies().
	 *
	 * @return void
	 */
	private function init_subsystems(): void {
		// Phase 1 — DB layer and token utility are always available.
		$this->db    = new CartShare_DB();
		$this->token = new CartShare_Token();

		// Phase 2 — Cart serialization / restoration.
		if ( class_exists( 'CartShare_Cart' ) ) {
			( new CartShare_Cart( $this->db ) )->init_hooks();
		}

		// Phase 3 — REST API controller.
		if ( class_exists( 'CartShare_REST' ) ) {
			( new CartShare_REST( $this->db ) )->init_hooks();
		}

		// Phase 4 — Frontend popup and classic-cart button.
		if ( class_exists( 'CartShare_Frontend' ) ) {
			( new CartShare_Frontend() )->init_hooks();
		}

		// Phase 5 — My Account tab.
		if ( class_exists( 'CartShare_MyAccount' ) ) {
			( new CartShare_MyAccount( $this->db ) )->init_hooks();
		}

		// Phase 6 — Admin settings page.
		if ( class_exists( 'CartShare_Admin' ) ) {
			( new CartShare_Admin() )->init_hooks();
		}

		// Phase 8 — Admin cart builder.
		if ( class_exists( 'CartShare_Cart_Builder' ) ) {
			( new CartShare_Cart_Builder() )->init_hooks();
		}

		// Phase 7 — Email sender: no hooks of its own; loaded by load_dependencies()
		// and instantiated on-demand by CartShare_REST::share_email().

		// Phase 7 — Cron cleanup.
		if ( class_exists( 'CartShare_Cron' ) ) {
			( new CartShare_Cron( $this->db ) )->init_hooks();
		}
	}
}
