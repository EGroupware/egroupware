<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpopup
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

require_once('class.iface_notification.inc.php');

/**
 * Instant user notification with egroupware popup.
 * egwpopup is a two stage notification. In the first stage 
 * notification is written into self::_notification_egwpopup
 * table. In the second stage a request from the client reads
 * out the table to look if there is a notificaton for this 
 * client. (multidisplay is supported)
 */
class notification_popup implements iface_notification {

	/**
	 * Notification window {div|alert}
	 */
	const _window = 'div';
	
	/**
	 * Appname
	 */
	const _appname = 'notifications';
	
	/**
	 * Notification table in SQL database
	 */
	const _notification_table = 'egw_notificationpopup';
	
	/**
	 * holds account object for user to notify
	 *
	 * @var object
	 */
	private $account;
	
	/**
	 * holds preferences object of user to notify
	 *
	 * @var object
	 */
	private $preferences;
	
	/**
	 * holds db object of SQL database
	 *
	 * @var egw_db
	 */
	private $db;
	
	/**
	 * constructor of notification_egwpopup
	 *
	 * @param object $_account
	 * @param object $_preferences
	 */
	public function __construct( $_account=false, $_preferences=false) {
		// If we are called from class notification account and prefs are objects.
		// otherwise we have to fetch this objects for current user.
		if (!is_object($_account)) {
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
			$this->account = $GLOBALS['egw']->accounts->get_account_data($account_id);
			$this->account[$account_id]['id'] = $account_id;
			$this->account = (object)$this->account[$account_id];
		}
		else {
			$this->account = $_account;
		}
		$this->preferences = is_object($_preferences) ? $_preferences : $GLOBALS['egw']->preferences;
		$this->db = &$GLOBALS['egw']->db;
		$this->db->set_app( self::_appname );
	}
	
	/**
	 * sends notification if user is online
	 *
	 * @param string $_message
	 */
	public function send( $_message ) {
		$sessions = $GLOBALS['egw']->session->list_sessions(0, 'asc', 'session_dla', true);
		$user_sessions = array();
		foreach ($sessions as $session) {
			if ($session['session_lid'] == $this->account->lid. '@'. $GLOBALS['egw_info']['user']['domain']) {
				$user_sessions[] = $session['session_id'];
			}
		}
		if ( empty($user_sessions) ) throw new Exception("Notice: User isn't online. Can't send notification via popup");
		$this->save( $_message, $user_sessions );
	}
	
	/**
	 * Gets all notification for current user.
	 * Requests and response is done via xajax
	 * 
	 * @return xajax response
	 */
	public function ajax_get_notifications() {
		$response =& new xajaxResponse();
		$session_id = $GLOBALS['egw_info']['user']['sessionid'];
		$message = '';
		$this->db->select(self::_notification_table, 
			'*', array(
				'account_id' => $this->account->id,
				'session_id' => $session_id,
			),
			__LINE__,__FILE__);
		if ($this->db->num_rows() != 0)	{
			while ($notification = $this->db->row(true)) {
				switch (self::_window ) {
					case 'div' :
						$message .= nl2br($notification['message']). '<br>';
						break;
					case 'alert' :
						$message .= $notification['message']. "\n";
						break;
				}
			}
			$this->db->delete(self::_notification_table,array(
				'account_id' =>$this->account->id,
				'session_id' => $session_id,
			),__LINE__,__FILE__);
			
			switch (self::_window) {
				case 'div' :
					$response->addAppend('notificationwindow_message','innerHTML',$message);
					$response->addScript('notificationwindow_display();');
					break;
				case 'alert' :
					$response->addAlert($message);
					break;
			}
		}
		return $response->getXML();
	}
	
	/**
	 * saves notification into database so that the client can fetch it from 
	 * there via notification->get
	 *
	 * @param string $_message
	 * @param array $_user_sessions
	 */
	private function save( $_message, array $_user_sessions ) {
		foreach ($_user_sessions as $user_session) {
			$result =& $this->db->insert( self::_notification_table, array(
				'account_id'	=> $this->account->id,
				'session_id'	=> $user_session,
				'message'		=> $_message
				), false,__LINE__,__FILE__);
		}
		if ($result === false) throw new Exception("Error: Can't save notification into SQL table");
	}
}
