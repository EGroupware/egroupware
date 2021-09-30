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
}
