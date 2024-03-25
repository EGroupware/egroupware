<?php
/**
 * EGroupware - eTemplate serverside Tabs widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate Tabs widget stacks multiple sub-templates and lets you switch between them
 *
 * Available attributes:
 * - addTabs: true: extraTabs contain additional tabs, false (default): tabs replace tabs in template
 * - extraTabs: array with (additional) tabs with values for following keys
 *   + label:    label of tab
 *   + template: template name with optional '?'.filemtime as cache-buster
 *   optional:
 *   + prepend:  true prepend tab to existing ones, false (default) append tabs or name of tab to prepend the tab
 *   + hidden:   true: hide tab, false (default): show tab
 *   + id:       id of tab
 *   + content:  optional namespace (content attribute of template)
 *   + statustext: tooltip of label
 * - cfTypeFilter: optional type-filter for automatic created custom-fields tabs
 * - cfPrivateTab: true: create an extra tab for private custom-fields, false (default): show private ones together with non-private ones
 * - cfPrepend: value for prepend tab-attribute for dynamic generated custom-field tabs, default "history"
 * - cfExclude: custom fields to exclude, comma-separated, to list fields added e.g. manually to the template
 */
class Tabbox extends Etemplate\Widget
{
	/**
	 * Run a given method on all children
	 *
	 * Default implementation only calls method on itself and run on all children.
	 * Overridden here to apply readonlys for the tabbox to disabled on the tab
	 * content.  This prevents running the method on disabled tabs.
	 *
	 * @param string|callable $method_name or function($cname, $expand, $widget)
	 * @param array $params =array('') parameter(s) first parameter has to be the cname, second $expand!
	 * @param boolean $respect_disabled =false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		$form_name = self::form_name($params[0], $this->id, $params[1]);

		// Make sure additional tabs are processed for any method
		if(!($tabs =& self::getElementAttribute($form_name, 'extraTabs')))
		{
			$tabs = $this->attrs['extraTabs'];
		}
		if($tabs && !$this->tabs_attr_evaluated)
		{
			$this->tabs_attr_evaluated = true;	// we must not evaluate tabs attribute more than once!

			// add_tabs toggles replacing or adding to existing tabs
			if(!($this->attrs['addTabs'] ?? $this->attrs['add_tabs']))
			{
				$this->children[1]->children = array();
			}

			//$this->tabs = array();
			foreach($tabs as &$tab)
			{
				$template= clone Template::instance($tab['template']);
				$this->children[1]->children[] = $template;
				$tab['url'] = Template::rel2url($template->rel_path);
				//$this->tabs[] = $tab;
				unset($template);
			}
			unset($tab);
			//error_log(__METHOD__."('$method_name', ...) this->id='$this->id' calling setElementAttribute('$form_name', 'tabs', ".array2string($tabs).")");
			self::setElementAttribute($form_name, 'extraTabs', $tabs);
		}

		// Check for disabled tabs set via readonly, and set them as disabled
		$readonlys = self::get_array(self::$request->readonlys, $form_name);

		// Set children of readonly tabs to readonly
		// to avoid checking for server side validation
		if ($form_name == 'tabs' && is_array($readonlys))
		{
			foreach($this->children[1]->children as $tab)
			{
				if (!empty($readonlys[$tab->id]))
				{
					$tab->attrs['disabled'] = $readonlys[$tab->id];
				}
			}
		}

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

	/**
	 * Method called before eT2 request is sent to client
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		[$app] = explode('.', self::$request->template['name']);
		// no need to run again for responses, or if we have no custom fields
		if (!empty(self::$response) || empty($app) || !($cfs = Api\Storage\Customfields::get($app, false, null, null, true)))
		{
			return;
		}
		$form_name = self::form_name($cname, $this->id, $expand);
		$extra_private_tab = self::expand_name(self::getElementAttribute($form_name, 'cfPrivateTab') ?? $this->attrs['cfPrivateTab'] ?? false,
			0, 0, 0, 0, self::$request->content);
		if (is_string($extra_private_tab) && $extra_private_tab[0] === '!')
		{
			$extra_private_tab = !substr($extra_private_tab, 1);
		}

		$prepend = $this->attrs['cfPrepend'] ?? 'history';

		// check if template still contains a legacy customfield tab
		$have_legacy_cf_tab = $this->haveLegacyCfTab();

		$exclude = self::getElementAttribute($form_name, 'cfExclude') ?? $this->attrs['cfExclude'] ?? null;
		$exclude = $exclude ? explode(',', $exclude) : [];

		$tabs = $private_tab = $default_tab = [];
		foreach($cfs as $cf)
		{
			if (in_array($cf['name'], $exclude))
			{
				continue;
			}
			if (!empty($cf['tab']))
			{
				$tab = $tabs[$cf['tab']]['id'] ?? 'cf-tab'.(1+count($tabs));
				if (!isset($tabs[$cf['tab']]))
				{
					$tabs[$cf['tab']] = [
						'id' => $tab,
						'template' => 'api.cf-tab',
						'label' => $cf['tab'],
						'prepend' => $prepend,
					];
				}
			}
			elseif ($have_legacy_cf_tab)
			{
				continue;
			}
			// does app want an extra private cf tab
			elseif (!empty($cf['private']) && $extra_private_tab)
			{
				if (!$private_tab)
				{
					$private_tab[] = [
						'id' => 'cf-default-private',
						'template' => 'api.cf-tab',
						'label' => 'Extra private',
						'statustext' => 'Private custom fields',
						'prepend' => $prepend,
					];
				}
			}
			// default cf tab
			elseif ((empty($cf['private']) || !$extra_private_tab && !empty($cf['private'])) && !$default_tab)
			{
				$default_tab[] = [
					'id' => $extra_private_tab ? 'cf-default-non-private' : 'cf-default',
					'template' => 'api.cf-tab',
					'label' => 'Custom fields',
					'prepend' => $prepend,
				];
			}
		}
		if ($tabs || $default_tab || $private_tab)
		{
			// pass given cfTypeFilter attribute via content to all customfields widgets (set in api.cf-tab template)
			if (($type_filter = self::getElementAttribute($form_name, 'cfTypeFilter') ?? $this->attrs['cfTypeFilter'] ?? null))
			{
				$content = self::$request->content;
				$content['cfTypeFilter'] = self::expand_name($type_filter, 0, 0, 0, 0, $content);
				self::$request->content = $content;
			}
			// pass cfExclude attribute via content to all customfields widgets (set in api.cf-tab template)
			if ($exclude)
			{
				$content = self::$request->content;
				$content['cfExclude'] = implode(',', $exclude);
				self::$request->content = $content;
			}

			// addTabs is default false (= replace tabs), we need a default of true
			$add_tabs =& self::setElementAttribute($this->id, 'addTabs', null);
			if (!isset($add_tabs)) $add_tabs = true;

			// if app already specified extraTabs (like e.g. Addressbook), we need to add to them not overwrite them
			$extra_tabs =& self::setElementAttribute($this->id, 'extraTabs', null);
			$extra_tabs = array_merge($extra_tabs ?? [], $default_tab, $private_tab, array_values($tabs));

			// if we have no explicit default cf widget/tab, we need to call customfields::beforeSendToClient() to pass cfs to client-side
			$cfs = new Customfields('<customfields/>');
			$cfs->beforeSendToClient($cname, $expand);
		}
	}

	/**
	 * Check if widget has a legacy custom-fields tab
	 *
	 * @return bool true: there is a tab named extra, custom or customfields
	 */
	public function haveLegacyCfTab()
	{
		foreach($this->children[$this->children[0]->type === 'tabs' ? 0 : 1]->children as $tab)
		{
			if (preg_match('/(^|\.)(extra|custom|customfields)$/', $tab->id))
			{
				return true;
			}
		}
		return false;
	}
}