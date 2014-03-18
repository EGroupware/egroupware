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
 *
 * Available attributes:
 * - add_tabs: true: tabs contain addtional tabs, false: tabs replace tabs in template
 * - tabs: array with (additional) tabs with values for following keys
 *   + label:    label of tab
 *   + template: template name with optional '?'.filemtime as cache-buster
 *   optional:
 *   + prepend:  true prepend tab to existing ones, false (default) append tabs
 *   + hidden:
 *   + id:       optinal namespace (content attribute of template)
 */
class etemplate_widget_tabbox extends etemplate_widget
{
	/**
	 * Fill additional tabs
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		unset($cname);
		if($this->attrs['tabs'])
		{
			// add_tabs toggles replacing or adding to existing tabs
			if(!$this->attrs['add_tabs'])
			{
				$this->children[1]->children = array();
			}
			foreach($this->attrs['tabs'] as $tab)
			{
				$template= clone etemplate_widget_template::instance($tab['template']);
				if($tab['id']) $template->attrs['content'] = $tab['id'];
				$this->children[1]->children[] = $template;
				unset($template);
			}
		}
	}

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
		$form_name = self::form_name($cname, $this->id, $expand);

		if (!empty($form_name))
		{
			$value = self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;

			if(!$this->attrs['tabs'])
			{
				return;
			}

			// Make sure additional tabs are processed

			// add_tabs toggles replacing or adding to existing tabs
			if(!$this->attrs['add_tabs'])
			{
				$this->children[1]->children = array();
			}
			foreach($this->attrs['tabs'] as $tab)
			{
				$template= clone etemplate_widget_template::instance($tab['template']);
				if($tab['id'] && $content[$tab['id']]) $template->attrs['content'] = $tab['id'];
				$this->children[1]->children[] = $template;
				unset($template);
			}
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_tabbox', array('tabbox'));
