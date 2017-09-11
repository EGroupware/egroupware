<?php
/**
 * EGroupware - API Setup
 *
 * Update scripts from 16.1 onwards
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Remove rests of EMailAdmin or install 14.1 tables for update from before 14.1
 *
 * 14.3.907 is the version set by setup, if api is not installed in 16.1 upgrade
 *
 * @return string
 */
function api_upgrade14_3_907()
{
	// check if EMailAdmin tables are there and create them if not
	$tables = $GLOBALS['egw_setup']->db->table_names(true);
	$phpgw_baseline = array();
	include (__DIR__.'/tables_current.inc.php');
	foreach($phpgw_baseline as $table => $definition)
	{
		if (!in_array($table, $tables))
		{
			$GLOBALS['egw_setup']->oProc->CreateTable($table, $definition);
		}
	}

	// uninstall no longer existing EMailAdmin
	if (in_array('egw_emailadmin', $tables))
	{
		$GLOBALS['egw_setup']->oProc->DropTable('egw_emailadmin');
	}
	$GLOBALS['egw_setup']->deregister_app('emailadmin');

	// uninstall obsolete FelamiMail tables, if still around
	$done = 0;
	foreach(array_intersect($tables, array('egw_felamimail_accounts', 'egw_felamimail_displayfilter', 'egw_felamimail_signatures')) as $table)
	{
		$GLOBALS['egw_setup']->oProc->DropTable($table);

		if (!$done++) $GLOBALS['egw_setup']->deregister_app('felamimail');
	}

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1';
}

/**
 * Add archive folder to mail accounts
 *
 * @return string
 */
function api_upgrade16_1()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_folder_archive', array(
		'type' => 'varchar',
		'precision' => '128',
		'comment' => 'archive folder'
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.001';
}

/**
 * Fix home-accounts in egw_customfields and egw_links to api-accounts
 *
 * @return string
 */
function api_upgrade16_1_001()
{
	foreach(array(
		'cf_type' => 'egw_customfields',
		'link_app1' => 'egw_links',
		'link_app2' => 'egw_links',
	) as $col => $table)
	{
		$GLOBALS['egw_setup']->db->query("UPDATE $table SET $col='api-accounts' WHERE $col='home-accounts'", __LINE__, __FILE__);
	}
	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.002';
}

use EGroupware\Api\Vfs;

/**
 * Create /templates and subdirectories, if they dont exist
 *
 * They are create as part of the installation for new installations and allways exist in EPL.
 * If they dont exist, you can not save the preferences of the concerned applications, unless
 * you either manually create the directory or remove the path from the default preferences.
 *
 * @return string
 */
function api_upgrade16_1_002()
{
	$admins = $GLOBALS['egw_setup']->add_account('Admins','Admin','Group',False,False);

	Vfs::$is_root = true;
	foreach(array('','addressbook', 'calendar', 'infolog', 'tracker', 'timesheet', 'projectmanager', 'filemanager') as $app)
	{
		if ($app && !file_exists(EGW_SERVER_ROOT.'/'.$app)) continue;

		// create directory and set permissions: Admins writable and other readable
		$dir = '/templates'.($app ? '/'.$app : '');
		if (Vfs::file_exists($dir)) continue;

		Vfs::mkdir($dir, 075, STREAM_MKDIR_RECURSIVE);
		Vfs::chgrp($dir, abs($admins));
		Vfs::chmod($dir, 075);
	}
	Vfs::$is_root = false;

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.003';
}

/**
 * Change egw_ea_accounts.acc_further_identities from boolean to int(1)
 *
 * @return string new version
 */
function api_upgrade16_1_003()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_ea_accounts', 'acc_further_identities', 'further_bool');
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_further_identities',array(
		'type' => 'int',
		'precision' => '1',
		'nullable' => False,
		'default' => '1',
		'comment' => '0=no, 1=yes, 2=only matching aliases'
	));
	$GLOBALS['egw_setup']->oProc->query('UPDATE egw_ea_accounts SET acc_further_identities=0 WHERE NOT further_bool', __LINE__, __FILE__);
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_ea_accounts',
		$GLOBALS['egw_setup']->db->get_table_definitions('api', 'egw_ea_accounts'), 'further_bool');

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.004';
}

