<?php
/**
 * EGroupware - eTemplate serverside URL widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @author Nathan Gray
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @copyright 2012 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;

/**
 * eTemplate URL widget handles URLs, emails & phone numbers
 */
class Url extends Etemplate\Widget
{
	/**
	 * Regexes for validating email addresses incl. email in angle-brackets eg.
	 * + "Ralf Becker <rb@stylite.de>"
	 * + "Ralf Becker (Stylite AG) <rb@stylite.de>"
	 * + "<rb@stylite.de>" or "rb@stylite.de"
	 * + '"Becker, Ralf" <rb@stylite.de>'
	 * + "'Becker, Ralf' <rb@stylite.de>"
	 * but NOT:
	 * - "Becker, Ralf <rb@stylite.de>" (contains comma outside " or ' enclosed block)
	 * - "Becker < Ralf <rb@stylite.de>" (contains <    ----------- " ---------------)
	 * automatic cleaning of common mistakes (makes no sense to complain about them, as they are not visible to the user)
	 * - "\u200Bfrancesca.klein@ikem.de" (starts with an unicode "zero width space")
	 * - "info︃@joswieg.de" (Unicode variation selector "\uFE0X" before the @, meaning eg. not to be displayed as Emotji)
	 *
	 * About umlaut or IDN domains: we currently only allow German umlauts in domain part!
	 * Client-side forbids all non-ascii chars in local part, as Horde does not yet support SMTPUTF8 extension (rfc6531)
	 * and we get a "SMTP server does not support internationalized header data" error otherwise.
	 * We can't do that easily on server-side, as used \x80-\xf0 works in JavaScript to detect non-ascii,
	 * but gives an invalid utf-8 compilation error in PHP together with /u modifier.
	 *
	 * Same preg is in et2_widget_url Javascript class, but no \x00 allowed and /u modifier for utf8!
	 */
	const EMAIL_PREG = "/^(([^,<][^,<]+|\042[^\042]+\042|\'[^\']+\'|)\s?<)?[^\x01-\x20()\xe2\x80\x8b<>@,;:\042\[\]]+(?<![.\s])@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,}>?$/iu";

	// allow private IP addresses (starting with 10.|169.254.|192.168.) too
	//const URL_PREG = '_^(?:(?:https?|ftp)://)?(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(localhost)|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS';
	const URL_PREG = '_^(?:(?:https?|ftp)://)?(?:\S+(?::\S*)?@)?(((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3})|(localhost)|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS';

	/**
	 * Validate input
	 *
	 * Following attributes get checked:
	 * - needed: value must NOT be empty
	 * - maxlength: maximum length of string (longer strings get truncated to allowed size)
	 * - preg: perl regular expression incl. delimiters (set by default for int, float and colorpicker)
	 * - int and float get casted to their type
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @param array $expand=array values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in = self::get_array($content, $form_name);

			if ((string)$value === '' && $this->required)
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!'),'');
				return;
			}
			elseif ((string)$value != '' && !isset($this->attrs['preg']))
			{
				$url_valid = true;
				switch($this->type)
				{
					case 'url':
						$this->attrs['preg'] = self::URL_PREG;
						if($this->attrs['allowPath'] ?? $this->attrs['allow_path'])
						{
							$url_valid = $value[0] === '/';

							// Just need to override the regex
							$this->attrs['preg'] = '_\/.*_';
						}
						// if no protocol given eg. "www.egroupware.org" prepend "http://" for validation
						else if (($missing_protocol = strpos($value, '://') === false))
						{
							$value = 'http://'.$value;
						}
						if($url_valid && !$this->attrs['allow_path'])
						{
							$url_valid = filter_var($value, FILTER_VALIDATE_URL) ||
								// Remove intl chars & check again, but if it passes we'll keep the original
								filter_var(preg_replace('/[^[:print:]]/','',$value), FILTER_VALIDATE_URL);
						}
						if(array_key_exists('trailing_slash', $this->attrs) || array_key_exists('trailingSlash', $this->attrs))
						{
							$trailing_slash = substr($value, -1) === '/';
							$url_valid = ((($this->attrs['trailingSlash'] ?? $this->attrs['trailing_slash']) == 'true') == $trailing_slash);
						}
						//error_log(__METHOD__."() filter_var(value=".array2string($value).", FILTER_VALIDATE_URL)=".array2string(filter_var($value, FILTER_VALIDATE_URL))." --> url_valid=".array2string($url_valid));
						// remove http:// validation prefix again
						if ($missing_protocol)
						{
							$value = substr($value, 7);
						}
						if (!$url_valid)
						{
							self::set_validation_error($form_name,lang("'%1' has an invalid format !!!",$value),'');
							return;
						}
						break;
					case 'url-email':
						// some automatic cleaning: unicode variation selectors, zero width space
						$value = preg_replace('/[\x{FE00}-\x{FE0F}\x{200B}]/u', '', $value);
						$this->attrs['preg'] = self::EMAIL_PREG;
						break;
				}
			}

			$valid =& self::get_array($validated, $form_name, true);

			if ((int) $this->attrs['maxlength'] > 0 && strlen($value) > (int) $this->attrs['maxlength'])
			{
				$value = substr($value,0,(int) $this->attrs['maxlength']);
			}
			if ($this->attrs['preg'] && !preg_match($this->attrs['preg'],$value))
			{
				switch($this->type)
				{
					default:
						//error_log("preg_match('{$this->attrs['preg']}', '$value')=".array2string(preg_match($this->attrs['preg'], $value)));
						self::set_validation_error($form_name,lang("'%1' has an invalid format !!!",$value)/*." !preg_match('$this->attrs[preg]', '$value')"*/,'');
						break;
				}
			}
			if (true) $valid = $value;
			//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value));
		}
	}

	/**
	 * Handle ajax searches for existing contact based on email
	 *
	 * @return Array|boolean Contact data of first match, or false if contact does not exist
	 */
	public static function ajax_contact($_email)
	{
		$email = \EGroupware\Api\Mail::stripRFC822Addresses(array($_email));
		$response = \EGroupware\Api\Json\Response::get();
		$result = $GLOBALS['egw']->contacts->search(
			array('contact_email' => $email[0], 'contact_email_home' => $email[0]),
			array('contact_id', 'email', 'email_home', 'n_fn'),
			'', '', '', false, 'OR', false
		);
		$response->data($result ? $result[0] : false);
	}
}