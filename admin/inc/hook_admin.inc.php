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

	$file = array(
		'Site Configuration'         => $phpgw->link('/admin/config.php','appname=admin'),
		'Peer Servers'               => $phpgw->link('/admin/servers.php'),
		'User Accounts'              => $phpgw->link('/index.php','menuaction=admin.uiaccounts.list_users'),
		'User Groups'                => $phpgw->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
		'Applications'               => $phpgw->link('/admin/applications.php'),
		'Global Categories'          => $phpgw->link('/admin/categories.php'),
		'Change Main Screen Message' => $phpgw->link('/admin/mainscreen_message.php'),
		'View Sessions'              => $phpgw->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions'),
		'View Access Log'            => $phpgw->link('/index.php','menuaction=admin.uiaccess_history.list_history'),
		'View Error Log'             => $phpgw->link('/admin/log.php'),
		'phpInfo'                    => $phpgw->link('/admin/phpinfo.php')
	);

	//Do not modify below this line
	display_section('admin','admin',$file);
?>
