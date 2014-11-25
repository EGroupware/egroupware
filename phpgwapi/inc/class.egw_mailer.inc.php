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

/**
 * Log mails to log file specified in $GLOBALS['egw_info']['server']['log_mail']
 * or regular error_log for true (can be set either in DB or header.inc.php).
 *
 * New egw_mailer object uses Horde Mime Mail class with compatibility methods for
 * old PHPMailer methods and class variable assignments.
 *
 * This class does NOT use anything EGroupware specific, it acts like PHPMail, but logs.
 */
class egw_mailer extends Horde_Mime_Mail
{
	/**
	 * Mail account used for sending mail
	 *
	 * @var emailadmin_account
	 */
	protected $account;

	/**
	 * Header / recipients set via Add(Address|Cc|Bcc|Replyto)
	 *
	 * @var Horde_Mail_Rfc822_List
	 */
	protected $to;
	protected $cc;
	protected $bcc;
	protected $replyto;

	/**
	 * Constructor: always throw exceptions instead of echoing errors and EGw pathes
	 *
	 * @param int|emailadmin_account $account =null mail account to use, default use emailadmin_account::get_default($smtp=true)
	 */
	function __construct($account=null)
	{
		// Horde use locale for translation of error messages
		common::setlocale(LC_MESSAGES);

		parent::__construct();
		$this->_headers->setUserAgent('EGroupware API '.$GLOBALS['egw_info']['server']['versions']['phpgwapi']);

		$this->setAccount($account);

		$this->is_html = false;

		$this->ClearAddresses();

		$this->clearParts();
	}

	/**
	 * Clear all addresses
	 */
	function clearAddresses()
	{
		// clear all addresses
		$this->to = new Horde_Mail_Rfc822_List();
		$this->cc = new Horde_Mail_Rfc822_List();
		$this->bcc = new Horde_Mail_Rfc822_List();
		$this->replyto = new Horde_Mail_Rfc822_List();
	}

	/**
	 * Set mail account to use for sending
	 *
	 * @param int|emailadmin_account $account =null mail account to use, default use emailadmin_account::get_default($smtp=true)
	 * @throws egw_exception_not_found if account was not found (or not valid for current user)
	 */
	function  setAccount($account=null)
	{
		if (is_a($account, 'emailadmin_account'))
		{
			$this->account = $account;
		}
		elseif ($account > 0)
		{
			$this->account = emailadmin_account::read($account);
		}
		else
		{
			$this->account = emailadmin_account::get_default(true);	// true = need an SMTP (not just IMAP) account
		}
		$identity = emailadmin_account::read_identity($this->account->ident_id, true, null, $this->account);

		// use smpt-username as sender/return-path, if available, but only if it is a full email address
		$sender = $this->account->acc_smtp_username && strpos($this->account->acc_smtp_username, '@') !== false ?
			$this->account->acc_smtp_username : $identity['ident_email'];
		$this->addHeader('Return-Path', '<'.$sender.'>', true);

		$this->setFrom($identity['ident_email'], $identity['ident_realname']);
	}

	/**
	 * Set From header
	 *
	 * @param string $address
	 * @param string $personal =''
	 */
	public function setFrom($address, $personal='')
	{
		$this->addHeader('From', self::add_personal($address, $personal));
	}

	/**
	 * Add one or multiple addresses to To, Cc, Bcc or Reply-To
	 *
	 * @param string|array|Horde_Mail_Rfc822_List $address
	 * @param string $personal ='' only used if $address is a string
	 * @param string $type ='to' type of address to add "to", "cc", "bcc" or "replyto"
	 */
	function addAddress($address, $personal='', $type='to')
	{
		static $type2header = array(
			'to' => 'To',
			'cc' => 'Cc',
			'bcc' => 'Bcc',
			'replyto' => 'Reply-To',
		);
		if (!isset($type2header[$type]))
		{
			throw new egw_exception_wrong_parameter("Unknown type '$type'!");
		}
		if ($personal) $address = self::add_personal ($address, $personal);

		// add to our local list
		$this->$type->add($address);

		// add as header
		$this->addHeader($type2header[$type], $this->$type, true);
	}

	/**
	 * Write Bcc as header for storing in sent or as draft
	 *
	 * Bcc is normally only add to recipients while sending, but not added visible as header.
	 *
	 * This function is should only be called AFTER calling send, or when NOT calling send at all!
	 */
	function forceBccHeader()
	{
		$this->_headers->removeHeader('Bcc');

		// only add Bcc header, if we have bcc's
		if (count($this->bcc))
		{
			$this->_headers->addHeader('Bcc', $this->bcc);
		}
	}

