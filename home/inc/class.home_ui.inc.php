<?php
/**
 * EGroupware - Home - user interface
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2013 by Nathan Gray
 * @package home
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * General user interface object of the Home app
 *
 * For the purposes of the Home application, a Portlet is [part of] an application that provides
 * a specific piece of content to be included as part of the Home page.
 * See /home/js/Portlet.js.
 *
 * While Home is not WSRP complient, it does use many of the ideas, and may someday be,
 * if someone wants to fully implement it.
 * @link http://docs.oasis-open.org/wsrp/v2/wsrp-2.0-spec-os-01.html
 */
class home_ui
{

	public $public_functions = array(
		'index' => true
	);

	/**
	 * Main UI - generates the container, and aggregates all
	 * the portlets from the applications
	 */
	public function index($content = array())
	{
		// CSS for Gridster grid layout
		egw_framework::includeCSS('/phpgwapi/js/jquery/gridster/jquery.gridster.css');

		$template = new etemplate('home.index');

		$content = array(
			'portlets' => $this->get_user_portlets($template)
		);
		$template->setElementAttribute('portlets','actions',$this->get_actions());
		//$template->setElementAttribute('portlets[1]','settings',$settings[1]);

		$GLOBALS['egw_info']['flags']['app_header'] = lang('home');
		$GLOBALS['egw_info']['flags']['currentapp'] = 'home';
		$template->exec('home.home_ui.index', $content);
	}

	/**
	 * Get a list of actions on the whole home page.  Each portlet also has
	 * its own actions
	 *
	 * @return array of actions
	 */
	protected function get_actions()
	{
		$actions = array(
			'add' => array(
				'type'	=> 'popup',
				'caption'	=> 'Add',
				'onExecute'	=> 'javaScript:app.home.add',
				'children'	=> $this->get_portlet_list()
			),
			'drop_create'	=> array(
				'type'	=> 'drop',
				//'acceptedTypes'	=>	'apps?'
				'onExecute'	=> 'javaScript:app.home.add'
			)
		);
		
		return $actions;
	}

	/**
	 * Get a list of the user's portlets, and their associated values & settings, for display
	 *
	 * Actual portlet content is provided by each portlet.
	 * @param template etemplate so attributes can be set
	 */
	protected function get_user_portlets(etemplate &$template)
	{
		$portlets = array(
			'Just a hard-coded test',
		);
		$attributes = array();
		$attributes[] = array(
			'title' => 'Has content',
		);

		foreach((array)$GLOBALS['egw_info']['user']['preferences']['home']['portlets'] as $id => $context)
		{
			$content = '';
			$attrs = array();
			$this->get_portlet($context, $content, $attrs);
			$portlets[$id] = $content;
			$attributes[$id] = $attrs;

		}
		foreach($portlets as $index => $portlet)
		{
			$template->setElementAttribute('portlets', $index, (array)$attributes[$index]);
		}
		return $portlets;
	}

	/**
	 * Load the needed info for one portlet, given the context
	 *
	 * @param context Array Settings to customize the portlet instance (size, entry, etc)
	 * 	These are specific values for the portlet's properties.
	 * @param content String HTML fragment to be displayed - will be set by the portlet
	 * @param attributes Array Settings that can be customized on a per-portlet basis - will be set
	 * @return home_portlet The portlet object that created the content
	 */
	protected function get_portlet(&$context, &$content, &$attributes)
	{
		if(!$context['class']) $context['class'] = 'home_link_portlet';

		$classname = $context['class'];
		$portlet = new $classname($context);

		$desc = $portlet->get_description();
		$content = $portlet->get_content();

		// Exclude common attributes changed through UI
		$settings = $portlet->get_properties() + $context;
		foreach(home_portlet::$common_attributes as $attr)
		{
			unset($settings[$attr]);
		}
		$attributes = array(
			'title' => $desc['title'],
			'settings' => $settings,
			'actions' => $portlet->get_actions(),
		);

		// Set any provided common attributes (size, etc)
		foreach(home_portlet::$common_attributes as $name)
		{
			if(array_key_exists($name, $context))
			{
				$attributes[$name] = $context[$name];
			}
		}
		return $portlet;
	}

