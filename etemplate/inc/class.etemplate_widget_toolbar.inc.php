<?php
/**
 * EGroupware - eTemplate serverside toolbar widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @version $Id$
 */

/**
 * eTemplate button widget
 */
class etemplate_widget_toolbar extends etemplate_widget
{
	/**
	 * Validate toolbar
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
		error_log(__METHOD__."('$cname', ".array2string($expand).", ...) $this: get_array(\$content, '$form_name')=".array2string(self::get_array($content, $form_name)));

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_toolbar', array('toolbar'));