	/**
	 * Add personal part to email address
	 *
	 * @param string $address
	 * @param string $personal
	 * @return string Rfc822 address
	 */
	static function add_personal($address, $personal)
	{
		if (is_string($address) && !empty($personal))
		{
			//if (!preg_match('/^[!#$%&\'*+/0-9=?A-Z^_`a-z{|}~-]+$/u', $personal))	// that's how I read the rfc(2)822
			if ($personal && !preg_match('/^[0-9A-Z -]*$/iu', $personal))	// but quoting is never wrong, so quote more then necessary
			{
				$personal = '"'.str_replace(array('\\', '"'),array('\\\\', '\\"'), $personal).'"';
			}
			$address = ($personal ? $personal.' <' : '').$address.($personal ? '>' : '');
		}
		return $address;
	}

	/**
	 * Add one or multiple addresses to Cc
	 *
	 * @param string|array|Horde_Mail_Rfc822_List $address
	 * @param string $personal ='' only used if $address is a string
	 */
	function AddCc($address, $personal=null)
	{
		$this->AddAddress($address, $personal, 'cc');
	}

	/**
	 * Add one or multiple addresses to Bcc
	 *
	 * @param string|array|Horde_Mail_Rfc822_List $address
	 * @param string $personal ='' only used if $address is a string
	 */
	function AddBcc($address, $personal=null)
	{
		$this->AddAddress($address, $personal, 'bcc');
	}

	/**
	 * Add one or multiple addresses to Reply-To
	 *
	 * @param string|array|Horde_Mail_Rfc822_List $address
	 * @param string $personal ='' only used if $address is a string
	 */
	function AddReplyTo($address, $personal=null)
	{
		$this->AddAddress($address, $personal, 'replyto');
	}

	/**
	 * Adds an attachment
	 *
	 * "text/calendar; method=..." get automatic detected and added as highes priority alternative,
	 * overwriting evtl. existing html body!
	 *
	 * @param string $file     The path to the file.
	 * @param string $name     The file name to use for the attachment.
	 * @param string $type     The content type of the file.
	 * @param string $charset  The character set of the part, only relevant for text parts.
	 * @return integer part-number
	 * @throws egw_exception_not_found if $file could not be opened for reading
	 */
	public function addAttachment($file, $name = null, $type = null, $charset = 'us-ascii')
	{
		// deprecated PHPMailer::AddAttachment($path, $name = '', $encoding = 'base64', $type = 'application/octet-stream') call
		if ($type === 'base64')
		{
			$type = $charset;
			$charset = 'us-ascii';
		}

		// pass file as resource to Horde_Mime_Part::setContent()
		if (!($resource = fopen($file, 'r')))
		{
			throw new egw_exception_not_found("File '$file' not found!");
		}
		$part = new Horde_Mime_Part();
		$part->setType($type ? $type : egw_vfs::mime_content_type($file));
		$part->setContents($resource);
		$part->setName($name ? $name : egw_vfs::basename($file));

		// store "text/calendar" as _htmlBody, to trigger "multipart/alternative"
		if (stripos($type,"text/calendar; method=") !== false)
		{
			$this->_htmlBody = $part;
			return;
		}
		// this should not be necessary, because binary data get detected by mime-type,
		// but at least Cyrus complains about NUL characters
		$part->setTransferEncoding('base64', array('send' => true));
		$part->setDisposition('attachment');

		return $this->addMimePart($part);
	}

	/**
	 * Adds a string or binary attachment (non-filesystem) to the list.
	 *
	 * "text/calendar; method=..." get automatic detected and added as highes priority alternative,
	 * overwriting evtl. existing html body!
	 *
	 * @param string $content String attachment data.
	 * @param string $filename Name of the attachment. We assume that this is NOT a path
	 * @param string $type File extension (MIME) type.
	 * @return int part-number
	 */
	public function AddStringAttachment($content, $filename, $type = 'application/octet-stream')
	{
		// deprecated PHPMailer::AddStringAttachment($content, $filename = '', $encoding = 'base64', $type = 'application/octet-stream') call
		if ($type === 'base64' || func_num_args() == 4)
		{
			$type = func_get_arg(3);
		}

		$part = new Horde_Mime_Part();
		$part->setType($type);
		$part->setCharset('utf-8');
		$part->setContents($content);
		// this should not be necessary, because binary data get detected by mime-type,
		// but at least Cyrus complains about NUL characters
		$part->setTransferEncoding('base64', array('send' => true));
		$part->setName($filename);

		// store "text/calendar" as _htmlBody, to trigger "multipart/alternative"
		if (stripos($type,"text/calendar; method=") !== false)
		{
			$this->_htmlBody = $part;
			return;
		}
		$part->setDisposition('attachment');

		return $this->addMimePart($part);
	}

