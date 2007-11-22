<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Christian Binder <christian@jaytraxx.de>
 */

require_once('class.iface_notification.inc.php');
require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.config.inc.php');

/**
 * User notification via winpopup.
 */
class notification_winpopup implements iface_notification {

	/**
	 * Appname
	 */
	const _appname = 'notifications';
	
	/**
	 * Notification table in SQL database
	 */
	const _login_table = 'egw_access_log';
	
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
	 * constructor of notification_winpopup
	 *
	 * @param object $_recipient
	 * @param object $_preferences
	 */
	public function __construct($_sender=false, $_recipient=false, $_config=false, $_preferences=false) {
		// If we are called from class notification sender, recipient, config and prefs are objects.
		// otherwise we have to fetch this objects for current user.
		if (!is_object($_sender)) {
			$this->sender = (object) $GLOBALS['egw']->accounts->read($_sender);
			$this->sender->id =& $this->sender->account_id;
		}
		else {
			$this->sender = $_sender;
		}
		if (!is_object($_recipient)) {
			$this->recipient = (object) $GLOBALS['egw']->accounts->read($_recipient);
			$this->recipient->id =& $this->recipient->account_id;
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
			$prefs = new preferences($this->recipient->id);
			$preferences = $prefs->read();
			$this->preferences = (object)$preferences[self::_appname ];
		} else {
			$this->preferences = $_preferences;
		}
	}
	
	/**
	 * sends notification
	 *
	 * @param string $_subject
	 * @param array $_messages
	 * @param array $_attachments
	 */
	public function send( $_subject = false, $_messages, $_attachments = false) {
		if(!$this->config->winpopup_netbios_command) {
			throw new Exception("Winpopup plugin not configured yet. Skipped sending notification message. Please check your settings.");
		}
		$sessions = $GLOBALS['egw']->session->list_sessions(0, 'asc', 'session_dla', true);
		$user_sessions = array();
		foreach ($sessions as $session) {
			if ($session['session_lid'] == $this->recipient->lid. '@'. $GLOBALS['egw_info']['user']['domain']) {
				$user_sessions[] = $session['session_ip'];
			}
		}
		if ( empty($user_sessions) ) throw new Exception("User #{$this->recipient->id} isn't online. Can't send notification via winpopup");
		$this->send_winpopup( $_messages['plain']['text'], $user_sessions );
	}
	
	/**
	 * sends the winpopup message via pre-defined smbclient tool in prefs
	 *
	 * @param string $_message
	 * @param array $_user_sessions
	 */
	private function send_winpopup( $_message, array $_user_sessions ) {
		foreach($_user_sessions as $user_session) {
			$ip_octets=explode(".",$user_session);
			// format the ip_octets to 3 digits each
			foreach($ip_octets as $id=>$ip_octet) {
				if(strlen($ip_octet)==1) { $ip_octets[$id] = '00'.$ip_octet; }
				if(strlen($ip_octet)==2) { $ip_octets[$id] = '0'.$ip_octet; }
			}
			$placeholders = array(	'/\[MESSAGE\]/' => $_message,
									'/\[1\]/' => $ip_octets[0],
									'/\[2\]/' => $ip_octets[1],
									'/\[3\]/' => $ip_octets[2],
									'/\[4\]/' => $ip_octets[3],
									'/\[IP\]/' => $user_session,
									'/\[SENDER\]/' => $GLOBALS['egw']->accounts->id2name($this->sender->id,'account_fullname'),
									);
			$command = preg_replace(array_keys($placeholders), $placeholders, $this->config->winpopup_netbios_command);
			if(!exec($command)) {
				throw new Exception("Failed sending notification message via winpopup. Please check your settings.");
			}
		}
	}
}