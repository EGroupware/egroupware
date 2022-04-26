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
class notifications_ajax {
	/**
	 * Appname
	 */
	const _appname = 'notifications';

	/**
	 * Mailappname
	 */
	const _mailappname = 'mail';

	/**
	 * Notification table in SQL database
	 */
	const _notification_table = 'egw_notificationpopup';

	/**
	 * Notification type
	 */
	const _type = 'base';

	/**
	 * holds account object for user to notify
	 *
	 * @var object
	 */
	private $recipient;

	/**
	 * holds config object (sitewide application config)
	 *
	 * @var object
	 */
	private $config;

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
	 * holds the users session data
	 *
	 * @var array
	 */
	var $session_data;

	/**
	 * holds the users session data defaults
	 *
	 * @var array
	 */
	var $session_data_defaults = array(
		'notified_mail_uids'	=> array(),
	);

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
	public function __construct() {
		$this->response = Api\Json\Response::get();
		$this->recipient = (object)$GLOBALS['egw']->accounts->read($GLOBALS['egw_info']['user']['account_id']);

		$this->config = (object)Api\Config::read(self::_appname);

		$prefs = new Api\Preferences($this->recipient->account_id);
		$this->preferences = $prefs->read();

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
		if ($GLOBALS['egw_info']['user']['apps']['admin'] &&
			$GLOBALS['egw_info']['user']['preferences']['common']['show_currentusers'])
		{
			$this->response->jquery('#currentusers', 'text', array((string)$GLOBALS['egw']->session->session_count()));
		}
		if  ($this->isPushServer) $this->response->data(['isPushServer' => true]);
		$this->get_egwpopup($browserNotify);
	}

	/**
	 * Remove given notification id(s) from the table
	 *
	 * @param array $notifymessages one or multiple notify_id(s)
	 */
	public function delete_message($notifymessages)
	{
		$notify_ids = $this->fetch_notify_ids($notifymessages);
		if (!empty($notify_ids))
		{
			$this->db->delete(self::_notification_table,array(
				'notify_id' => $notify_ids,
				'account_id' => $this->recipient->account_id,
				'notify_type' => self::_type
			),__LINE__,__FILE__,self::_appname);
		}
		$this->response->data(['deleted'=>$notify_ids]);
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
	public function update_status($notifymessages, $status = "SEEN")
	{
		$notify_ids = $this->fetch_notify_ids($notifymessages);
		if (!empty($notify_ids))
		{
			$this->db->update(self::_notification_table,array('notify_status' => $status),array(
				'notify_id' => $notify_ids,
				'account_id' => $this->recipient->account_id,
				'notify_type' => self::_type
			),__LINE__,__FILE__,self::_appname);
		}
	}

	/**
	 * gets all relevant notify ids based on given notify message data
	 * @param $notifymessages
	 * @return array
	 */
	public function fetch_notify_ids ($notifymessages)
	{
		$notify_ids = [];

		foreach ($notifymessages as $data)
		{
			if (is_array($data) && $data['id'])
			{
				array_push($notify_ids, (string)$data['id']);
				if (is_array($data['data'])) $notify_ids = array_unique(array_merge($notify_ids, $this->search_in_notify_data($data['data']['id'], $data['data']['app'])));
			}
			else
			{
				array_push($notify_ids, (string)$data);
			}

		}
		return $notify_ids;
	}

	/**
	 * Fetches all notify_ids relevant to the entry
	 * @param $_id
	 * @param $_appname
	 * @return array
	 */
	public function search_in_notify_data($_id, $_appname)
	{
		$ret = [];
		if ($_id && $_appname)
		{
			try {
				// mariaDB supported query
				$ret = $this->db->select(self::_notification_table, 'notify_id', array(
					'account_id' => $this->recipient->account_id,
					'notify_type' => self::_type,
					'notify_data->"$.appname"' => $_appname,
					'notify_data->"$.data.id"' => $_id
				),
					__LINE__,__FILE__,0 ,'ORDER BY notify_id DESC',self::_appname);
			}
			catch (Api\Db\Exception $e) {
				// do it manual for all other DB
				foreach($this->db->select(self::_notification_table, '*', array(
					'account_id' => $this->recipient->account_id,
					'notify_type' => self::_type
				),
					__LINE__,__FILE__,0 ,'ORDER BY notify_id DESC',self::_appname) as $row)
				{
					$data = json_decode($row['notify_data'], true);
					if ($data['appname'] == $_appname && $data['data']['id'] == $_id) $ret[] = $row['notify_id'];
				}
			}
		}
		return $ret;
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

	/**
	 * restores the users session data for notifications
	 *
	 * @return boolean true
	 */
	private function restore_session_data() {
		$session_data = Api\Cache::getSession(self::_appname, 'session_data');
		if(is_array($session_data)) {
			$this->session_data = $session_data;
		} else {
			$this->session_data = $this->session_data_defaults;
		}

		return true;
	}

	/**
	 * saves the users session data for notifications
	 *
	 * @return boolean true
	 */
	private function save_session_data() {
		Api\Cache::setSession(self::_appname, 'session_data', $this->session_data);
		return true;
	}
}