<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage backends
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

require_once('class.iface_notification.inc.php');
require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.html.inc.php');

/**
 * Instant user notification with egroupware popup.
 *
 * @abstract egwpopup is a two stage notification. In the first stage 
 * notification is written into self::_notification_egwpopup
 * table. In the second stage a request from the client reads
 * out the table to look if there is a notificaton for this 
 * client. The second stage is done in class.ajaxnotifications.inc.php
 * (multidisplay is supported)
 *
 */
class notification_popup implements iface_notification {
	
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
	 * holds html object to render elements
	 *
	 * @var object
	 */
	private $html;
	
	/**
	 * constructor of notification_egwpopup
	 *
	 * @param object $_sender
	 * @param object $_recipient
	 * @param object $_config
	 * @param object $_preferences
	 */
	public function __construct($_sender, $_recipient, $_config = null, $_preferences = null) {
		if(!is_object($_sender)) { throw new Exception("no sender given."); }
		if(!is_object($_recipient)) { throw new Exception("no recipient given."); }
		$this->sender = $_sender;
		$this->recipient = $_recipient;
		$this->config = $_config;
		$this->preferences = $_preferences;
		$this->db = &$GLOBALS['egw']->db;
		$this->db->set_app( self::_appname );
		$this->html = & html::singleton();
	}
	
	/**
	 * sends notification if user is online
	 *
	 * @param array $_messages
	 * @param string $_subject
	 * @param array $_links
	 * @param array $_attachments
	 */
	public function send(array $_messages, $_subject = false, $_links = false, $_attachments = false) {
		$sessions = $GLOBALS['egw']->session->list_sessions(0, 'asc', 'session_dla', true);
		$user_sessions = array();
		foreach ($sessions as $session) {
			if ($session['session_lid'] == $this->recipient->account_lid. '@'. $GLOBALS['egw_info']['user']['domain']) {
				$user_sessions[] = $session['session_id'];
			}
		}
		if ( empty($user_sessions) ) throw new Exception("User {$this->recipient->account_lid} isn't online. Can't send notification via popup");
		
		$message = 	$this->render_infos($_subject)
					.$this->html->hr()
					.$_messages['html']
					.$this->html->hr()
					.$this->render_links($_links);
					
		$this->save( $message, $user_sessions );
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
	
	/**
	 * renders plaintext/html links from given link array
	 *
	 * @param array $_links
	 * @return html rendered link(s) as complete string (jspopup)
	 */
	private function render_links($_links = false) {
		if(!is_array($_links) || count($_links) == 0) { return false; }
		$newline = "<br />"; 
		
		$link_array = array();
		foreach($_links as $link) {
			$url = $this->html->link('/index.php?menuaction='.$link->menuaction, $link->params);
			$menuaction_arr = explode('.',$link->menuaction);
			$application = $menuaction_arr[0];
			$image = $application ? $this->html->image($application,'navbar',$link->text,'align="middle" style="width: 24px; margin-right: 0.5em;"') : '';
			$link_array[] = $this->html->div($image.$link->text,'onclick="'.$this->jspopup($url).'"','jspopup');
		}

		return $this->html->bold(lang('Linked entries:')).$newline.implode($newline,$link_array);
	}
		
	/**
	 * returns javascript to open a popup window: window.open(...)
	 *
	 * @param string $link link or this.href
	 * @param string $target='_blank' name of target or this.target
	 * @param int $width=750 width of the window
	 * @param int $height=400 height of the window
	 * @return string javascript (using single quotes)
	 */
	private function jspopup($link,$target='_blank',$width=750,$height=410)
	{
		return 'egw_openWindowCentered2('.($link == 'this.href' ? $link : "'".$link."'").','.
			($target == 'this.target' ? $target : "'".$target."'").",$width,$height,'yes')";
	}
	
	/**
	 * renders additional infos from sender and subject
	 *
	 * @param string $_subject
	 * @return html rendered info as complete string
	 */
	private function render_infos($_subject = false) {
		$infos = array();
		$newline = "<br />"; 
		
		$sender = $this->sender->account_fullname ? $this->sender->account_fullname : $this->sender_account_email;
		$infos[] = lang('Message from').': '.$sender;
		if(!empty($_subject)) { $infos[] = $this->html->bold($_subject); }
		return implode($newline,$infos);
	}
}
