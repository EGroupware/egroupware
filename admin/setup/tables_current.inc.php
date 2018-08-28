<?php
/**
 * EGroupware - Setup
 *
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage setup
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_admin_queue' => array(
		'fd' => array(
			'cmd_id' => array('type' => 'auto','nullable' => False),
			'cmd_uid' => array('type' => 'ascii','precision' => '64','nullable' => False),
			'cmd_creator' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'cmd_creator_email' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'cmd_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False),
			'cmd_type' => array('type' => 'ascii','precision' => '32','nullable' => False,'default' => 'admin_cmd'),
			'cmd_status' => array('type' => 'int','precision' => '1'),
			'cmd_scheduled' => array('type' => 'int','meta' => 'timestamp','precision' => '8'),
			'cmd_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8'),
			'cmd_modifier' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'cmd_modifier_email' => array('type' => 'varchar','precision' => '128'),
			'cmd_error' => array('type' => 'varchar','precision' => '255'),
			'cmd_errno' => array('type' => 'int','precision' => '4'),
			'cmd_requested' => array('type' => 'int','precision' => '4'),
			'cmd_requested_email' => array('type' => 'varchar','precision' => '128'),
			'cmd_comment' => array('type' => 'varchar','precision' => '255'),
			'cmd_data' => array('type' => 'ascii','precision' => '16384'),
			'remote_id' => array('type' => 'int','precision' => '4'),
			'cmd_app' => array('type' => 'ascii','precision' => '16','comment' => 'affected app'),
			'cmd_account' => array('type' => 'int','meta' => 'account','precision' => '4','comment' => 'affected account'),
			'cmd_rrule' => array('type' => 'varchar','precision' => '128','comment' => 'rrule for periodic execution'),
			'cmd_parent' => array('type' => 'int','precision' => '4','comment' => 'cmd_id of periodic command'),
			'cmd_run' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'periodic execution time')
		),
		'pk' => array('cmd_id'),
		'fk' => array(),
		'ix' => array('cmd_status','cmd_scheduled','cmd_app','cmd_account','cmd_parent'),
		'uc' => array('cmd_uid')
	),
	'egw_admin_remote' => array(
		'fd' => array(
			'remote_id' => array('type' => 'auto','nullable' => False),
			'remote_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'remote_hash' => array('type' => 'ascii','precision' => '32','nullable' => False),
			'remote_url' => array('type' => 'ascii','precision' => '128','nullable' => False),
			'remote_domain' => array('type' => 'ascii','precision' => '64','nullable' => False)
		),
		'pk' => array('remote_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('remote_name')
	)
);
