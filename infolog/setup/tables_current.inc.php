<?php
/**
 * EGroupware - InfoLog - Setup
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @subpackage setup
 * @copyright (c) 2003-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_infolog' => array(
		'fd' => array(
			'info_id' => array('type' => 'auto','nullable' => False),
			'info_type' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => 'task'),
			'info_from' => array('type' => 'varchar','precision' => '255'),
			'info_addr' => array('type' => 'varchar','precision' => '255'),
			'info_subject' => array('type' => 'varchar','precision' => '255'),
			'info_des' => array('type' => 'text'),
			'info_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_responsible' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '0'),
			'info_access' => array('type' => 'varchar','precision' => '10','default' => 'public'),
			'info_cat' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_datemodified' => array('type' => 'int','precision' => '8','nullable' => False),
			'info_startdate' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'info_enddate' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'info_id_parent' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_planned_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_replanned_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_used_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_status' => array('type' => 'varchar','precision' => '40','default' => 'done'),
			'info_confirm' => array('type' => 'varchar','precision' => '10','default' => 'not'),
			'info_modifier' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_link_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_priority' => array('type' => 'int','precision' => '2','default' => '1'),
			'pl_id' => array('type' => 'int','precision' => '4'),
			'info_price' => array('type' => 'float','precision' => '8'),
			'info_percent' => array('type' => 'int','precision' => '2','default' => '0'),
			'info_datecompleted' => array('type' => 'int','precision' => '8'),
			'info_location' => array('type' => 'varchar','precision' => '255'),
			'info_custom_from' => array('type' => 'int','precision' => '1'),
			'info_uid' => array('type' => 'varchar','precision' => '255'),
			'info_cc' => array('type' => 'varchar','precision' => '255'),
			'caldav_name' => array('type' => 'varchar','precision' => '64','comment' => 'name part of CalDAV URL, if specified by client'),
			'info_etag' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'etag, not yet used'),
			'info_created' => array('type' => 'int','precision' => '8'),
			'info_creator' => array('type' => 'int','precision' => '4'),
		),
		'pk' => array('info_id'),
		'fk' => array(),
		'ix' => array('caldav_name',array('info_owner','info_responsible','info_status','info_startdate'),array('info_id_parent','info_owner','info_responsible','info_status','info_startdate')),
		'uc' => array()
	),
	'egw_infolog_extra' => array(
		'fd' => array(
			'info_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_extra_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'info_extra_value' => array('type' => 'text','nullable' => False)
		),
		'pk' => array('info_id','info_extra_name'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
