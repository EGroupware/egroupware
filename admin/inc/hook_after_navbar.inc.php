<?php
	/**************************************************************************\
	* phpGroupWare - Admin                                                     *
	* http://www.phpgroupware.org                                              *
	* This application written by Miles Lott <milosch@phpgroupware.org>        *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/* Check currentapp and API upgrade status */

	if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'home' &&
		$GLOBALS['phpgw_info']['flags']['currentapp'] != 'welcome' &&
		(isset($GLOBALS['phpgw_info']['server']['checkappversions']) &&
		$GLOBALS['phpgw_info']['server']['checkappversions']))
	{
		if ((isset($GLOBALS['phpgw_info']['user']['apps']['admin']) &&
			$GLOBALS['phpgw_info']['user']['apps']['admin']) ||
			$GLOBALS['phpgw_info']['server']['checkappversions'] == 'All')
		{
			$_current = False;
			$app_name = $GLOBALS['phpgw_info']['flags']['currentapp'];
			$GLOBALS['phpgw']->db->query("SELECT app_name,app_version FROM phpgw_applications WHERE app_name='$app_name' OR app_name='phpgwapi'",__LINE__,__FILE__);
			while($GLOBALS['phpgw']->db->next_record())
			{
				$_db_version  = $GLOBALS['phpgw']->db->f('app_version');
				$app_name     = $GLOBALS['phpgw']->db->f('app_name');
				$_versionfile = $GLOBALS['phpgw']->common->get_app_dir($app_name) . '/setup/setup.inc.php';
				if(file_exists($_versionfile))
				{
					include($_versionfile);
					/* echo '<br>' . $_versionfile . ','; */
					$_file_version = $setup_info[$app_name]['version'];
					$_app_title    = $setup_info[$app_name]['title'];
					unset($setup_info);

					$api_str = '<br>' . lang('The API requires an upgrade');
					/* echo $app_name . ',' . $_db_version . ',' . $_file_version; */
					if(!$GLOBALS['phpgw']->common->cmp_version_long($_db_version,$_file_version))
					{
						$_current = True;
						if($app_name == 'phpgwapi')
						{
							$api_str = '<br>' . lang('The API is current');
						}
					}
					unset($_file_version);
					unset($_app_title);
				}
				unset($_db_version);
				unset($_versionfile);
			}
			if(!$_current)
			{
				echo '<center>';
				echo $api_str;
				echo '<br>' . lang('This application requires an upgrade') . ':' . "\n";
				echo '<br>' . lang('Please run setup to become current') . '.' . "\n";
				echo '</center>';
			}
			else
			{
				echo '<center>';
				echo $api_str;
				echo '<br>' . lang('This application is current') . "\n";
				echo '</center>';
			}
		}
	}
