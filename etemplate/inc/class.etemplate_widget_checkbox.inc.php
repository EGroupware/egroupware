<?php
/**
 * EGroupware - eTemplate serverside checkbox widget
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
 * eTemplate checkbox widget
 *
 * Multiple checkbox widgets can have the same name ending in [], in which case an array with the selected_value's of the checked boxes get returned.
 */
class etemplate_widget_checkbox extends etemplate_widget
{
	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = array(
		'checkbox' => 'selected_value,unselected_value,ro_true,ro_false',
		'radio' => 'set_value,ro_true,ro_false',
	);

	/**
	 * Validate input
	 *
	 * In case of multiple checkboxes using the same name ending in [], each widget only validates it's own value!
	 * Same is true for the radio buttons of a radio-group sharing the same name.
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		if (($multiple = substr($form_name, -2) == '[]') && $this->type == 'checkbox')
		{
			$form_name = substr($form_name, 0, -2);
		}

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);

			if (!$value && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!'),'');
			}
			$type = $this->type ? $this->type : $this->attrs['type'];
			$value_attr = $type == 'radio' ? 'set_value' : 'selected_value';
			// defaults for set and unset values
			if (!$this->attrs[$value_attr] && !$this->attrs['unselected_value'])
			{
				$selected_value = true;
				$unselected_value = false;
			}
			else
			{
				// Expand any content stuff
				$selected_value = self::expand_name($this->attrs[$value_attr], $expand['c'], $expand['row'], $expand['c_'], $expand['row_'],$expand['cont']);
				$unselected_value = self::expand_name($this->attrs['unselected_value'], $expand['c'], $expand['row'], $expand['c_'], $expand['row_'],$expand['cont']);
			}
			if ($type == 'radio')
			{
				$options = etemplate_widget_menupopup::selOptions($form_name, true);
				if (in_array($value, $options))
				{
					$valid = $value;
				}
				elseif (!isset($valid))
				{
					$valid = '';	// do not overwrite value of an other radio-button of the same group (identical name)!
				}
			}
			elseif (in_array((string)$selected_value, (array)$value))
			{
				if ($multiple)
				{
					if (!isset($valid)) $valid = array();
					$valid[] = $selected_value;
				}
				else
				{
					$valid = $selected_value;
				}
			}
			else	// if checkbox is not checked, html returns nothing: eTemplate returns unselected_value (default false)
			{
				if ($multiple)
				{
					if (!isset($valid)) $valid = array();
				}
				elseif ($value === 'true')
				{
					// 'true' != true
					$valid = $selected_value;
				}
				else
				{
					$valid = $unselected_value;
				}
			}
			//error_log(__METHOD__.'() '.$form_name.($multiple?'[]':'').': '.array2string($value).' --> '.array2string($valid));
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_checkbox', array('checkbox', 'radio'));
