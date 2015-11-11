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
	 * Translates between interal Horde_Mail_Rfc822_List attributes and header names
	 *
	 * @var array
	 */
	static $type2header = array(
		'to' => 'To',
		'cc' => 'Cc',
		'bcc' => 'Bcc',
		'replyto' => 'Reply-To',
	);

	/**
	 * Constructor: always throw exceptions instead of echoing errors and EGw pathes
	 *
	 * @param int|emailadmin_account|boolean $account =null mail account to use, default use emailadmin_account::get_default($smtp=true)
	 *	false: no NOT initialise account and set other EGroupware specific headers, used to parse mails (not sending them!)
	 */
	function __construct($account=null)
	{
		// Horde use locale for translation of error messages
		common::setlocale(LC_MESSAGES);

		parent::__construct();

		if ($account !== false)
		{
			$this->_headers->setUserAgent('EGroupware API '.$GLOBALS['egw_info']['server']['versions']['phpgwapi']);

			$this->setAccount($account);

			$this->is_html = false;

			$this->clearAllRecipients();
			$this->clearReplyTos();

			$this->clearParts();
		}
	}

	/**
	 * Clear all recipients: to, cc, bcc (but NOT reply-to!)
	 */
	function clearAllRecipients()
	{
		// clear all addresses
		$this->clearAddresses();
		$this->clearCCs();
		$this->clearBCCs();
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
			if (!($this->account = emailadmin_account::get_default(true)))	// true = need an SMTP (not just IMAP) account
			{
				throw new egw_exception_not_found('SMTP: '.lang('Account not found!'));
			}
		}

		try
		{
			$identity = emailadmin_account::read_identity($this->account->ident_id, true, null, $this->account);
		}
		catch(Exception $e)
		{
			unset($e);
			error_log(__METHOD__.__LINE__.' Could not read_identity for account:'.$account['acc_id'].' with IdentID:'.$account['ident_id']);
			$identity['ident_email'] = $this->account->ident_email;
			$identity['ident_realname'] = $this->account->ident_realname ? $this->account->ident_realname : $this->account->ident_email;
		}

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
		if (!isset(self::$type2header[$type]))
		{
			throw new egw_exception_wrong_parameter("Unknown type '$type'!");
		}
		if ($personal) $address = self::add_personal ($address, $personal);

		// add to our local list
		$this->$type->add($address);

		// add as header
		$this->addHeader(self::$type2header[$type], $this->$type, true);
	}

	/**
	 * Remove all addresses from To, Cc, Bcc or Reply-To
	 *
	 * @param string $type ='to' type of address to add "to", "cc", "bcc" or "replyto"
	 */
	function clearAddresses($type='to')
	{
		$this->$type = new Horde_Mail_Rfc822_List();

		$this->removeHeader(self::$type2header[$type]);
	}

	/**
	 * Get set to addressses
	 *
	 * @param string $type ='to' type of address to add "to", "cc", "bcc" or "replyto"
	 * @param boolean $return_array =false true: return array of string, false: Horde_Mail_Rfc822_List
	 * @return array|Horde_Mail_Rfc822_List supporting arrayAccess and Iterable
	 */
	function getAddresses($type='to', $return_array=false)
	{
		if ($return_array)
		{
			$addresses = array();
			foreach($this->$type as $addr)
			{
				$addresses[] = (string)$addr;
			}
			return $addresses;
		}
		return $this->$type;
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
	function addCc($address, $personal=null)
	{
		$this->addAddress($address, $personal, 'cc');
	}

	/**
	 * Clear all cc
	 */
	function clearCCs()
	{
		$this->clearAddresses('cc');
	}

	/**
	 * Add one or multiple addresses to Bcc
	 *
	 * @param string|array|Horde_Mail_Rfc822_List $address
	 * @param string $personal ='' only used if $address is a string
	 */
	function addBcc($address, $personal=null)
	{
		$this->addAddress($address, $personal, 'bcc');
	}

	/**
	 * Clear all bcc
	 */
	function clearBCCs()
	{
		$this->clearAddresses('bcc');
	}

	/**
	 * Add one or multiple addresses to Reply-To
	 *
	 * @param string|array|Horde_Mail_Rfc822_List $address
	 * @param string $personal ='' only used if $address is a string
	 */
	function addReplyTo($address, $personal=null)
	{
		$this->addAddress($address, $personal, 'replyto');
	}

	/**
	 * Clear all reply-to
	 */
	function clearReplyTos()
	{
		$this->clearAddresses('replyto');
	}

	/**
	 * Get set ReplyTo addressses
	 *
	 * @return Horde_Mail_Rfc822_List supporting arrayAccess and Iterable
	 */
	function getReplyTo()
	{
		return $this->replyto;
	}

	/**
	 * Adds an attachment
	 *
	 * "text/calendar; method=..." get automatic detected and added as highest priority alternative
	 *
	 * @param string|resource $data Path to the attachment or open file-descriptor
	 * @param string $name     The file name to use for the attachment.
	 * @param string $type     The content type of the file.
	 * @param string $charset  The character set of the part, only relevant for text parts.
	 * @return integer part-number
	 * @throws egw_exception_not_found if $file could not be opened for reading
	 */
	public function addAttachment($data, $name = null, $type = null, $charset = 'us-ascii')
	{
		// deprecated PHPMailer::AddAttachment($path, $name = '', $encoding = 'base64', $type = 'application/octet-stream') call
		if ($type === 'base64')
		{
			$type = $charset;
			$charset = 'us-ascii';
		}

		// pass file as resource to Horde_Mime_Part::setContent()
		if (is_resource($data))
		{
			$resource = $data;
		}
		elseif (!($resource = fopen($data, 'r')))
		{
			throw new egw_exception_not_found("File '$data' not found!");
		}

		if (empty($type) && !is_resource($data)) $type = egw_vfs::mime_content_type($data);

		// set "text/calendar; method=*" as alternativ body
		$matches = null;
		if (preg_match('|^text/calendar; method=([^;]+)|i', $type, $matches))
		{
			$this->setAlternativBody($resource, $type, array('method' => $matches[1]), 'utf-8');
			return;
		}

		$part = new Horde_Mime_Part();
		$part->setType($type);
		$part->setContents($resource);

		// setting name, also sets content-disposition attachment (!), therefore we have to do it after "text/calendar; method=" handling
		if ($name || !is_resource($data)) $part->setName($name ? $name : egw_vfs::basename($data));

		// this should not be necessary, because binary data get detected by mime-type,
		// but at least Cyrus complains about NUL characters
		$part->setTransferEncoding('base64', array('send' => true));
		$part->setDisposition('attachment');

		return $this->addMimePart($part);
	}

	/**
	 * Adds an embedded image or other inline attachment
	 *
	 * @param string|resource $data Path to the attachment or open file-descriptor
	 * @param string $cid Content ID of the attachment.  Use this to identify
	 *        the Id for accessing the image in an HTML form.
	 * @param string $name Overrides the attachment name.
	 * @param string $type File extension (MIME) type.
	 * @return integer part-number
	 */
	public function addEmbeddedImage($data, $cid, $name = '', $type = 'application/octet-stream')
	{
		// deprecated PHPMailer::AddEmbeddedImage($path, $cid, $name='', $encoding='base64', $type='application/octet-stream') call
		if ($type === 'base64' || func_num_args() == 5)
		{
			$type = func_get_arg(4);
		}

		$part_id = $this->addAttachment($data, $name, $type);
		//error_log(__METHOD__."(".array2string($data).", '$cid', '$name', '$type') added with (temp.) part_id=$part_id");

		$part = $this->_parts[$part_id];
		$part->setDisposition('inline');
		$part->setContentId($cid);

		return $part_id;
	}

	/**
	 * Adds a string or binary attachment (non-filesystem) to the list.
	 *
	 * "text/calendar; method=..." get automatic detected and added as highest priority alternative,
	 * overwriting evtl. existing html body!
	 *
	 * @param string|resource $content String attachment data or open file descriptor
	 * @param string $filename Name of the attachment. We assume that this is NOT a path
	 * @param string $type File extension (MIME) type.
	 * @return int part-number
	 */
	public function addStringAttachment($content, $filename, $type = 'application/octet-stream')
	{
		// deprecated PHPMailer::AddStringAttachment($content, $filename = '', $encoding = 'base64', $type = 'application/octet-stream') call
		if ($type === 'base64' || func_num_args() == 4)
		{
			$type = func_get_arg(3);
		}

		// set "text/calendar; method=*" as alternativ body
		$matches = null;
		if (preg_match('|^text/calendar; method=([^;]+)|i', $type, $matches))
		{
			$this->setAlternativBody($content, $type, array('method' => $matches[1]), 'utf-8');
			return;
		}

		$part = new Horde_Mime_Part();
		$part->setType($type);
		$part->setCharset('utf-8');
		$part->setContents($content);

		// this should not be necessary, because binary data get detected by mime-type,
		// but at least Cyrus complains about NUL characters
		$part->setTransferEncoding('base64', array('send' => true));
		$part->setName($filename);
		$part->setDisposition('attachment');

		return $this->addMimePart($part);
	}

	/**
	 * Highest/last alternativ body part.
	 *
	 * @var Horde_Mime_Part
	 */
	protected $_alternativBody;

	/**
	 * Sets an alternativ body, eg. text/calendar has highest / last alternativ
	 *
	 * @param string|resource $content
	 * @param string $type eg. "text/calendar"
	 * @param array $parameters =array() eg. array('method' => 'REQUEST')
	 * @param string $charset =null default to $this->_charset="utf-8"
	 */
	function setAlternativBody($content, $type, $parameters=array(), $charset=null)
	{
		$this->_alternativBody = new Horde_Mime_Part();
		$this->_alternativBody->setType($type);
		foreach($parameters as $label => $data)
		{
			$this->_alternativBody->setContentTypeParameter($label, $data);
		}
		$this->_alternativBody->setCharset($charset ? $charset : $this->_charset);
		$this->_alternativBody->setContents($content);
		$this->_base = null;
	}

	/**
	 * Send mail, injecting mail transport from account
	 *
	 * Log mails to log file specified in $GLOBALS['egw_info']['server']['log_mail']
	 * or regular error_log for true (can be set either in DB or header.inc.php).
	 *
	 * @throws egw_exception_not_found for no smtp account available
	 * @throws Horde_Mime_Exception
	 */
	function send()
	{
		if (!($message_id = $this->getHeader('Message-ID')) &&
			class_exists('Horde_Mime_Headers_MessageId'))	// since 2.5.0
		{
			$message_id = Horde_Mime_Headers_MessageId::create('EGroupware');
			$this->addHeader('Message-ID', $message_id);
		}
		$body_sha1 = null;	// skip sha1, it requires whole mail in memory, which we traing to avoid now

		$mail_id = $GLOBALS['egw']->hooks->process(array(
			'location' => 'send_mail',
			'subject' => $subject=$this->getHeader('Subject'),
			'from' => $this->getHeader('Return-Path') ? $this->getHeader('Return-Path') : $this->getHeader('From'),
			'to' => $to=$this->getAddresses('to', true),
			'cc' => $cc=$this->getAddresses('cc', true),
			'bcc' => $bcc=$this->getAddresses('bcc', true),
			'body_sha1' => $body_sha1,
			'message_id' => (string)$message_id,
		), array(), true);	// true = call all apps

		// check if we are sending an html mail with inline images
		if (!empty($this->_htmlBody) && count($this->_parts))
		{
			$related = null;
			foreach($this->_parts as $n => $part)
			{
				if ($part->getDisposition() == 'inline' && $part->getContentId())
				{
					// we need to send a multipart/related with html-body as first part and inline images as further parts
					if (!isset($related))
					{
						$related = new Horde_Mime_Part();
						$related->setType('multipart/related');
						$related[] = $this->_htmlBody;
						$this->_htmlBody = $related;
					}
					$related[] = $part;
					unset($this->_parts[$n]);
				}
			}
		}

		try {
			// no flowed for encrypted messages
			$flowed = $this->_body && $this->_body->getType() != 'multipart/encrypted';

			// check if flowed is disabled in mail site configuration
			if (($config = config::read('mail')) && $config['disable_rfc3676_flowed'])
			{
				$flowed = false;
			}

			// handling of alternativ body
			if (!empty($this->_alternativBody))
			{
				$body = new Horde_Mime_Part();
				$body->setType('multipart/alternative');
				if (!empty($this->_body))
				{
					// Send in flowed format.
					if ($flowed)
					{
						$text_flowed = new Horde_Text_Flowed($this->_body->getContents(), $this->_body->getCharset());
						$text_flowed->setDelSp(true);
						$this->_body->setContentTypeParameter('format', 'flowed');
						$this->_body->setContentTypeParameter('DelSp', 'Yes');
						$this->_body->setContents($text_flowed->toFlowed());
					}
					$body[] = $this->_body;
				}
				if (!empty($this->_htmlBody))
				{
					$body[] = $this->_htmlBody;
					unset($this->_htmlBody);
				}
				$body[] = $this->_alternativBody;
				unset($this->_alternativBody);
				$this->_body = $body;
				$flowed = false;
			}
			parent::send($this->account->smtpTransport(), true,	$flowed);	// true: keep Message-ID
		}
		catch (Exception $e) {
			// in case of errors/exceptions call hook again with previous returned mail_id and error-message to log
			$GLOBALS['egw']->hooks->process(array(
				'location' => 'send_mail',
				'subject' => $subject,
				'from' => $this->getHeader('Return-Path') ? $this->getHeader('Return-Path') : $this->getHeader('From'),
				'to' => $to,
				'cc' => $cc,
				'bcc' => $bcc,
				'body_sha1' => $body_sha1,
				'message_id' => (string)$message_id,
				'mail_id' => $mail_id,
				'error' => $e->getMessage(),
			), array(), true);	// true = call all apps
		}

		// log mails to file specified in $GLOBALS['egw_info']['server']['log_mail'] or error_log for true
		if ($GLOBALS['egw_info']['server']['log_mail'])
		{
			$msg = $GLOBALS['egw_info']['server']['log_mail'] !== true ? date('Y-m-d H:i:s')."\n" : '';
			$msg .= (isset($e) ? 'Mail send' : 'Mail NOT send').
				' to '.implode(', ', $to).' with subject: "'.$subject.'"';

			$msg .= ' from instance '.$GLOBALS['egw_info']['user']['domain'].' and IP '.egw_session::getuser_ip();
			$msg .= ' from user #'.$GLOBALS['egw_info']['user']['account_id'];

			if ($GLOBALS['egw_info']['user']['account_id'] && class_exists('common',false))
			{
				$msg .= ' ('.common::grab_owner_name($GLOBALS['egw_info']['user']['account_id']).')';
			}
			if (isset($e))
			{
				$msg .= $GLOBALS['egw_info']['server']['log_mail'] !== true ? "\n" : ': ';
				$msg .= 'ERROR '.$e->getMessage();
			}
			$msg .= ' cc='.implode(', ', $cc).', bcc='.implode(', ', $bcc);
			if ($GLOBALS['egw_info']['server']['log_mail'] !== true) $msg .= "\n\n";

			error_log($msg,$GLOBALS['egw_info']['server']['log_mail'] === true ? 0 : 3,
				$GLOBALS['egw_info']['server']['log_mail']);
		}
		// rethrow error
		if (isset($e)) throw $e;
	}


	/**
	 * Reset all Settings to send multiple Messages
	 */
	function clearAll()
	{
		$this->__construct($this->account);
	}

	/**
	 * Get value of a header set with addHeader()
	 *
	 * @param string $header
	 * @return string|array
	 */
	function getHeader($header)
	{
		return $this->_headers ? $this->_headers->getValue($header) : null;
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
		// code copied from Horde_Mime_Mail::getRaw(), as there is no way to inject charset in
		// _headers->toString(), which is required to encode headers containing non-ascii chars correct
        if ($stream) {
            $hdr = new Horde_Stream();
            $hdr->add($this->_headers->toString(array('charset' => 'utf-8', 'canonical' => true)), true);
            return Horde_Stream_Wrapper_Combine::getStream(
                array($hdr->stream,
                      $this->getBasePart()->toString(
                        array('stream' => true, 'canonical' => true, 'encode' => Horde_Mime_Part::ENCODE_7BIT | Horde_Mime_Part::ENCODE_8BIT | Horde_Mime_Part::ENCODE_BINARY))
                )
            );
        }

        return $this->_headers->toString(array('charset' => 'utf-8', 'canonical' => true)) .
			$this->getBasePart()->toString(array('canonical' => true));
    }

	/**
	 * Convert charset of text-parts of message to utf-8
	 *
	 * @param string|resource $message
	 * @param boolean $stream =false return stream or string (default)
	 * @param string $charset ='utf-8' charset to convert to
	 * @param boolean &$converted =false on return if conversation was necessary
	 * @return string|stream
	 */
	static function convert($message, $stream=false, $charset='utf-8', &$converted=false)
	{
		$mailer = new egw_mailer(false);	// false = no default headers and mail account
		$mailer->addHeaders(Horde_Mime_Headers::parseHeaders($message));
		$base = Horde_Mime_Part::parseMessage($message);
		foreach($base->partIterator() as $part)
		{
			if ($part->getPrimaryType()== 'text')
			{
				$charset = $part->getContentTypeParameter('charset');
				if ($charset && $charset != 'utf-8')
				{
					$content = translation::convert($part->toString(array(
						'encode' => Horde_Mime_Part::ENCODE_BINARY,	// otherwise we cant recode charset
					)), $charset, 'utf-8');
					$part->setContents($content, array(
						'encode' => Horde_Mime_Part::ENCODE_BINARY,	// $content is NOT encoded
					));
					$part->setContentTypeParameter('charset', 'utf-8');
					if ($part === $base)
					{
						$mailer->addHeader('Content-Type', $part->getType(true));
						// need to set Transfer-Encoding used by base-part, it always seems to be "quoted-printable"
						$mailer->addHeader('Content-Transfer-Encoding', 'quoted-printable');
					}
					$converted = true;
				}
			}
			elseif ($part->getType() == 'message/rfc822')
			{
				$part->setContents(self::convert($part->toString(), $stream, $charset, $converted));
			}
		}
		if ($converted)
		{
			$mailer->setBasePart($base);
			return $mailer->getRaw($stream);
		}
		return $message;
	}

	/**
	 * Find body: 1. part with mimetype "text/$subtype"
	 *
	 * Use getContents() on non-null return-value to get string content
	 *
	 * @param string $subtype =null
	 * @return Horde_Mime_Part part with body or null
	 */
	function findBody($subtype=null)
	{
		try {
			$base = $this->getBasePart();
			if (!($part_id = $base->findBody($subtype))) return null;
			return $base->getPart($part_id);
		}
		catch (Exception $e) {
			unset($e);
			return $subtype == 'html' ? $this->_htmlBody : $this->_body;
		}
	}

	/**
	 * Parse base-part into _body, _htmlBody, _alternativBody and _parts to eg. add further attachments
	 */
	function parseBasePart()
	{
		try {
			$base = $this->getBasePart();
			$plain_id = $base->findBody('plain');
			$html_id = $base->findBody('html');

			// find further alternativ part
			if ($base->getType() == 'multipart/alternativ' && count($base) !== ($html_id ? $html_id : $plain_id))
			{
				$alternativ_id = (string)count($base);
			}

			$this->_body = $this->_htmlBody = $this->_alternativBody = null;
			$this->clearParts();

			foreach($base->partIterator() as $part)
			{
				$id = $part->getMimeId();
				//error_log(__METHOD__."() plain=$plain_id, html=$html_id: $id: ".$part->getType());
				switch($id)
				{
					case '0':	// base-part itself
						continue 2;
					case $plain_id:
						$this->_body = $part;
						break;
					case $html_id:
						$this->_htmlBody = $part;
						break;
					case $alternativ_id:
						$this->_alternativBody = $part;
						break;
					default:
						$this->_parts[] = $part;
				}
			}
			$this->setBasePart(null);
		}
		catch (Exception $e) {
			// ignore that there is no base-part yet, so nothing to do
			unset($e);
		}
	}

	/**
	 * Adds a MIME message part.
	 *
	 * Reimplemented to add parts / attachments if message was parsed / already has a base-part
	 *
	 * @param Horde_Mime_Part $part  A Horde_Mime_Part object.
	 * @return integer  The part number.
	 */
	public function addMimePart($part)
	{
		if ($this->_base) $this->parseBasePart();

		return parent::addMimePart($part);
	}

	/**
	 * Sets OpenPGP encrypted body according to rfc3156, section 4
	 *
	 * @param string $body             The message content.
	 * @link https://tools.ietf.org/html/rfc3156#section-4
	 */
	public function setOpenPgpBody($body)
	{
		$this->_body = new Horde_Mime_Part();
		$this->_body->setType('multipart/encrypted');
		$this->_body->setContentTypeParameter('protocol', 'application/pgp-encrypted');
		$this->_body->setContents('');

		$part1 = new Horde_Mime_Part();
		$part1->setType('application/pgp-encrypted');
		$part1->setContents("Version: 1\r\n", array('encoding' => '7bit'));
		$this->_body->addPart($part1);

		$part2 = new Horde_Mime_Part();
		$part2->setType('application/octet-stream');
		$part2->setContents($body, array('encoding' => '7bit'));
		$this->_body->addPart($part2);

		$this->_base = null;
	}

	/**
	 * Clear all non-standard headers
	 *
	 * Used in merge-print to remove headers before sending "new" mail
	 */
	function clearCustomHeaders()
	{
		foreach($this->_headers->toArray() as $header => $value)
		{
			if (stripos($header, 'x-') === 0 || $header == 'Received')
			{
				$this->_headers->removeHeader($header);
			}
			unset($value);
		}
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
			case '_bcc':
				$this->_bcc = $value;	// this is NOT PHPMailer compatibility, but need for working BCC, if $this->_bcc is NOT set
				break;
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
				$this->addHeader('X-Priority', $value);
				break;
			case 'Subject':
				$this->addHeader($name, $value);
				break;
			case 'MessageID':
				$this->addHeader('Message-ID', $value);
				break;
			case 'Date':
			case 'RFCDateToSet':
				if ($value) $this->addHeader('Date', $value, true);
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
				error_log(__METHOD__."('$name', ".array2string($value).") unsupported  attribute '$name' --> ignored ".function_backtrace());
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
			case '_bcc':
				return $this->_bcc;	// this is NOT PHPMailer compatibility, but quietening below log, if $this->_bcc is NOT set
			case 'Sender':
				return $this->getHeader('Return-Path');
			case 'From':
				return $this->getHeader('From');
			case 'Body':
			case 'AltBody':
				$body = $this->findBody($name == 'Body' ? 'plain' : 'html');
				return $body ? $body->getContents() : null;
		}
		error_log(__METHOD__."('$name') unsupported  attribute '$name' --> returning NULL ".function_backtrace());
		return null;
	}
}
