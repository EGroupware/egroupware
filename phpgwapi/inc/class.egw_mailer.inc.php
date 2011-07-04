<?php
/**
 * eGroupWare API: Sending mail via PHPMailer
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage mail
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once(EGW_API_INC.'/class.phpmailer.inc.php');

/**
 * Log mails to log file specified in $GLOBALS['egw_info']['server']['log_mail']
 * or regular error_log for true (can be set either in DB or header.inc.php).
 *
 * This class does NOT use anything EGroupware specific, it acts like PHPMail, but logs.
 */
class egw_mailer extends PHPMailer
{
	/**
	 * Constructor: always throw exceptions instead of echoing errors and EGw pathes
	 */
	function __construct()
	{
		parent::__construct(true);	// throw exceptions instead of echoing errors

		// setting EGroupware specific path for PHPMailer lang files
		list($lang,$nation) = explode('-',$GLOBALS['egw_info']['user']['preferences']['common']['lang']);
		$lang_path = EGW_SERVER_ROOT.'/phpgwapi/lang/';
		if ($nation && file_exists($lang_path."phpmailer.lang-$nation.php"))	// atm. only for pt-br => br
		{
			$lang = $nation;
		}
		if (!$this->SetLanguage($lang,$lang_path))
		{
			$this->SetLanguage('en',$lang_path);	// use English default
		}
	}

	/**
	 * Log mails to log file specified in $GLOBALS['egw_info']['server']['log_mail']
	 * or regular error_log for true (can be set either in DB or header.inc.php).
	 *
	 * We can NOT supply this method as callback to phpMailer, as phpMailer only accepts
	 * functions (not methods) and from a function we can NOT access $this->ErrorInfo.
	 *
	 * @param boolean $isSent
	 * @param string $to
	 * @param string $cc
	 * @param string $bcc
	 * @param string $subject
	 * @param string $body
	 */
  	protected function doCallback($isSent,$to,$cc,$bcc,$subject,$body)
	{
		if ($GLOBALS['egw_info']['server']['log_mail'])
		{
			$msg = $GLOBALS['egw_info']['server']['log_mail'] !== true ? date('Y-m-d H:i:s')."\n" : '';
			$msg .= ($isSent ? 'Mail send' : 'Mail NOT send').
				' to '.$to.' with subject: "'.trim($subject).'"';

			$msg .= ' from instance '.$GLOBALS['egw_info']['user']['domain'].' and IP '.egw_session::getuser_ip();
			$msg .= ' from user #'.$GLOBALS['egw_info']['user']['account_id'];

			if ($GLOBALS['egw_info']['user']['account_id'] && class_exists('common',false))
			{
				$msg .= ' ('.common::grab_owner_name($GLOBALS['egw_info']['user']['account_id']).')';
			}
			if (!$isSent)
			{
				$this->SetError('');	// queries error from (private) smtp and stores it in $this->ErrorInfo
				$msg .= $GLOBALS['egw_info']['server']['log_mail'] !== true ? "\n" : ': ';
				$msg .= 'ERROR '.str_replace(array('Language string failed to load: smtp_error',"\n","\r"),'',
					strip_tags($this->ErrorInfo));
			}
			$msg .= " cc=$cc, bcc=$bcc";
			if ($GLOBALS['egw_info']['server']['log_mail'] !== true) $msg .= "\n\n";

			error_log($msg,$GLOBALS['egw_info']['server']['log_mail'] === true ? 0 : 3,
				$GLOBALS['egw_info']['server']['log_mail']);
		}
		// calling the orginal callback of phpMailer
		parent::doCallback($isSent,$to,$cc,$bcc,$subject,$body);
	}

	private $addresses = array();

	/**
	 * Initiates a connection to an SMTP server.
	 * Returns false if the operation failed.
	 * @uses SMTP
	 * @access public
	 * @return bool
	 */
	public function SmtpConnect() 
	{
		$port = $this->Port;
		$hosts = explode(';',$this->Host);
		foreach ($hosts as $k => &$host)
		{
			if (in_array($port,array(465,587)) && strpos($host,'://')===false) 
			{
				//$host = ($port==587?'tls://':'ssl://').trim($host);
				$this->SMTPSecure = ($port==587?'tls':'ssl');
			}
			//error_log(__METHOD__.__LINE__.' Smtp Host:'.$host.' SmtpSecure:'.($this->SMTPSecure?$this->SMTPSecure:'no'));
		}
		return parent::SmtpConnect();
	}

	/**
	 * Sends mail via SMTP using PhpSMTP
	 *
	 * Overwriting this method from phpmailer, to allow apps to intercept it
	 * via "send_mail" hook, eg. to log or authorize sending of mail.
	 * Hooks can throw phpmailerException($message, phpMailer::STOP_CRITICAL),
	 * to stop sending the mail out like an SMTP error.
	 *
	 * @param string $header The message headers
	 * @param string $body The message body
	 * @return bool
	 */
	public function SmtpSend($header, $body)
	{
		$GLOBALS['egw']->hooks->process(array(
			'location' => 'send_mail',
			'subject' => $this->Subject,
			'from' => $this->Sender ? $this->Sender : $this->From,
			'to' => $this->addresses['To'],
			'cc' => $this->addresses['Cc'],
			'bcc' => $this->addresses['Bcc'],
			'body_sha1' => sha1($body),
			'message_id' => preg_match('/^Message-ID: (.*)$/m', $header,$matches) ? $matches[1] : null,
		), array(), true);	// true = call all apps

		$this->addresses = array();	// reset addresses for next mail

		// calling the overwritten method
		return parent::SmtpSend($header, $body);
	}

	/**
	 * Creates recipient headers.
	 *
	 * Overwritten to get To, Cc and Bcc addresses, which are private in phpMailer
	 *
	 * @access public
	 * @return string
 	 */
	public function AddrAppend($type, $addr)
	{
		foreach($addr as $data)
		{
			$this->addresses[$type][] = $data[0];
		}
		return parent::AddrAppend($type, $addr);
	}

	/**
	 * Adds a "Bcc" address.
	 *
	 * Reimplemented as AddrAppend() for Bcc get's NOT called for SMTP!
	 *
	 * @param string $address
	 * @param string $name
	 * @return boolean true on success, false if address already used
	 */
	public function AddBCC($address, $name = '')
	{
		$this->AddrAppend('Bcc', array(array($address,$name)));

		return parent::AddBCC($address, $name);
	}
}
