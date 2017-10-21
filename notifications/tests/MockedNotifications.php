<?php

/**
 * App
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace Egroupware\Notifications;


/**
 * Used for testing notifications by allowing the change tracking and
 * notification process to be run normally, we just interrupt the actual sending
 * before the notification is passed off to the individual chains for delivery.
 *
 * In your test you can define a callback and set it staticly, then pass
 * MockedNotifications::class on to the Tracker class:
 * <code>
 *		$callback = function() use ($expected) {
 *			$test->assertContains($expected,$this->message_plain);
 *		};
 * 		MockedNotifications::set_callback($callback);
 *		$this->bo->tracking = new \<app>_tracking($this->bo, MockedNotifications::class);
 * </code>
 *
 * Everything will run as normal except notifications won't be sent, and you can
 * put assertions into the callback for what you expect of the notification(s).
 */
class MockedNotifications extends \notifications
{
	static $send_callback;
	public static function set_callback(\Closure $send)
	{
		static::$send_callback = $send;
	}
	public function send()
	{
		return static::$send_callback->call($this);
	}
}