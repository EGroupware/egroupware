<?php
/**
 * EGroupware - Home - A simple portlet for displaying an entry
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2013 by Nathan Gray
 * @package home
 * @subpackage portlet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

class home_link_portlet extends home_portlet
{

	/**
	 * Context for this portlet
	 */
	protected $context = array();

	/**
	 * Title of entry
	 */
	protected $title = 'Link';

	/**
	 * Base name for template
	 * @var string
	 */
	protected $template_name = 'home.link';

	/**
	 * Construct the portlet
	 *
	 * @param boolean $need_reload Flag to indicate that the portlet needs to be reloaded (exec will be called)
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		// Process dropped data into something useable
		if($context['dropped_data'])
		{
			list($context['entry']['app'], $context['entry']['id']) = explode('::', $context['dropped_data'][0], 2);
			unset($context['dropped_data']);
			$need_reload = true;
		}
		if($context['entry'] && is_array($context['entry']));
		{
			$this->title = $context['entry']['title'] = egw_link::title($context['entry']['app'], $context['entry']['id']);
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
			'displayName'=> 'Single Entry',
			'title'=>	$this->context['entry'] ? lang($this->context['entry']['app']) : lang('None'),
			'description'=>	lang('Show one entry')
		);
	}

	/**
	 * Generate display
	 *
	 * @param id String unique ID, provided to the portlet so it can make sure content is
	 * 	unique, if needed.
	 */
	public function exec($id = null, etemplate_new &$etemplate = null)
	{
		// Check for custom template for app
		if($this->context && $this->context['entry'] && $this->context['entry']['app'] &&
			$etemplate->read($this->context['entry']['app'] . '.' . $this->template_name))
		{
			// No action needed, custom template loaded as side-effect
		}
		else
		{
			$etemplate->read($this->template_name);
		}

		$etemplate->set_dom_id($id);
		
		$content = $this->context;
		if(!is_array($content['entry']))
		{
			$content['entry'] = null;
		}
		
		$etemplate->exec('home.home_link_portlet.exec',$content);
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
		$properties = parent::get_properties();

		$properties[] = array(
				'name'	=>	'entry',
				'type'	=>	'link-entry',
				'label'	=>	lang('Entry'),
				'size' => ''
		);
		return $properties;
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
			'view' => array(
				'icon' => 'view',
				'caption' => lang('open'),
				'default' => true,
				'hideOnDisabled' => false,
				'onExecute' => 'javaScript:app.home.open_link',
			),
			'edit_settings' => array(
				'default' => false
			)
		);
		$actions['view']['enabled'] = (bool)$this->context['entry'];

		return $actions;
	}
}