/**
 * Fix non-unique multi-column index on egw_sqlfs_props: fs_id, prop_namesape and prop_name
 *
 * Index needs to be unique as a WebDAV property can only have one value.
 *
 * MySQL REPLACE used in PROPPATCH otherwise inserts further rows instead of updating them,
 * which we also clean up here (MySQL only).
 *
 * @return string new version
 */
function api_upgrade16_1_004()
{
	// delete doublicate rows for identical attributes by only keeping oldest one / highest prop_id
	// this is only necessary for MySQL, as other DBs dont have REPLACE
	if ($GLOBALS['egw_setup']->db->Type == 'mysql')
	{
		$junk_size = 100;
		$total = 0;
		do {
			$n = 0;
			foreach($GLOBALS['egw_setup']->db->query('SELECT fs_id,prop_namespace,prop_name,MAX(prop_id) AS prop_id
FROM egw_sqlfs_props
GROUP BY fs_id,prop_namespace,prop_name
HAVING COUNT(*) > 1', __LINE__, __FILE__, 0, $junk_size, false, Api\Db::FETCH_ASSOC) as $row)
			{
				$prop_id = $row['prop_id'];
				unset($row['prop_id']);
				$GLOBALS['egw_setup']->db->delete('egw_sqlfs_props', $row+array('prop_id != '.(int)$prop_id), __LINE__, __FILE__);
				$total += $GLOBALS['egw_setup']->db->affected_rows();
				$n++;
			}
		}
		while($n == $junk_size);

		if ($total)
		{
			echo "Api Update 16.1.005: deleted $total doublicate rows from egw_sqlfs_props table.\n";

			// drop autoincrement (prop_id) and recreate it, in case it got to close to 32 bit limit
			$GLOBALS['egw_setup']->db->query('ALTER TABLE egw_sqlfs_props DROP prop_id', __LINE__, __FILE__);
			$GLOBALS['egw_setup']->db->query('ALTER TABLE egw_sqlfs_props ADD prop_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY', __LINE__, __FILE__);
		}
	}

	// drop non-unique index and re-create it as unique
	$GLOBALS['egw_setup']->oProc->DropIndex('egw_sqlfs_props', array('fs_id', 'prop_namespace', 'prop_name'));
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_sqlfs_props', array('fs_id', 'prop_namespace', 'prop_name'), true);

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.005';
}

/**
 * Update to 17.1 development as 16.9
 *
 * @return string
 */
function api_upgrade16_1_005()
{
	return $GLOBALS['setup_info']['api']['currentver'] = '16.9';
}

/**
 * Give egw_ea_credentials.cred_password size 9600 to accomodate private s/mime keys
 *
 * @return string
 */
function api_upgrade16_9()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_ea_credentials','cred_password',array(
		'type' => 'varchar',
		'precision' => '9600',
		'comment' => 'password encrypted'
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.001';
}

function api_upgrade16_9_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_folder_ham',array(
		'type' => 'varchar',
		'precision' => '128',
		'comment' => 'ham folder'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_spam_api',array(
		'type' => 'varchar',
		'precision' => '128',
		'comment' => 'SpamTitan API URL'
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.002';
}


function api_upgrade16_9_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_ews_type',array(
		'type' => 'varchar',
		'precision' => '128',
		'default' => 'inbox',
		'comment' => 'inbox/public_folders'
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.003';
}


function api_upgrade16_9_003()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_ea_ews',array(
		'fd' => array(
			'ews_profile' => array('type' => 'int','precision' => '11','nullable' => False,'comment' => 'ewg_ea_account, acc_id'),
			'ews_folder' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'Exchange Folder ID'),
			'ews_name' => array('type' => 'varchar','precision' => '100','nullable' => False,'comment' => 'Exchange Folder Name'),
			'ews_read_permission' => array('type' => 'bool','comment' => 'Permission to read folder'),
			'ews_write_permission' => array('type' => 'bool','comment' => 'Permission to write to folder'),
			'ews_delete_permission' => array('type' => 'bool','comment' => 'Permission to delete from folder'),
			'ews_is_default' => array('type' => 'bool','comment' => 'Default folder'),
			'ews_order' => array('type' => 'int','precision' => '5','comment' => 'Order to display in tree'),
			'ews_move_anywhere' => array('type' => 'bool','comment' => 'Permission to move emails between folders'),
			'ews_move_to' => array('type' => 'text','comment' => 'Array with only folders allowed to move emails to')
		),
		'pk' => array('ews_profile','ews_folder'),
		'fk' => array('ews_profile' => 'egw_ea_account.acc_id'),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.004';
}


