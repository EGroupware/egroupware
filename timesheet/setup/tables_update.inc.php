<?php
/**
 * TimeSheet - setup table updates
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @subpackage setup
 * @copyright (c) 2005 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

	$test[] = '0.1.001';
	function timesheet_upgrade0_1_001()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('egw_timesheet','pl_id',array(
			'type' => 'int',
			'precision' => '4',
			'default' => '0'
		));

		return $GLOBALS['setup_info']['timesheet']['currentver'] = '0.2.001';
	}
