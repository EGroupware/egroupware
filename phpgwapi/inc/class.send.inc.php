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
 * The configuration is read from Admin >> Site configuration and it does NOT depend on one of the email-apps anymore.
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
		$restoreSession = $getUserDefinedProfiles = true;
		// if dontUseUserDefinedProfiles is set to yes/true/1 dont restore the session AND dont retrieve UserdefinedAccount settings
		$notification_config = config::read('notifications');
		if ($notification_config['dontUseUserDefinedProfiles']) $restoreSession = $getUserDefinedProfiles = false;
		try
		{
			$bopreferences =& CreateObject('felamimail.bopreferences',$restoreSession);
		}
		catch (egw_exception_assertion_failed $e)
		{
			$bopreferences = false;
		}
		if ($bopreferences) {
			if ($this->debug) error_log(__METHOD__." using felamimail preferences for mailing.");
			// if dontUseUserDefinedProfiles is set to yes/true/1  dont retrieve UserdefinedAccount settings
			$preferences  = $bopreferences->getPreferences($getUserDefinedProfiles);
			if ($preferences) {
				$ogServer = $preferences->getOutgoingServer(0);
				if ($ogServer) {
					$this->Host     = $ogServer->host;
					$this->Port = $ogServer->port;
					if($ogServer->smtpAuth) {
						$this->SMTPAuth = true;
						list($username,$senderadress) = explode(';', $ogServer->username,2);
						if (!isset($this->Sender) || empty($this->Sender)) // if there is no Sender info, try to determine one
						{
							if (isset($senderadress) && !empty($senderadress)) // thats the senderinfo, that may be part of the
							{												   // SMTP Auth. this one has precedence over other settings
								$this->Sender = $senderadress;
							}
							else // there is no senderinfo with smtp auth, fetch the identities mailaddress, as it should be connected to
							{    // the active profiles smtp settings
								$activeMailProfile = $preferences->getIdentity(0); // fetch active identity
								if (isset($activeMailProfile->emailAddress) && !empty($activeMailProfile->emailAddress))
								{
									$this->Sender = $activeMailProfile->emailAddress;
								}
							}
						}
						$this->Username = $username;
						$this->Password = $ogServer->password;
						// if we have NO password, eg. because we run by async service outside a regular user session
						// --> fall back to the default profile / mail config from setup
						if (empty($this->Password)) $bopreferences = false;
					}
					if ($this->debug) error_log(__METHOD__." using Host ".print_r($this->Host,true)." to be send");
					if ($this->debug) error_log(__METHOD__." using User ".print_r($this->Username,true)." to be send");
					if ($this->debug) error_log(__METHOD__." using Sender ".print_r($this->Sender,true)." to be send");
				}
			}
		}
		if (!$bopreferences) {
			if ($this->debug) error_log(__METHOD__." using global config to send");
			$this->Host = $GLOBALS['egw_info']['server']['smtp_server']?$GLOBALS['egw_info']['server']['smtp_server']:'localhost';
			$this->Port = $GLOBALS['egw_info']['server']['smtp_port']?$GLOBALS['egw_info']['server']['smtp_port']:25;
			$this->SMTPAuth = !empty($GLOBALS['egw_info']['server']['smtp_auth_user']);
			list($username,$senderadress) = explode(';', $GLOBALS['egw_info']['server']['smtp_auth_user'],2);
			if (isset($senderadress) && !empty($senderadress)) $this->Sender = $senderadress;
			$this->Username = $username;
			$this->Password = $GLOBALS['egw_info']['server']['smtp_auth_passwd'];
			if ($this->debug) error_log(__METHOD__." using Host ".print_r($this->Host,true)." to be send");
			if ($this->debug) error_log(__METHOD__." using User ".print_r($this->Username,true)." to be send");
			if ($this->debug) error_log(__METHOD__." using Sender ".print_r($this->Sender,true)." to be send");
		}
		$this->Hostname = $GLOBALS['egw_info']['server']['hostname'];
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

		$this->FromName = $GLOBALS['egw_info']['user']['fullname'];
		$this->From = $GLOBALS['egw_info']['user']['email'];
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
		//echo "<p>send::msg(,to='$to',subject='$subject',,'$msgtype',cc='$cc',bcc='$bcc',from='$from',sender='$sender','$content_type','$boundary')<pre>$body</pre>\n";
		$this->ClearAll();	// reset everything to its default, we might be called more then once !!!

		if ($service != 'email')
		{
			return False;
		}
		if ($from)
		{
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
