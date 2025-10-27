<?php
/**
 * EGroupware - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpopup
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>, Christian Binder <christian@jaytraxx.de>
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Ajax methods for notifications
 */
class notifications_ajax
{
	/**
	 * Appname
	 */
	const _appname = 'notifications';

	/**
	 * Notification table in SQL database
	 */
	const _notification_table = 'egw_notificationpopup';

	/**
	 * Notification type
	 */
	const _type = 'base';

	/**
	 * Do NOT consider notifications older than this
	 */
	static public $cut_off_date = '-30days';

	/**
	 * holds account object for user to notify
	 *
	 * @var object
	 */
	private $recipient;

	/**
	 * holds preferences array of user to notify
	 *
	 * @var array
	 */
	private $preferences;

	/**
	 * reference to global db object
	 *
	 * @var Api\Db
	 */
	private $db;

	/**
	 * the xml response object
	 *
	 * @var Api\Json\Response
	 */
	private $response;

	/**
	 * Do we have a real push server, or only a fallback
	 *
	 * @var bool
	 */
	private $isPushServer;

	/**
	 * constructor
	 *
	 */
	public function __construct()
	{
		$this->response = Api\Json\Response::get();
		$this->recipient = (object)$GLOBALS['egw_info']['user'];

		$this->db = $GLOBALS['egw']->db;

		$this->isPushServer = Api\Cache::getInstance('notifications', 'isPushServer', function ()
		{
			return !Api\Json\Push::onlyFallback();
		}, [], 900);
	}

	/**
	 * public AJAX trigger function to be called by the JavaScript client
	 *
	 * this function calls all other recurring AJAX notifications methods
	 * to have ONE single recurring AJAX call per user
	 *
	 * @return xajax response
	 */
	public function get_notifications($browserNotify = false)
	{
		// close session now, to not block other user actions, as specially mail checks can be time consuming
		$GLOBALS['egw']->session->commit_session();

		// call a hook for notifications on new mail
		//if ($GLOBALS['egw_info']['user']['apps']['mail'])  $this->check_mailbox();
		Api\Hooks::process('check_notify');

		// update currentusers
		if (!empty($GLOBALS['egw_info']['user']['apps']['admin']) &&
			!empty($GLOBALS['egw_info']['user']['preferences']['common']['show_currentusers']))
		{
			$this->response->jquery('#currentusers', 'text', array((string)$GLOBALS['egw']->session->session_count()));
		}
		if  ($this->isPushServer) $this->response->data(['isPushServer' => true]);
		$this->get_egwpopup($browserNotify);
	}

	/**
	 * Remove given notification id(s) and app_ids from the table
	 *
	 * @param int[]|array[] $notifymessages one or multiple notify_id(s) or objects incl. id attribute
	 * @param array[] $app_ids app-name int[] pairs
	 */
	public function delete_message(array $notifymessages, array $app_ids=[])
	{
		$this->update($notifymessages, null, $app_ids ?? []);   // null = delete

		// if we delete all messages (we either delete one or all!), we return the next chunk of messages directly
		if (count($notifymessages) > 1)
		{
			$this->get_notifications();
		}
	}

	/**
	 * Method to update message(s) status
	 *
	 * @param array $notifymessages one or more notify_id(s)
	 * @param string $status = SEEN, status of message:
	 *  - SEEN: message has been seen
	 *	- UNSEEN: message has not been seen
	 *	- DISPLAYED: message has been shown but no further status applied yet
	 *				 this status has been used more specifically for browser type
	 *				 of notifications.
	 */
	public function update_status(array $notifymessages, $status = "SEEN")
	{
		$this->update($notifymessages, $status);
	}

	/**
	 * Update or delete the given notification messages, incl. not explicitly mentioned ones with same app:id
	 *
	 * @param array $notifymessages
	 * @param string|null $status use null to delete
	 * @param array[] $app_ids app-name int[] pairs
	 * @return array
	 */
	protected function update(array $notifymessages, $status='SEEN', array $app_ids=[])
	{
		$notify_ids = [];
		foreach ($notifymessages as $data)
		{
			if (is_array($data) && !empty($data['id']))
			{
				if (is_array($data['data'] ?? null) && !empty($data['data']['id']))
				{
					$app_ids[$data['data']['app']][$data['data']['id']] = $data['data']['id'];
				}
				$notify_ids[] = $data['id'];
			}
			else
			{
				$notify_ids[] = $data;
			}
		}
		$cut_off = $this->db->quote(Api\DateTime::to(self::$cut_off_date, Api\DateTime::DATABASE));
		try {
			foreach($app_ids as $app => $ids)
			{
				$where = [
					'account_id' => $this->recipient->account_id,
					'notify_type' => self::_type,
					'notify_app' => $app,
					'notify_app_id' => array_unique($ids),
					'notify_created > '.$cut_off,
				];
				if (isset($status))
				{
					$this->db->update(self::_notification_table, ['notify_status' => $status], $where, __LINE__, __FILE__, self::_appname);
				}
				else
				{
					$this->db->delete(self::_notification_table, $where, __LINE__, __FILE__, self::_appname);
				}
			}
		}
		catch (Api\Db\Exception $e) {
			// ignore, if DB is not yet updated with notify_app(_id) columns
		}
		$where = [
			'notify_id' => array_unique($notify_ids),
			'account_id' => $this->recipient->account_id,
			'notify_type' => self::_type
		];
		if (isset($status))
		{
			$this->db->update(self::_notification_table, ['notify_status' => $status], $where, __LINE__, __FILE__, self::_appname);
		}
		else
		{
			$this->db->delete(self::_notification_table, $where, __LINE__, __FILE__, self::_appname);
		}

		// cleanup messages older than our cut-off-date
		$this->db->delete(self::_notification_table, [
			'notify_created <= '.$cut_off,
			'notify_type' => self::_type
		], __LINE__, __FILE__, self::_appname);
	}

	/**
	 * gets all egwpopup notifications for calling user
	 *
	 * @return boolean true or false
	 */
	private function get_egwpopup($browserNotify = false)
	{
		$entries = notifications_popup::read($this->recipient->account_id);
		$this->response->apply('app.notifications.append', array($entries['rows']??[], $browserNotify, $entries['total']??0));
		return true;
	}

	public function initStatic()
	{
		$config = Api\Config::read(self::_appname);

		if (!empty($config['popup_cut_off_days']) && $config['popup_cut_off_days'] > 0)
		{
			self::$cut_off_date = "-$config[popup_cut_off_days]days";
		}
	}
}