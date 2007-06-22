<?php
/**
 * InfoLog - delete account hook
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// Delete all records for a user
if((int)$GLOBALS['hook_values']['account_id'] > 0)
{
	require_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.soinfolog.inc.php');

	$grants = array();
	$info =& new soinfolog($grants);

	$info->change_delete_owner((int)$GLOBALS['hook_values']['account_id'],(int)$_POST['new_owner']);

	unset($info);
}
