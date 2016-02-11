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
 *   + add_tabs: true(default) add to given tabs to template, false replace tabs in template
 */
class etemplate_widget_tabbox extends etemplate_widget
{
	/**
	 * Run a given method on all children
	 *
	 * Default implementation only calls method on itself and run on all children.
	 * Overridden here to apply readonlys for the tabbox to disabled on the tab
	 * content.  This prevents running the method on disabled tabs.
	 *
	 * @param string $method_name
	 * @param array $params =array('') parameter(s) first parameter has to be the cname, second $expand!
	 * @param boolean $respect_disabled =false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		$form_name = self::form_name($params[0], $this->id, $params[1]);

		// Make sure additional tabs are processed for any method
		if (!($tabs =& self::getElementAttribute($form_name, 'tabs')))
		{
			$tabs = $this->attrs['tabs'];
		}
		if($tabs && !$this->tabs_attr_evaluated)
		{
			$this->tabs_attr_evaluated = true;	// we must not evaluate tabs attribte more then once!

			// add_tabs toggles replacing or adding to existing tabs
			if(!$this->attrs['add_tabs'])
			{
				$this->children[1]->children = array();
			}

			//$this->tabs = array();
			foreach($tabs as &$tab)
			{
				$template= clone etemplate_widget_template::instance($tab['template']);
				if($tab['id']) $template->attrs['content'] = $tab['id'];
				$this->children[1]->children[] = $template;
				$tab['url'] = etemplate_widget_template::rel2url($template->rel_path);
				//$this->tabs[] = $tab;
				unset($template);
			}
			unset($tab);
			//error_log(__METHOD__."('$method_name', ...) this->id='$this->id' calling setElementAttribute('$form_name', 'tabs', ".array2string($tabs).")");
			self::setElementAttribute($form_name, 'tabs', $tabs);
		}

		// Check for disabled tabs set via readonly, and set them as disabled
		$readonlys = self::get_array(self::$request->readonlys, $form_name);
		if($respect_disabled && $readonlys)
		{
			foreach($this->children[1]->children as $tab)
			{
				$parts = explode('.',$tab->template ? $tab->template : $tab->id);
				$ro_id = array_pop($parts);
				if($readonlys[$ro_id])
				{
					$tab->attrs['disabled'] = $readonlys[$ro_id];
				}
			}
		}

		// Tabs are set up now, continue as normal
		parent::run($method_name, $params, $respect_disabled);
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
		}
	}
}
etemplate_widget::registerWidget('etemplate_widget_tabbox', array('tabbox'));
