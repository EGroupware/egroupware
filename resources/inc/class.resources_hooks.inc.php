<?php
	/**************************************************************************\
	* eGroupWare - resources hooks                                             *
	* http://www.eGroupWare.org                                                *
	* Originally written by Ralf Becker <RalfBecker@outdoor-training.de>       *
	* Changes for resources by Cornelius Weiﬂ <egw@von-und-zu-weiss.de>        *
	*                                                                          *
	* -------------------------------------------------------                  *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

class resources_hooks
{
	function admin_prefs_sidebox($args)
	{
		$appname = 'resources';
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'sidebox_menu')
		{
			$file = array(
				'resources list' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'resources.ui_resources.index' )),
				/*'add' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'resources.ui_resources.edit' ))*/
			);
			display_sidebox($appname,$GLOBALS['egw_info']['apps']['resources']['title'].' '.lang('Menu'),$file);
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
				'Site configuration' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'resources.ui_resources.admin' )),
				'Global Categories'  => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.uicategories.index',
					'appname'    => $appname,
					'global_cats'=> True)),
				'Configure Access Permissions' => $GLOBALS['egw']->link('/index.php',
					'menuaction=resources.ui_acl.acllist')
// 				'Custom fields, typ and status' => $GLOBALS['egw']->link('/index.php',array(
// 					'menuaction' => 'infolog.uicustomfields.edit')),
// 				'CSV-Import'         => $GLOBALS['egw']->link('/infolog/csv_import.php')
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
		$appname = 'resources';
		return array(	'query' => 'resources.bo_resources.link_query',
				'title' => 'resources.bo_resources.link_title',
				'view' => array('menuaction' => 'resources.ui_resources.view'),
				'view_id' => 'id'
		);
	}
}

?>
