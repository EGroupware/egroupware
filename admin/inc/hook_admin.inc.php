<?php
	/**************************************************************************\
	* phpGroupWare - administration                                            *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if (! $GLOBALS['phpgw']->acl->check('site_config_access',1,'admin'))
	{
		$file['Site Configuration']         = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiconfig.index&appname=admin');
	}

	if (! $GLOBALS['phpgw']->acl->check('peer_server_access',1,'admin'))
	{
		$file['Peer Servers']               = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.list_servers');
	}

	if (! $GLOBALS['phpgw']->acl->check('account_access',1,'admin'))
	{
		$file['User Accounts']              = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_users');
	}

	if (! $GLOBALS['phpgw']->acl->check('group_access',1,'admin'))
	{
		$file['User Groups']                = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups');
	}

	if (! $GLOBALS['phpgw']->acl->check('applications_access',1,'admin'))
	{
		$file['Applications']               = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiapplications.get_list');
	}

	if (! $GLOBALS['phpgw']->acl->check('global_categories_access',1,'admin'))
	{
		$file['Global Categories']          = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index');
	}

	if (! $GLOBALS['phpgw']->acl->check('mainscreen_message_access',1,'admin'))
	{
		$file['Change Main Screen Message'] = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uimainscreen.index');
	}

	if (! $GLOBALS['phpgw']->acl->check('current_sessions_access',1,'admin'))
	{
		$file['View Sessions'] = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions');
	}

	/* These need to be added still */
	$file['View Access Log'] = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccess_history.list_history');
	$file['View Error Log']  = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uilog.list_log');
	$file['phpInfo']         = "javascript:openwindow('" . $GLOBALS['phpgw']->link('/admin/phpinfo.php') . "')"; //$GLOBALS['phpgw']->link('/admin/phpinfo.php');

	/* Do not modify below this line */
	display_section('admin','admin',$file);
?>
