<?php
/**
 * EGroupware - Notifications
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
$setup_info[NOTIFICATION_APP]['version']   = '1.9.003';
$setup_info[NOTIFICATION_APP]['app_order'] = 1;
$setup_info[NOTIFICATION_APP]['tables']    = array('egw_notificationpopup');
$setup_info[NOTIFICATION_APP]['enable']    = 2;

$setup_info[NOTIFICATION_APP]['author'] = 'Cornelius Weiss';
$setup_info[NOTIFICATION_APP]['maintainer'] = array(
	'name'  => 'eGroupware coreteam',
	'email' => 'egroupware-developers@lists.sf.net'
);
$setup_info[NOTIFICATION_APP]['license']  = 'GPL';
$setup_info[NOTIFICATION_APP]['description'] =
'Instant notification of users via various channels.';

/* The hooks this app includes, needed for hooks registration */
$setup_info[NOTIFICATION_APP]['hooks'][] = 'after_navbar';
$setup_info[NOTIFICATION_APP]['hooks'][] = 'preferences';
$setup_info[NOTIFICATION_APP]['hooks'][] = 'settings';
$setup_info[NOTIFICATION_APP]['hooks'][] = 'admin';
$setup_info[NOTIFICATION_APP]['hooks']['deleteaccount'] = 'notifications.notifications.deleteaccount';

/* Dependencies for this app to work */
$setup_info[NOTIFICATION_APP]['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.7','1.8','1.9')
);
$setup_info[NOTIFICATION_APP]['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('1.7','1.8','1.9')
);
