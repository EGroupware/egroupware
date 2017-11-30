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


/**
 * Add contact_files bit-field and strip jpeg photo, PGP & S/Mime pubkeys from table
 *
 * @return string
 */
function api_upgrade16_9_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook','contact_files',array(
		'type' => 'int',
		'precision' => '1',
		'default' => '0',
		'comment' => '&1: photo, &2: pgp, &4: smime'
	));

	$junk_size = 100;
	$total = 0;
	Api\Vfs::$is_root = true;
	do {
		$n = 0;
		foreach($GLOBALS['egw_setup']->db->query("SELECT contact_id,contact_jpegphoto,contact_pubkey
FROM egw_addressbook
WHERE contact_jpegphoto != '' OR contact_pubkey IS NOT NULL AND contact_pubkey LIKE '%-----%'",
			__LINE__, __FILE__, 0, $junk_size, false, Api\Db::FETCH_ASSOC) as $row)
		{
			$files = 0;
			$contact_id = $row['contact_id'];
			unset($row['contact_id']);
			if ($row['contact_jpegphoto'] && ($fp = Api\Vfs::string_stream($row['contact_jpegphoto'])))
			{
				if (Api\Link::attach_file('addressbook', $contact_id, array(
					'name' => Api\Contacts::FILES_PHOTO,
					'type' => 'image/jpeg',
					'tmp_name' => $fp,
				)))
				{
					$files |= Api\Contacts::FILES_BIT_PHOTO;
					$row['contact_jpegphoto'] = null;
				}
				fclose($fp);
			}
			foreach(array(
				array(addressbook_bo::$pgp_key_regexp, Api\Contacts::FILES_PGP_PUBKEY, Api\Contacts::FILES_BIT_PGP_PUBKEY, 'application/pgp-keys'),
				array(Api\Mail\Smime::$certificate_regexp, Api\Contacts::FILES_SMIME_PUBKEY, Api\Contacts::FILES_BIT_SMIME_PUBKEY, 'application/x-pem-file'),
			) as $data)
			{
				list($regexp, $file, $bit, $mime) = $data;
				$matches = null;
				if ($row['contact_pubkey'] && preg_match($regexp, $row['contact_pubkey'], $matches) &&
					($fp = Api\Vfs::string_stream($matches[0])))
				{
					if (Api\Link::attach_file('addressbook', $contact_id, array(
						'name' => $file,
						'type' => $mime,
						'tmp_name' => $fp,
					)))
					{
						$files |= $bit;
						$row['contact_pubkey'] = str_replace($matches[0], '', $row['contact_pubkey']);
					}
					fclose($fp);
				}
			}
			if (!trim($row['contact_pubkey'])) $row['contact_pubkey'] = null;

			if ($files)
			{
				$GLOBALS['egw_setup']->db->query('UPDATE egw_addressbook SET '.
					$GLOBALS['egw_setup']->db->column_data_implode(',', $row, true).
					',contact_files='.$files.' WHERE contact_id='.$contact_id, __LINE__, __FILE__);
				$total++;
			}
			$n++;
		}
	}
	while($n == $junk_size);
	Api\Vfs::$is_root = false;

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.003';
}

/**
 * Drop contact_jpegphoto column
 *
 * @return string
 */
