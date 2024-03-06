<?php
/**
 * EGroupware - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage backends
 * @link http://www.egroupware.org
 * @author Christian Binder <christian@jaytraxx.de>
 * @version $Id$
 */

use EGroupware\Api;

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
	 * @var Api\Preferences
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

		// Use configured mail account
		$ident = null;
		if(!empty($this->config->async_identity))
		{
			$ident = Api\Mail\Account::read($this->config->async_identity, $_sender->account_id ?? null);
		}
		$this->mail = new Api\Mailer($ident, $_sender->account_id ?? null);
	}

	/**
	 * sends notification
	 *
	 * @param array $_messages
	 * @param string $_subject
	 * @param array $_links
	 * @param array $_attachments
	 * @param array $_data value for key "reply_to" to use a custom ReplyTo address
	 */
	public function send(array $_messages, $_subject = false, $_links = false, $_attachments = false, $_data = false)
	{
		$body_plain = $_messages['plain'].$this->render_links($_links, false, $this->preferences->external_mailclient??false);
		$body_html = "<html><body>\n".$_messages['html'].$this->render_links($_links, true, $this->preferences->external_mailclient??false)."</body>\n</html>\n";

		$this->mail->ClearAddresses();
		$this->mail->ClearAttachments();
		$this->mail->addAddress($this->recipient->account_email, $this->recipient->account_fullname);
		$this->mail->addHeader('X-EGroupware-Type', 'notification-mail');
		$this->mail->addHeader('X-EGroupware-Install', $GLOBALS['egw_info']['server']['install_id'].'@'.$GLOBALS['egw_info']['server']['default_domain']);
		//$this->mail->AddHeader('X-EGroupware-URL', 'notification-mail');
		//$this->mail->AddHeader('X-EGroupware-Tracker', 'notification-mail');
		//error_log(__METHOD__.__LINE__."preparing notification message via email.".array2string($this->mail));

		if ( $_data && !empty( $_data['reply_to'] ) )
		{
			$this->mail->addReplyTo($_data['reply_to']);
		}
		// do NOT set sender as From, as this might not be allowed, set it instead as ReplyTo, if that one it not explicitly set already
		elseif ($this->mail->getHeader('From') != Api\Mailer::add_personal($this->sender->account_email, $this->sender->account_fullname))
		{
			$this->mail->addReplyTo($this->sender->account_email, $this->sender->account_fullname);
		}

		$this->mail->addHeader('Subject', trim($_subject)); // trim the subject to avoid strange wrong encoding problem
		if ($_messages['html'])
		{
			// embed images as inline
			\EGroupware\Api\Mail::processURL2InlineImages($this->mail, $body_html, null);
		}
		$this->mail->setHtmlBody($body_html, null, false);	// no automatic alternativ
		$this->mail->setBody($body_plain);

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
		//error_log(__METHOD__.__LINE__."about sending notification message via email.".array2string($this->mail));
		$this->mail->send();
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
		$hruler = $_render_html ? Api\Html::hr() : '';

		$rendered_links = array();
		foreach($_links as $link) {
			if($_render_external || ! $link->popup) { $link->view['no_popup'] = 1; }
			// do not expose sensitive data
			$url = preg_replace('/(sessionid|kp3|domain)=[^&]+&?/','',Api\Html::link('/index.php', $link->view));
			// complete missing protocol and domain part if needed
			if ($url[0] == '/' && $_render_external) $url = Api\Framework::getUrl($url);
			$a_href = '<a href="'.$url.'" target="_blank">'.$link->text.'</a>';
			$rendered_links[] = $_render_html ? $a_href : $url;
		}

		return $hruler.$newline.lang('Linked entries:').$newline.implode($newline,$rendered_links);
	}

}