<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * Notifies users according to their preferences.
 * 
 * @abstract NOTE:Notifications are small messages. No subject and no attechments. 
 * If you need this kind of elements you probably want to send a mail, don't you :-)
 * @abstract NOTE: This is for instant notifications. If you need time dependend notifications use the 
 * asyncservices wrapper!
 * 
 * The classes doing the notifications are called notification_<method> and should only be 
 * called from this class.
 * The <method> gets extractd out of the preferences labels.
 *
 */
final class notification {

	/**
	 * Appname
	 */
	const _appname = 'notifications';
	
	/**
	 * array with objects of receivers
	 * @var array
	 */
	private $receivers = array();
	
	/**
	 * holds notification message
	 * @var string
	 */
	private $message = '';
	
	/**
	 * sets notification message
	 * @param string &$message
	 */
	public function set_message($_message) {
		$this->message = $_message;
		return true;
	}
	
	/**
	 * Set receivers for the current notification
	 *
	 * @param array $_receivers array with objects of accounts
	 * as long as the accounts class isn't a nice object, it's an array of account id's :-(
	 */
	public function set_receivers(array $_receivers) {
		foreach ($_receivers as $receiver_id) {
			$receiver = $GLOBALS['egw']->accounts->get_account_data($receiver_id);
			$receiver[$receiver_id]['id'] = $receiver_id;
			$this->receivers[$receiver_id] = (object)$receiver[$receiver_id];
		}
	}
	
	/**
	 * sends notification 
	 */
	public function send() {
		if (empty($this->receivers) || !$this->message) {
			throw new Exception('Error: Coud not send notification. No receiver or no message where supplied');
		}
		
		foreach ($this->receivers as $receiver) {
			$prefs = new preferences($receiver->id);
			$preferences = $prefs->read();
			$preferences = (object)$preferences[self::_appname ];
			
			if (!$preferences->disable_ajaxpopup) {
				$notification_backends[] = 'notification_popup';
			}
			
			$send_succseed = 0;
			foreach ((array)$notification_backends as $notification_backend) {
				try {
					require_once(EGW_INCLUDE_ROOT. SEP. self::_appname. SEP. 'inc'. SEP. 'class.'. $notification_backend. '.inc.php');
					
					$obj = @new $notification_backend( $receiver, $preferences );
					if ( !is_a( $obj, iface_notification )) {
						unset ( $obj );
						throw new Exception('Error: '.$notification_backend. ' is no implementation of iface_notification');
					}
					
					$obj->send( $this->message );
					$send_succseed++;
				}
				catch (Exception $exception) {
					$send_succseed--;
					//echo $exception->getMessage(), "\n";
				}
			}
			
			if ($send_succseed == 0) {
				throw new Exception('Error: Was not able to send Notification to user!');
			}
		}
	}
	
	/**
	 * gets message
	 * 
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}
	
	/**
	 * gets receivers
	 *
	 * @return array of account objects
	 */
	public function get_receivers() {
		return $this->receivers;
	}

}
