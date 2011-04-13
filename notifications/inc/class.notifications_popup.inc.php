<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage backends
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>, Christian Binder <christian@jaytraxx.de>
 * @version $Id$
 */

/**
 * Instant user notification with egroupware popup.
 *
 * @abstract egwpopup is a two stage notification. In the first stage
 * notification is written into self::_notification_table.
 * In the second stage a request from the client reads
 * out the table to look if there is a notificaton for this
 * client. The second stage is done in class.notifications_ajax.inc.php
 *
 * Todo:
 * - save the messages by uid instead of sessionid into the notification table, this
 * has several advantages (users poll the messages via ajax from multiple logins, and
 * do not have to read one message twice, poll after re-login with different sessionid)
 * - delete message from the table only if the user has really seen it
 * - if the above things are done we should get rid of rendering the links here,
 * instead it should be done by the ajax class, so sessionids in links could be possible then
 *
 * (multidisplay is supported)
 *
 */
class notifications_popup implements notifications_iface {

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
	 * constructor of notifications_egwpopup
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
		// Check access log to see if user is still logged in
		if ( !egw_session::notifications_active($this->recipient->account_id) )
		{
			throw new Exception("User {$this->recipient->account_lid} isn't online. Can't send notification via popup");
		}

		$message = 	$this->render_infos($_subject)
					.html::hr()
					.$_messages['html']
					.$this->render_links($_links);

		$this->save( $message );
	}

	/**
	 * saves notification into database so that the client can fetch it from there
	 *
	 * @param string $_message
	 * @param array $_user_sessions
	 */
	private function save( $_message ) {
		$result = $this->db->insert( self::_notification_table, array(
			'account_id'	=> $this->recipient->account_id,
			'message'		=> $_message
			), false,__LINE__,__FILE__,self::_appname);
		if ($result === false) throw new Exception("Can't save notification into SQL table");
	}

	/**
	 * renders plaintext/html links from given link array
	 * should be moved to the ajax class later - like mentioned in the Todo
	 *
	 * @param array $_links
	 * @return html rendered link(s) as complete string with jspopup or a new window
	 */
	private function render_links($_links = false) {
		if(!is_array($_links) || count($_links) == 0) { return false; }
		$newline = "<br />";

		$rendered_links = array();
		foreach($_links as $link) {
			if(!$link->popup) { $link->view['no_popup'] = 1; }

			$url = html::link('/index.php', $link->view);
			// do not expose sensitive data 	 
			$url = preg_replace('/(sessionid|kp3|domain)=[^&]+&?/','',$url);
			// extract application-icon from menuaction
			if($link->view['menuaction']) {
				$menuaction_arr = explode('.',$link->view['menuaction']);
				$application = $menuaction_arr[0];
				$image = $application ? html::image($application,'navbar',$link->text,'align="middle" style="width: 24px; margin-right: 0.5em;"') : '';
			} else {
				$image = '';
			}
			if($link->popup) {
				$dimensions = explode('x', $link->popup);
				$rendered_links[] = html::div($image.$link->text,'onclick="'.$this->jspopup($url, '_blank', $dimensions[0], $dimensions[1]).'"','link');
			} else {
				$rendered_links[] = html::div('<a href="'.$url.'" target="_blank">'.$image.$link->text.'</a>','','link');
			}

		}
		if(count($rendered_links) > 0) {
			return html::hr().html::bold(lang('Linked entries:')).$newline.implode($newline,$rendered_links);
		}
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
		if(!empty($_subject)) { $infos[] = html::bold($_subject); }
		return implode($newline,$infos);
	}

	/**
	 * Actions to take when deleting an account
	 *
	 * @param settings array with keys account_id and new_owner (new_owner is optional)
	 */
	public function deleteaccount($settings) {
		if($settings['new_owner']) {
			$this->db->update( self::_notification_table, array(
				'account_id'	=> $settings['new_owner']
			), array(
				'account_id'	=> $settings['account_id']
			),__LINE__,__FILE__,self::_appname);
		} else {
			$this->db->delete( self::_notification_table, array(
				'account_id'	=> $settings['account_id']
			),__LINE__,__FILE__,self::_appname);
		}
	}
}
