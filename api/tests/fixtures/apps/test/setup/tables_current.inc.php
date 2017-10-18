<?php
/**
 * eGroupWare - Setup
 * http://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage tests
 */


$phpgw_baseline = array(
	'egw_test' => array(
		'fd' => array(
			't_id' => array('type' => 'auto','nullable' => False),
			't_title' => array('type' => 'varchar','precision' => '80'),
			't_desc' => array('type' => 'varchar','precision' => '16000'),
			't_modifier' => array('type' => 'int','meta' => 'account','precision' => '4'),
			't_modified' => array('type' => 'timestamp','meta' => 'timestamp','default' => 'current_timestamp', 'nullable' => false),
			't_start' => array('type' => 'int','meta' => 'timestamp','precision' => '8'),
			't_end' => array('type' => 'timestamp','meta' => 'timestamp')
		),
		'pk' => array('t_id'),
		'fk' => array(),
		'ix' => array('t_modified'),
		'uc' => array()
	)
);
