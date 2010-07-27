<?php
/**
 * eGroupWare - Preferences
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package preferences
 * @subpackage setup
 * @version $Id$
 */

$setup_info['preferences']['name']      = 'preferences';
$setup_info['preferences']['title']     = 'Preferences';
$setup_info['preferences']['version']   = '1.7';
$setup_info['preferences']['app_order'] = 1;
$setup_info['preferences']['tables']    = '';
$setup_info['preferences']['enable']    = 2;
$setup_info['preferences']['license']   = 'GPL';

/* The hooks this app includes, needed for hooks registration */
$setup_info['preferences']['hooks']['deleteaccount'] = 'preferences_hooks::deleteaccount';
$setup_info['preferences']['hooks']['deletegroup']   = 'preferences_hooks::deleteaccount';
$setup_info['preferences']['hooks']['preferences']   = 'preferences_hooks::preferences';
$setup_info['preferences']['hooks']['settings']      = 'preferences_hooks::settings';
$setup_info['preferences']['hooks']['edit_user']     = 'preferences.uisettings.edit_user';

/* Dependencies for this app to work */
$setup_info['preferences']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => Array('1.2','1.3','1.4','1.5','1.6','1.7')
);

/**
 * Password change without preferences rights
 */
$setup_info['password']['name']      = 'password';
$setup_info['password']['title']     = 'Password';
$setup_info['password']['version']   = $setup_info['preferences']['version'];
$setup_info['password']['app_order'] = 1;
$setup_info['password']['tables']    = array();
$setup_info['password']['enable']    = 2;
$setup_info['password']['index']     = '/preferences/password.php';
$setup_info['password']['author']    = $setup_info['preferences']['author']; 
$setup_info['password']['maintainer']= $setup_info['preferences']['maintainer'];
$setup_info['password']['license']   = $setup_info['preferences']['license'];
$setup_info['password']['depends']   = $setup_info['preferences']['depends'];
