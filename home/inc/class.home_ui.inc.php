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

		$template = new etemplate_new('home.index');

		// Get a list of portlets
		$content = array(
			'portlets' => $this->get_user_portlets($template)
		);
		$template->setElementAttribute('home.index','actions',$this->get_actions());
		//$template->setElementAttribute('portlets[1]','settings',$settings[1]);

		$GLOBALS['egw_info']['flags']['app_header'] = lang('home');
		$GLOBALS['egw_info']['flags']['currentapp'] = 'home';

		$template->exec('home.home_ui.index', $content);

		// Now run the portlets themselves
		foreach($content['portlets'] as $portlet => $p_data)
		{
			$id = $p_data['id'];

			if(!$id) continue;
			$portlet = $this->get_portlet($id, $p_data, $content, $attrs, true);
		}
	}

	/**
	 * Get a list of actions on the whole home page.  Each portlet also has
	 * its own actions
	 *
	 * @return array of actions
	 */
	protected function get_actions()
	{
		$portlets = $this->get_portlet_list();
		$add_portlets = $portlets;
		foreach($add_portlets as $id => &$add)
		{
			$add['id'] = 'add_' . $id;
			$add['class'] = $id;
		}
		$actions = array(
			'add' => array(
				'type'	=> 'popup',
				'caption'	=> 'Add',
				'onExecute'	=> 'javaScript:app.home.add',
				'children'	=> $add_portlets
			)
		);

		// Add all known portlets as drop actions too.  If there are multiple matches, there will be a menu
		$drop_execute = 'javaScript:app.home.add_from_drop';
		foreach($portlets as $app => &$children)
		{
			// Home portlets - uses link system, so all apps that support that are accepted
			if(!$children['children'])
			{
				$children['class'] = $app;
				$children['onExecute'] = $drop_execute;
				$children['acceptedTypes'] = array('file','link');
				$children['type'] = 'drop';
				$actions["drop_$app"] = $children;
			}
			else
			{
				foreach($children as $portlet => $app_portlets)
				{
					$app_portlets['onExecute'] = $drop_execute;
					$app_portlet['acceptedTypes'] = $app;
					$app_portlet['type'] = 'drop';
					$actions["drop_$portlet"] = $app_portlets;
				}
			}
		}
		
		return $actions;
	}

	/**
	 * Get a list of the user's portlets, and their associated values & settings, for display
	 *
	 * Actual portlet content is provided by each portlet.
	 * @param template etemplate so attributes can be set
	 */
	protected function get_user_portlets(etemplate_new &$template)
	{
		$portlets = array();

		foreach((array)$GLOBALS['egw_info']['user']['preferences']['home']['portlets'] as $id => $context)
		{
			if(!$id || in_array($id, array_keys($GLOBALS['egw_info']['user']['apps']))) continue;

			$classname = $context['class'];
			$portlet = new $classname($context);
			$desc = $portlet->get_description();
			$portlet_content = array(
				'id'	=>	$id
			) + $desc + $context;


			// Get settings
			// Exclude common attributes changed through UI and settings lacking a type
			$settings = $portlet->get_properties();
			foreach($settings as $key => $setting)
			{
				if(is_array($setting) && !array_key_exists('type',$setting)) unset($settings[$key]);
			}
			$settings += $context;
			foreach(home_portlet::$common_attributes as $attr)
			{
				unset($settings[$attr]);
			}
			$portlet_content['settings'] = $settings;

			// Set actions
			// Must be after settings so actions can take settings into account
			$template->setElementAttribute("portlets[" . count($portlets) . "[$id]", 'actions', $portlet->get_actions());

			$portlets[] = $portlet_content;
		}
		
		// Add in legacy HTML home bits
		// TODO: DOM IDs still collide
		//$this->get_legacy_portlets($portlets, $attributes);

		return $portlets;
	}

	/**
	 * Load the needed info for one portlet, given the context
	 *
	 * @param context Array Settings to customize the portlet instance (size, entry, etc)
	 * 	These are specific values for the portlet's properties.
	 * @param content String HTML fragment to be displayed - will be set by the portlet
	 * @param attributes Array Settings that can be customized on a per-portlet basis - will be set
	 * @param full_exec Boolean If set, the portlet etemplates should use mode 2, if not use mode -1
	 * @return home_portlet The portlet object that created the content
	 */
	protected function get_portlet($id, &$context, &$content, &$attributes, $full_exec = false)
	{
		if(!$context['class']) $context['class'] = 'home_link_portlet';

		// This should be set already, but just in case the execution path
		// is different from normal...
		if(egw_json_response::isJSONResponse())
		{
			$GLOBALS['egw']->framework->response = egw_json_response::get();
		}

		$classname = $context['class'];
		$portlet = new $classname($context, $full_exec);

		$desc = $portlet->get_description();

		// Pre-set up etemplate so it only needs done once
		$dom_id = 'home-index_'.$id.'_content';

		$etemplate = new etemplate_new();
		
		// Exclude common attributes changed through UI and settings lacking a type
		$settings = $portlet->get_properties();
		foreach($settings as $key => $setting)
		{
			if(is_array($setting) && !array_key_exists('type',$setting)) unset($settings[$key]);
		}
		$settings += $context;
		foreach(home_portlet::$common_attributes as $attr)
		{
			unset($settings[$attr]);
		}

		$attributes = array(
			'title' => $desc['title'],
			'color' => $settings['color'],
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
		foreach($attributes as $attr => $value)
		{
			$etemplate->setElementAttribute($id, $attr, $value);
		}
		if($full_exec)
		{
			$content = $portlet->exec($id, $etemplate, $full_exec ? 2 : -1);
		}

		return $portlet;
	}
	
	/**
	 * Get a list of pre-etemplate2 home hook content according to the individual
	 * application preferences.  If we find a preference that indicates the user
	 * wants some content, we make a portlet for that app using the home_legacy_portlet,
	 * which fetches content from the home hook.
	 */
	protected function get_legacy_portlets(&$content, &$attributes)
	{
		$sorted_apps = array_keys($GLOBALS['egw_info']['user']['apps']);
		$portal_oldvarnames = array('mainscreen_showevents', 'homeShowEvents','homeShowLatest','mainscreen_showmail','mainscreen_showbirthdays','mainscreen_show_new_updated', 'homepage_display');
		
		foreach($sorted_apps as $appname)
		{
			// If there's already [new] settings, or no preference, skip it
			if($content[$appname]) continue;
			$no_pref = true;
			foreach($portal_oldvarnames as $varcheck)
			{
				$thisd = $GLOBALS['egw_info']['user']['preferences'][$appname][$varcheck];
				if(!(int)$thisd && $thisd)
				{
					$no_pref = false;
					break;
				}
			}
			if($no_pref || !$GLOBALS['egw']->hooks->hook_exists('home', $appname))
			{
				continue;
			}
			$context = array(
				'class' => 'home_legacy_portlet', 'app' => $appname,
				'width' => 8, 'height' => 3
			);
			$_content = '';
			$_attributes = array();
			$this->get_portlet($appname, $context, $_content, $_attributes);
			if(trim($_content))
			{
				$content[$appname] = $_content;
				$attributes[$appname] = $_attributes;
			}
		}
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
					if (!in_array($entry, array('.','..','class.home_legacy_portlet.inc.php')) && substr($entry,-8) == '.inc.php' && strpos($entry,'portlet'))
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
						'caption' => $desc['displayName'],
						'hint' => $desc['description'],
						'onExecute' => 'javaScript:app.home.add',
						'allowOnMultiple' => $instance->accept_multiple()
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
				$context = $values+(array)$portlets[$portlet_id]; //array('class'=>$attributes['class']);

				// Handle add IDs
				$classname =& $context['class'];
				if(strpos($classname,'add_') == 0 && !class_exists($classname))
				{
					$add = true;
					$classname = substr($classname, 4);
				}
				$full_exec = false;
				$portlet = $this->get_portlet($portlet_id, $context, $content, $attributes);

				$context['class'] = get_class($portlet);
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
				$update = array('attributes' => $attributes);

				// New portlet?  Flag going straight to edit mode
				if($add)
				{
					$update['edit_settings'] = true;
				}
				// Send this back to the portlet widget
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
