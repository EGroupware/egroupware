<?php
/**
 * EGroupware - Home - A simple portlet for displaying a list of entries
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2013 by Nathan Gray
 * @package home
 * @subpackage portlet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */


/**
 * The home_list_portlet uses the link system and its associated link-list widget
 * to display a list of entries.  This is a simple static list that the user can manually
 * add to and remove from.
 *
 * Any application that supports the link system should be able to be added into the list.
 */
class home_list_portlet extends home_portlet
{

	/**
	 * Context for this portlet
	 */
	protected $context = array();

	/**
	 * Title of entry
	 */
	protected $title = 'List';

	/**
	 * Construct the portlet
	 *
	 */
	public function __construct(Array &$context = array())
	{
		if(!is_array($context['list'])) $context['list'] = array();

		// Process dropped data (Should be GUIDs) into something useable
		if($context['dropped_data'])
		{
			foreach((Array)$context['dropped_data'] as $dropped)
			{
				$add = array();
				list($add['app'], $add['id']) = explode('::', $dropped, 2);
				if($add['app'] && $add['id'])
				{
					$context['list'][] = $add;
				}
			}
			unset($add);
			unset($context['dropped_data']);
		}
		if($context['title'])
		{
			$this->title = $context['title'];
		}
		// Add a new entry to the list
		if($context['add'])
		{
			$ok_to_add = true;
			foreach($context['list'] as $key => $entry)
			{
				if($entry == $context['add'])
				{
					$ok_to_add = false;
					break;
				}
			}
			if($ok_to_add)
			{
				$context['list'][] = $context['add'];
			}
			unset($context['add']);
		}

		$this->context = $context;
	}

	/**
	 * Some descriptive information about the portlet, so that users can decide if
	 * they want it or not, and for inclusion in lists, hover text, etc.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @return Array with keys
	 * - displayName: Used in lists
	 * - title: Put in the portlet header
	 * - description: A short description of what this portlet does or displays
	 */
	public function get_description()
	{
		return array(
			'displayName'=> 'List of entries',
			'title'=>	$this->title,
			'description'=>	lang('Show a list of entries')
		);
	}

	/**
	 * Get a fragment of HTML for display
	 *
	 * @param id String unique ID, provided to the portlet so it can make sure content is
	 * 	unique, if needed.
	 * @return string HTML fragment for display
	 */
	public function get_content($id = null)
	{
		$list = array();
		foreach($this->context['list'] as $link_id => $link)
		{
			$list[] = $link + array(
				'title' => egw_link::title($link['app'], $link['id']),
				'icon'	=> egw_link::get_registry($link['app'], 'icon')
			);
		}

		// Find the portlet widget, and add a link-list to it
		return "<script>app.home.List.set_content('$id', ".json_encode($list).")</script>";
	}

	/**
	 * Return a list of settings to customize the portlet.
	 *
	 * Settings should be in the same style as for preferences.  It is OK to return an empty array
	 * for no customizable settings.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @see preferences/inc/class.preferences_settings.inc.php
	 * @return Array of settings.  Each setting should have the following keys:
	 * - name: Internal reference
	 * - type: Widget type for editing
	 * - label: Human name
	 * - help: Description of the setting, and what it does
	 * - default: Default value, for when it's not set yet
	 */
	public function get_properties()
	{
		return array(
			array(
				'name'	=>	'title',
				'type'	=>	'textbox',
				'label'	=>	lang('Title'),
			),
			// Internal
			array(
				'name'	=>	'list'
			)
		) + parent::get_properties();
	}

	/**
	 * Return a list of allowable actions for the portlet.
	 *
	 * These actions will be merged with the default porlet actions.
	 * We add an 'edit' action as default so double-clicking the widget
	 * opens the entry
	 */
	public function get_actions()
	{
		$actions = array(
			'add' => array(
				'icon' => 'add',
				'caption' => lang('add'),
				'hideOnDisabled' => false,
				'onExecute' => 'javaScript:app.home.add_link',
			),
			'add_drop' => array(
				'type' => 'drop',
				'caption' => lang('add'),
				'onExecute' => 'javaScript:app.home.add_link',
				'acceptedTypes' => array('file') + array_keys($GLOBALS['egw_info']['apps']),
			)
		);
		return $actions;
	}

	/**
	 * List portlet displays multiple entries, so it makes sense to accept multiple dropped entries
	 */
	public function accept_multiple()
	{
		return true;
	}
}
