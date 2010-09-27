<?php
/**
 * EGroupware - API Setup
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/* Basic information about this app */
$setup_info['phpgwapi']['name']      = 'phpgwapi';
$setup_info['phpgwapi']['title']     = 'eGroupWare API';
$setup_info['phpgwapi']['version']   = '1.9.004';
$setup_info['phpgwapi']['versions']['current_header'] = '1.29';
$setup_info['phpgwapi']['enable']    = 3;
$setup_info['phpgwapi']['app_order'] = 1;
$setup_info['phpgwapi']['license'] = 'GPL';
$setup_info['phpgwapi']['maintainer']	= $setup_info['phpgwapi']['author']	= array(
	'name'  => 'eGroupWare coreteam',
	'email' => 'egroupware-developers@lists.sourceforge.net',
);

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
$setup_info['phpgwapi']['tables'][]  = 'egw_links';
$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook';
$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook_extra';
$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook_lists';
$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook2list';
$setup_info['phpgwapi']['tables'][]  = 'egw_sqlfs';
$setup_info['phpgwapi']['tables'][]  = 'egw_index_keywords';
$setup_info['phpgwapi']['tables'][]  = 'egw_index';
$setup_info['phpgwapi']['tables'][]  = 'egw_cat2entry';
$setup_info['phpgwapi']['tables'][]  = 'egw_locks';
$setup_info['phpgwapi']['tables'][]  = 'egw_sqlfs_props';

// hooks used by vfs_home_hooks to manage user- and group-directories for the new stream based VFS
$setup_info['phpgwapi']['hooks']['addaccount']		= 'phpgwapi.vfs_home_hooks.addAccount';
$setup_info['phpgwapi']['hooks']['deleteaccount']	= 'phpgwapi.vfs_home_hooks.deleteAccount';
$setup_info['phpgwapi']['hooks']['editaccount']		= 'phpgwapi.vfs_home_hooks.editAccount';
$setup_info['phpgwapi']['hooks']['addgroup']		= 'phpgwapi.vfs_home_hooks.addGroup';
$setup_info['phpgwapi']['hooks']['deletegroup']		= 'phpgwapi.vfs_home_hooks.deleteGroup';
$setup_info['phpgwapi']['hooks']['editgroup']		= 'phpgwapi.vfs_home_hooks.editGroup';

/* CalDAV/CardDAV/GroupDAV app */
$setup_info['groupdav']['name']      = 'groupdav';
$setup_info['groupdav']['version']   = '1.8';
$setup_info['groupdav']['enable']    = 2;
$setup_info['groupdav']['app_order'] = 1;
$setup_info['groupdav']['icon']      = 'groupdav';
$setup_info['groupdav']['icon_app']  = 'phpgwapi';
$setup_info['groupdav']['author'] = $setup_info['groupdav']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@outdoor-training.de'
);
$setup_info['groupdav']['license'] = 'GPL';




