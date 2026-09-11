<?php
/**
 * EGroupware Api: mail address-list parsing/formatting
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api\Mail;
use Horde_Idna;
use Horde_Mail_Rfc822;
use Horde_Mail_Rfc822_Address;
use Horde_Mail_Rfc822_List;

/**
 * Pure address-list parsing/formatting helpers, extracted from Api\Mail (mail_bo).
 *
 * Deliberately has NO dependency on an IMAP connection, session, or any other Api\Mail instance
 * state - every method here is a plain string/array transform, which is exactly what makes it
 * unit-testable without a mock mailbox (see api/tests/Mail/AddressListTest.php). All in-repo call
 * sites were repointed here directly (mail_compose, mail_ui, calendar, Contacts, Avatar, the Url
 * widget). Api\Mail::parseAddressList() and Api\Mail::decode_subject() are kept as thin delegating
 * wrappers, NOT removed like the others, because tracker's mail-handler (a separate repo) calls
 * them by those exact names - see doc/ai/projects/mail-bo-decoupling.md.
 */
class AddressList
{
	/**
	 * Parse a list of addresses into a Horde_Mail_Rfc822_List of Horde_Mail_Rfc822_Address objects
	 *
	 * Has repair logic for a few known real-world malformations (see inline comments), most
	 * notably a display-name phrase containing a literal, unencoded comma wrapped in a single
	 * RFC 2047 encoded-word - valid per RFC 2047, but breaks a naive comma-split; the resulting
	 * "no mailbox or host part" fragment gets merged back into the following address' personal name.
	 *
	 * @param string|array $addresses
	 * @param ?string $default_domain
	 * @return Horde_Mail_Rfc822_List
	 */
	public static function parseAddressList($addresses, $default_domain=null)
	{
		$options = $default_domain ? ['default_domain' => $default_domain] : [];
		$rfc822 = new Horde_Mail_Rfc822();
		$ret = $rfc822->parseAddressList($addresses, $options);
		if ((empty($ret) || $ret->count() == 0) && is_string($addresses) && strlen($addresses) > 0)
		{
			// last-resort fallback: the input wasn't parseable as an address list at all -
			// pull out anything that looks like a bare email address and retry with just that
			$matches = [];
			preg_match_all("/[\w\.,-.,_.,0-9.]+@[\w\.,-.,_.,0-9.]+/", $addresses, $matches);
			foreach ($matches[0] as &$match)
			{
				$match = trim($match, ', ');
			}
			$addresses = implode(',', $matches[0]);
			$ret = $rfc822->parseAddressList($addresses, $options);
		}

		$remember = '';
		$previousFailed = false;
		$ret2 = new Horde_Mail_Rfc822_List();
		foreach ($ret as $adr)
		{
			// addresses enclosed in single quotes like 'me@you.com' show up as "'me" as mailbox
			// and "you.com'" as host
			if ($adr->mailbox && stripos($adr->mailbox, "'") === 0 &&
				$adr->host && stripos($adr->host, "'") === strlen($adr->host) - 1)
			{
				$adr->mailbox = str_replace("'", '', $adr->mailbox);
				$adr->host = str_replace("'", '', $adr->host);
			}

			// try to strip extra quoting or slashes from the personal part
			$adr->personal = stripslashes($adr->personal);
			if ($adr->personal && (stripos($adr->personal, '"') == 0 &&
					substr($adr->personal, -1) == '"') ||
				(substr($adr->personal, -2) == '""'))
			{
				$adr->personal = str_replace('"', '', $adr->personal);
			}

			// no mailbox or host part - eg. 'Xrählyz, User <mailboxpart1.mailboxpart2@yourhost.com>'
			// gets parsed as 2 addresses separated by the comma inside the unquoted display name;
			// merge the bogus fragment into the following address' personal name instead of
			// returning it as an address of its own
			if (strlen($adr->mailbox) == 0 || strlen($adr->host) == 0)
			{
				$remember = $adr->mailbox ?: ($adr->host ?: '');
				$previousFailed = true;
			}
			else
			{
				if ($previousFailed && $remember)
				{
					$adr->personal = $remember.' '.$adr->personal;
				}
				$remember = '';
				$previousFailed = false;
				$ret2->add($adr);
			}
		}
		return $ret2;
	}

	/**
	 * Strip everything but the bare email address from a list of "Name <email>"-style strings
	 *
	 * @param string[] $_addresses
	 * @return string[]
	 */
	public static function stripRFC822Addresses($_addresses)
	{
		$matches = [];
		foreach ($_addresses as &$address)
		{
			preg_match("/<([^\'\" <>]+)>$/", $address, $matches);
			if (!empty($matches[1]))
			{
				$address = $matches[1];
			}
		}
		return $_addresses;
	}

