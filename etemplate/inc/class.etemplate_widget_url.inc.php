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
 * @copyright 2002-11 by RalfBecker@outdoor-training.de
 * @copyright 2012 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate URL widget handles URLs, emails & phone numbers
 */
class etemplate_widget_url extends etemplate_widget
{
	/**
	 * Regexes for validating
	 */
	const EMAIL_PREG = '^[^\x00-\x20()<>@,;:\\".\[\]]+@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,6}';

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
						$valid = filter_var($value, FILTER_VALIDATE_URL);
						if($valid === false &&
							// Remove intl chars & check again, but if it passes we'll keep the original
							filter_var(preg_replace('/[^[:print:]]/','',$value), FILTER_VALIDATE_URL) === false)
						{
							self::set_validation_error($form_name,lang("'%1' has an invalid format",$value),'');
							return;
						}
						break;
					case 'url-email':
						$this->attrs['preg'] = '/('.self::EMAIL_PREG.')?$/iu';
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
						self::set_validation_error($form_name,lang("'%1' has an invalid format",$value)/*." !preg_match('$this->attrs[preg]', '$value')"*/,'');
						break;
				}
			}
			$valid = $value;
			error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value));
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_url', array('url'));
