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

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;

/**
 * General hook object for resources
 * It encapsulats all the diffent hook methods
 * @package resources
 */
class resources_hooks
{
	function admin_prefs_sidebox($args)
	{
		$this->acl = new resources_acl_bo();

		$appname = 'resources';
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'sidebox_menu')
		{
			// Magic etemplate2 favorites menu (from nextmatch widget)
			display_sidebox($appname, lang('Favorites'), Framework\Favorites::list_favorites($appname, 'nextmatch-resources.show.rows-favorite'));

			$title = $GLOBALS['egw_info']['apps']['resources']['title'].' '.lang('Menu');
			$file = array(
				'Resources list' => Egw::link('/index.php',array(
				'menuaction' => 'resources.resources_ui.index',
				'ajax' => 'true')),
			);
			if($this->acl->get_cats(Acl::ADD))
			{
				$file['Add resource'] = "javascript:egw_openWindowCentered2('".Egw::link('/index.php',array(
						'menuaction' => 'resources.resources_ui.edit',
						'accessory_of' => -1
					),false)."','_blank',800,600,'yes')";
			}
			$file['Placeholders'] = Egw::link('/index.php','menuaction=resources.resources_merge.show_replacements');
			display_sidebox($appname,$title,$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = array(
				'Site Configuration'           => Egw::link('/index.php', 'menuaction=admin.admin_config.index&appname=' . $appname . '&ajax=true'),
				'Global Categories'            => Egw::link('/index.php', array(
					'menuaction'  => 'admin.admin_categories.index',
					'appname'     => $appname,
					'global_cats' => true,
					'ajax'        => 'true'
				)),
				'Configure Access Permissions' => Egw::link(
					'/index.php',
					'menuaction=resources.resources_acl_ui.index&ajax=true'
				),
				'Custom Fields'                => egw::link(
					'/index.php',
					'menuaction=resources.resources_customfields.index&appname=resources&ajax=true'
				),
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
				'menuaction' => 'resources.resources_ui.edit'
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
			'search' => 'resources_bo::calendar_search',// method to use for the selection of resources, otherwise Link system is used
			'info' => 'resources.resources_bo.get_calendar_info',// info method, returns array with id, type & name for a given id
			'max_quantity' => 'useable',// if set, key for max. quantity in array returned by info method
			'new_status' => 'resources.resources_bo.get_calendar_new_status',// method returning the status for new items, else 'U' is used
			'type' => 'r',// one char type-identifiy for this resources
			'icon' => 'calicon',//icon
			'participants_header' => lang('resources'), // header of participants from this type
			'check_invite' => 'resources_acl_bo::check_calendar_invite' // Check that the current user is allowed to invite the givent resource
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
		$cat = Api\Categories::read($args['cat_id']);

		if(!$cat) return; // Can't find current cat?

		if($cat['parent'] == 0)
		{
			// No parent, try the default cat from setup
			$categories = new Api\Categories('', 'resources');
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
					resources_acl_bo::set_rights($new_cat_id, array($admin), array($admin), array($admin), array($admin),array($admin));
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
			if(is_array($resource))
			{
				$resource['cat_id'] = $new_cat_id;
				$bo->save($resource);
			}
		}
	}

	/**
	 * Hook to tell framework we use only global Api\Categories (return link data in that case and false otherwise)
	 *
	 * @param string|array $data hook-data or location
	 * @return boolean|array
	 */
	public static function categories($data)
	{
		if($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			return array(
				'menuaction'  => 'admin.admin_categories.index',
				'appname'     => $appname,
				'global_cats' => true
			);
		}
		return false;
	}

	/**
	 * populates $settings for the Api\Preferences
	 *
	 * @return array
	 */
	static function settings()
	{
		$settings[] = array(
			'type'    => 'section',
			'title'   => lang('Data exchange settings'),
			'no_lang' => true,
			'xmlrpc'  => False,
			'admin'   => False
		);

		// Merge print
		if($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$merge = new resources_merge();
			$settings += $merge->merge_preferences();
		}
		return $settings;
	}
}
