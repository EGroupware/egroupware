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
 */

function admin_upgrade1_2()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.4';
}


function admin_upgrade1_4()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_admin_queue',array(
		'fd' => array(
			'cmd_id' => array('type' => 'auto'),
			'cmd_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cmd_creator' => array('type' => 'int','precision' => '4','nullable' => False),
			'cmd_creator_email' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'cmd_created' => array('type' => 'int','precision' => '8','nullable' => False),
			'cmd_type' => array('type' => 'varchar','precision' => '32','nullable' => False,'default' => 'admin_cmd'),
			'cmd_status' => array('type' => 'int','precision' => '1'),
			'cmd_scheduled' => array('type' => 'int','precision' => '8'),
			'cmd_modified' => array('type' => 'int','precision' => '8'),
			'cmd_modifier' => array('type' => 'int','precision' => '4'),
			'cmd_modifier_email' => array('type' => 'varchar','precision' => '128'),
			'cmd_error' => array('type' => 'varchar','precision' => '255'),
			'cmd_errno' => array('type' => 'int','precision' => '4'),
			'cmd_requested' => array('type' => 'int','precision' => '4'),
			'cmd_requested_email' => array('type' => 'varchar','precision' => '128'),
			'cmd_comment' => array('type' => 'varchar','precision' => '255'),
			'cmd_data' => array('type' => 'blob')
		),
		'pk' => array('cmd_id'),
		'fk' => array(),
		'ix' => array('cmd_status','cmd_scheduled'),
		'uc' => array('cmd_uid')
	));
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.5.001';
}


function admin_upgrade1_5_001()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_admin_remote',array(
		'fd' => array(
			'remote_id' => array('type' => 'auto'),
			'remote_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'remote_hash' => array('type' => 'varchar','precision' => '32','nullable' => False),
			'remote_url' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'remote_domain' => array('type' => 'varchar','precision' => '64','nullable' => False)
		),
		'pk' => array('remote_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('remote_name')
	));

	return $GLOBALS['setup_info']['admin']['currentver'] = '1.5.002';
}


function admin_upgrade1_5_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_admin_queue','remote_id',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['admin']['currentver'] = '1.5.003';
}


function admin_upgrade1_5_003()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.6';
}


function admin_upgrade1_6()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.8';
}


/**
 * Change index page via setup.inc.php
 *
 * @return string
 */
function admin_upgrade1_8()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.9.001';
}


function admin_upgrade1_9_001()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '14.1';
}

function admin_upgrade14_1()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_admin_queue','cmd_uid',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_admin_queue','cmd_type',array(
		'type' => 'ascii',
		'precision' => '32',
		'nullable' => False,
		'default' => 'admin_cmd'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_admin_queue','cmd_data',array(
		'type' => 'ascii',
		'precision' => '16384'
	));

	return $GLOBALS['setup_info']['admin']['currentver'] = '14.2.001';
}


function admin_upgrade14_2_001()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_admin_remote','remote_hash',array(
		'type' => 'ascii',
		'precision' => '32',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_admin_remote','remote_url',array(
		'type' => 'ascii',
		'precision' => '128',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_admin_remote','remote_domain',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['admin']['currentver'] = '14.3';
}

/**
 * Remove cleartext passwords from egw_admin_queue
 *
 * @return string
 */
function admin_upgrade14_3()
{
	// asuming everythings not MySQL uses PostgreSQL regular expression syntax
	$regexp = substr($GLOBALS['egw_setup']->db->Type, 0, 5) == 'mysql' ? 'REGEXP' : '~*';

	foreach($GLOBALS['egw_setup']->db->select('egw_admin_queue', 'cmd_id,cmd_data',
		'cmd_status NOT IN ('.implode(',', admin_cmd::$require_pw_stati).") AND cmd_data $regexp '(pw|passwd\\_?\\d*|password|db\\_pass)\\?\"'",
		__LINE__, __FILE__, false, '', 'admin') as $row)
	{
		if (($masked = admin_cmd::mask_passwords($row['cmd_data'])) != $row['cmd'])
		{
			$GLOBALS['egw_setup']->db->update('egw_admin_queue', array('cmd_data' => $masked),
				array('cmd_id' => $row['cmd_id']), __LINE__, __FILE__, 'admin');
		}
	}
	return $GLOBALS['setup_info']['admin']['currentver'] = '14.3.001';
}

