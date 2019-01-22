<?php
/**
 * Class Google\WP_Sourcery\Plugin
 *
 * @package   Google\WP_Sourcery
 * @link      https://github.com/westonruter/wp-sourcery
 * @license   GPL-2.0-or-later
 * @copyright 2019 Google Inc.
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
	 * Instance of Invocation_Watcher.
	 *
	 * @var Invocation_Watcher
	 */
	public $invocation_watcher;

	/**
	 * Instance of Server_Timing_Headers.
	 *
	 * @var Server_Timing_Headers
	 */
	public $server_timing_headers;

	/**
	 * Instance of Dependencies.
	 *
	 * @var Dependencies
	 */
	public $dependencies;

	/**
	 * Instance of Database.
	 *
	 * @var Database
	 */
	public $database;

	/**
	 * Instance of File_Locator.
	 *
	 * @var File_Locator
	 */
	public $file_locator;

	/**
	 * Instance of Output_Annotator.
	 *
	 * @var Output_Annotator
	 */
	public $output_annotator;

	/**
	 * Instance of Hook_Wrapper.
	 *
	 * @var Hook_Wrapper
	 */
	public $hook_wrapper;

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
		static::$instance->start();

		return true;
	}

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
	 * Construct classes used by the plugin.
	 *
	 * @global \wpdb $wpdb
	 */
	public function init() {
		global $wpdb;

		$this->file_locator = new File_Locator();

		$this->dependencies = new Dependencies();

		$this->output_annotator = new Output_Annotator( $this->dependencies );

		$this->database = new Database( $wpdb );

		$this->hook_wrapper = new Hook_Wrapper();

		$this->invocation_watcher = new Invocation_Watcher(
			$this->file_locator,
			$this->output_annotator,
			$this->dependencies,
			$this->database,
			$this->hook_wrapper,
			[
				'can_show_queries_callback' => function() {
					return current_user_can( $this->show_queries_cap );
				},
			]
		);

		$this->output_annotator->set_invocation_watcher( $this->invocation_watcher );

		$this->server_timing_headers = new Server_Timing_Headers( $this->invocation_watcher );
	}

	/**
	 * Run.
	 */
	public function start() {
		if ( ! $this->should_run() ) {
			return;
		}

		$this->init();

		$this->invocation_watcher->start();
		$this->output_annotator->start();
		add_action( 'shutdown', [ $this->server_timing_headers, 'send' ] );
	}
}
