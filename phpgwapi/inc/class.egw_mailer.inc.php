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

use EGroupware\Api\Mailer;

/**
 * Log mails to log file specified in $GLOBALS['egw_info']['server']['log_mail']
 * or regular error_log for true (can be set either in DB or header.inc.php).
 *
 * New egw_mailer object uses Horde Mime Mail class with compatibility methods for
 * old PHPMailer methods and class variable assignments.
 *
 * This class does NOT use anything EGroupware specific, it acts like PHPMail, but logs.
 */
class egw_mailer extends Mailer
{
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