	/**
	 * Send mail, injecting mail transport from account
	 *
	 * @ToDo hooks port hook from SmtpSend
	 * @throws egw_exception_not_found for no smtp account available
	 * @throws Horde_Mime_Exception
	 */
	function send()
	{
		parent::send($this->account->smtpTransport(), true);	// true: keep Message-ID
	}

	/**
	 * Reset all Settings to send multiple Messages
	 */
	function ClearAll()
	{
		$this->__construct($this->account);
	}

	/**
	 * Get value of a header set with addHeader()
	 *
	 * @param string $header
	 * @return string
	 */
	function getHeader($header)
	{
		return $this->_headers->getString($header);
	}

	/**
     * Get the raw email data sent by this object.
     *
	 * Reimplement to be able to call it for saveAsDraft by calling
	 * $this->send(new Horde_Mail_Transport_Null()),
	 * if no base-part is set, because send is not called before.
	 *
     * @param  boolean $stream  If true, return a stream resource, otherwise
     * @return stream|string  The raw email data.
     */
	function getRaw($stream=true)
	{
		try {
			$this->getBasePart();
		}
		catch(Horde_Mail_Exception $e)
		{
			unset($e);
			parent::send(new Horde_Mail_Transport_Null(), true);	// true: keep Message-ID
		}
		return parent::getRaw($stream);
	}

	/**
	 * Deprecated PHPMailer compatibility methods
	 */

	/**
	 * Get header part of mail
	 *
	 * @deprecated use getRaw($stream=true) to get a stream of whole mail containing headers and body
	 * @return string
	 */
	function getMessageHeader()
	{
		try {
			$this->getBasePart();
		}
		catch(Horde_Mail_Exception $e)
		{
			unset($e);
			parent::send(new Horde_Mail_Transport_Null(), true);	// true: keep Message-ID
		}
		return $this->_headers->toString();
	}

	/**
	 * Get body part of mail
	 *
	 * @deprecated use getRaw($stream=true) to get a stream of whole mail containing headers and body
	 * @return string
	 */
	function getMessageBody()
	{
		try {
			$this->getBasePart();
		}
		catch(Horde_Mail_Exception $e)
		{
			unset($e);
			parent::send(new Horde_Mail_Transport_Null(), true);	// true: keep Message-ID
		}
		return $this->getBasePart()->toString(
			array('stream' => false, 'encode' => Horde_Mime_Part::ENCODE_7BIT | Horde_Mime_Part::ENCODE_8BIT | Horde_Mime_Part::ENCODE_BINARY));
	}

	/**
	 * Use SMPT
	 *
	 * @deprecated not used, SMTP always used
	 */
	function IsSMTP()
	{

	}

	/**
	 * @deprecated use AddHeader($header, $value)
	 */
	function AddCustomHeader($str)
	{
		$matches = null;
		if (preg_match('/^([^:]+): *(.*)$/', $str, $matches))
		{
			$this->addHeader($matches[1], $matches[2]);
		}
	}
	/**
	 * @deprecated use clearParts()
	 */
	function ClearAttachments()
	{
		$this->clearParts();
	}
	/**
	 * @deprecated done by Horde automatic
	 */
	function EncodeHeader($str/*, $position = 'text'*/)
	{
		return $str;
	}

	protected $is_html = false;
	/**
	 * Defines that setting $this->Body should set Body or AltBody
	 * @param boolean $html
	 * @deprecated use either setBody() or setHtmlBody()
	 */
	function isHtml($html)
	{
		$this->is_html = (bool)$html;
	}

	/**
	 * Sets the message type
	 *
	 * @deprecated no longer necessary to call, happens automatic when calling send or getRaw($stream=true)
	 */
	public function SetMessageType()
	{

	}

