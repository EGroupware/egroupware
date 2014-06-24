<?php
/**
 * EGroupware - Preferences
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package preferences
 * @subpackage setup
 * @version $Id$
 */

$setup_info['preferences']['name']      = 'preferences';
$setup_info['preferences']['title']     = 'Preferences';
$setup_info['preferences']['version']   = '14.1';
$setup_info['preferences']['app_order'] = 1;
$setup_info['preferences']['tables']    = '';
$setup_info['preferences']['enable']    = 2;
$setup_info['preferences']['license']   = 'GPL';
$setup_info['preferences']['index']     = 'preferences.preferences_settings.index&ajax=true';

/* The hooks this app includes, needed for hooks registration */
$setup_info['preferences']['hooks']['deleteaccount'] = 'preferences_hooks::deleteaccount';
$setup_info['preferences']['hooks']['deletegroup']   = 'preferences_hooks::deleteaccount';
$setup_info['preferences']['hooks']['settings']      = 'preferences_hooks::settings';
$setup_info['preferences']['hooks']['edit_user']     = 'preferences_hooks::edit_user';
$setup_info['preferences']['hooks']['view_user']     = 'preferences_hooks::edit_user';
$setup_info['preferences']['hooks']['edit_group']    = 'preferences_hooks::edit_user';
$setup_info['preferences']['hooks']['group_manager'] = 'preferences_hooks::edit_user';
$setup_info['preferences']['hooks']['admin']         = 'preferences_hooks::admin';
$setup_info['preferences']['hooks']['deny_prefs']    = 'preferences_hooks::deny_prefs';
$setup_info['preferences']['hooks']['deny_acl']      = 'preferences_hooks::deny_acl';
$setup_info['preferences']['hooks']['deny_cats']     = 'preferences_hooks::deny_cats';

/* Dependencies for this app to work */
$setup_info['preferences']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => Array('14.1')
);
$setup_info['preferences']['depends'][] = array(
	'appname' => 'etemplate',
	'versions' => Array('14.1')
);
