<?php
/**
 * eGroupWare API: Sending mail via egw_mailer
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
	var $err    = array();
	var $to_res = array();
	// switching on debug with a numeric value other than 0, switches debug in PHPMailer/SMTP Class on
	var $debug  = false;

	/**
	 * eGW specific initialisation of the PHPMailer: charset, language, smtp-host, ...
	 *
	 * To be able to call PHPMailer's Send function, we check if a subject, body or address is set and call it in that case,
	 * else we do our constructors work.
	 */
	function send()
	{
		if ($this->debug && is_numeric($this->debug)) $this->SMTPDebug = $this->debug;
		if ($this->Subject || $this->Body || count($this->to))
		{
			if ($this->debug) error_log(__METHOD__." ".print_r($this->Subject,true)." to be send");
			return PHPMailer::Send();
		}
		parent::__construct();	// calling parent constructor

		$this->CharSet = translation::charset();
		$this->IsSmtp();

		// smtp settings from default account of current user
		$account = emailadmin_account::read(emailadmin_account::get_default_acc_id(true));	// true=SMTP
		$this->Host = $account->acc_smtp_host;
		$this->Port = $account->acc_smtp_port;
		switch($account->acc_smtp_ssl)
		{
			case emailadmin_account::SSL_TLS:			// requires modified PHPMailer, or comment next two lines to use just ssl!
				$this->Host = 'tlsv1://'.$this->Host;
				break;
			case emailadmin_account::SSL_SSL:
				$this->Host = 'ssl://'.$this->Host;
				break;
			case emailadmin_account::SSL_STARTTLS:	// PHPMailer uses 'tls' for STARTTLS, not ssl connection with tls version >= 1 and no sslv2/3
				$this->Host = 'tls://'.$this->Host;
		}
		$this->SMTPAuth = !empty($account->acc_smtp_username);
		$this->Username = $account->acc_smtp_username;
		$this->Password = $account->acc_smtp_password;
		$this->defaultDomain = $account->acc_domain;
		$this->Sender = emailadmin_account::rfc822($account);

		$this->Hostname = $GLOBALS['egw_info']['server']['hostname'];

		if ($this->debug) error_log(__METHOD__."() initialised egw_mailer with ".array2string($this)." from mail default account ".array2string($account->params));
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
		$this->Sender = '';

		$this->AddCustomHeader('X-Mailer:eGroupWare (http://www.eGroupWare.org)');
	}

	/**
	* Emulating the old send::msg interface for compatibility with existing code
	*
	* You can either use that code or the PHPMailer variables and methods direct.
	*/
	function msg($service, $to, $subject, $body, $msgtype='', $cc='', $bcc='', $from='', $sender='', $content_type='', $boundary='Message-Boundary')
	{
		if ($this->debug) error_log(__METHOD__." to='$to',subject='$subject',,'$msgtype',cc='$cc',bcc='$bcc',from='$from',sender='$sender'");
		unset($boundary);	// not used, but required by function signature
		//echo "<p>send::msg(,to='$to',subject='$subject',,'$msgtype',cc='$cc',bcc='$bcc',from='$from',sender='$sender','$content_type','$boundary')<pre>$body</pre>\n";
		$this->ClearAll();	// reset everything to its default, we might be called more then once !!!

		if ($service != 'email')
		{
			return False;
		}
		if ($from)
		{
			$matches = null;
			if (preg_match('/"?(.+)"?<(.+)>/',$from,$matches))
			{
				list(,$this->FromName,$this->From) = $matches;
			}
			else
			{
				$this->From = $from;
				$this->FromName = '';
			}
		}
		if ($sender)
		{
			$this->Sender = $sender;
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
				$method = 'Add'.($adr == 'to' ? 'Address' : $adr);

				foreach($addresses as $n => $address)
				{
					$this->$method($address,$names[$n]);
				}
			}
		}
		if (!empty($msgtype))
		{
			$this->AddCustomHeader('X-eGW-Type: '.$msgtype);
		}
		if ($content_type)
		{
			$this->ContentType = $content_type;
		}
		$this->Subject = $subject;
		$this->Body = $body;

		//echo "PHPMailer = <pre>".print_r($this,True)."</pre>\n";
		if (!$this->Send())
		{
			$this->err = array(
				'code' => 1,	// we dont get a numerical code from PHPMailer
				'msg'  => $this->ErrorInfo,
				'desc' => $this->ErrorInfo,
			);
			return False;
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
