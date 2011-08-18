<?php
/**
 * EGroupware - eTemplate serverside textbox widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-11 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate textbox widget with following sub-types:
 * - textbox optional multiline="true" and rows="123"
 * - int
 * - float
 * - hidden
 * - colorpicker
 * sub-types are either passed to constructor or set via 'type' attribute!
 */
class etemplate_widget_textbox extends etemplate_widget
{
	/**
	 * Validate input
	 *
	 * Following attributes get checked:
	 * - needed: value must NOT be empty
	 * - min, max: int and float widget only
	 * - maxlength: maximum length of string (longer strings get truncated to allowed size)
	 * - preg: perl regular expression incl. delimiters (set by default for int, float and colorpicker)
	 * - int and float get casted to their type
	 *
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @param string $cname='' current namespace
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate(array $content, &$validated=array(), $cname = '')
	{
		$ok = true;
		$type = isset($this->attrs['type']) ? $this->attrs['type'] : $this->type;
		if (!$this->is_readonly($cname))
		{
			if (!isset($this->attrs['preg']))
			{
				switch($type)
				{
					case 'int':
						$this->attrs['preg'] = '/^-?[0-9]*$/';
						break;
					case 'float':
						$this->attrs['preg'] = '/^-?[0-9]*[,.]?[0-9]*$/';
						break;
					case 'colorpicker':
						$this->attrs['preg'] = '/^(#[0-9a-f]{6}|)$/i';
						break;
				}
			}
			$form_name = self::form_name($cname, $this->id);

			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);

			if ((string)$value === '' && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!'),'');
				$ok = false;
			}
			if ((int) $this->attrs['maxlength'] > 0 && strlen($value) > (int) $this->attrs['maxlength'])
			{
				$value = substr($value,0,(int) $this->attrs['maxlength']);
			}
			if ($this->attrs['preg'] && !preg_match($this->attrs['preg'],$value))
			{
				switch($type)
				{
					case 'int':
						self::set_validation_error($form_name,lang("'%1' is not a valid integer !!!",$value),'');
						break;
					case 'float':
						self::set_validation_error($form_name,lang("'%1' is not a valid floatingpoint number !!!",$value),'');
						break;
					default:
						self::set_validation_error($form_name,lang("'%1' has an invalid format !!!",$value)/*." !preg_match('$this->attrs[preg]', '$value')"*/,'');
						break;
				}
				$ok = false;
			}
			elseif ($type == 'int' || $type == 'float')	// cast int and float and check range
			{
				if ((string)$value !== '' || $this->attrs['needed'])	// empty values are Ok if needed is not set
				{
					$value = $type == 'int' ? (int) $value : (float) str_replace(',','.',$value);	// allow for german (and maybe other) format

					if (!empty($this->attrs['min']) && $value < $this->attrs['min'])
					{
						self::set_validation_error($form_name,lang("Value has to be at least '%1' !!!",$this->attrs['min']),'');
						$value = $type == 'int' ? (int) $this->attrs['min'] : (float) $this->attrs['min'];
						$ok = false;
					}
					if (!empty($this->attrs['max']) && $value > $this->attrs['max'])
					{
						self::set_validation_error($form_name,lang("Value has to be at maximum '%1' !!!",$this->attrs['max']),'');
						$value = $type == 'int' ? (int) $this->attrs['max'] : (float) $this->attrs['max'];
						$ok = false;
					}
				}
			}
			$valid = $value;
		}
		return parent::validate($content, $validated, $cname) && $ok;
	}
}
etemplate_widget::registerWidget('etemplate_widget_textbox', array('textbox','int','float','passwd','hidden','colorpicker'));