	/**
	 * Get a list of all available portlets for add menu
	 */
	protected function get_portlet_list()
	{
		$list = array();

		$list = egw_cache::getTree('home', 'portlet_classes', function() {
			$list = array();
			$classes = array();

			// Look through all known classes for portlets - for now, they need 'portlet' in the file name
			foreach($GLOBALS['egw_info']['apps'] as $appname => $app)
			{
				if(in_array($appname, array('phpgwapi', 'felamimail'))) continue;
				$files = (array)@scandir(EGW_SERVER_ROOT . '/'.$appname .'/inc/');
				if(!$files) continue;

				foreach($files as $entry)
				{
					if (!in_array($entry, array('.','..')) && substr($entry,-8) == '.inc.php' && strpos($entry,'portlet'))
					{
						list(,$classname) = explode('.', $entry);
						if(class_exists($classname) &&
							in_array('home_portlet', class_parents($classname, false)))
						{
							$classes[$appname][] = $classname;
						}
					}
				}

				if(!$classes[$appname]) continue;

				// Build 'Add' actions for each discovered portlet.
				// Portlets from other apps go in sub-actions
				$add_to =& $list;
				if($classes[$appname] && $appname != 'home')
				{
					$list[$appname] = array(
						'caption' => lang($appname),
					);
					$add_to =& $list[$appname]['children'];
				}
				foreach($classes[$appname] as $portlet)
				{
					$instance = new $portlet();
					$desc = $instance->get_description();

					$add_to[$portlet] = array(
						'id'	=> $portlet,
						'type'	=> 'popup',
						'caption' => $desc['displayName'],
						'hint' => $desc['description'],
						'onExecute' => 'javaScript:app.home.add'
					);
				}
			}
			
			return $list;
		}, array(), 60);

		return $list;
	}

	/**
	 * Update the settings for a particular portlet, and give updated content
	 *
	 * @param portlet_id String Unique ID (for the user) for a portlet
	 * @param values Array List of property => value pairs
	 *
	 */
	public function ajax_set_properties($portlet_id, $attributes, $values)
	{
		if(!$attributes)
		{
			$attributes = array();
		}
		$response = egw_json_response::get();
		if ($GLOBALS['egw_info']['user']['apps']['preferences'])
		{
			$prefs = $GLOBALS['egw']->preferences->read_repository();
			$portlets = (array)$prefs['home']['portlets'];
			if($values == '~remove~')
			{
				unset($portlets[$portlet_id]);
				// Already removed client side
			}
			else
			{
				// Get portlet settings, and merge new with old
				$content = '';
				$portlet = $this->get_portlet(array_merge((array)$attributes, $values), $content, $attributes);
				$context = array('class' => get_class($portlet));
				foreach($portlet->get_properties() as $property)
				{
					if($values[$property['name']])
					{
						$context[$property['name']] = $values[$property['name']];
					}
					elseif($portlets[$portlet_id][$property['name']])
					{
						$context[$property['name']] = $portlets[$portlet_id][$property['name']];
					}
				}

				// Update client side
				$update = array('content' => $content, 'attributes' => $attributes);

				// New portlet?  Flag going straight to edit mode
				if(!array_key_exists($portlet_id,$portlets) && $attributes['settings'])
				{
					$update['edit_settings'] = true;
				}
				$response->data($update);

				// Store for preference update
				$portlets[$portlet_id] = $context;
			}

			// Save updated preferences
			$GLOBALS['egw']->preferences->add('home', 'portlets', $portlets);
			$GLOBALS['egw']->preferences->save_repository(True);
		}
	}
}
