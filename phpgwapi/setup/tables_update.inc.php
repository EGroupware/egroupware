<?php
/**
 * eGroupWare - API Setup
 *
 * Update scripts 1.6 --> 1.8
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

/**
 * Update from the stable 1.6 branch to the new devel branch 1.7.xxx
 */
function phpgwapi_upgrade1_6_001()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.7.001';
}
