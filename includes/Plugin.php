<?php
/**
 * Class Google\WP_Sourcery\Plugin
 *
 * @package Google\WP_Feature_Policy
 * @license GNU General Public License, version 2
 * @link    https://wordpress.org/plugins/feature-policy/
 */

namespace Google\WP_Sourcery;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Main instance of the plugin.
	 *
	 * @since 0.1.0
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Absolute path to the plugin main file.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $main_file;

	/**
	 * Capability required to reveal the database queries on the page.
	 *
	 * @var string
	 */
	public $show_queries_cap = 'manage_options';

	/**
	 * Instance of Hook_Inspector.
	 *
	 * @var Hook_Inspector
	 */
	public $hook_inspector;

	/**
	 * Instance of Hook_Inspector.
	 *
	 * @var Hook_Wrapper
	 */
	public $hook_wrapper;

	/**
	 * Plugin constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string $main_file Absolute path to the plugin main file. This is primarily useful for plugins that subclass this class.
	 */
	public function __construct( $main_file ) {
		$this->main_file = $main_file;
	}

	/**
	 * Gets the plugin basename, which consists of the plugin directory name and main file name.
	 *
	 * @since 0.1.0
	 *
	 * @return string Plugin basename.
	 */
	public function basename() {
		return plugin_basename( $this->main_file );
	}

	/**
	 * Gets the absolute path for a path relative to the plugin directory.
	 *
	 * @since 0.1.0
	 *
	 * @param string $relative_path Optional. Relative path. Default '/'.
	 * @return string Absolute path.
	 */
	public function path( $relative_path = '/' ) {
		return plugin_dir_path( $this->main_file ) . ltrim( $relative_path, '/' );
	}

	/**
	 * Gets the full URL for a path relative to the plugin directory.
	 *
	 * @since 0.1.0
	 *
	 * @param string $relative_path Optional. Relative path. Default '/'.
	 * @return string Full URL.
	 */
	public function url( $relative_path = '/' ) {
		return plugin_dir_url( $this->main_file ) . ltrim( $relative_path, '/' );
	}

	/**
	 * Determine whether sourcery should run for the current request.
	 *
	 * @return bool
	 */
	public function should_run() {
		return (
			defined( 'WP_DEBUG' )
			&&
			WP_DEBUG
			&&
			isset( $_GET['sourcery'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.NoNonceVerification
		);
	}

	/**
	 * Init.
	 */
	public function init() {
		if ( ! $this->should_run() ) {
			return;
		}

		$this->hook_inspector = new Hook_Inspector(
			array(
				'can_show_queries_callback' => function() {
					return current_user_can( $this->show_queries_cap );
				},
			)
		);
		$this->hook_wrapper   = new Hook_Wrapper(
			array( $this->hook_inspector, 'before_hook' ),
			array( $this->hook_inspector, 'after_hook' )
		);

		// Output buffer so that Server-Timing headers can be sent, and prevent plugins from flushing it.
		ob_start(
			array( $this->hook_inspector, 'finalize_hook_annotations' ),
			null,
			0
		);

		// Prevent PHP Notice: ob_end_flush(): failed to send buffer.
		remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );

		$this->hook_wrapper->add_all_hook();

		add_action( 'shutdown', array( $this, 'send_server_timing_headers' ) );
	}

	/**
	 * Send Server-Timing headers.
	 *
	 * @todo Move this to another class.
	 */
	public function send_server_timing_headers() {
		$entity_timings = array();

		foreach ( $this->hook_inspector->processed_hooks as $processed_hook ) {
			try {
				$hook_duration = $processed_hook->duration();
			} catch ( \Exception $e ) {
				$hook_duration = -1;
			}

			$file_location = $processed_hook->file_location();
			if ( $file_location ) {
				$entity_key = sprintf( '%s:%s', $file_location['type'], $file_location['name'] );
				if ( ! isset( $entity_timings[ $entity_key ] ) ) {
					$entity_timings[ $entity_key ] = 0.0;
				}
				$entity_timings[ $entity_key ] += $hook_duration;
			}
		}

		$round_to_fourth_precision = function( $timing ) {
			return round( $timing, 4 );
		};

		foreach ( array_map( $round_to_fourth_precision, $entity_timings ) as $entity => $timing ) {
			$value  = strtok( $entity, ':' );
			$value .= sprintf( ';desc="%s"', $entity );
			$value .= sprintf( ';dur=%f', $timing * 1000 );
			header( sprintf( 'Server-Timing: %s', $value ), false );
		}
	}

	/**
	 * Retrieves the main instance of the plugin.
	 *
	 * @since 0.1.0
	 *
	 * @return Plugin Plugin main instance.
	 */
	public static function instance() {
		return static::$instance;
	}

	/**
	 * Loads the plugin main instance and initializes it.
	 *
	 * @since 0.1.0
	 *
	 * @param string $main_file Absolute path to the plugin main file.
	 * @return bool True if the plugin main instance could be loaded, false otherwise.
	 */
	public static function load( $main_file ) {
		if ( null !== static::$instance ) {
			return false;
		}

		static::$instance = new static( $main_file );
		static::$instance->init();

		return true;
	}
}
