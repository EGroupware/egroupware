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

	$title = $appname;
	$file = array(
		'Preferences'    => $GLOBALS['egw']->link(
			'/index.php',
			'menuaction=notifications.uinotificationprefs.index',
			'_account_id=0', 
			'_referer='
		),
	);
	
	display_section($appname,$title,$file);
?>
