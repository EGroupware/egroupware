<?php
/**
 * EGroupware - InfoLog - Setup
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @subpackage setup
 * @copyright (c) 2003-17 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$phpgw_baseline = array(
	'egw_infolog' => array(
		'fd' => array(
			'info_id' => array('type' => 'auto','nullable' => False,'comment' => 'id of the infolog-entry'),
			'info_type' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => 'task','comment' => 'infolog-type e.g. task, phone, email or note'),
			'info_from' => array('type' => 'varchar','precision' => '255','comment' => 'text of the primary link'),
			'info_subject' => array('type' => 'varchar','precision' => '255','comment' => 'title of the infolog-entry'),
			'info_des' => array('type' => 'longtext','comment' => 'desciption of the infolog-entry'),
			'info_owner' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False,'comment' => 'owner of the entry, can be account or group'),
			'info_access' => array('type' => 'ascii','precision' => '10','default' => 'public','comment' => 'public or privat'),
			'info_cat' => array('type' => 'int','meta' => 'category','precision' => '4','nullable' => False,'default' => '0','comment' => 'category id'),
			'info_datemodified' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'timestamp of the last mofification'),
			'info_startdate' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'default' => '0','comment' => 'timestamp of the startdate'),
			'info_enddate' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'default' => '0','comment' => 'timestamp of the enddate'),
			'info_id_parent' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'id of the parent infolog'),
			'info_planned_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'pm-field: planned time'),
			'info_replanned_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'pm-field: replanned time'),
			'info_used_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'pm-field: used time'),
			'info_status' => array('type' => 'varchar','precision' => '40','default' => 'done','comment' => 'status e.g. ongoing, done ...'),
			'info_confirm' => array('type' => 'ascii','precision' => '10','default' => 'not'),
			'info_modifier' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'default' => '0','comment' => 'account id of the last modifier'),
			'info_link_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'id of the primary link'),
			'info_priority' => array('type' => 'int','precision' => '2','default' => '1','comment' => '0=Low, 1=Normal, 2=High, 3=Urgent'),
			'pl_id' => array('type' => 'int','precision' => '4','comment' => 'pm-field: id of the pricelist'),
			'info_price' => array('type' => 'float','precision' => '8','comment' => 'pm-field: price-field'),
			'info_percent' => array('type' => 'int','meta' => 'percent','precision' => '2','default' => '0','comment' => 'percentage of completion'),
			'info_datecompleted' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'timestamp of completion'),
			'info_location' => array('type' => 'varchar','precision' => '255','comment' => 'textfield location'),
			'info_custom_from' => array('type' => 'int','precision' => '1','comment' => 'tick-box to show infolog_from'),
			'info_uid' => array('type' => 'ascii','precision' => '255','comment' => 'unique id of the infolog-entry'),
			'caldav_name' => array('type' => 'ascii','precision' => '260','comment' => 'name part of CalDAV URL, if specified by client'),
			'info_etag' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'etag, not yet used'),
			'info_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'timestamp of the creation date'),
			'info_creator' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'account id of the creator')
		),
		'pk' => array('info_id'),
		'fk' => array(),
		'ix' => array('info_owner','info_datemodified','info_id_parent','caldav_name'),
		'uc' => array()
	),
	'egw_infolog_extra' => array(
		'fd' => array(
			'info_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_extra_name' => array('type' => 'varchar','meta' => 'cfname','precision' => '64','nullable' => False),
			'info_extra_value' => array('type' => 'varchar','meta' => 'cfvalue','precision' => '16384','nullable' => False)
		),
		'pk' => array('info_id','info_extra_name'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_infolog_users' => array(
		'fd' => array(
			'info_res_id' => array('type' => 'auto','nullable' => False,'comment' => 'auto id'),
			'info_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'ascii','meta' => 'account','precision' => '32','nullable' => False,'comment' => 'account_id or md5 of lowercased email'),
			'info_res_deleted' => array('type' => 'bool','comment' => 'NULL or true, not false!'),
			'info_res_modified' => array('type' => 'timestamp','meta' => 'timestamp','default' => 'current_timestamp','comment' => 'last modification time'),
			'info_res_modifier' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'modifying user'),
			'info_res_status' => array('type' => 'varchar','precision' => '16','default' => 'NEEDS-ACTION','comment' => 'attendee status'),
			'info_res_attendee' => array('type' => 'varchar','precision' => '255','comment' => 'attendee email or json object with attr. cn, url, ...')
		),
		'pk' => array('info_res_id'),
		'fk' => array(),
		'ix' => array('account_id'),
		'uc' => array(array('info_id','account_id'))
	)
);
