<?php
	/***************************************************************************\
	 * eGroupWare - account administration                                      *
	 * http://www.egroupware.org                                                *
	 * --------------------------------------------                             *
	 *  This program is free software; you can redistribute it and/or modify it *
	 *  under the terms of the GNU General Public License as published by the   *
	 *  Free Software Foundation; either version 2 of the License, or (at your  *
	 *  option) any later version.                                              *
	 \**************************************************************************/

	/* $Id$ */

	class soaccounts
	{
		function soaccounts()
		{
		}

		function add_user($userData)
		{
			$userData['account_expires'] = $userData['expires'];

			if($userData['email'] != "")
			{
				$userData['account_email'] = $userData['email'];
			}
			if ($userData['mustchangepassword'] == 1) $userData['account_lastpwd_change']=0;
			if (!($userData['account_id'] = $GLOBALS['egw']->accounts->create($userData)))
			{
				return false;
			}
			$GLOBALS['egw']->accounts->set_memberships($userData['account_groups'],$userData['account_id']);

			$apps =& CreateObject('phpgwapi.applications',$userData['account_id']);
			$apps->read_installed_apps();
			/* dont think this is still used -- RalfBecker 2006-06-03
			 // Read Group Apps
			 if ($userData['account_groups'])
			 {
				 $apps->account_type = 'g';
				 reset($userData['account_groups']);
				 while($groups = each($userData['account_groups']))
				 {
					 $apps->account_id = $groups[0];
					 $old_app_groups = $apps->read_account_specific();
					 @reset($old_app_groups);
					 while($old_group_app = each($old_app_groups))
					 {
						 if (!$apps_after[$old_group_app[0]])
						 {
							 $apps_after[$old_group_app[0]] = $old_app_groups[$old_group_app[0]];
						 }
					 }
				 }
			 }
			 */
			$apps->account_type = 'u';
			$apps->account_id = $userData['account_id'];
			$apps->data = Array(Array());

			if ($userData['account_permissions'])
			{
				@reset($userData['account_permissions']);
				while (list($app,$turned_on) = each($userData['account_permissions']))
				{
					if ($turned_on)
					{
						$apps->add($app);
						/* dont think this is still used -- RalfBecker 2006-06-03
						 if (!$apps_after[$app])
						 {
							 $apps_after[] = $app;
						 }
						 */
					}
				}
			}
			$apps->save_repository();

			if (!$userData['changepassword'])
			{
				$GLOBALS['egw']->acl->add_repository('preferences','nopasswordchange',$userData['account_id'],1);
			}

			$apps->account_apps = array(array());
			//			$apps_after = array(array());

			return $userData['account_id'];
		}
	}
?>