	/**
	 * Assembles message header
	 *
	 * @deprecated use getMessageHeader() or better getRaw($stream=true)
	 * @return string The assembled header
	 */
	public function CreateHeader()
	{
		return $this->getMessageHeader();
	}

	/**
	 * Assembles message body
	 *
	 * @deprecated use getMessageBody() or better getRaw($stream=true)
	 * @return string The assembled header
	 */
	public function CreateBody()
	{
		return $this->getMessageBody();
	}

	protected $from = '';
	/**
	 * Magic method to intercept assignments to old PHPMailer variables
	 *
	 * @deprecated use addHeader(), setBody() or setHtmlBody()
	 * @param type $name
	 * @param type $value
	 */
	function __set($name, $value)
	{
		switch($name)
		{
			case 'Sender':
				$this->addHeader('Return-Path', '<'.$value.'>', true);
				break;
			case 'From':
			case 'FromName':
				if (empty($this->from) || $name == 'From' && $this->from[0] == '<')
				{
					$this->from = $name == 'From' ? '<'.$value.'>' : $value;
				}
				elseif ($name == 'From')
				{
					$this->from = self::add_personal($value, $this->from);
				}
				else
				{
					$this->from = self::add_personal(substr($this->from, 1, -1), $value);
				}
				$this->addHeader('From', $this->from, true);
				break;
			case 'Priority':
				$this->AddHeader('X-Priority', $value);
				break;
			case 'Subject':
				$this->AddHeader($name, $value);
				break;
			case 'MessageID':
				$this->AddHeader('Message-ID', $value);
				break;
			case 'AltExtended':
			case 'AltExtendedContentType':
				// todo addPart()
				break;
			case 'Body':
				$this->is_html ? $this->setHtmlBody($value, null, false) : $this->setBody($value);
				break;
			case 'AltBody':
				!$this->is_html ? $this->setHtmlBody($value, null, false) : $this->setBody($value);
				break;

			default:
				error_log(__METHOD__."('$name', ".array2string($value).") unsupported  attribute '$name' --> ignored");
				break;
		}
	}
	/**
	 * Magic method to intercept readin old PHPMailer variables
	 *
	 * @deprecated use getHeader(), etc.
	 * @param type $name
	 */
	function __get($name)
	{
		switch($name)
		{
			case 'Sender':
				return $this->getHeader('Return-Path');
			case 'From':
				return $this->getHeader('From');
		}
		error_log(__METHOD__."('$name') unsupported  attribute '$name' --> returning NULL");
		return null;
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
	 *
	 * Overwriting this method from phpmailer, to make sure we set SMTPSecure to ssl or tls if the standardports for ssl or tls
	 * are configured for the given profile
	 *
	 * @uses SMTP
	 * @access public
	 * @return bool
	 */
	public function SmtpConnect()
	{
		$port = $this->Port;
		$hosts = explode(';',$this->Host);
		foreach ($hosts as &$host)
		{
			$host = trim($host); // make sure there is no whitespace leading or trailling the host string
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
		$matches = null;
		$mail_id = $GLOBALS['egw']->hooks->process(array(
			'location' => 'send_mail',
			'subject' => $this->Subject,
			'from' => $this->Sender ? $this->Sender : $this->From,
			'to' => $this->addresses['To'],
			'cc' => $this->addresses['Cc'],
			'bcc' => $this->addresses['Bcc'],
			'body_sha1' => sha1($body),
			'message_id' => preg_match('/^Message-ID: (.*)$/m', $header, $matches) ? $matches[1] : null,
		), array(), true);	// true = call all apps

		$this->addresses = array();	// reset addresses for next mail

		try {
			// calling the overwritten method
			return parent::SmtpSend($header, $body);
		}
		catch (phpmailerException $e) {
			// in case of errors/exceptions call hook again with previous returned mail_id and error-message to log
			$GLOBALS['egw']->hooks->process(array(
				'location' => 'send_mail',
				'subject' => $this->Subject,
				'from' => $this->Sender ? $this->Sender : $this->From,
				'to' => $this->addresses['To'],
				'cc' => $this->addresses['Cc'],
				'bcc' => $this->addresses['Bcc'],
				'body_sha1' => sha1($body),
				'message_id' => preg_match('/^Message-ID: (.*)$/m', $header,$matches) ? $matches[1] : null,
				'mail_id' => $mail_id,
				'error' => $e->getMessage(),
			), array(), true);	// true = call all apps
			// re-throw exception
			throw $e;
		}
	}
}
