<?php
/**
 * EGroupware - API Setup
 *
 * Update scripts 1.8 --> 2.0
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/* Include older eGroupWare update support */
include('tables_update_0_9_9.inc.php');
include('tables_update_0_9_10.inc.php');
include('tables_update_0_9_12.inc.php');
include('tables_update_0_9_14.inc.php');
include('tables_update_1_0.inc.php');
include('tables_update_1_2.inc.php');
include('tables_update_1_4.inc.php');
include('tables_update_1_6.inc.php');

/**
 * Update from the stable 1.8 branch to the new devel branch 1.9.xxx
 */
function phpgwapi_upgrade1_8_001()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.001';
}
