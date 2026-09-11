<?php

/**
 * Concrete implementation of tracking class for testing
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @subpackage tests
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Storage;


require_once __DIR__ . '/../../src/Storage/Tracking.php';

class TestTracking extends Tracking
{

	var $app = 'test';
	var $id_field = 't_id';

	/**
	 * Injectable config values, returned by get_config($name,...) - keyed by $name
	 *
	 * Lets tests control what get_config('copy'|'assigned'|'skip_notify'|'lang'|...) returns,
	 * without needing a real Tracking subclass per scenario.
	 *
	 * @var array
	 */
	public $config = array();

	/**
	 * Expose protected parent method so it can be tested
	 * @param string $message
	 * @param string|int $receiver
	 * @return string
	 */
	public function sanitize_custom_message($message, $receiver)
	{
		return parent::sanitize_custom_message($message, $receiver);
	}

	/**
	 * Expose protected parent method so it can be tested directly (bypassing track())
	 *
	 * @param array $data
	 * @param ?array $old
	 * @param boolean $deleted
	 * @param ?array $changed_fields
	 * @return int
	 */
	public function save_history(array $data, ?array $old = null, $deleted = null, ?array $changed_fields = null)
	{
		return parent::save_history($data, $old, $deleted, $changed_fields);
	}

	/**
	 * Test double for get_config(): returns whatever was set in $this->config[$name], or null
	 *
	 * @param string $name
	 * @param array $data
	 * @param ?array $old
	 * @return mixed
	 */
	protected function get_config($name, $data, $old = null)
	{
		return $this->config[$name] ?? null;
	}
}