function admin_upgrade14_3_001()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '16.1';
}

function admin_upgrade16_1()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '17.1';
}

function admin_upgrade17_1()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_admin_queue','cmd_app',array(
		'type' => 'ascii',
		'precision' => '16',
		'comment' => 'affected app'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_admin_queue','cmd_account',array(
		'type' => 'int',
		'meta' => 'account',
		'precision' => '4',
		'comment' => 'affected account'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_admin_queue','cmd_rrule',array(
		'type' => 'varchar',
		'precision' => '128',
		'comment' => 'rrule for periodic execution'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_admin_queue','cmd_parent',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'cmd_id of periodic command'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_admin_queue','cmd_run',array(
		'type' => 'int',
		'meta' => 'timestamp',
		'precision' => '8',
		'comment' => 'periodic execution time'
	));

	// fill cmd_account/app from
	foreach($GLOBALS['egw_setup']->db->select('egw_admin_queue', 'cmd_id,cmd_data',
		"cmd_data LIKE '%\"account\":%' OR cmd_data LIKE '%\"app\":%'",
		__LINE__, __FILE__, false, '', 'admin') as $row)
	{
		$data = json_php_unserialize($row['cmd_data']);
		if (!empty($data['account']))
		{
			$row['cmd_account'] = $data['account'];
			unset($data['account']);
		}
		if (!empty($data['app']))
		{
			$row['cmd_app'] = $data['app'];
			unset($data['app']);
		}
		if (isset($row['cmd_account']) || isset($row['cmd_app']))
		{
			$cmd_id = $row['cmd_id'];
			unset($row['cmd_id']);
			$row['cmd_data'] = json_encode($data);
			$GLOBALS['egw_setup']->db->update('egw_admin_queue', $row, array('cmd_id' => $cmd_id), __LINE__, __FILE__, 'admin');
		}
	}

	return $GLOBALS['setup_info']['admin']['currentver'] = '18.1';
}

/**
 * Update admin_cmd_config to use "store_as_api" instead of "app"="phpgwapi" and "appname"
 *
 * @return string
 */
function admin_upgrade18_1()
{
	// fill cmd_account/app from
	foreach($GLOBALS['egw_setup']->db->select('egw_admin_queue', 'cmd_id,cmd_app,cmd_data', array(
			'cmd_app' => 'phpgwapi',
			'cmd_type' => 'admin_cmd_config',
		), __LINE__, __FILE__, false, '', 'admin') as $row)
	{
		$data = json_php_unserialize($row['cmd_data']);
		$data['store_as_api'] = $row['cmd_app'] === 'phpgwapi';
		$row['cmd_app'] = !empty($data['appname']) ? $data['appname'] : 'setup';
		unset($data['appname']);

		$cmd_id = $row['cmd_id'];
		unset($row['cmd_id']);
		$row['cmd_data'] = json_encode($data);
		$GLOBALS['egw_setup']->db->update('egw_admin_queue', $row,
			array('cmd_id' => $cmd_id), __LINE__, __FILE__, 'admin');
	}

	return $GLOBALS['setup_info']['admin']['currentver'] = '18.1.001';
}

/**
 * Bump version to 19.1
 *
 * @return string
 */
function admin_upgrade18_1_001()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '19.1';
}

/**
 * Bump version to 20.1
 *
 * @return string
 */
function admin_upgrade19_1()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '20.1';
}

/**
 * Bump version to 21.1
 *
 * @return string
 */
function admin_upgrade20_1()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '21.1';
}

/**
 * Bump version to 23.1
 *
 * @return string
 */
function admin_upgrade21_1()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '23.1';
}