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
	 * @var PushBackend
	 */
	protected static $backend;

	/**
	 * account_id we are pushing too
	 *
	 * @var int|int[]
	 */
	protected $account_id;

	/**
	 * Push to all clients / broadcast
	 */
	const ALL = 0;
	/**
	 * Push to current session
	 */
	const SESSION = null;

	/**
	 * How long to cache online status / maximum frequency for querying
	 */
	const INSTANCE_ONLINE_CACHE_EXPIRATION = 10;

	/**
	 * account_id's of users currently online
	 *
	 * @var array|null
	 */
	protected static $online;

	/**
	 *
	 * @param ?int|int[] $account_id =null account_id(s) to push message too or
	 *	self::SESSION(=null): for current session only or self::ALL(=0) for whole instance / broadcast
	 */
	public function __construct($account_id=self::SESSION)
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
		self::checkSetBackend();

		self::$backend->addGeneric($this->account_id, $key, $data);
	}

	/**
	 * Get users online / connected to push-server
	 *
	 * @return array of integer account_id currently available for push
	 */
	public static function online()
	{
		if (!isset(self::$online))
		{
			self::$online = Api\Cache::getInstance(__CLASS__, 'online', function()
			{
				self::checkSetBackend();

				return self::$backend->online();
			}, [], self::INSTANCE_ONLINE_CACHE_EXPIRATION);
		}
		return self::$online;
	}

	/**
	 * Get given user is online / connected to push-server
	 *
	 * @return boolean
	 */
	public static function isOnline($account_id)
	{
		return in_array($account_id, self::online());
	}

	/**
	 * Check and if neccessary set push backend
	 *
	 * @param boolean $ignore_cache =false
	 * @throws Exception\NotOnline
	 */
	protected static function checkSetBackend($ignore_cache=false)
	{
		if ($ignore_cache || !isset(self::$backend))
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
	}

	/**
	 * Check if only fallback / no real push available
	 *
	 * @param boolean $ignore_cache =false
	 * @return bool true: fallback, false: real push
	 */
	public static function onlyFallback($ignore_cache=false)
	{
		try {
			self::checkSetBackend($ignore_cache);
		}
		catch (\Exception $e) {
			return true;
		}
		return self::$backend instanceof \notifications_push;
	}
}
