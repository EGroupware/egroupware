<?php
/**
 * EGroupware - eTemplate serverside button widget
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
 * eTemplate button widget
 */
class etemplate_widget_button extends etemplate_widget
{
	/**
	 * True after submit-validation, if cancel button was pressed
	 *
	 * @var boolean
	 */
	public static $canceled = false;
	/**
	 * True after submit-validation,if a non-cancel button was pressed
	 *
	 * @var boolean
	 */
	public static $button_pressed = false;

	/**
	 * Validate buttons
	 *
	 * Readonly buttons can NOT be pressed!
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
		//error_log(__METHOD__."('$cname', ".array2string($expand).", ...) $this: get_array(\$content, '$form_name')=".array2string(self::get_array($content, $form_name)));

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = self::get_array($content, $form_name);

			if(
				// Handle case of not existing $row_cont[id], eg: create[]
				is_array($value) && count($value) == 1 ||
				// check === true, as get_array() ignores a "[]" postfix and returns array() eg. for a not existing $row_cont[id] in "delete[$row_cont[id]]"
				$value == true
			)
			{
				$valid =& self::get_array($validated, $form_name, true);
				$valid = is_array($value) ? $value : 'pressed';

				// recorded pressed button globally, was in the template object before, put now as static on this object
				if ($this->type == 'cancel' || $form_name == 'cancel' || substr($form_name,-10) == '[cancel]')
				{
					self::$canceled = true;
				}
				else
				{
					self::$button_pressed = true;
				}
			}
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_button', array('button','buttononly'));
