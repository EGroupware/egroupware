<?php
/**
 * TimeSheet - setup current tables
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @subpackage setup
 * @copyright (c) 2005-14 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_timesheet' => array(
		'fd' => array(
			'ts_id' => array('type' => 'auto','nullable' => False,'comment' => 'id of the timesheet entry'),
			'ts_project' => array('type' => 'varchar','precision' => '255','comment' => 'project title'),
			'ts_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'title of the timesheet entry'),
			'ts_description' => array('type' => 'varchar','precision' => '16384','comment' => 'description of the timesheet entry'),
			'ts_start' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'timestamp of the startdate'),
			'ts_duration' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0','comment' => 'duration of the timesheet-entry'),
			'ts_quantity' => array('type' => 'float','precision' => '8','nullable' => False,'comment' => 'quantity'),
			'ts_unitprice' => array('type' => 'float','precision' => '4','comment' => 'unitprice'),
			'cat_id' => array('type' => 'int','meta' => 'category','precision' => '4','default' => '0','comment' => 'category'),
			'ts_owner' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'owner of the timesheet'),
			'ts_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'date modified ot the timesheet'),
			'ts_modifier' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'account id of the last modifier'),
			'pl_id' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'id of the linked project'),
			'ts_status' => array('type' => 'int','precision' => '4','comment' => 'status of the timesheet-entry')
		),
		'pk' => array('ts_id'),
		'fk' => array(),
		'ix' => array('ts_project','ts_owner','ts_status'),
		'uc' => array()
	),
	'egw_timesheet_extra' => array(
		'fd' => array(
			'ts_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'ts_extra_name' => array('type' => 'varchar','meta' => 'cfname','precision' => '32','nullable' => False),
			'ts_extra_value' => array('type' => 'varchar','meta' => 'cfvalue','precision' => '255','nullable' => False,'default' => '')
		),
		'pk' => array('ts_id','ts_extra_name'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
