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
	 * holds preferences object of user to notify
	 *
	 * @var object
	 */
	private $preferences;
	
	/**
	 * reference to global db object
	 *
	 * @var egw_db
	 */
	private $db;


	/**
	 * constructor of ajaxnotifications
	 *
	 */
	public function __construct() {
		$this->recipient = (object)$GLOBALS['egw']->accounts->read();

		$config = new config(self::_appname);
		$this->config = (object)$config->read_repository();

		$prefs = new preferences($this->recipient->account_id);
		$preferences = $prefs->read();
		$this->preferences = (object)$preferences[self::_appname];

		$this->db = $GLOBALS['egw']->db;
	}
	
	/**
	 * Gets all egwpopup notification for calling user.
	 * Requests and response is done via xajax
	 * 
	 * @return xajax response
	 */
	public function get_popup_notifications() {
		$response = new xajaxResponse();
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
				$response->addScriptCall('append_notification_message',$notification['message']);
			}
			$myval=$this->db->delete(self::_notification_table,array(
				'account_id' => $this->recipient->account_id,
				'session_id' => $session_id,
			),__LINE__,__FILE__,self::_appname);
			
			switch($this->preferences->egwpopup_verbosity) {
				case 'low':
					$response->addScript('notificationbell_switch("active");');
					break;
				case 'high':
					$response->addAlert(lang('eGroupWare has notifications for you'));
					$response->addScript('notificationwindow_display();');
					break;
				case 'medium':
				default:
					$response->addScript('notificationwindow_display();');
					break;
			}
		}
		return $response->getXML();
	}
}