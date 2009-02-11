<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpopup
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>, Christian Binder <christian@jaytraxx.de>
 */
 
/**
 * Ajax methods for notifications
 */
class ajaxnotifications {
	/**
	 * Appname
	 */
	const _appname = 'notifications';
	
	/**
	 * Mailappname
	 */
	const _mailappname = 'felamimail';
	
	/**
	 * Notification table in SQL database
	 */
	const _notification_table = 'egw_notificationpopup';
	
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
	 * @var db
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
	 * @var response
	 */
	private $response;
	
	/**
	 * constructor of ajaxnotifications
	 *
	 */
	public function __construct() {
		$this->response = new xajaxResponse();
		$this->recipient = (object)$GLOBALS['egw']->accounts->read();

		$config = new config(self::_appname);
		$this->config = (object)$config->read_repository();

		$prefs = new preferences($this->recipient->account_id);
		$preferences = $prefs->read();
		$this->preferences = $prefs->read();

		$this->db = $GLOBALS['egw']->db;
	}
	
	/**
	 * destructor of ajaxnotifications
	 *
	 */
	public function __destruct() {}
	
	/**
	 * Gets all egwpopup notification for calling user.
	 * Requests and response is done via xajax
	 * 
	 * @return xajax response
	 */
	public function get_popup_notifications() {
		$session_id = $GLOBALS['egw_info']['user']['sessionid'];
		$message = '';
		$rs = $this->db->select(self::_notification_table, 
			'*', array(
				'account_id' => $this->recipient->account_id,
				'session_id' => $session_id,
			),
			__LINE__,__FILE__,false,'',self::_appname);
		if ($rs->NumRows() > 0)	{
			foreach ($rs as $notification) {
				$this->response->addScriptCall('append_notification_message',$notification['message']);
			}
			$myval=$this->db->delete(self::_notification_table,array(
				'account_id' => $this->recipient->account_id,
				'session_id' => $session_id,
			),__LINE__,__FILE__,self::_appname);
			
			switch($this->preferences[self::_appname]['egwpopup_verbosity']) {
				case 'low':
					$this->response->addScript('notificationbell_switch("active");');
					break;
				case 'high':
					$this->response->addAlert(lang('eGroupWare has notifications for you'));
					$this->response->addScript('notificationwindow_display();');
					break;
				case 'medium':
				default:
					$this->response->addScript('notificationwindow_display();');
					break;
			}
		}
		return $this->response->getXML();
	}
	
	/**
	 * checks users mailbox and sends a notification if new mails have arrived
	 * 
	 * @return xajax response
	 */
	public function check_mailbox() {
		if(!isset($this->preferences[self::_mailappname]['notify_folders'])) {
			return $this->response->getXML(); //no pref set for notifying - exit
		}
		$notify_folders = explode(',', $this->preferences[self::_mailappname]['notify_folders']);
		if (count($notify_folders) == 0) {
			return $this->response->getXML(); //no folders configured for notifying - exit
		}
		
		$bofelamimail = new bofelamimail($GLOBALS['egw']->translation->charset());
		if(!$bofelamimail->openConnection()) {
			// TODO: This is ugly. Log a bit nicer!
			error_log(self::_appname.' (user: '.$this->recipient->account_lid.'): cannot connect to mailbox. Please check your prefs!');
			return $this->response->getXML(); // cannot connect to mailbox
		}
		
		$this->restore_session_data();
		
		$recent_messages = array();
		foreach($notify_folders as $id=>$notify_folder) {
			$headers = $bofelamimail->getHeaders($notify_folder, 1, false, 0, true, array('status'=>'UNSEEN'));
			if(is_array($headers['header']) && count($headers['header']) > 0) {
				foreach($headers['header'] as $id=>$header) {
					// check if unseen mail has already been notified
				 	if(!in_array($header['uid'], $this->session_data['notified_mail_uids'])) {
				 		// got a REAL recent message
				 		$header['folder'] = $notify_folder;
				 		$recent_messages[] = $header;
				 	}
				}
			}
		}
		
		if(count($recent_messages) > 0) {
			// create notify messages and save notification status in user session
			if(count($recent_messages) == 1) {
				$notify_subject = lang("You've got a new mail");
			} else {
				$notify_subject = lang("You've got new mails");
			}
			$notify_message = '<table>';
			foreach($recent_messages as $id=>$recent_message) {
				$notify_message .=	'<tr>'
									.'<td>'.$recent_message['folder'].'</td>'
									.'<td>'.$recent_message['subject'].'</td>'
									.'<td>'.$recent_message['sender_address'].'</td>'
									.'</tr>';
				// save notification status
				$this->session_data['notified_mail_uids'][] = $recent_message['uid'];
			}
			$notify_message .= '</table>';
			
			// send notification
			$notification = new notifications();
			$notification->set_receivers(array($this->recipient->account_id));
			$notification->set_message($notify_message);
			$notification->set_sender($this->recipient->account_id);
			$notification->set_subject($notify_subject);
			$notification->set_skip_backends(array('email'));
			$notification->send();
		}
		
		$this->save_session_data();
		return $this->response->getXML();
	}
	
	/**
	 * restores the users session data for notifications
	 * 
	 * @return boolean true
	 */
	private function restore_session_data() {
		$session_data = $GLOBALS['egw']->session->appsession('session_data',self::_appname);
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
		$GLOBALS['egw']->session->appsession('session_data',self::_appname,$this->session_data);
		return true;
	}
}
