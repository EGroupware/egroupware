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
		$file['Site Configuration']         = $phpgw->link('/admin/config.php','appname=admin');
	}

	if (! $GLOBALS['phpgw']->acl->check('peer_server_access',1,'admin'))
	{
		$file['Peer Servers']               = $phpgw->link('/admin/servers.php');
	}

	if (! $GLOBALS['phpgw']->acl->check('account_access',1,'admin'))
	{
		$file['User Accounts']              = $phpgw->link('/index.php','menuaction=admin.uiaccounts.list_users');
	}

	if (! $GLOBALS['phpgw']->acl->check('group_access',1,'admin'))
	{
		$file['User Groups']                = $phpgw->link('/index.php','menuaction=admin.uiaccounts.list_groups');
	}

	if (! $GLOBALS['phpgw']->acl->check('applications_access',1,'admin'))
	{
		$file['Applications']               = $phpgw->link('/admin/applications.php');
	}

	if (! $GLOBALS['phpgw']->acl->check('global_categories_access',1,'admin'))
	{
		$file['Global Categories']          = $phpgw->link('/admin/categories.php');
	}

	if (! $GLOBALS['phpgw']->acl->check('mainscreen_message_access',1,'admin'))
	{
		$file['Change Main Screen Message'] = $phpgw->link('/admin/mainscreen_message.php');
	}

	if (! $GLOBALS['phpgw']->acl->check('current_sessions_access',1,'admin'))
	{
		$file['View Sessions']              = $phpgw->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions');
	}

	// These need to be added still
	$file['View Access Log']            = $phpgw->link('/index.php','menuaction=admin.uiaccess_history.list_history');
	$file['View Error Log']             = $phpgw->link('/admin/log.php');
	$file['phpInfo']                    = $phpgw->link('/admin/phpinfo.php');

	//Do not modify below this line
	display_section('admin','admin',$file);
?>