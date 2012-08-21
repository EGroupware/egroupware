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
 * User notification via email.
 */
class notifications_email implements notifications_iface {

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
	 * @var preferences
	 */
	private $preferences;

	/**
	 * holds mail object
	 *
	 * @var send
	 */
	private $mail;

	/**
	 * constructor of notifications_email
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
		if(is_object($this->mail))
		{
			unset($this->mail);
		}
		$this->mail = new send();
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
		$body_html = "<html><body>\n".$_messages['html'].$this->render_links($_links, true, $this->preferences->external_mailclient)."</body>\n</html>\n";

		$this->mail->ClearAddresses();
		$this->mail->ClearAttachments();
		$this->mail->AddAddress($this->recipient->account_email, $this->recipient->account_fullname);
		$this->mail->AddCustomHeader('X-eGroupWare-type: notification-mail');
		$this->mail->From = $this->sender->account_email;
		$this->mail->FromName = $this->sender->account_fullname;
		$this->mail->Subject = $_subject;
		// add iCal invitation as mulitpart alternative for calendar notifications
		if ($_attachments && stripos($_attachments[0]->type,"text/calendar; method=")!==false)
		{
			$this->mail->AltExtended = $_attachments[0]->string;
			$this->mail->AltExtendedContentType = $_attachments[0]->type;
			unset($_attachments[0]);
		}
		$this->mail->IsHTML(($isMeetingRequestNotif?false:true));
		$this->mail->Body = $body_html;
		$this->mail->AltBody = $body_plain;

		if(is_array($_attachments) && count($_attachments) > 0)
		{
			foreach($_attachments as $attachment)
			{
				if ($attachment->string)
				{
					$this->mail->AddStringAttachment($attachment->string, $attachment->filename, $attachment->encoding, $attachment->type);
				}
				elseif($attachment->path)
				{
					$this->mail->AddAttachment($attachment->path, $attachment->filename, $attachment->encoding, $attachment->type);
				}
	  		}
		}
		if(!$error=$this->mail->Send())
		{
			throw new Exception("Failed sending notification message via email.$error".print_r($this->mail->ErrorInfo,true));
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
		$hruler = $_render_html ? html::hr() : '';

		$rendered_links = array();
		foreach($_links as $link) {
			if($_render_external || ! $link->popup) { $link->view['no_popup'] = 1; }
			$url = html::link('/index.php', $link->view);
			// do not expose sensitive data
			$url = preg_replace('/(sessionid|kp3|domain)=[^&]+&?/','',$url);
			// complete missing protocol and domain part if needed
			if ($url{0} == '/' && $_render_external) {
				$url = ($_SERVER['HTTPS'] || $GLOBALS['egw_info']['server']['enforce_ssl'] ? 'https://' : 'http://').
					($GLOBALS['egw_info']['server']['hostname'] ? $GLOBALS['egw_info']['server']['hostname'] : $_SERVER['HTTP_HOST']).$url;
			}
			$a_href = '<a href="'.$url.'" target="_blank">'.$link->text.'</a>';
			$rendered_links[] = $_render_html ? $a_href : $url;
		}

		return $hruler.$newline.lang('Linked entries:').$newline.implode($newline,$rendered_links);
	}

}
