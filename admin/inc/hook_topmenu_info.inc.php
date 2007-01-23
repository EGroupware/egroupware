<?php
/**************************************************************************\
* eGroupWare - Admin                                                       *
* http://www.egroupware.org                                                *
* This application written by Miles Lott <milos@groupwhere.org>            *
* This file is ported to the topmenu hook by Pim Snel pim@lingewoud.nl     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id: hook_after_navbar.inc.php 20071 2005-12-01 20:21:16Z ralfbecker $ */

/* Check currentapp and API upgrade status */
if(	(isset($GLOBALS['egw_info']['server']['checkappversions']) && $GLOBALS['egw_info']['server']['checkappversions']))
{
	if((isset($GLOBALS['egw_info']['user']['apps']['admin']) &&
		$GLOBALS['egw_info']['user']['apps']['admin']) ||
		$GLOBALS['egw_info']['server']['checkappversions'] == 'All')
	{
		$_returnhtml = array();
		$app_name = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw']->db->query("SELECT app_name,app_version FROM egw_applications WHERE app_name='$app_name' OR app_name='phpgwapi'",__LINE__,__FILE__);
		while($GLOBALS['egw']->db->next_record())
		{
			$_db_version  = $GLOBALS['egw']->db->f('app_version');
			$app_name     = $GLOBALS['egw']->db->f('app_name');
			$_versionfile = $GLOBALS['egw']->common->get_app_dir($app_name) . '/setup/setup.inc.php';
			if(file_exists($_versionfile))
			{
				include($_versionfile);
				$_file_version = $setup_info[$app_name]['version'];
				unset($setup_info);

				if(amorethanb($_file_version, $_db_version))
				{
					if($app_name == 'phpgwapi' )
					{
						$_returnhtml[$app_name] = lang('The API requires an upgrade');
					}
					else
					{
						$_returnhtml[$app_name] = lang('This application requires an upgrade') . ": \n <br />" . lang('Please run setup to become current') . '.' . "\n";
					}
				}
				unset($_file_version);
			}
			unset($_db_version);
			unset($_versionfile);
		}

		if(count($_returnhtml)>0)
		{
		   $icon_newmsg = $GLOBALS['egw']->common->image('admin','navbar18');
		   //$link_inbox = $GLOBALS['egw']->link('/index.php','menuaction=messenger.uimessenger.inbox');
		   $lang_msg = '<p style="text-align: center;">'.implode('<br />',$_returnhtml)."</p>\n";

		   $GLOBALS['egw']->framework->topmenu_info_icon('admin_new_msg',$icon_newmsg,'',true,$lang_msg);
		}

		unset($_returnhtml);
		unset($_html);
	}
}
