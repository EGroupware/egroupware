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

	$test[] = '0.2.001';
	function timesheet_upgrade0_2_001()
	{
		$GLOBALS['egw_setup']->oProc->CreateTable('egw_timesheet_extra',array(
			'fd' => array(
				'ts_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'ts_extra_name' => array('type' => 'varchar','precision' => '32','nullable' => False),
				'ts_extra_value' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '')
			),
			'pk' => array('ts_id','ts_extra_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));


		$GLOBALS['setup_info']['timesheet']['currentver'] = '0.2.002';
		return $GLOBALS['setup_info']['timesheet']['currentver'];
	}
