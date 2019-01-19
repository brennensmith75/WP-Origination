<?php
/**
 * Server_Timing_Headers Class.
 *
 * @package   Google\WP_Sourcery
 * @link      https://github.com/westonruter/wp-sourcery
 * @license   GPL-2.0-or-later
 * @copyright 2019 Google Inc.
 */

namespace Google\WP_Sourcery;

/**
 * Class Server_Timing_Headers.
 */
class Server_Timing_Headers {

	/**
	 * Instance of Invocation_Watcher.
	 *
	 * @var Invocation_Watcher
	 */
	public $invocation_watcher;

	/**
	 * Server_Timing_Headers constructor.
	 *
	 * @param Invocation_Watcher $invocation_watcher Invocation watcher.
	 */
	public function __construct( Invocation_Watcher $invocation_watcher ) {
		$this->invocation_watcher = $invocation_watcher;
	}

	/**
	 * Send the headers for the processed invocations on the invocation watcher.
	 */
	public function send() {
		$entity_timings = [];

		foreach ( $this->invocation_watcher->invocations as $processed_hook ) {
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
}
