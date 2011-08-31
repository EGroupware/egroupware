<?php
/**
 * EGroupware - Admin
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage setup
 * @version $Id$
 */

$setup_info['admin']['name']      = 'admin';
$setup_info['admin']['version']   = '1.8';
$setup_info['admin']['app_order'] = 1;
$setup_info['admin']['tables']    = array('egw_admin_queue','egw_admin_remote');
$setup_info['admin']['enable']    = 1;

$setup_info['admin']['author'][] = array(
	'name'  => 'eGroupWare coreteam',
	'email' => 'egroupware-developers@lists.sourceforge.net'
);

$setup_info['admin']['maintainer'][] = array(
	'name'  => 'eGroupWare coreteam',
	'email' => 'egroupware-developers@lists.sourceforge.net',
	'url'   => 'www.egroupware.org'
);

$setup_info['admin']['license']  = 'GPL';
$setup_info['admin']['description'] = 'EGroupware administration application';

/* The hooks this app includes, needed for hooks registration */
$setup_info['admin']['hooks'] = array(
	'acl_manager',
	'add_def_pref',
	'after_navbar',
	'config_validate',
	'deleteaccount',
	'view_user' => 'admin.uiaccounts.edit_view_user_hook',
	'edit_user' => 'admin.uiaccounts.edit_view_user_hook',
	'group_manager' => 'admin.uiaccounts.edit_group_hook',
	'topmenu_info'
);
$setup_info['admin']['hooks']['preferences'] =$setup_info['admin']['name'].'.admin_prefs_sidebox_hooks.all_hooks';
$setup_info['admin']['hooks']['admin'] =$setup_info['admin']['name'].'.admin_prefs_sidebox_hooks.all_hooks';
$setup_info['admin']['hooks']['sidebox_menu'] =$setup_info['admin']['name'].'.admin_prefs_sidebox_hooks.all_hooks';

/* Dependencies for this app to work */
$setup_info['admin']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => Array('1.7','1.8','1.9')
);
$setup_info['admin']['depends'][] = array(
	'appname' => 'etemplate',
	'versions' => Array('1.7','1.8','1.9')
);
