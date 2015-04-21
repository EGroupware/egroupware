<?php
/**
 * EGroupware API: Sending mail via egw_mailer
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage mail
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * New eGW send-class. It implements the old interface (msg-method) on top of PHPMailer.
 *
 * The configuration is read via emailadmin_account::get_default_acc_id(true);	// true=SMTP
 */
class send extends egw_mailer
{
	/**
	 * eGW specific initialisation of the PHPMailer: charset, language, smtp-host, ...
	 *
	 * To be able to call PHPMailer's Send function, we check if a subject, body or address is set and call it in that case,
	 * else we do our constructors work.
	 */
	function send()
	{
		if ($this->getHeader('Subject') || $this->Body || count($this->getAddresses('to', true)))
		{
			return parent::send();
		}
		parent::__construct();	// calling parent constructor
	}

	/**
	* Reset all Settings to send multiple Messages
	*/
	function ClearAll()
	{
		$this->err = array();

		$this->Subject = $this->Body = $this->AltBody = '';
		$this->IsHTML(False);
		$this->ClearAllRecipients();
		$this->ClearAttachments();
		$this->ClearCustomHeaders();

		$this->FromName = $GLOBALS['egw_info']['user']['account_fullname'];
		$this->From = $GLOBALS['egw_info']['user']['account_email'];

		$this->AddCustomHeader('X-Mailer:eGroupWare (http://www.eGroupWare.org)');
	}

	/**
	* Emulating the old send::msg interface for compatibility with existing code
	*
	* You can either use that code or the PHPMailer variables and methods direct.
	* @deprecated use egw_mailer::sendWithDefaultSmtpProfile
	*/
	function msg($service, $to, $subject, $body, $msgtype='', $cc='', $bcc='', $from='', $sender='', $content_type='', $boundary='Message-Boundary')
	{
		//error_log(__METHOD__." to='$to',subject='$subject',,'$msgtype',cc='$cc',bcc='$bcc',from='$from',sender='$sender'");
		unset($boundary);	// not used, but required by function signature
		//echo "<p>send::msg(,to='$to',subject='$subject',,'$msgtype',cc='$cc',bcc='$bcc',from='$from',sender='$sender','$content_type','$boundary')<pre>$body</pre>\n";
		if ($service != 'email')
		{
			return False;
		}
		if ($from)
		{
			$matches = null;
			if (preg_match('/"?(.+)"?<(.+)>/',$from,$matches))
			{
				list(,$FromName,$from) = $matches;
			}
		}
		foreach(array('to','cc','bcc') as $adr)
		{
			if ($$adr)
			{
				if (is_string($$adr) && preg_match_all('/"?(.+)"?<(.+)>,?/',$$adr,$matches))
				{
					$names = $matches[1];
					$addresses = $matches[2];
				}
				else
				{
					$addresses = is_string($$adr) ? explode(',',trim($$adr)) : explode(',',trim(array_shift($$adr)));
					$names = array();
				}

				foreach($addresses as $n => $address)
				{
					$method[$adr][] =$address;
				}
			}
		}

		try {
			egw_mailer::sendWithDefaultSmtpProfile('email',$method['to'],$subject,$body,'',$method['cc'],$method['bcc'],$from,$sender);
		} catch(Exception $e) {
			// ignore exception, but log it, to block the account and give a correct error-message to user
			return false;
		}

		return True;
	}

	/**
	* encode 8-bit chars in subject-line
	*
	* @deprecated This is not needed any more, as it is done be PHPMailer, but older code depend on it.
	*/
	function encode_subject($subject)
	{
		return $subject;
	}
}
