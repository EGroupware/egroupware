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
require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.config.inc.php');

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
	 * holds account object for user who sends the message
	 *
	 * @var object
	 */
	private $sender;
	
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
	 * @param object $_sender
	 * @param object $_recipient
	 * @param object $_config
	 * @param object $_preferences
	 */
	public function __construct( $_sender=false, $_recipient=false, $_config=false, $_preferences=false) {
		// If we are called from class notification sender, recipient, config and prefs are objects.
		// otherwise we have to fetch this objects for current user.
		if (!is_object($_sender)) {
			$this->sender = (object) $GLOBALS['egw']->accounts->read($_sender);
		}
		else {
			$this->sender = $_sender;
		}
		if (!is_object($_recipient)) {
			$this->recipient = (object) $GLOBALS['egw']->accounts->read($_recipient);
		}
		else {
			$this->recipient = $_recipient;
		}
		if(!is_object($_config)) {
			$config = new config(self::_appname);
			$this->config = (object) $config->read_repository();
		} else {
			$this->config = $_config;
		}
		if(!is_object($_preferences)) {
			$prefs = new preferences($this->recipient->account_id);
			$preferences = $prefs->read();
			$this->preferences = (object)$preferences[self::_appname ];
		} else {
			$this->preferences = $_preferences;
		}
		$this->db = &$GLOBALS['egw']->db;
		$this->db->set_app( self::_appname );
	}
	
	/**
	 * sends notification if user is online
	 *
	 * @param string $_subject
	 * @param array $_messages
	 * @param array $_attachments
	 */
	public function send( $_subject = false, $_messages, $_attachments = false) {
		if(!is_object($this->sender)) {
			throw new Exception("No sender given.");
		}
		$sessions = $GLOBALS['egw']->session->list_sessions(0, 'asc', 'session_dla', true);
		$user_sessions = array();
		foreach ($sessions as $session) {
			if ($session['session_lid'] == $this->recipient->account_lid. '@'. $GLOBALS['egw_info']['user']['domain']) {
				$user_sessions[] = $session['session_id'];
			}
		}
		if ( empty($user_sessions) ) throw new Exception("User {$this->recipient->account_lid} isn't online. Can't send notification via popup");
		$this->save( $_messages['html']['info_sender'].$_messages['html']['info_subject'].$_messages['html']['text'].$_messages['html']['link_jspopup'], $user_sessions );
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
				'account_id' => $this->recipient->account_id,
				'session_id' => $session_id,
			),
			__LINE__,__FILE__);
		if ($this->db->num_rows() != 0)	{
			while ($notification = $this->db->row(true)) {
				switch (self::_window ) {
					case 'div' :
						$response->addScriptCall('append_notification_message',$notification['message']);
						break;
					case 'alert' :
						$response->addAlert($notification['message']);
						break;
				}
			}
			$myval=$this->db->delete(self::_notification_table,array(
				'account_id' => $this->recipient->account_id,
				'session_id' => $session_id,
			),__LINE__,__FILE__);
			
			switch (self::_window) {
				case 'div' :
					switch($this->preferences->egwpopup_verbosity) {
						case 'low':
							$response->addScript('notificationbell_switch("active");');
							break;
						case 'high':
							$response->addAlert(lang('eGroupware has some notifications for you'));
							$response->addScript('notificationwindow_display();');
							break;
						case 'medium':
						default:
							$response->addScript('notificationwindow_display();');
							break;
					}
					break;
				case 'alert' :
					// nothing to do for now
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
				'account_id'	=> $this->recipient->account_id,
				'session_id'	=> $user_session,
				'message'		=> $_message
				), false,__LINE__,__FILE__);
		}
		if ($result === false) throw new Exception("Can't save notification into SQL table");
	}
}
