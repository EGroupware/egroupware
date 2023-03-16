<?php
/***
 * EGroupware - Home - A simple portlet for displaying a list of entries
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2013 by Nathan Gray
 * @package home
 * @subpackage portlet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.home_list_portlet.inc.php 49321 2014-11-06 21:40:03Z nathangray $
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;


/**
 * The home_favorite_portlet uses a nextmatch to display the entries for a particular
 * favorite, for a given app.
 */
class home_favorite_portlet extends home_portlet
{

	/**
	 * Context for this portlet - the application and favorite name
	 */
	protected $context = array(
		'appname'	=>	'',
		'favorite'	=>	'blank'
	);

	/**
	 * Nextmatch settings
	 * @see Etemplate\Widget\Nextmatch
	 * @var array
	 */
	protected $nm_settings = array(
		'lettersearch'	=> false,
		'favorites'		=> false,	// Hide favorite control
		'actions'		=> array(),
		'placeholder_actions' => array()
	);

	/**
	 * Constructor sets up the portlet according to the user's saved property values
	 * for this particular portlet.  It is possible to have multiple instances of the
	 * same portlet with different properties.
	 *
	 * The implementing class is allowed to modify the context, if needed, but it is
	 * better to use get_properties().
	 *
	 * We try to keep the constructor light as it gets called often, and only load
	 * things needed for display in exec.
	 *
	 * @param context Array portlet settings such as size, as well as values for properties
	 * @param boolean $need_reload Flag to indicate that the portlet needs to be reloaded (exec will be called)
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		// Process dropped data (Should be [appname => <appname>, id => <favorite ID>]) into something useable
		if($context['dropped_data'])
		{
			foreach((Array)$context['dropped_data'] as $dropped)
			{
				// Only handle one, but dropped is an array
				$context['appname'] = $dropped['appname'];
				$context['favorite'] = $dropped['id'];
				break;
			}

			unset($context['dropped_data']);

			$need_reload = true;
		}
		// Favorite not set for new widgets created via context menu
		if(!$context['favorite'] && !($context['width'] || $context['height']))
		{
			// Set initial size to 6x3, default is way too small
			$context['width'] = max($context['width'], 6);
			$context['height'] = max($context['height'], 3);

			$need_reload = true;
		}

		// Load and copy favorite
		if($context['appname'])
		{
			$favorites = Framework\Favorites::get_favorites($context['appname']);
		}

		$this->favorite = (array)$favorites[$context['favorite'] ?: 'blank'];
		$this->title = lang($context['appname']) . ': ' . $this->favorite['name'];
		$this->context = $context;
		if($this->favorite)
		{
			$this->nm_settings['favorite'] = $this->context['favorite'];
			if(is_array($favorites[$context['favorite']]['state']))
			{
				$this->nm_settings += $this->favorite['state'];
			}
		}
		$this->nm_settings['columnselection_pref'] = "nextmatch-home.{$this->context['id']}";

		Api\Translation::add_app($context['appname']);
	}

	public function exec($id = null, Etemplate &$etemplate = null)
	{
		if($etemplate == null)
		{
			$etemplate = new Etemplate();
		}
		$etemplate->read('home.favorite');

		$etemplate->set_dom_id($id);

		$content = $this->context + array('nm' => $this->nm_settings);
		$content['header_node'] = "home-index_{$id}_header";
		unset($content['template']);
		$sel_options = $content['sel_options'] ? $content['sel_options'] : array();
		unset($content['sel_options']);
		$etemplate->setElementAttribute('nm', 'template',$this->nm_settings['template']);


		// Always load app's css
		Framework::includeCSS($this->context['appname'], 'app-'.$GLOBALS['egw_info']['user']['preferences']['common']['theme']) ||
			Framework::includeCSS($this->context['appname'],'app');

		// Always load app's javascript, so most actions have a chance of working
		Framework::includeJS('.','app',$this->context['appname']);

		// Set this so app's JS gets initialized
		$old_app = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw_info']['flags']['currentapp'] = $this->context['appname'];

		$etemplate->exec(get_called_class() .'::process',$content,$sel_options);

		$GLOBALS['egw_info']['flags']['currentapp'] = $old_app;
	}

	public static function process($content = array())
	{
		unset($content);    // not used, but required by function signature

		// We need to keep the template going, thanks.
		Etemplate\Widget::setElementAttribute('', '', '');
	}

	public function get_actions()
	{
		return array();
	}

	public function get_type()
	{
		return 'et2-portlet-favorite';
	}

	/**
	 * Get a list of "Add" actions
	 * @return array
	 */
	public function get_add_actions()
	{
		$desc = $this->get_description();
		$actions = array();

		// Add a list of favorites
		if($this->context['appname'] && ($favorites = Framework\Favorites::get_favorites($this->context['appname'])))
		{
			foreach($favorites as $name => $favorite)
			{
				$actions[] = array(
					'id'        => __CLASS__ . $name,
					'caption'   => $name,
					'onExecute' => 'javaScript:app.home.add'
				);
			}
		}
		else
		{
			$actions[] = array(
				'id'              => __CLASS__,
				'caption'         => lang('List'),
				'hint'            => $desc['description'],
				'onExecute'       => 'javaScript:app.home.add',
				'acceptedTypes'   => $this->accept_drop(),
				'allowOnMultiple' => $this->accept_multiple()
			);
		}
		return $actions;
	}

	/**
	 * Some descriptive information about the portlet, so that users can decide if
	 * they want it or not, and for inclusion in lists, hover text, etc.
	 *
	 * These should be already translated, no further Api\Translation will be done.
	 *
	 * @return Array with keys
	 * - displayName: Used in lists
	 * - title: Put in the portlet header
	 * - description: A short description of what this portlet does or displays
	 */
	public function get_description()
	{
		return array(
			'displayName'=> lang('Favorite'),
			'title'=>	$this->title,
			'description'=>	lang('Show all the entries using a favorite')
		);
	}
	/**
	 * Return a list of settings to customize the portlet.
	 *
	 * Settings should be in the same style as for preferences.  It is OK to return an empty array
	 * for no customizable settings.
	 *
	 * These should be already translated, no further Api\Translation will be done.
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

		$favorite = array(
			'label'	=>	lang('Favorite'),
			'name'	=>	'favorite',
			'type'	=>	'select',
			'select_options' => $favorite_list
		);
		if($this->context['favorite'])
		{
			$favorite['type'] = 'select_ro';
		}
		$properties[] = $favorite;
		$properties[] = array(
			'appname' => 'appname'
		);
		return $properties;
	}
}