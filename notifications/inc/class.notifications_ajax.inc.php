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
class notifications_ajax {
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
	 * constructor
	 *
	 */
	public function __construct() {
		$this->response = new xajaxResponse();
		$this->recipient = (object)$GLOBALS['egw']->accounts->read($GLOBALS['egw_info']['user']['account_id']);

		$config = new config(self::_appname);
		$this->config = (object)$config->read_repository();

		$prefs = new preferences($this->recipient->account_id);
		$preferences = $prefs->read();
		$this->preferences = $prefs->read();

		$this->db = $GLOBALS['egw']->db;
	}

	/**
	 * destructor
	 *
	 */
	public function __destruct() {}

	/**
	 * public AJAX trigger function to be called by the JavaScript client
	 *
	 * this function calls all other recurring AJAX notifications methods
	 * to have ONE single recurring AJAX call per user
	 *
	 * @return xajax response
	 */
	public function get_notifications() {
		if ($GLOBALS['egw_info']['user']['apps']['felamimail'])  $this->check_mailbox();
		$this->get_egwpopup();

		return $this->response->getXML();
	}

	/**
	 * checks users mailbox and sends a notification if new mails have arrived
	 *
	 * @return boolean true or false
	 */
	private function check_mailbox()
	{
		//error_log(__METHOD__.__LINE__.array2string($this->preferences[self::_mailappname]['notify_folders']));
		if(!isset($this->preferences[self::_mailappname]['notify_folders'])||$this->preferences[self::_mailappname]['notify_folders']=='none') {
			return true; //no pref set for notifying - exit
		}
		$notify_folders = explode(',', $this->preferences[self::_mailappname]['notify_folders']);
		if(count($notify_folders) == 0) {
			return true; //no folders configured for notifying - exit
		}
		// explicit require bofelamimail, 'til it get a autoloading conform name
		require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.bofelamimail.inc.php');

		$bofelamimail = new bofelamimail($GLOBALS['egw']->translation->charset());
		// buffer felamimail sessiondata, as they are needed for information exchange by the app itself
		$bufferFMailSession = $bofelamimail->sessionData;
		if(!$bofelamimail->openConnection()) {
			// TODO: This is ugly. Log a bit nicer!
			error_log(self::_appname.' (user: '.$this->recipient->account_lid.'): cannot connect to mailbox. Please check your prefs!');
			return false; // cannot connect to mailbox
		}

		$this->restore_session_data();

		$recent_messages = array();
		$folder_status = array();
		foreach($notify_folders as $id=>$notify_folder) {
			if(!is_array($this->session_data['notified_mail_uids'][$notify_folder])) {
				$this->session_data['notified_mail_uids'][$notify_folder] = array();
			}
			$folder_status[$notify_folder] = $bofelamimail->getFolderStatus($notify_folder);
			$cutoffdate = time();
			$cutoffdate = $cutoffdate - (60*60*24*14); // last 14 days
			$_filter = array('status'=>'UNSEEN','type'=>"SINCE",'string'=> date("d-M-Y", $cutoffdate));
			//error_log(__METHOD__.__LINE__.' (user: '.$this->recipient->account_lid.') Mailbox:'.$notify_folder.' filter:'.array2string($_filter));
			// $_folderName, $_startMessage, $_numberOfMessages, $_sort, $_reverse, $_filter, $_thisUIDOnly=null, $_cacheResult=true
			$headers = $bofelamimail->getHeaders($notify_folder, 1, 999, 0, true, $_filter);
			if(is_array($headers['header']) && count($headers['header']) > 0) {
				foreach($headers['header'] as $id=>$header) {
					// check if unseen mail has already been notified
				 	if(!in_array($header['uid'], $this->session_data['notified_mail_uids'][$notify_folder])) {
				 		// got a REAL recent message
				 		$header['folder'] = $notify_folder;
				 		$header['folder_display_name'] = $folder_status[$notify_folder]['displayName'];
				 		$header['folder_base64'] =  base64_encode($notify_folder);
				 		$recent_messages[] = $header;
				 	}
				}
			}
		}
		// restore the felamimail session data, as they are needed by the app itself
		$bofelamimail->sessionData = $bufferFMailSession;
		$bofelamimail->saveSessionData();
		if(count($recent_messages) > 0) {
			// create notify message
			$notification_subject = lang("You've got new mail");
			$values = array();
			$values[] = array(); // content array starts at index 1
			foreach($recent_messages as $id=>$recent_message) {
				$values[] =	array(
					'mail_uid'				=> $recent_message['uid'],
					'mail_folder' 			=> $recent_message['folder_display_name'],
					'mail_folder_base64' 	=> $recent_message['folder_base64'],
					'mail_subject'			=> $recent_message['subject'],
					'mail_from'				=> !empty($recent_message['sender_name']) ? $recent_message['sender_name'] : $recent_message['sender_address'],
					'mail_received'			=> $recent_message['date'],
				);
				// save notification status
				$this->session_data['notified_mail_uids'][$recent_message['folder']][] = $recent_message['uid'];
			}

			// create etemplate
			$tpl = new etemplate();
			$tpl->read('notifications.checkmailbox');
			$notification_message = $tpl->exec(false, $values, false, false, false, 1);

			// send notification
			$notification = new notifications();
			$notification->set_receivers(array($this->recipient->account_id));
			$notification->set_message($notification_message);
			$notification->set_sender($this->recipient->account_id);
			$notification->set_subject($notification_subject);
			$notification->set_skip_backends(array('email'));
			$notification->send();
		}

		$this->save_session_data();
		return true;
	}

	/**
	 * gets all egwpopup notifications for calling user
	 *
	 * @return boolean true or false
	 */
	private function get_egwpopup() {
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
					$this->response->addScript('egwpopup_display();');
					break;
				case 'medium':
				default:
					$this->response->addScript('egwpopup_display();');
					break;
			}
		}
		return true;
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