function api_upgrade16_9_004()
{
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_ea_ews',array(
		'fd' => array(
			'ews_profile' => array('type' => 'int','precision' => '11','nullable' => False,'comment' => 'ewg_ea_account, acc_id'),
			'ews_folder' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'Exchange Folder ID'),
			'ews_name' => array('type' => 'varchar','precision' => '100','nullable' => False,'comment' => 'Exchange Folder Name'),
			'ews_write_permission' => array('type' => 'bool','comment' => 'Permission to write to folder'),
			'ews_delete_permission' => array('type' => 'bool','comment' => 'Permission to delete from folder'),
			'ews_is_default' => array('type' => 'bool','comment' => 'Default folder'),
			'ews_order' => array('type' => 'int','precision' => '5','comment' => 'Order to display in tree'),
			'ews_move_anywhere' => array('type' => 'bool','comment' => 'Permission to move emails between folders'),
			'ews_move_to' => array('type' => 'text','comment' => 'Array with only folders allowed to move emails to')
		),
		'pk' => array('ews_profile','ews_folder'),
		'fk' => array('ews_profile' => 'egw_ea_account.acc_id'),
		'ix' => array(),
		'uc' => array()
	),'ews_read_permission');
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_ea_ews',array(
		'fd' => array(
			'ews_profile' => array('type' => 'int','precision' => '11','nullable' => False,'comment' => 'ewg_ea_account, acc_id'),
			'ews_folder' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'Exchange Folder ID'),
			'ews_name' => array('type' => 'varchar','precision' => '100','nullable' => False,'comment' => 'Exchange Folder Name'),
			'ews_delete_permission' => array('type' => 'bool','comment' => 'Permission to delete from folder'),
			'ews_is_default' => array('type' => 'bool','comment' => 'Default folder'),
			'ews_order' => array('type' => 'int','precision' => '5','comment' => 'Order to display in tree'),
			'ews_move_anywhere' => array('type' => 'bool','comment' => 'Permission to move emails between folders'),
			'ews_move_to' => array('type' => 'text','comment' => 'Array with only folders allowed to move emails to')
		),
		'pk' => array('ews_profile','ews_folder'),
		'fk' => array('ews_profile' => 'egw_ea_account.acc_id'),
		'ix' => array(),
		'uc' => array()
	),'ews_write_permission');
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_ea_ews',array(
		'fd' => array(
			'ews_profile' => array('type' => 'int','precision' => '11','nullable' => False,'comment' => 'ewg_ea_account, acc_id'),
			'ews_folder' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'Exchange Folder ID'),
			'ews_name' => array('type' => 'varchar','precision' => '100','nullable' => False,'comment' => 'Exchange Folder Name'),
			'ews_is_default' => array('type' => 'bool','comment' => 'Default folder'),
			'ews_order' => array('type' => 'int','precision' => '5','comment' => 'Order to display in tree'),
			'ews_move_anywhere' => array('type' => 'bool','comment' => 'Permission to move emails between folders'),
			'ews_move_to' => array('type' => 'text','comment' => 'Array with only folders allowed to move emails to')
		),
		'pk' => array('ews_profile','ews_folder'),
		'fk' => array('ews_profile' => 'egw_ea_account.acc_id'),
		'ix' => array(),
		'uc' => array()
	),'ews_delete_permission');
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_ews','ews_permissions',array(
		'type' => 'text',
		'comment' => 'Array with folder permissions'
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.005';
}


function api_upgrade16_9_005()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_ews','ews_apply_permissions',array(
		'type' => 'bool',
		'comment' => 'Whether to apply extra permissions or not'
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.006';
}


function api_upgrade16_9_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_ews_apply_permissions',array(
		'type' => 'bool',
		'comment' => 'Always apply permissions '
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.007';
}

