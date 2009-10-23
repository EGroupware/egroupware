<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage backends
 * @link http://www.egroupware.org
 * @author Christian Binder <christian@jaytraxx.de>
 * @version $Id$
 */

/**
 * User notification via winpopup.
 */
class notifications_winpopup implements notifications_iface {

	/**
	 * Appname
	 */
	const _appname = 'notifications';

	/**
	 * Login table in SQL database
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
	 * holds the netbios command to be executed on notification
	 *
	 * @abstract
	 * Example: $netbios_command = "/bin/echo [MESSAGE] | /usr/bin/smbclient -M computer-[4] -I [IP] -U [SENDER]";
	 *
	 * Placeholders are:
	 * [MESSAGE] is the notification message itself
	 * [1] - [4] are the IP-Octets of the windows machine to notify
	 * [IP] is the IP-Adress of the windows machine to notify
	 * [SENDER] is the sender of the message
	 * Note: the webserver-user needs execute rights for this command
	 *
	 * @var string
	 */
	private $netbios_command = false;

	/**
	 * constructor of notifications_winpopup
	 *
	 * @param object $_sender
	 * @param object $_recipient
	 * @param object $_config
	 * @param object $_preferences
	 */
	public function __construct($_sender, $_recipient, $_config = null, $_preferences = null) {
		if(!is_object($_sender)) { throw new Exception("no sender given."); }
		if(!is_object($_recipient)) { throw new Exception("no recipient given."); }
		if(!$this->netbios_command) {
			throw new Exception(	'Winpopup plugin not configured yet. Skipped sending notification message. '.
									'Please check var "netbios_command" in winpopup backend '.
									'('.EGW_INCLUDE_ROOT. SEP. self::_appname. SEP. 'inc'. SEP. 'class.notifications_winpopup.inc.php).');
		}
		$this->sender = $_sender;
		$this->recipient = $_recipient;
		$this->config = $_config;
		$this->preferences = $_preferences;
	}

	/**
	 * sends notification
	 *
	 * @param array $_messages
	 * @param string $_subject
	 * @param array $_links
	 * @param array $_attachments
	 */
	public function send(array $_messages, $_subject = false, $_links = false, $_attachments = false) {
		$user_sessions = array();
		foreach (egw_session::session_list(0, 'asc', 'session_dla', true) as $session) {
			if ($session['session_lid'] == $this->recipient->account_lid. '@'. $GLOBALS['egw_info']['user']['domain']) {
				if($this->valid_ip($session['session_ip'])) {
					$user_sessions[] = $session['session_ip'];
				}
			}
		}
		if ( empty($user_sessions) ) throw new Exception("User #{$this->recipient->account_id} isn't online. Can't send notification via winpopup");

		$this->send_winpopup( $this->render_infos($_subject).$_messages['plain'], $user_sessions );
		return true;
	}

	/**
	 * sends the winpopup message via command line string netbios_command specified above
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
			$placeholders = array(	'/\[MESSAGE\]/' => escapeshellarg($_message), // prevent code injection
									'/\[1\]/' => $ip_octets[0],
									'/\[2\]/' => $ip_octets[1],
									'/\[3\]/' => $ip_octets[2],
									'/\[4\]/' => $ip_octets[3],
									'/\[IP\]/' => $user_session,
									'/\[SENDER\]/' => $this->sender->account_fullname ? escapeshellarg($this->sender->account_fullname) : escapeshellarg($this->sender->account_email),
									);
			$command = preg_replace(array_keys($placeholders), $placeholders, $this->netbios_command);
			exec($command,$output,$returncode);
			if($returncode != 0) {
				throw new Exception("Failed sending notification message via winpopup. Error while executing the specified command.");
			}
		}
	}

	/**
	 * checks for a valid IPv4-address without CIDR notation
	 *
	 * @param string $_ip
	 * @return true or false
	 */
	private function valid_ip($_ip) {
		return eregi('^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$',$_ip);
	}

	/**
	 * renders additional info from subject
	 *
	 * @param string $_subject
	 * @return plain rendered info as complete string
	 */
	private function render_infos($_subject = false) {
		$newline = "\n";
		if(!empty($_subject)) { return $_subject.$newline; }
		return false;
	}
}