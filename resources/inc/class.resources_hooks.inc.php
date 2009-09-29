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
			$file[] = array(
					'text' => lang('resources list'),
					'no_lang' => true,
					'link' => $GLOBALS['egw']->link('/index.php',array('menuaction' => 'resources.ui_resources.index' )),
// 					'icon' =>
			);

			if($this->acl->get_cats(EGW_ACL_ADD))
			{
				$file[] = array(
					'text' => '<a class="textSidebox" href="'.$GLOBALS['egw']->link('/index.php',array('menuaction' => 'resources.ui_resources.edit')).
						'" onclick="window.open(this.href,\'_blank\',\'dependent=yes,width=800,height=600,scrollbars=yes,status=yes\');
						return false;">'.lang('add resource').'</a>',
					'no_lang' => true,
					'link' => false
				);
			}
// 			$file[] = array(
// 					'text' => lang('planer'),
// 					'no_lang' => true,
// 					'link' => $GLOBALS['egw']->link('/index.php',array('menuaction' => 'resources.ui_calviews.planer' )),
// 					'icon' =>
// 			);
			display_sidebox($appname,$title,$file);
		}

/*		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => $GLOBALS['egw']->link('/preferences/preferences.php','appname='.$appname),
				'Grant Access'    => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
				'Edit Categories' => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
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
*/
		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Global Categories'  => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.uicategories.index',
					'appname'    => $appname,
					'global_cats'=> true)),
				'Configure Access Permissions' => $GLOBALS['egw']->link('/index.php',
					'menuaction=resources.ui_acl.acllist'),
				'Custom Fields'=>$GLOBALS['egw']->link('/index.php',
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
			'query'      => 'resources.bo_resources.link_query',
			'title'      => 'resources.bo_resources.link_title',
			'titles'     => 'resources.bo_resources.link_titles',
			'view'       => array(
				'menuaction' => 'resources.ui_resources.show'
			),
			'view_id'    => 'res_id',
			'view_popup' => '850x600',
			'add'        => array(
				'menuaction' => 'resources.ui_resources.edit',
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
			'info' => 'resources.bo_resources.get_calendar_info',// info method, returns array with id, type & name for a given id
			'max_quantity' => 'useable',// if set, key for max. quantity in array returned by info method
			'new_status' => 'resources.bo_resources.get_calendar_new_status',// method returning the status for new items, else 'U' is used
			'type' => 'r',// one char type-identifiy for this resources
			'icon' => 'calicon',//icon
			'participants_header' => lang('resources'), // header of participants from this type
			'cal_sidebox' => array(
				'menu_title' => lang('Select resources'),
				'file' => 'resources.ui_resources.get_calendar_sidebox'
			)
		);
	}
}