	/**
	 * Convert an IMAP-style address array (or a plain string) into a comma-separated address string
	 *
	 * @param array|string $rfcAddressArray
	 * @return string
	 */
	public static function convertAddressArrayToString($rfcAddressArray)
	{
		if (!is_array($rfcAddressArray))
		{
			// do not mess with strings, return them untouched /* ToDo: validate string as Address */
			$rfcAddressArray = self::decode_header($rfcAddressArray, true);
			return str_replace(['<', '>', '"\'', '\'"'], ['[', ']', '"', '"'], $rfcAddressArray);
		}

		$returnAddr = [];
		foreach ((array)$rfcAddressArray as $addressData)
		{
			if ($addressData['MAILBOX_NAME'] == 'NIL' ||
				strtolower($addressData['MAILBOX_NAME']) == 'undisclosed-recipients')
			{
				continue;
			}
			if ($addressData['RFC822_EMAIL'])
			{
				$addressObjectA = self::parseAddressList($addressData['RFC822_EMAIL']);
			}
			else
			{
				$emailaddress = $addressData['PERSONAL_NAME'] ?
					$addressData['PERSONAL_NAME'].' <'.$addressData['EMAIL'].'>' : $addressData['EMAIL'];
				$addressObjectA = self::parseAddressList($emailaddress);
			}
			$addressObject = $addressObjectA[0];
			if (!$addressObject->valid)
			{
				continue;
			}
			$returnAddr[] = str_replace(
				['<', '>', '"\'', '\'"'], ['[', ']', '"', '"'],
				self::writeAddressAttemptingIdnaDecode($addressObject)
			);
		}
		return implode(',', $returnAddr);
	}

	/**
	 * Decode a (possibly RFC 2047 encoded-word) mail header value
	 *
	 * @param string|array $_string input to be converted, if array call decode_header recursively on each value
	 * @param bool|string $_tryIDNConversion true to try IDNA-decoding a host part, 'FORCE' to always decode
	 * @return string|array based on the input type
	 */
	public static function decode_header($_string, $_tryIDNConversion=false)
	{
		if (is_array($_string))
		{
			foreach ($_string as $k => $v)
			{
				$_string[$k] = self::decode_header($v, $_tryIDNConversion);
			}
			return $_string;
		}

		$_string = Html::decodeMailHeader($_string, Mail::$displayCharset);
		$_string = self::ensureValidUtf8($_string);

		if ($_tryIDNConversion === true && stripos($_string, '@') !== false)
		{
			$stringA = [];
			foreach (self::parseAddressList($_string) as $rfcAddr)
			{
				if (!$rfcAddr->valid)
				{
					$stringA = [];
					break; // skip idna conversion if we encounter an error here
				}
				$stringA[] = self::writeAddressAttemptingIdnaDecode($rfcAddr);
			}
			if (!empty($stringA))
			{
				$_string = implode(',', $stringA);
			}
		}
		if ($_tryIDNConversion === 'FORCE')
		{
			$_string = Horde_Idna::decode($_string);
		}
		return $_string;
	}

	/**
	 * Decode a mail subject
	 *
	 * If array given, note that only values will be converted
	 *
	 * @param mixed $_string input to be converted, if array call decode_header recursively on each value
	 * @param boolean $decode try decoding
	 * @return mixed - based on the input type
	 */
	public static function decode_subject($_string, $decode=true)
	{
		if ($_string == 'NIL')
		{
			return 'No Subject';
		}
		if ($decode)
		{
			$_string = self::decode_header($_string);
		}
		return self::ensureValidUtf8($_string, false);
	}

	/**
	 * Format an address, trying to IDNA-decode its host part (eg. "xn--mller-kva.de" -> "müller.de")
	 *
	 * Falls back to the un-decoded host on any Idna failure, since that's still a usable (if less
	 * pretty) address, whereas losing the address entirely would not be.
	 */
	private static function writeAddressAttemptingIdnaDecode(Horde_Mail_Rfc822_Address $addr) : string
	{
		try
		{
			return imap_rfc822_write_address($addr->mailbox, Horde_Idna::decode($addr->host), $addr->personal);
		}
		catch (\Exception $e)
		{
			unset($e);
			return imap_rfc822_write_address($addr->mailbox, $addr->host, $addr->personal);
		}
	}

	/**
	 * Make sure a string is valid UTF-8, repairing it if not
	 *
	 * @param string $_string
	 * @param bool $tryHarder attempt mb_convert_encoding()/iconv() repair before falling back to
	 *      utf8_encode(); decode_subject() never needed this stronger (and slower) path, only
	 *      decode_header() did, so it stays opt-in rather than always running both attempts
	 */
	private static function ensureValidUtf8(string $_string, bool $tryHarder=true) : string
	{
		if (@json_encode($_string) !== false || strlen($_string) == 0)
		{
			return $_string;
		}
		if (!$tryHarder)
		{
			return utf8_encode($_string);
		}
		$x = utf8_encode($_string);
		if (@json_encode($x) !== false)
		{
			return $x;
		}
		// this should not be needed, unless something fails with charset detection/wrong charset passed
		if (function_exists('mb_convert_encoding'))
		{
			return mb_convert_encoding($_string, 'UTF-8', 'UTF-8');
		}
		if (function_exists('iconv'))
		{
			return @iconv('UTF-8', 'UTF-8//IGNORE', $_string);
		}
		return $_string;
	}
}
