<?php
/**
 * EGroupware - eTemplate serverside Tabs widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate Tabs widget stacks multiple sub-templates and lets you switch between them
 */
class etemplate_widget_tabbox extends etemplate_widget_box
{
	/**
	 * Validate input - just pass through, tabs doesn't care
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @param array $expand=array values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = $cname;

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);
			$valid = $value;

			if(!$this->attrs['tabs'])
			{
				return;
			}

			// Make sure additional tabs are processed
			foreach($this->attrs['tabs'] as $tab)
			{
				if($tab['id'] && $content[$tab['id']])
				{
					/* This doesn't work for some reason
					$template = etemplate_widget_template::instance($tab['template']);
					$template->run('validate', array($tab['id'], $expand, $content, &$validated), true);
					*/
					$tab_valid =& self::get_array($validated, $tab['id'], true);
					$tab_valid = $content[$tab['id']];
				}
			}
			$valid = $value;
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_tabbox', array('tabbox'));
