<?php
/**
 * Manage Plugins screen provider.
 *
 * @package SatisPress
 * @license GPL-2.0-or-later
 * @since 0.2.0
 */

declare ( strict_types = 1 );

namespace SatisPress\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use SatisPress\PackageManager;

/**
 * Manage Plugins screen provider class.
 *
 * @since 0.2.0
 */
class ManagePlugins extends AbstractHookProvider {
	/**
	 * Package manager.
	 *
	 * @var PackageManager
	 */
	protected $package_manager;

	/**
	 * Initialise SatisPress object.
	 *
	 * @param PackageManager $package_manager Package manager.
	 */
	public function __construct( PackageManager $package_manager ) {
		$this->package_manager = $package_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.3.0
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_satispress_toggle_plugin', [ $this, 'ajax_toggle_plugin_status' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'manage_plugins_columns', [ $this, 'register_columns' ] );
		add_action( 'manage_plugins_custom_column', [ $this, 'display_columns' ], 10, 2 );
	}

	/**
	 * Toggle whether or not a plugin is included in packages.json.
	 *
	 * @since 0.2.0
	 */
	public function ajax_toggle_plugin_status() {
		if ( ! isset( $_POST['plugin_file'], $_POST['_wpnonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
		$plugin  = $_POST['plugin_file'];
		$plugins = (array) get_option( 'satispress_plugins', [] );

		// Bail if the nonce can't be verified.
		check_admin_referer( 'toggle-status_' . $plugin );

		$key = array_search( $plugin, $plugins, true );
		if ( false !== $key ) {
			unset( $plugins[ $key ] );
		} else {
			$plugins[] = $plugin;
		}

		$plugins = array_filter( array_unique( $plugins ) );
		sort( $plugins );

		update_option( 'satispress_plugins', $plugins );
		wp_send_json_success();
	}

	/**
	 * Enqueue assets for the screen.
	 *
	 * @since 0.2.0
	 *
	 * @param string $hook_suffix Screen hook id.
	 */
	public function enqueue_assets( string $hook_suffix ) {
		if ( 'plugins.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script( 'satispress-admin' );
		wp_enqueue_style( 'satispress-admin' );
	}

	/**
	 * Register admin columns.
	 *
	 * @since 0.2.0
	 *
	 * @param array $columns List of admin columns.
	 * @return array
	 */
	public function register_columns( array $columns ): array {
		$columns['satispress'] = 'SatisPress';

		return $columns;
	}

	/**
	 * Display admin columns.
	 *
	 * @since 0.2.0
	 *
	 * @throws \Exception If package type not known.
	 *
	 * @param string $column_name Column identifier.
	 * @param string $plugin_file Plugin file basename.
	 */
	public function display_columns( string $column_name, string $plugin_file ) {
		if ( 'satispress' !== $column_name ) {
			return;
		}

		$packages = $this->package_manager->get_packages();
		$plugins  = get_option( 'satispress_plugins' );
		$plugin   = $this->package_manager->get_package( $plugin_file, 'plugin' );

		printf( '<input type="checkbox" value="%1$s"%2$s%3$s class="satispress-status">',
			esc_attr( $plugin_file ),
			\checked( isset( $packages[ $plugin->get_slug() ] ), true, false ),
			\disabled( ! isset( $packages[ $plugin->get_slug() ] ) || in_array( $plugin_file, $plugins, true ), false, false )
		);

		echo '<span class="spinner"></span>';

		printf( '<input type="hidden" value="%s" class="satispress-status-nonce">',
			esc_attr( wp_create_nonce( 'toggle-status_' . $plugin_file ) )
		);
	}
}