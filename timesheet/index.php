<?php
/**
 * TimeSheet - index
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @copyright (c) 2005-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api\Framework;

include_once('./setup/setup.inc.php');
$ts_version = $setup_info[TIMESHEET_APP]['version'];
unset($setup_info);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> TIMESHEET_APP,
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

if ($ts_version != $GLOBALS['egw_info']['apps'][TIMESHEET_APP]['version'])
{
	Framework::render('<p style="text-align: center; color:red; font-weight: bold;">'.
		lang('Your database is NOT up to date (%1 vs. %2), please run %3setup%4 to update your database.',
		$ts_version,$GLOBALS['egw_info']['apps'][TIMESHEET_APP]['version'],
		'<a href="../setup/">','</a>')."</p>\n", null, true);
	exit();
}

Framework::redirect_link('/index.php',array('menuaction'=>TIMESHEET_APP.'.timesheet_ui.index'));
