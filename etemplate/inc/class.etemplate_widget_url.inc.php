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
 * @copyright 2002-14 by RalfBecker@outdoor-training.de
 * @copyright 2012 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate URL widget handles URLs, emails & phone numbers
 */
class etemplate_widget_url extends etemplate_widget
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
	 *
	 * About umlaut or IDN domains: we currently only allow German umlauts in domain part!
	 *
	 * Same preg is in et2_widget_url Javascript class, but no \x00 allowed and /u modifier for utf8!
	 */
	const EMAIL_PREG = "/^(([^\042',<][^,<]+|\042[^\042]+\042|\'[^\']+\'|)\s?<)?[^\x01-\x20()<>@,;:\042\[\]]+@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,6}>?$/iu";

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

			if ((string)$value === '' && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!'),'');
				return;
			}
			elseif ((string)$value != '' && !isset($this->attrs['preg']))
			{
				switch($this->type)
				{
					case 'url':
						// if no protocol given eg. "www.egroupware.org" prepend "http://" for validation
						if (($missing_protocol = strpos($value, '://') === false))
						{
							$value = 'http://'.$value;
						}
						$url_valid = filter_var($value, FILTER_VALIDATE_URL) ||
							// Remove intl chars & check again, but if it passes we'll keep the original
							filter_var(preg_replace('/[^[:print:]]/','',$value), FILTER_VALIDATE_URL);
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
			$valid = $value;
			//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value));
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_url', array('url'));
