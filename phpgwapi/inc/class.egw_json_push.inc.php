<?php
/**
 * EGroupware API: push JSON commands to client
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

/**
 * Class to push JSON commands to client
 */
class egw_json_push extends egw_json_msg
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
	 * @param int $account_id account_id of user to push to
	 */
	public function __construct($account_id)
	{
		$this->account_id = $account_id;
	}

	/**
	 * Adds any type of data to the message
	 *
	 * @param string $key
	 * @param mixed $data
	 * @throws egw_json_push_exception_not_online if $account_id is not online
	 */
	protected function addGeneric($key, $data)
	{
		if (!isset(self::$backend))
		{
			foreach(self::$backends as $class)
			{
				if (class_exists($class))
				{
					try {
						self::$backend = new $class;
						break;
					}
					catch (Exception $e) {
						// ignore exception
						unset($e, self::$backend);
					}
				}
			}
			if (!isset(self::$backend))
			{
				throw new egw_json_push_exception_not_online('No valid push-backend found!');
			}
		}
		self::$backend->addGeneric($this->account_id, $key, $data);
	}
}

/**
 * Interface for push backends
 */
interface egw_json_push_backend
{
	/**
	 * Adds any type of data to the message
	 *
	 * @param int $account_id account_id to push message too
	 * @param string $key
	 * @param mixed $data
	 * @throws egw_json_push_exception_not_online if $account_id is not online
	 */
	public function addGeneric($account_id, $key, $data);
}

/**
 * Exception thrown, if message can not be pushed
 */
class egw_json_push_exception_not_online extends egw_exception_not_found
{

}