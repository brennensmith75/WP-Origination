<?php
/**
 * Hook_Invocation Class.
 *
 * @package   Google\WP_Origination
 * @link      https://github.com/westonruter/wp-origination
 * @license   GPL-2.0-or-later
 * @copyright 2019 Google Inc.
 */

namespace Google\WP_Origination;

/**
 * Class Hook_Invocation.
 */
class Hook_Invocation extends Invocation {

	/**
	 * Name of filter/action.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Priority.
	 *
	 * @var int
	 */
	public $priority;

	/**
	 * Returns whether it is an action hook.
	 *
	 * @return bool Whether an action hook.
	 */
	public function is_action() {
		return did_action( $this->name ) > 0;
	}

	/**
	 * Whether a filter modified the value.
	 *
	 * @var null|bool
	 */
	public $value_modified = null;

	/**
	 * Whether this invocation is expected to produce output (an action) vs a filter.
	 *
	 * @return bool Whether output is expected.
	 */
	public function can_output() {
		return $this->is_action();
	}

	/**
	 * Get data for exporting.
	 *
	 * @return array Data.
	 */
	public function data() {
		$data  = parent::data();
		$index = $data['index'];
		unset( $data['index'] );

		$data = array_merge(
			compact( 'index' ),
			[
				'type'     => $this->is_action() ? 'action' : 'filter',
				'name'     => $this->name,
				'priority' => $this->priority,
			],
			$data
		);

		if ( 'filter' === $data['type'] ) {
			$data['value_modified'] = $this->value_modified;
		}

		return $data;
	}
}
