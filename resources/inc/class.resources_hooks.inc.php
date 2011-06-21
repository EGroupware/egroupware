<?php
/**
 * eGroupWare - resources
 * General hook object for resources
 * It encapsulats all the diffent hook methods
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @version $Id$
 */

/**
 * General hook object for resources
 * It encapsulats all the diffent hook methods
 * @package resources
 */
class resources_hooks
{
	function admin_prefs_sidebox($args)
	{
		$this->acl =& CreateObject('resources.bo_acl');

		$appname = 'resources';
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'sidebox_menu')
		{
			$title = $GLOBALS['egw_info']['apps']['resources']['title'].' '.lang('Menu');
			$file = array(
				'Resources list' => egw::link('/index.php',array('menuaction' => 'resources.resources_ui.index' )),
			);
			if($this->acl->get_cats(EGW_ACL_ADD))
			{
				$file['Add resource'] = "javascript:egw_openWindowCentered2('".egw::link('/index.php',array(
						'menuaction' => 'resources.resources_ui.edit',
					),false)."','_blank',800,600,'yes')";
			}
			display_sidebox($appname,$title,$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin'
			&& $GLOBALS['egw_info']['user']['apps']['importexport'])	// Only one preference right now, need this to prevent errors
		{
			$file = array(
				'Preferences'     => egw::link('/preferences/preferences.php','appname='.$appname),
			// Categories control access, not regular ACL system
			//	'Grant Access'    => egw::link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
			//	'Edit Categories' => egw::link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Global Categories'  => egw::link('/index.php',array(
					'menuaction' => 'admin.admin_categories.index',
					'appname'    => $appname,
					'global_cats'=> true)),
				'Configure Access Permissions' => egw::link('/index.php',
					'menuaction=resources.ui_acl.acllist'),
				'Custom Fields'=>egw::link('/index.php',
					'menuaction=admin.customfields.edit&appname=resources'),
			);
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}

	function search_link($args)
	{
		return array(
			'query'      => 'resources.resources_bo.link_query',
			'title'      => 'resources.resources_bo.link_title',
			'titles'     => 'resources.resources_bo.link_titles',
			'view'       => array(
				'menuaction' => 'resources.resources_ui.show'
			),
			'view_id'    => 'res_id',
			'view_popup' => '850x600',
			'view_list'  => 'resources.resources_ui.index',
			'add'        => array(
				'menuaction' => 'resources.resources_ui.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'add_popup'  => '800x600',
			'find_extra' => array('name_preg' => '/^(?(?=^.picture.jpg$)|.+)$/'),	// remove pictures from regular attachment list
		);
	}

	function calendar_resources($args)
	{
		return array(
			'widget' => 'resources_select',// widget to use for the selection of resources
			'info' => 'resources.resources_bo.get_calendar_info',// info method, returns array with id, type & name for a given id
			'max_quantity' => 'useable',// if set, key for max. quantity in array returned by info method
			'new_status' => 'resources.resources_bo.get_calendar_new_status',// method returning the status for new items, else 'U' is used
			'type' => 'r',// one char type-identifiy for this resources
			'icon' => 'calicon',//icon
			'participants_header' => lang('resources'), // header of participants from this type
			'cal_sidebox' => array(
				'menu_title' => lang('Select resources'),
				'file' => 'resources.resources_ui.get_calendar_sidebox'
			)
		);
	}

	/**
	 * Handle deleted category
	 *
	 * Resources' ACL _requires_ a category.
	 * Moves all resources to parent, if it exists.  If it doesn't, another category is created.
	 */
	function delete_category($args) 
	{
		$cat = categories::read($args['cat_id']);

		if(!$cat) return; // Can't find current cat?

		if($cat['parent'] == 0)
		{
			// No parent, try the default cat from setup
			$categories = new categories('', 'resources');
			$default = $categories->name2id('General resources');
			if($default)
			{
				$new_cat_id = $default;
			}
			else
			{
				// Default missing, look for 'No category'
				$new_cat_id = $categories->name2id('No category');
				if($new_cat_id == 0) {
					// No category not there, add it
					$new_cat_id = $categories->add(array(
						'name'		=> 'No category',
						'description'	=> 'This category has been added to rescue resources whose category was deleted.',
						'parent'	=> 0
					));
					$admin = -2;
					ExecMethod2('resources.bo_acl.set_rights', $new_cat_id, array($admin), array($admin), array($admin), array($admin),array($admin));
				}
			}
		}
		else
		{
			$new_cat_id = $cat['parent'];
		}

		// Get any resources affected
		$query = array('filter' => $args['cat_id']);
		$bo = new resources_bo();
		$bo->get_rows($query, $resources, $readonly);
		foreach($resources as $resource)
		{
			$resource['cat_id'] = $new_cat_id;
			$bo->save($resource);
		}
	}

	/**
	 * populates $settings for the preferences
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	static function settings($hook_data)
	{
		$settings = array();

		if ($GLOBALS['egw_info']['user']['apps']['importexport'])
		{
			$definitions = new importexport_definitions_bo(array(
				'type' => 'export',
				'application' => 'resources'
			));
			$options = array();
			$default_def = 'export-resources';
			foreach ((array)$definitions->get_definitions() as $identifier)
			{
				try
				{
					$definition = new importexport_definition($identifier);
				}
				catch (Exception $e)
				{
					// permission error
					continue;
				}
				if ($title = $definition->get_title())
				{
					$options[$title] = $title;
				}
				unset($definition);
			}
			$settings['nextmatch-export-definition'] = array(
				'type'   => 'select',
				'values' => $options,
				'label'  => 'Export definitition to use for nextmatch export',
				'name'   => 'nextmatch-export-definition',
				'help'   => lang('If you specify an export definition, it will be used when you export'),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> isset($options[$default_def]) ? $default_def : false,
			);
		}
		return $settings;
	}
}
