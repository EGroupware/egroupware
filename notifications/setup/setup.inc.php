<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

if (!defined('NOTIFICATION_APP'))
{
	define('NOTIFICATION_APP','notifications');
}

$setup_info[NOTIFICATION_APP]['name']      = NOTIFICATION_APP;
$setup_info[NOTIFICATION_APP]['version']   = '1.4';
$setup_info[NOTIFICATION_APP]['app_order'] = 1;
$setup_info[NOTIFICATION_APP]['tables']    = array('egw_notificationpopup');
$setup_info[NOTIFICATION_APP]['enable']    = 2;

$setup_info[NOTIFICATION_APP]['author'] = 
$setup_info[NOTIFICATION_APP]['maintainer'] = array(
	'name'  => 'Cornelius Weiss',
	'email' => 'nelius@cwtech.de'
);
$setup_info[NOTIFICATION_APP]['license']  = 'GPL';
$setup_info[NOTIFICATION_APP]['description'] = 
'Instant notification of users via various channels.';

/* The hooks this app includes, needed for hooks registration */
$setup_info[NOTIFICATION_APP]['hooks'][] = 'after_navbar';
$setup_info[NOTIFICATION_APP]['hooks'][] = 'preferences';
$setup_info[NOTIFICATION_APP]['hooks'][] = 'settings';
$setup_info[NOTIFICATION_APP]['hooks'][] = 'admin';
//$setup_info[NOTIFICATION_APP]['hooks']['settings'] = NOTIFICATION_APP.'.ts_admin_prefs_sidebox_hooks.settings';
//$setup_info[NOTIFICATION_APP]['hooks']['admin'] = NOTIFICATION_APP.'.ts_admin_prefs_sidebox_hooks.all_hooks';
//$setup_info[NOTIFICATION_APP]['hooks']['sidebox_menu'] = NOTIFICATION_APP.'.ts_admin_prefs_sidebox_hooks.all_hooks';
//$setup_info[NOTIFICATION_APP]['hooks']['search_link'] = NOTIFICATION_APP.'.bonotification.search_link';

/* Dependencies for this app to work */
$setup_info[NOTIFICATION_APP]['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.3','1.4','1.5')
);
$setup_info[NOTIFICATION_APP]['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('1.3','1.4','1.5')
);

