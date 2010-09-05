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
$setup_info['preferences']['version']   = '1.8';
$setup_info['preferences']['app_order'] = 1;
$setup_info['preferences']['tables']    = '';
$setup_info['preferences']['enable']    = 2;

/* The hooks this app includes, needed for hooks registration */
$setup_info['preferences']['hooks']['deleteaccount'] = 'preferences_hooks::deleteaccount';
$setup_info['preferences']['hooks']['deletegroup']   = 'preferences_hooks::deleteaccount';
$setup_info['preferences']['hooks']['preferences']   = 'preferences_hooks::preferences';
$setup_info['preferences']['hooks']['settings']      = 'preferences_hooks::settings';
$setup_info['preferences']['hooks']['edit_user']     = 'preferences.uisettings.edit_user';

/* Dependencies for this app to work */
$setup_info['preferences']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => Array('1.7','1.8','1.9')
);
