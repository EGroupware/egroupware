<?php
/**
 * EGroupware - eTemplate serverside textbox widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;
use XMLReader;

/**
 * eTemplate textbox widget with following sub-types:
 * - textbox with optional multiline="true" and rows="123"
 * - integer or int
 * - float
 * - hidden
 * - colorpicker
 * sub-types are either passed to constructor or set via 'type' attribute!
 */
class Textbox extends Etemplate\Widget
{
	/**
	 * Constructor
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
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
	 * @param boolean $cloned =true true: object does NOT need to be cloned, false: to set attribute, set them in cloned object
	 * @return Template current object or clone, if any attribute was set
	 */
	public function set_attrs($xml, $cloned=true)
	{
		parent::set_attrs($xml, $cloned);

		// Legacy handling only
		// A negative size triggered the HTML readonly attribute, but not etemplate readonly,
		// so you got an input element, but it was not editable.
		if (isset($this->attrs['size']) && $this->attrs['size'] < 0)
		{
			self::setElementAttribute($this->id, 'size', abs($this->attrs['size']));
			self::$request->readonlys[$this->id] = false;
			self::setElementAttribute($this->id, 'readonly', true);
			//trigger_error("Using a negative size to set textbox readonly. " .$this, E_USER_DEPRECATED);
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
				switch($this->attrs['type'])
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

			if ((string)$value === '' && $this->required)
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!'),'');
			}
			if ((int) $this->attrs['maxlength'] > 0 && mb_strlen($value) > (int) $this->attrs['maxlength'])
			{
				$value = mb_substr($value,0,(int) $this->attrs['maxlength']);
			}
			// PHP xml parser reads backslashes literal from attributes, while JavaScript ones need them escaped (eg. like PHP strings)
			// --> replace \\ with \ to get following XML working: validator="/^\\d+$" (server- AND client-side!)
			if ($this->attrs['validator'] && !preg_match(str_replace('\\\\','\\', $this->attrs['validator']), $value))
			{
				switch($this->attrs['type'])
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
			elseif ($this->attrs['type'] == 'integer' || $this->attrs['type'] == 'float')	// cast int and float and check range
			{
				if ((string)$value !== '' || $this->required)	// empty values are Ok if needed is not set
				{
					$value = $this->attrs['type'] == 'integer' ? (int) $value : (float) str_replace(',','.',$value);	// allow for german (and maybe other) format

					$min = self::expand_name($this->attrs['min'], $expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
					if (!(empty($min) && $min !== 0) && $value < $min)
					{
						self::set_validation_error($form_name, lang("Value has to be at least '%1' !!!", $min),'');
						$value = $this->attrs['type'] == 'integer' ? (int) $min : (float) $min;
					}
					$max = self::expand_name($this->attrs['max'], $expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
					if (!(empty($max) && $max !== 0) && $value > $max)
					{
						self::set_validation_error($form_name, lang("Value has to be at maximum '%1' !!!", $max),'');
						$value = $this->attrs['type'] == 'integer' ? (int) $max : (float) $max;
					}
				}
			}
			if (isset($value))
			{
				self::set_array($validated, $form_name, $value);
				//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value));
			}
		}
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__ . '\\Textbox', array('et2-textarea', 'et2-textbox', 'textbox', 'text',
																	'int', 'integer', 'float', 'et2-number', 'hidden',
																	'colorpicker'));