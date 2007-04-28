<?php
	/**************************************************************************\
	* eGroupWare - phpgwapi setup                                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id$

	/* Basic information about this app */
	$setup_info['phpgwapi']['name']      = 'phpgwapi';
	$setup_info['phpgwapi']['title']     = 'eGroupWare API';
	$setup_info['phpgwapi']['version']   = '1.3.021';
	$setup_info['phpgwapi']['versions']['current_header'] = '1.28';
	$setup_info['phpgwapi']['enable']    = 3;
	$setup_info['phpgwapi']['app_order'] = 1;

	/* The tables this app creates */
	$setup_info['phpgwapi']['tables'][]  = 'egw_config';
	$setup_info['phpgwapi']['tables'][]  = 'egw_applications';
	$setup_info['phpgwapi']['tables'][]  = 'egw_acl';
	$setup_info['phpgwapi']['tables'][]  = 'egw_accounts';
	$setup_info['phpgwapi']['tables'][]  = 'egw_preferences';
	$setup_info['phpgwapi']['tables'][]  = 'egw_sessions';
	$setup_info['phpgwapi']['tables'][]  = 'egw_app_sessions';
	$setup_info['phpgwapi']['tables'][]  = 'egw_access_log';
	$setup_info['phpgwapi']['tables'][]  = 'egw_hooks';
	$setup_info['phpgwapi']['tables'][]  = 'egw_languages';
	$setup_info['phpgwapi']['tables'][]  = 'egw_lang';
	$setup_info['phpgwapi']['tables'][]  = 'egw_nextid';
	$setup_info['phpgwapi']['tables'][]  = 'egw_categories';
	$setup_info['phpgwapi']['tables'][]  = 'egw_log';
	$setup_info['phpgwapi']['tables'][]  = 'egw_log_msg';
	$setup_info['phpgwapi']['tables'][]  = 'egw_interserv';
	$setup_info['phpgwapi']['tables'][]  = 'egw_vfs';
	$setup_info['phpgwapi']['tables'][]  = 'egw_history_log';
	$setup_info['phpgwapi']['tables'][]  = 'egw_async';
	$setup_info['phpgwapi']['tables'][]  = 'egw_api_content_history';
	$setup_info['phpgwapi']['tables'][]  = 'phpgw_vfs2_mimetypes';
	$setup_info['phpgwapi']['tables'][]  = 'phpgw_vfs2_files';
	$setup_info['phpgwapi']['tables'][]  = 'phpgw_vfs2_customfields';
	$setup_info['phpgwapi']['tables'][]  = 'phpgw_vfs2_quota';
	$setup_info['phpgwapi']['tables'][]  = 'phpgw_vfs2_shares';
	$setup_info['phpgwapi']['tables'][]  = 'phpgw_vfs2_versioning';
	$setup_info['phpgwapi']['tables'][]  = 'phpgw_vfs2_customfields_data';
	$setup_info['phpgwapi']['tables'][]  = 'phpgw_vfs2_prefixes';
	$setup_info['phpgwapi']['tables'][]  = 'egw_links';
	$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook';
	$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook_extra';
	$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook_lists';
	$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook2list';

	/* Basic information about this app */
	$setup_info['notifywindow']['name']      = 'notifywindow';
	$setup_info['notifywindow']['title']     = 'Notify Window';
	$setup_info['notifywindow']['version']   = '1.0.0';
	$setup_info['notifywindow']['enable']    = 2;
	$setup_info['notifywindow']['app_order'] = 1;
	$setup_info['notifywindow']['tables']    = '';
	$setup_info['notifywindow']['hooks'][]   = 'home';



