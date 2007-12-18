<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage backends
 * @link http://www.egroupware.org
 * @author Christian Binder <christian@jaytraxx.de>
 */

require_once('class.iface_notification.inc.php');
require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.html.inc.php');
require_once(EGW_INCLUDE_ROOT. '/phpgwapi/inc/class.send.inc.php');

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
	 * holds html object to render elements
	 *
	 * @var object
	 */
	private $html;
	
	/**
	 * constructor of notification_email
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
		if(!is_object($this->mail))
		{
			$this->mail = new send();
		}
		$this->html = & html::singleton();
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
		$body_plain = $_messages['plain'].$this->render_links($_links, false, $this->preferences->external_mailclient);
		$body_html = "<html>\n<body>\n".$_messages['html'].$this->render_links($_links, true, $this->preferences->external_mailclient)."</body>\n</html>\n";
		
		$this->mail->ClearAddresses();
		$this->mail->ClearAttachments();
		$this->mail->IsHTML(true);
		$this->mail->AddAddress($this->recipient->account_email, $this->recipient->account_fullname);
		$this->mail->AddCustomHeader('X-eGroupWare-type: notification-mail');
		$this->mail->From = $this->sender->account_email;
		$this->mail->FromName = $this->sender->account_fullname;
		$this->mail->Subject = $this->mail->encode_subject($_subject);
		$this->mail->Body = $body_html;
		$this->mail->AltBody = $body_plain;
		if(is_array($_attachments) && count($_attachments) > 0) {
		  foreach($_attachments as $attachment) {
			$this->mail->AddStringAttachment($attachment->string, $attachment->filename, $attachment->encoding, $attachment->type);
		}
		}
		if(!$error=$this->mail->Send()) {
			throw new Exception("Failed sending notification message via email.$error");
		}
	}
	
	/**
	 * renders plaintext/html links from given link array
	 *
	 * @param array $_links
	 * @param boolean $_render_html
	 * @param boolean $_render_external
	 * @return plain or html rendered link(s) as complete string
	 */
	private function render_links($_links = false, $_render_html = false, $_render_external = true) {
		if(!is_array($_links) || count($_links) == 0) { return false; }
		
		// provide defaults if given arguments are null
		// php distinguishes between missing and present(null) arguments
		if(is_null($_render_html)) { $_render_html = false; }
		if(is_null($_render_external)) { $_render_external = true; }
		
		$newline = $_render_html ? "<br />" : "\n";		
		$link_array = array();
		foreach($_links as $link) {
			if($_render_external) {
				$link->params['no_popup'] = 1;
			}
			$url = $this->html->link('/index.php?menuaction='.$link->menuaction, $link->params);
			$link_array[] = $_render_html ? $this->html->a_href($link->text, $url) : $url;
		}

		return lang('Linked entries:').$newline.implode($newline,$link_array);
	}

}
