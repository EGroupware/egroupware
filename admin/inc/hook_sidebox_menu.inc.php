<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* Written by Pim Snel <pim@lingewoud.nl>                                   *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
	{

	/*
		This hookfile is for generating an app-specific side menu used in the idots 
		template set.

		$menu_title speaks for itself
		$file is the array with link to app functions

		display_sidebox can be called as much as you like
	*/

		$menu_title = $GLOBALS['egw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
		$file = array();

		if (! $GLOBALS['egw']->acl->check('site_config_access',1,'admin'))
		{
			$file['Site Configuration']         = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiconfig.index&appname=admin');
		}
/*
		if (! $GLOBALS['egw']->acl->check('peer_server_access',1,'admin'))
		{
			$file['Peer Servers']               = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiserver.list_servers');
		}
*/
		if (! $GLOBALS['egw']->acl->check('account_access',1,'admin'))
		{
			$file['User Accounts']              = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_users');
		}

		if (! $GLOBALS['egw']->acl->check('group_access',1,'admin'))
		{
			$file['User Groups']                = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups');
		}

		if (! $GLOBALS['egw']->acl->check('applications_access',1,'admin'))
		{
			$file['Applications']               = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiapplications.get_list');
		}

		if (! $GLOBALS['egw']->acl->check('global_categories_access',1,'admin'))
		{
			$file['Global Categories']          = $GLOBALS['egw']->link('/index.php','menuaction=admin.uicategories.index');
		}

		if (!$GLOBALS['egw']->acl->check('mainscreen_message_access',1,'admin') || !$GLOBALS['egw']->acl->check('mainscreen_message_access',2,'admin'))
		{
			$file['Change Main Screen Message'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uimainscreen.index');
		}

		if (! $GLOBALS['egw']->acl->check('current_sessions_access',1,'admin'))
		{
			$file['View Sessions'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions');
		}

		if (! $GLOBALS['egw']->acl->check('access_log_access',1,'admin'))
		{
			$file['View Access Log'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccess_history.list_history');
		}

		if (! $GLOBALS['egw']->acl->check('error_log_access',1,'admin'))
		{
			$file['View Error Log']  = $GLOBALS['egw']->link('/index.php','menuaction=admin.uilog.list_log');
		}

		if (! $GLOBALS['egw']->acl->check('applications_access',16,'admin'))
		{
			$file['Find and Register all Application Hooks'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiapplications.register_all_hooks');
		}

		if (! $GLOBALS['egw']->acl->check('asyncservice_access',1,'admin'))
		{
			$file['Asynchronous timed services'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiasyncservice.index');
		}

		if (! $GLOBALS['egw']->acl->check('db_backup_access',1,'admin'))
		{
			$file['DB backup and restore'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.admin_db_backup.index');
		}

		if (! $GLOBALS['egw']->acl->check('info_access',1,'admin'))
		{
			$file['phpInfo']         = "javascript:openwindow('" . $GLOBALS['egw']->link('/admin/phpinfo.php') . "')";
		}

		display_sidebox($appname,$menu_title,$file);
	}
