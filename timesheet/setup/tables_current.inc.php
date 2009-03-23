<?php
/**
 * TimeSheet - setup current tables
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @subpackage setup
 * @copyright (c) 2005-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_timesheet' => array(
		'fd' => array(
			'ts_id' => array('type' => 'auto','nullable' => False),
			'ts_project' => array('type' => 'varchar','precision' => '80'),
			'ts_title' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'ts_description' => array('type' => 'text'),
			'ts_start' => array('type' => 'int','precision' => '8','nullable' => False),
			'ts_duration' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'ts_quantity' => array('type' => 'float','precision' => '8','nullable' => False),
			'ts_unitprice' => array('type' => 'float','precision' => '4'),
			'cat_id' => array('type' => 'int','precision' => '4','default' => '0'),
			'ts_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'ts_modified' => array('type' => 'int','precision' => '8','nullable' => False),
			'ts_modifier' => array('type' => 'int','precision' => '4','nullable' => False),
			'pl_id' => array('type' => 'int','precision' => '4','default' => '0'),
			'ts_status' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('ts_id'),
		'fk' => array(),
		'ix' => array('ts_project','ts_owner','ts_status'),
		'uc' => array()
	),
	'egw_timesheet_extra' => array(
		'fd' => array(
			'ts_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'ts_extra_name' => array('type' => 'varchar','precision' => '32','nullable' => False),
			'ts_extra_value' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '')
		),
		'pk' => array('ts_id','ts_extra_name'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
