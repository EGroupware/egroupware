<?php
	/**************************************************************************\
	* eGroupWare - InfoLog Admin-, Preferences- and SideboxMenu-Hooks          *
	* http://www.eGroupWare.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* -------------------------------------------------------                  *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

/**
 * Class containing admin, preferences and sidebox-menus (used as hooks)
 *
 * @package infolog
 * @author RalfBecker-At-outdoor-training.de
 * @copyright GPL - GNU General Public License
 */
class admin_prefs_sidebox_hooks
{
	function all_hooks($args)
	{
		$appname = 'infolog';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			$file = array(
				'infolog list' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'infolog.uiinfolog.index' )),
				'add' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'infolog.uiinfolog.edit' ))
			);
			display_sidebox($appname,$GLOBALS['egw_info']['apps']['infolog']['title'].' '.lang('Menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
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

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site configuration' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'infolog.uiinfolog.admin' )),
				'Global Categories'  => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.uicategories.index',
					'appname'    => $appname,
					'global_cats'=> True)),
				'Custom fields, typ and status' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'infolog.uicustomfields.edit')),
				'CSV-Import'         => $GLOBALS['egw']->link('/infolog/csv_import.php')
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
}

?>