function api_upgrade16_9_003()
{
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_addressbook',array(
		'fd' => array(
			'contact_id' => array('type' => 'auto','nullable' => False),
			'contact_tid' => array('type' => 'char','precision' => '1','default' => 'n'),
			'contact_owner' => array('type' => 'int','meta' => 'account','precision' => '8','nullable' => False,'comment' => 'account or group id of the adressbook'),
			'contact_private' => array('type' => 'int','precision' => '1','default' => '0','comment' => 'privat or personal'),
			'cat_id' => array('type' => 'ascii','meta' => 'category','precision' => '255','comment' => 'Category(s)'),
			'n_family' => array('type' => 'varchar','precision' => '64','comment' => 'Family name'),
			'n_given' => array('type' => 'varchar','precision' => '64','comment' => 'Given Name'),
			'n_middle' => array('type' => 'varchar','precision' => '64'),
			'n_prefix' => array('type' => 'varchar','precision' => '64','comment' => 'Prefix'),
			'n_suffix' => array('type' => 'varchar','precision' => '64','comment' => 'Suffix'),
			'n_fn' => array('type' => 'varchar','precision' => '128','comment' => 'Full name'),
			'n_fileas' => array('type' => 'varchar','precision' => '255','comment' => 'sort as'),
			'contact_bday' => array('type' => 'varchar','precision' => '12','comment' => 'Birtday'),
			'org_name' => array('type' => 'varchar','precision' => '128','comment' => 'Organisation'),
			'org_unit' => array('type' => 'varchar','precision' => '64','comment' => 'Department'),
			'contact_title' => array('type' => 'varchar','precision' => '64','comment' => 'jobtittle'),
			'contact_role' => array('type' => 'varchar','precision' => '64','comment' => 'role'),
			'contact_assistent' => array('type' => 'varchar','precision' => '64','comment' => 'Name of the Assistent (for phone number)'),
			'contact_room' => array('type' => 'varchar','precision' => '64','comment' => 'room'),
			'adr_one_street' => array('type' => 'varchar','precision' => '64','comment' => 'street (business)'),
			'adr_one_street2' => array('type' => 'varchar','precision' => '64','comment' => 'street (business) - 2. line'),
			'adr_one_locality' => array('type' => 'varchar','precision' => '64','comment' => 'city (business)'),
			'adr_one_region' => array('type' => 'varchar','precision' => '64','comment' => 'region (business)'),
			'adr_one_postalcode' => array('type' => 'varchar','precision' => '64','comment' => 'postalcode (business)'),
			'adr_one_countryname' => array('type' => 'varchar','precision' => '64','comment' => 'countryname (business)'),
			'contact_label' => array('type' => 'text','comment' => 'currently not used'),
			'adr_two_street' => array('type' => 'varchar','precision' => '64','comment' => 'street (private)'),
			'adr_two_street2' => array('type' => 'varchar','precision' => '64','comment' => 'street (private) - 2. line'),
			'adr_two_locality' => array('type' => 'varchar','precision' => '64','comment' => 'city (private)'),
			'adr_two_region' => array('type' => 'varchar','precision' => '64','comment' => 'region (private)'),
			'adr_two_postalcode' => array('type' => 'varchar','precision' => '64','comment' => 'postalcode (private)'),
			'adr_two_countryname' => array('type' => 'varchar','precision' => '64','comment' => 'countryname (private)'),
			'tel_work' => array('type' => 'varchar','precision' => '40','comment' => 'phone-number (business)'),
			'tel_cell' => array('type' => 'varchar','precision' => '40','comment' => 'mobil phone (business)'),
			'tel_fax' => array('type' => 'varchar','precision' => '40','comment' => 'fax-number (business)'),
			'tel_assistent' => array('type' => 'varchar','precision' => '40','comment' => 'phone-number assistent'),
			'tel_car' => array('type' => 'varchar','precision' => '40'),
			'tel_pager' => array('type' => 'varchar','precision' => '40','comment' => 'pager'),
			'tel_home' => array('type' => 'varchar','precision' => '40','comment' => 'phone-number (private)'),
			'tel_fax_home' => array('type' => 'varchar','precision' => '40','comment' => 'fax-number (private)'),
			'tel_cell_private' => array('type' => 'varchar','precision' => '40','comment' => 'mobil phone (private)'),
			'tel_other' => array('type' => 'varchar','precision' => '40','comment' => 'other phone'),
			'tel_prefer' => array('type' => 'varchar','precision' => '32','comment' => 'prefered phone-number'),
			'contact_email' => array('type' => 'varchar','precision' => '128','comment' => 'email address (business)'),
			'contact_email_home' => array('type' => 'varchar','precision' => '128','comment' => 'email address (private)'),
			'contact_url' => array('type' => 'varchar','precision' => '128','comment' => 'website (business)'),
			'contact_url_home' => array('type' => 'varchar','precision' => '128','comment' => 'website (private)'),
			'contact_freebusy_uri' => array('type' => 'ascii','precision' => '128','comment' => 'freebusy-url for calendar of the contact'),
			'contact_calendar_uri' => array('type' => 'ascii','precision' => '128','comment' => 'url for users calendar - currently not used'),
			'contact_note' => array('type' => 'varchar','precision' => '8192','comment' => 'notes field'),
			'contact_tz' => array('type' => 'varchar','precision' => '8','comment' => 'timezone difference'),
			'contact_geo' => array('type' => 'ascii','precision' => '32','comment' => 'currently not used'),
			'contact_pubkey' => array('type' => 'ascii','precision' => '16384','comment' => 'public key'),
			'contact_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'timestamp of the creation'),
			'contact_creator' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'account id of the creator'),
			'contact_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'timestamp of the last modified'),
			'contact_modifier' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'account id of the last modified'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'account id'),
			'contact_etag' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'etag of the changes'),
			'contact_uid' => array('type' => 'ascii','precision' => '128','comment' => 'unique id of the contact'),
			'adr_one_countrycode' => array('type' => 'ascii','precision' => '2','comment' => 'countrycode (business)'),
			'adr_two_countrycode' => array('type' => 'ascii','precision' => '2','comment' => 'countrycode (private)'),
			'carddav_name' => array('type' => 'ascii','precision' => '128','comment' => 'name part of CardDAV URL, if specified by client'),
			'contact_files' => array('type' => 'int','precision' => '1','default' => '0','comment' => '&1: photo, &2: pgp, &4: smime')
		),
		'pk' => array('contact_id'),
		'fk' => array(),
		'ix' => array('contact_owner','cat_id','n_fileas','contact_modified','contact_uid','carddav_name',array('n_family','n_given'),array('n_given','n_family'),array('org_name','n_family','n_given')),
		'uc' => array('account_id')
	),'contact_jpegphoto');

	return $GLOBALS['setup_info']['api']['currentver'] = '16.9.004';
}

/**
 * Bump version to 17.1
 *
 * @return string
 */
function api_upgrade16_9_004()
{
	return $GLOBALS['setup_info']['api']['currentver'] = '17.1';
}
