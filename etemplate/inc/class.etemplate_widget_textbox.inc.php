<?php
/**
 * EGroupware - eTemplate serverside textbox widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-13 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate textbox widget with following sub-types:
 * - textbox with optional multiline="true" and rows="123"
 * - integer or int
 * - float
 * - hidden
 * - colorpicker
 * - passwd (passwords are never send back to client, instead a number of asterisks is send and replaced again!)
 * sub-types are either passed to constructor or set via 'type' attribute!
 */
class etemplate_widget_textbox extends etemplate_widget
{
	/**
	 * Constructor
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws egw_exception_wrong_parameter
	 */
	public function __construct($xml)
	{
		parent::__construct($xml);

		// normalize types
		if ($this->type !== 'textbox')
		{
			if ($this->type == 'int') $this->type = 'integer';

			$this->attrs['type'] = $this->type;
			$this->type = 'textbox';
		}
	}

	/**
	 * Parse and set extra attributes from xml in template object
	 *
	 * Reimplemented to handle legacy read-only by setting size < 0
	 *
	 * @param string|XMLReader $xml
	 * @param boolean $cloned=true true: object does NOT need to be cloned, false: to set attribute, set them in cloned object
	 * @return etemplate_widget_template current object or clone, if any attribute was set
	 */
	public function set_attrs($xml, $cloned=true)
	{
		parent::set_attrs($xml, $cloned);

		// Legacy handling only
		// A negative size triggered the HTML readonly attibute, but not etemplate readonly,
		// so you got an input element, but it was not editable.
		if ($this->attrs['size'] < 0)
		{
			$this->setElementAttribute($this->id, 'size', abs($this->attrs['size']));
			self::$request->readonlys[$this->id] = false;
			$this->setElementAttribute($this->id, 'readonly', true);
			trigger_error("Using a negative size to set textbox readonly. " .$this, E_USER_DEPRECATED);
		}
		return $this;
	}

	/**
	 * Set up what we know on the server side.
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		// to NOT transmit passwords back to client, we need to store (non-empty) value in preserv
		if ($this->attrs['type'] == 'passwd' || $this->type == 'passwd')
		{
			$form_name = self::form_name($cname, $this->id, $expand);
			$value =& self::get_array(self::$request->content, $form_name);
			if (!empty($value))
			{
				$preserv =& self::get_array(self::$request->preserv, $form_name, true);
				if (true) $preserv = (string)$value;
				$value = str_repeat('*', strlen($preserv));
			}
		}
	}

	/**
	 * Validate input
	 *
	 * Following attributes get checked:
	 * - needed: value must NOT be empty
	 * - min, max: int and float widget only
	 * - maxlength: maximum length of string (longer strings get truncated to allowed size)
	 * - validator: perl regular expression incl. delimiters (set by default for int, float and colorpicker)
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
			if (!isset($this->attrs['validator']))
			{
				switch($this->type)
				{
					case 'int':
					case 'integer':
						$this->attrs['validator'] = '/^-?[0-9]*$/';
						break;
					case 'float':
						$this->attrs['validator'] = '/^-?[0-9]*[,.]?[0-9]*$/';
						break;
					case 'colorpicker':
						$this->attrs['validator'] = '/^(#[0-9a-f]{6}|)$/i';
						break;
				}
			}

			$value = $value_in = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);

			// passwords are not transmitted back to client (just asterisks)
			// therefore we need to replace it again with preserved value
			if (($this->attrs['type'] == 'passwd' || $this->type == 'passwd'))
			{
				$preserv = self::get_array(self::$request->preserv, $form_name);
				if ($value == str_repeat('*', strlen($preserv)))
				{
					$value = $preserv;
				}
			}

			if ((string)$value === '' && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!'),'');
			}
			if ((int) $this->attrs['maxlength'] > 0 && mb_strlen($value) > (int) $this->attrs['maxlength'])
			{
				$value = mb_substr($value,0,(int) $this->attrs['maxlength']);
			}
			if ($this->attrs['validator'] && !preg_match($this->attrs['validator'],$value))
			{
				switch($this->type)
				{
					case 'integer':
						self::set_validation_error($form_name,lang("'%1' is not a valid integer !!!",$value),'');
						break;
					case 'float':
						self::set_validation_error($form_name,lang("'%1' is not a valid floatingpoint number !!!",$value),'');
						break;
					default:
						self::set_validation_error($form_name,lang("'%1' has an invalid format !!!",$value)/*." !preg_match('$this->attrs[validator]', '$value')"*/,'');
						break;
				}
			}
			elseif ($this->type == 'integer' || $this->type == 'float')	// cast int and float and check range
			{
				if ((string)$value !== '' || $this->attrs['needed'])	// empty values are Ok if needed is not set
				{
					$value = $this->type == 'integer' ? (int) $value : (float) str_replace(',','.',$value);	// allow for german (and maybe other) format

					if (!empty($this->attrs['min']) && $value < $this->attrs['min'])
					{
						self::set_validation_error($form_name,lang("Value has to be at least '%1' !!!",$this->attrs['min']),'');
						$value = $this->type == 'integer' ? (int) $this->attrs['min'] : (float) $this->attrs['min'];
					}
					if (!empty($this->attrs['max']) && $value > $this->attrs['max'])
					{
						self::set_validation_error($form_name,lang("Value has to be at maximum '%1' !!!",$this->attrs['max']),'');
						$value = $this->type == 'integer' ? (int) $this->attrs['max'] : (float) $this->attrs['max'];
					}
				}
			}
			if (true) $valid = $value;
			//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value));
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_textbox', array('textbox','text','int','integer','float','passwd','hidden','colorpicker','hidden'));
