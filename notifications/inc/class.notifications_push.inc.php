<?php
/**
 * Notifications: push via notification polling
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Json;

/**
 * Class to push via notification polling and other json requests from client-side
 */
class notifications_push implements Json\PushBackend
{
	const APP = 'notifications';

	/**
	 * Notification table in SQL database
	 */
	const TABLE = 'egw_notificationpopup';

	/**
	 * Notification type
	 */
	const TYPE = 'push';

	/**
	 * Reference to global DB object
	 *
	 * @var Api\Db
	 */
	public static $db;

	public static function get()
	{
		$already_send =& Api\Cache::getSession(__CLASS__, 'already_send');
		$max_id = Api\Cache::getInstance(__CLASS__, 'max_id');

		if (!isset($already_send))
		{
			self::cleanup_push_msgs();

			if (!isset($max_id))
			{
				$max_id = (int)self::$db->select(self::TABLE, 'MAX(notify_id)', false, __LINE__, __FILE__, false, '', self::APP)->fetchColumn();
				Api\Cache::setInstance(__CLASS__, 'max_id', $max_id);
			}
			$already_send = $max_id;
		}
		elseif (isset($max_id) && $max_id > $already_send)
		{
			$response = Json\Response::get();

			foreach(self::$db->select(self::TABLE, '*', array(
				'account_id' => array(0, $GLOBALS['egw_info']['user']['account_id']),
				'notify_type' => self::TYPE,
				'notify_id > '.(int)$already_send,
			), __LINE__, __FILE__, false, 'ORDER BY notify_id ASC', self::APP) as $row)
			{
				$message = json_decode($row['notify_message'], true);
				//error_log(__METHOD__."() already_send=$already_send, message=".array2string($message));
				if (is_array($message) && method_exists($response, $message['method']))
				{
					call_user_func_array(array($response, $message['method']), (array)$message['data']);
				}
				$already_send = $row['notify_id'];
			}
		}
		//error_log(__METHOD__."() max_id=$max_id, already_sent=$already_send");
	}

	/**
	 * Adds any type of data to the message
	 *
	 * @param int $account_id account_id to push message too
	 * @param string $key
	 * @param mixed $data
	 *
	 * This function doesn't throw exception regarding if user availability anymore,
	 * if you need to check if user is online or not please use this function
	 * Api\Session::notifications_active($account_id) in a clause.
	 */
	public function addGeneric($account_id, $key, $data)
	{
		self::$db->insert(self::TABLE, array(
			'account_id'  => $account_id,
			'notify_type' => self::TYPE,
			'notify_message' => json_encode(array(
				'method' => $key,
				'data' => $data,
			)),
		), false, __LINE__, __FILE__, self::APP);

		// cache highest id, to not poll database
		Api\Cache::setInstance(__CLASS__, 'max_id', self::$db->get_last_insert_id(self::TABLE, 'notify_id'));

		self::cleanup_push_msgs();
	}

	/**
	 * Delete push messges older then our heartbeat-limit (poll frequency of notifications)
	 */
	protected static function cleanup_push_msgs()
	{
		if (($ts = self::$db->from_unixtime(Api\Session::heartbeat_limit())))
		{
			self::$db->delete(self::TABLE, array(
				'notify_type' => self::TYPE,
				'notify_created < '.$ts,
			), __LINE__, __FILE__, self::APP);
		}
	}

	/**
	 * Init our static variables eg. database object
	 */
	public static function init_static()
	{
		self::$db =& $GLOBALS['egw']->db;
	}
}
notifications_push::init_static();
