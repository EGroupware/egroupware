<?php
/**
 * EGroupware API: push JSON commands to client
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage json
 * @author Ralf Becker <rb@stylite.de>
 */

namespace EGroupware\Api\Json;

use EGroupware\Api;

/**
 * Class to push JSON commands to client
 */
class Push extends Msg
{
	/**
	 * Available backends to try
	 *
	 * @var array
	 */
	protected static $backends = array(
		'notifications_push',
	);
	/**
	 * Backend to use
	 *
	 * @var egw_json_push_backend
	 */
	protected static $backend;

	/**
	 * account_id we are pushing too
	 *
	 * @var int
	 */
	protected $account_id;

	/**
	 *
	 * @param int $account_id =null account_id to push message too or
	 *	null: for current session only or 0 for whole instance / broadcast
	 */
	public function __construct($account_id=null)
	{
		$this->account_id = $account_id;
	}

	/**
	 * Adds any type of data to the message
	 *
	 * @param string $key
	 * @param mixed $data
	 * @throws Exception\NotOnline if $account_id is not online
	 */
	protected function addGeneric($key, $data)
	{
		if (!isset(self::$backend))
		{
			// we prepend so the default backend stays last
			foreach(Api\Hooks::process('push-backends', [], true) as $class)
			{
				if (!empty($class))
				{
					array_unshift(self::$backends, $class);
				}
			}
			foreach(self::$backends as $class)
			{
				if (class_exists($class))
				{
					try {
						self::$backend = new $class;
						break;
					}
					catch (\Exception $e) {
						// ignore all exceptions
						unset($e);
						self::$backend = null;
					}
				}
			}
			if (!isset(self::$backend))
			{
				throw new Exception\NotOnline('No valid push-backend found!');
			}
		}
		self::$backend->addGeneric($this->account_id, $key, $data);
	}
}
