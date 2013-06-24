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
 * Multiple checkbox widgets can have the same name ending in [], in which case an array with the set_val's of the checked boxes get returned.
 */
class etemplate_widget_checkbox extends etemplate_widget
{
	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = array(
		'checkbox' => 'set_val,unset_val,ro_true,ro_false',
		'radio' => 'set_val,ro_true,ro_false',
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
			// defaults for set and unset values
			if (!$this->attrs['set_val'] && !$this->attrs['unset_val'])
			{
				$set_val = true;
				$unset_val = false;
			}
			else
			{
				// Expand any content stuff
				$set_val = self::expand_name($this->attrs['set_val'], $expand['c'], $expand['row'], $expand['c_'], $expand['row_'],$expand['cont']);
				$unset_val = self::expand_name($this->attrs['unset_val'], $expand['c'], $expand['row'], $expand['c_'], $expand['row_'],$expand['cont']);
			}
			if (in_array((string)$set_val, (array)$value))
			{
				if ($multiple)
				{
					if (!isset($valid)) $valid = array();
					$valid[] = $set_val;
				}
				else
				{
					$valid = $set_val;
				}
			}
			elseif ($this->type == 'radio')
			{
				if (!isset($valid)) $valid = '';	// do not overwrite value of an other radio-button of the same group (identical name)!
			}
			else	// if checkbox is not checked, html returns nothing: eTemplate returns unset_val (default false)
			{
				if ($multiple)
				{
					if (!isset($valid)) $valid = array();
				}
				elseif ($value === 'true')
				{
					// 'true' != true
					$valid = $set_val;
				}
				else
				{
					$valid = $unset_val;
				}
			}
			//error_log(__METHOD__.'() '.$form_name.($multiple?'[]':'').': '.array2string($value).' --> '.array2string($valid));
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_checkbox', array('checkbox', 'radio'));
