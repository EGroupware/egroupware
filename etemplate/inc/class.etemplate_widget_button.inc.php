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
	 * Validate input
	 *
	 * Readonly buttons can NOT be pressed
	 *
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @param string $cname='' current namespace
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate(array $content, &$validated=array(), $cname = '')
	{
		$form_name = self::form_name($cname, $this->id);

		if (self::get_array($content, $form_name) && !$this->is_readonly($cname))
		{
			$valid =& self::get_array($validated, $form_name, true);
			$valid = 'pressed';	// that's what it was in old etemplate

			// recored pressed button globally, was in the template object before, not sure self::$request is the right place ...
			if ($this->type == 'cancel' || $form_name == 'cancel' || substr($form_name,-10) == '[cancel]')
			{
				self::$request->canceled = true;
			}
			else
			{
				self::$request->button_pressed = true;
			}
		}
		return parent::validate($content, $validated, $cname);
	}
}
