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

		$GLOBALS['egw_info']['flags']['app_header'] = lang('home');
		$GLOBALS['egw_info']['flags']['currentapp'] = 'home';

		// Main screen message
		translation::add_app('mainscreen');
		$greeting = translation::translate('mainscreen_message',false,'');

		if($greeting == 'mainscreen_message'|| empty($greeting))
		{
			translation::add_app('mainscreen','en');    // trying the en one
			$greeting = translation::translate('mainscreen_message',false,'');
		}
		if(!($greeting == 'mainscreen_message'|| empty($greeting)))
		{
			$content['mainscreen_message'] = $greeting;
		}

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
		$change_for_add = function(&$add_portlets) use (&$change_for_add)
		{
			foreach($add_portlets as $id => &$add)
			{
				if(is_array($add['children']))
				{
					$change_for_add($add['children']);
				}
				if($id && !$add['children'])
				{
					$add['id'] = 'add_' . $id;
					$add['class'] = 'add_'.$id;
				}
			}
		};
		$change_for_add($add_portlets);
		$actions = array(
			'add' => array(
				'type'	=> 'popup',
				'caption'	=> 'Add',
				'onExecute'	=> 'javaScript:app.home.add',
				'children'	=> $add_portlets
			),
			// Favorites are sortable which needs special handling,
			// handled directly through jQuery
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
				$children['type'] = 'drop';
				$actions["drop_$app"] = $children;
			}
			else
			{
				foreach($children['children'] as $portlet => $app_portlet)
				{
					if(!is_array($app_portlet)) continue;
					$app_portlet['class'] = $portlet;
					$app_portlet['id'] = 'drop_' . $app_portlet['id'];
					$app_portlet['onExecute'] = $drop_execute;
					$app_portlet['acceptedTypes'] = $app;
					$app_portlet['type'] = 'drop';
					$actions["drop_$portlet"] = $app_portlet;
				}
			}
		}

		// For admins, add the ability to set current home as a default
		self::create_default_actions($actions);
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

		foreach((array)$GLOBALS['egw_info']['user']['preferences']['home']as $id => $context)
		{
			if(strpos($id,'portlet_') !== 0 || // Not a portlet
				in_array($id, array_keys($GLOBALS['egw_info']['user']['apps'])) || // Some other app put it's pref in here
				!is_array($context) // Not a valid portlet (probably user deleted a default)
			) continue;

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
			$actions = $portlet->get_actions();

			// Add in default for admins
			self::create_default_actions($actions, $id);
			
			$template->setElementAttribute("portlets[" . count($portlets) . "[$id]", 'actions', $actions);

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
		// Add in default settings
		self::create_default_actions($attributes['actions'], $id);

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
				if($thisd)
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
			
			// Ignore some problem files and base classes that shouldn't be options
			$ignore = array(
				'.','..',
				'class.home_portlet.inc.php',
				'class.home_legacy_portlet.inc.php',
				'class.home_favorite_portlet.inc.php'
			);
			// Look through all known classes for portlets - for now, they need 'portlet' in the file name
			foreach($GLOBALS['egw_info']['apps'] as $appname => $app)
			{
				if(in_array($appname, array('phpgwapi', 'felamimail'))) continue;
				$files = (array)@scandir(EGW_SERVER_ROOT . '/'.$appname .'/inc/');
				if(!$files) continue;

				foreach($files as $entry)
				{
					if (!in_array($entry, $ignore) && substr($entry,-8) == '.inc.php' && strpos($entry,'portlet'))
					{
						list(,$classname) = explode('.', $entry);
						if(class_exists($classname) &&
							in_array('home_portlet', class_parents($classname, false)))
						{
							$classes[$appname][] = $classname;
						}
						else
						{
							error_log("Could not load $classname from $entry");
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
						'acceptedTypes' => $instance->accept_drop(),
						'allowOnMultiple' => $instance->accept_multiple()
					);
				}
			}
			
			return $list;
		}, array(), 60);
		
		return $list;
	}

	/**
	 * Create an action to set the portlet as default
	 *
	 * @param Array $actions Existing action list
	 * @param String $portlet_id Provide the ID to have the checkbox set
	 */
	protected static function create_default_actions(&$actions, $portlet_id = null)
	{
		if($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$actions['add_default'] = array(
				'type'		=> 'popup',
				'caption'	=> 'Set as default',
				'onExecute'	=> 'javaScript:app.home.set_default',
				'group'		=> 'Admins',
				'icon'		=> 'preference'
			);
			// Customize for the given portlet
			if($portlet_id !== null)
			{
				$portlet = $GLOBALS['egw_info']['user']['preferences']['home'][$portlet_id];

				foreach(array('forced','group','default') as $location)
				{
					$loc = $GLOBALS['egw']->preferences->$location;
					
					if($loc['home'][$portlet_id])
					{
						// If it's forced, no point in setting default
						if($location == 'forced')
						{
							unset($actions['add_default']);
						}
						// If it's a group, we'd like to know which
						if($location == 'group')
						{
							$options = array('account_type' => $type);
							$groups = accounts::link_query('',$options);
							foreach($groups as $gid => $name)
							{
								$prefs = new preferences($gid);
								$prefs->read_repository();
								if (isset($prefs->user['home'][$portlet_id]))
								{
									$location = $gid;
									break;
								}
							}
						}
						$actions['remove_default_'.$location] = array(
							'type'		=> 'popup',
							'caption'	=> lang('Remove default %1',is_numeric($location) ? accounts::id2name($location) : $location),
							'onExecute'	=> 'javaScript:app.home.set_default',
							'group'		=> 'Admins',
							'portlet_group' => $location
						);
					}
				}
			}
		}
		
		// Change action for forced
		if($portlet_id && $GLOBALS['egw']->preferences->forced['home'][$portlet_id])
		{
			// No one can remove it
			$actions['remove_portlet']['enabled'] = false;
			$actions['remove_portlet']['caption'] .= ' ('.lang('Forced') .')';

			// Non-admins can't edit it
			if($actions['edit_settings'] && !$GLOBALS['egw_info']['user']['apps']['admin'])
			{
				$actions['edit_settings']['enabled'] = false;
				$actions['edit_settings']['visible'] = false;
			}
		}
	}

	/**
	 * Update the settings for a particular portlet, and give updated content
	 *
	 * @param portlet_id String Unique ID (for the user) for a portlet
	 * @param values Array List of property => value pairs
	 * @param boolean|int|String $group False for current user, ID of the group to create the favorite for, or 'all' for all users
	 *
	 */
	public function ajax_set_properties($portlet_id, $attributes, $values, $group = false)
	{
		if(!$attributes)
		{
			$attributes = array();
		}
		
		if(!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			if($group == 'forced')
			{
				// Quietly reject
				return;
			}
			// Not an admin, can only override.
			$group = false;
		}
		if($group && $GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$prefs = new preferences(is_numeric($group) ? $group : $GLOBALS['egw_info']['user']['account_id']);
		}
		else
		{
			$prefs = $GLOBALS['egw']->preferences;
		}
		$type = is_numeric($group) ? "user" : $group;

		$prefs->read_repository();

		$response = egw_json_response::get();

		if($values =='~reload~')
		{
			$full_exec = true;
			$values = array();
		}
		if($values == '~remove~')
		{
			// Already removed client side, needs to be removed permanently
			$default = $prefs->default_prefs('home',$portlet_id) || $prefs->group['home'][$portlet_id];

			if($default)
			{
				// Can't delete forced - not a UI option though
				if(!$GLOBALS['egw']->preferences->forced['home'][$portlet_id])
				{
					// Set a flag to override default instead of just delete
					$GLOBALS['egw']->preferences->add('home',$portlet_id, 'deleted');
					$GLOBALS['egw']->preferences->save_repository();
				}
			}
			else
			{
				$prefs->delete('home', $portlet_id);
			}
		}
		else
		{
			$portlets = $prefs->read();
			$portlets = $portlets['home'];

			// Remove some constant stuff that winds up here
			unset($values['edit_template']);
			unset($values['readonly']);
			unset($values['disabled']);unset($values['no_lang']);
			unset($values['actions']);
			unset($values['statustext']);
			unset($values['type']);unset($values['label']);unset($values['status']);
			unset($values['value']);unset($values['align']);

			// Get portlet settings, and merge new with old
			$context = $values+(array)$portlets[$portlet_id];
			$context['group'] = $group;



			// Handle add IDs
			$classname =& $context['class'];
			if(strpos($classname,'add_') == 0 && !class_exists($classname))
			{
				$add = true;
				$classname = substr($classname, 4);
			}
			$portlet = $this->get_portlet($portlet_id, $context, $content, $attributes, $full_exec);

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
			$prefs->add('home', $portlet_id, $context, $type);
		}

		// Save updated preferences
		$prefs->save_repository(True,$type);
	}

	/**
	 * Set the selected portlets as default for a group
	 *
	 * @param String $action 'add' or 'delete'
	 * @param String[] $portlet_ids
	 * @param int|String $group Group ID or 'default' or 'forced'
	 */
	public static function ajax_set_default($action, $portlet_ids, $group)
	{
		// Admins only
		if(!$GLOBALS['egw_info']['apps']['admin']) return;

		// Load the appropriate group
		if($group)
		{
			$prefs = new preferences(is_numeric($group) ? $group : $GLOBALS['egw_info']['user']['account_id']);
		}
		else
		{
			$prefs = $GLOBALS['egw']->preferences;
		}
		$prefs->read_repository();

		$type = is_numeric($group) ? "user" : $group;

		if($action == 'add')
		{
			foreach($portlet_ids as $id)
			{
				egw_json_response::get()->call('egw.message', lang("Set default"));
				// Current user is setting the default, copy their settings
				$settings = $GLOBALS['egw_info']['user']['preferences']['home'][$id];
				$settings['group'] = $group;
				$prefs->add('home',$id,$settings,$type);

				// Remove user's copy
				$GLOBALS['egw']->preferences->delete('home',$id);
				$GLOBALS['egw']->preferences->save_repository(true);
			}
		}
		else if ($action == "delete")
		{
			foreach($portlet_ids as $id)
			{
				egw_json_response::get()->call('egw.message', lang("Removed default"));
				error_log("Clearing $type $group default $id");
				$result = $prefs->delete('home',$id, $type);
			}
		}
		$prefs->save_repository(false,$type);

		// Update preferences client side for consistency
		$prefs = $GLOBALS['egw']->preferences;
		$pref = $prefs->read_repository();
		egw_json_response::get()->call('egw.set_preferences', (array)$pref['home'], 'home');
	}
}
