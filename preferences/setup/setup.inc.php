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
$setup_info['preferences']['version']   = '1.6';
$setup_info['preferences']['app_order'] = 1;
$setup_info['preferences']['tables']    = '';
$setup_info['preferences']['enable']    = 2;

/* The hooks this app includes, needed for hooks registration */
$setup_info['preferences']['hooks'][] = 'deleteaccount';
$setup_info['preferences']['hooks'][] = 'config';
$setup_info['preferences']['hooks'][] = 'preferences';
$setup_info['preferences']['hooks'][] = 'settings';
$setup_info['preferences']['hooks']['edit_user']    = 'preferences.uisettings.edit_user';

/* Dependencies for this app to work */
$setup_info['preferences']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => Array('1.2','1.3','1.4','1.5','1.6','1.7')
);
