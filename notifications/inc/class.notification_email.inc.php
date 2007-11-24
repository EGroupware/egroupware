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
require_once(EGW_INCLUDE_ROOT. '/phpgwapi/inc/class.send.inc.php');
require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.config.inc.php');

/**
 * User notification via email.
 */
class notification_email implements iface_notification {

	/**
	 * Appname
	 */
	const _appname = 'notifications';
	
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
	 * holds mail object
	 *
	 * @var object
	 */
	private $mail;
	
	/**
	 * constructor of notification_email
	 *
	 * @param object $_recipient
	 * @param object $_preferences
	 */
	public function __construct( $_sender=false, $_recipient=false, $_config=false, $_preferences=false) {
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
		if(!is_object($this->mail))
		{
			$this->mail = new send();
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
		$sender_email = $GLOBALS['egw']->accounts->id2name($this->sender->id,'account_email');
		$sender_fullname = $GLOBALS['egw']->accounts->id2name($this->sender->id,'account_fullname');
		$recipient_email = $GLOBALS['egw']->accounts->id2name($this->recipient->id,'account_email');
		$recipient_fullname = $GLOBALS['egw']->accounts->id2name($this->recipient->id,'account_fullname');
		if (!$sender_email || strpos($sender_email,'@') === false) {
			throw new Exception("Failed sending notification message via email. No valid sender given.");
		}
		if (!$recipient_email || strpos($recipient_email,'@') === false) {
			throw new Exception("Failed sending notification message via email. No valid recipient given.");
		}
		if($this->preferences->external_mailclient) {
			$body_plain = $_messages['plain']['text'].$_messages['plain']['link_external'];
			$body_html = "<html>\n<body>\n".$_messages['html']['text'].$_messages['html']['link_external']."</body>\n</html>\n";
		} else {
			$body_plain = $_messages['plain']['text'].$_messages['plain']['link_internal'];
			$body_html = "<html>\n<body>\n".$_messages['html']['text'].$_messages['html']['link_internal']."</body>\n</html>\n";
		}
		$this->mail->ClearAddresses();
		$this->mail->ClearAttachments();
		$this->mail->IsHTML(true);
		$this->mail->AddAddress($recipient_email, $recipient_fullname);
		$this->mail->AddCustomHeader('X-eGroupWare-type: notification-mail');
		$this->mail->From = $sender_email;
		$this->mail->FromName = $sender_fullname;
		$this->mail->Subject = $this->mail->encode_subject($_subject);
		$this->mail->Body = $body_html;
		$this->mail->AltBody = $body_plain;
		if(is_array($_attachments)) {
		  foreach($_attachments as $attachment) {
			$this->mail->AddStringAttachment($attachment->string, $attachment->filename, $attachment->encoding, $attachment->type);
		}
		}
		if(!$error=$this->mail->Send()) {
			throw new Exception("Failed sending notification message via email.$error");
		}
	}

}
