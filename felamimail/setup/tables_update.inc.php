<?php
/**
 * EGroupware - FMail
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package felamimail
 * @subpackage setup
 * @version $Id$
 */

function felamimail_upgrade0_8_2()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_felamimail_cache','to_name',array('type' => 'varchar', 'precision' => 120));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_felamimail_cache','to_address',array('type' => 'varchar', 'precision' => 120));

	$GLOBALS['setup_info']['felamimail']['currentver'] = '0.8.3';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}

function felamimail_upgrade0_8_3()
{

	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_felamimail_cache','attachments',array('type' => 'varchar', 'precision' => 120));

	$GLOBALS['setup_info']['felamimail']['currentver'] = '0.8.4';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}

function felamimail_upgrade0_8_4()
{
	$GLOBALS['setup_info']['felamimail']['currentver'] = '0.9.0';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}

function felamimail_upgrade0_9_0()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_folderstatus', 'accountname', array('type' => 'varchar', 'precision' => 200, 'nullable' => false));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_cache', 'accountname', array('type' => 'varchar', 'precision' => 200, 'nullable' => false));

	$GLOBALS['setup_info']['felamimail']['currentver'] = '0.9.1';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}

function felamimail_upgrade0_9_1()
{
	$GLOBALS['setup_info']['felamimail']['currentver'] = '0.9.2';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}

function felamimail_upgrade0_9_2()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_felamimail_displayfilter',
		Array(
			'fd' => array(
				'accountid' 	=> array('type' => 'int', 'precision' => 4, 'nullable' => false),
				'filter' 	=> array('type' => 'text')
			),
			'pk' => array('accountid'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)

	);

	$GLOBALS['setup_info']['felamimail']['currentver'] = '0.9.3';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}

function felamimail_upgrade0_9_3()
{
	$GLOBALS['egw_setup']->oProc->DropTable('phpgw_felamimail_cache');
	$GLOBALS['egw_setup']->oProc->query('delete from phpgw_felamimail_folderstatus',__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_felamimail_cache',
		Array(
			'fd' => array(
				'accountid' 	=> array('type' => 'int', 'precision' => 4, 'nullable' => false),
				'hostname' 	=> array('type' => 'varchar', 'precision' => 60, 'nullable' => false),
				'accountname' 	=> array('type' => 'varchar', 'precision' => 200, 'nullable' => false),
				'foldername' 	=> array('type' => 'varchar', 'precision' => 200, 'nullable' => false),
				'uid' 		=> array('type' => 'int', 'precision' => 4, 'nullable' => false),
				'subject'	=> array('type' => 'text'),
				'striped_subject'=> array('type' => 'text'),
				'sender_name'	=> array('type' => 'varchar', 'precision' => 120),
				'sender_address'=> array('type' => 'varchar', 'precision' => 120),
				'to_name'	=> array('type' => 'varchar', 'precision' => 120),
				'to_address'	=> array('type' => 'varchar', 'precision' => 120),
				'date'		=> array('type' => 'varchar', 'precision' => 120),
				'size'		=> array('type' => 'int', 'precision' => 4),
				'attachments'	=> array('type' => 'varchar', 'precision' =>120)
			),
			'pk' => array('accountid','hostname','accountname','foldername','uid'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);

	$GLOBALS['setup_info']['felamimail']['currentver'] = '0.9.4';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade0_9_4()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_cache','accountname',array(
		'type' => 'varchar',
		'precision' => '25',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_cache','date',array(
		'type' => 'int',
		'precision' => '8'
	));

	$GLOBALS['setup_info']['felamimail']['currentver'] = '0.9.5';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade0_9_5()
{
	$GLOBALS['setup_info']['felamimail']['currentver'] = '1.0.0';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade1_0_0()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','accountid','fmail_accountid');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','hostname','fmail_hostname');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','accountname','fmail_accountname');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','foldername','fmail_foldername');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','uid','fmail_uid');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','subject','fmail_subject');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','striped_subject','fmail_striped_subject');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','sender_name','fmail_sender_name');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','sender_address','fmail_sender_address');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','to_name','fmail_to_name');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','to_address','fmail_to_address');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','date','fmail_date');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','size','fmail_size');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_cache','attachments','fmail_attachments');

	$GLOBALS['setup_info']['felamimail']['currentver'] = '1.0.0.001';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade1_0_0_001()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','accountid','fmail_accountid');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','hostname','fmail_hostname');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','accountname','fmail_accountname');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','foldername','fmail_foldername');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','messages','fmail_messages');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','recent','fmail_recent');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','unseen','fmail_unseen');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','uidnext','fmail_uidnext');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_folderstatus','uidvalidity','fmail_uidvalidity');

	$GLOBALS['setup_info']['felamimail']['currentver'] = '1.0.0.002';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade1_0_0_002()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_displayfilter','accountid','fmail_filter_accountid');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_felamimail_displayfilter','filter','fmail_filter_data');

	$GLOBALS['setup_info']['felamimail']['currentver'] = '1.0.0.003';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade1_0_0_003()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_cache', 'fmail_accountname', array('type' => 'varchar','precision' => '200','nullable' => False));

	$GLOBALS['setup_info']['felamimail']['currentver'] = '1.0.0.004';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade1_0_0_004()
{
	// index was to big for mysql with charset utf8 (max 1000byte = 333 utf8 chars)
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_cache','fmail_accountname',array(
		'type' => 'varchar',
		'precision' => '128',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_cache','fmail_foldername',array(
		'type' => 'varchar',
		'precision' => '128',
		'nullable' => False
	));

	$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_felamimail_cache','egw_felamimail_cache');

	$GLOBALS['setup_info']['felamimail']['currentver'] = '1.0.0.005';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade1_0_0_005()
{
	// index was to big for mysql with charset utf8 (max 1000byte = 333 utf8 chars)
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_folderstatus','fmail_accountname',array(
		'type' => 'varchar',
		'precision' => '128',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_folderstatus','fmail_foldername',array(
		'type' => 'varchar',
		'precision' => '128',
		'nullable' => False
	));

	$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_felamimail_folderstatus','egw_felamimail_folderstatus');
	$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_felamimail_displayfilter','egw_felamimail_displayfilter');

	$GLOBALS['setup_info']['felamimail']['currentver'] = '1.0.0.006';
	return $GLOBALS['setup_info']['felamimail']['currentver'];
}


function felamimail_upgrade1_0_0_006()
{
	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.2';
}


function felamimail_upgrade1_2()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('fm_accounts',array(
		'fd' => array(
			'fm_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'fm_id' => array('type' => 'auto'),
			'fm_realname' => array('type' => 'varchar','precision' => '128'),
			'fm_organization' => array('type' => 'varchar','precision' => '128'),
			'fm_emailaddress' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'fm_ic_hostname' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'fm_ic_port' => array('type' => 'int','precision' => '4','nullable' => False),
			'fm_ic_username' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'fm_ic_password' => array('type' => 'varchar','precision' => '128'),
			'fm_ic_encryption' => array('type' => 'bool','nullable' => False),
			'fm_og_hostname' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'fm_og_port' => array('type' => 'int','precision' => '4','nullable' => False),
			'fm_og_smtpauth' => array('type' => 'bool','nullable' => False),
			'fm_og_username' => array('type' => 'varchar','precision' => '128'),
			'fm_og_password' => array('type' => 'varchar','precision' => '128')
		),
		'pk' => array('fm_id'),
		'fk' => array(),
		'ix' => array('fm_owner'),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.2.001';
}


function felamimail_upgrade1_2_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('fm_accounts','fm_active',array(
		'type' => 'bool',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.2.002';
}


function felamimail_upgrade1_2_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('fm_accounts','fm_validatecertificate',array(
		'type' => 'bool',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.2.003';
}


function felamimail_upgrade1_2_003()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('fm_accounts','fm_validatecertificate','fm_ic_validatecertificate');

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.2.004';
}


function felamimail_upgrade1_2_004()
{
	$GLOBALS['egw_setup']->oProc->query('delete from egw_felamimail_folderstatus',__LINE__,__FILE__);

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.3.000';
}


function felamimail_upgrade1_3_000()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_cache','fmail_sender_name',array(
		'type' => 'varchar',
		'precision' => '256'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_cache','fmail_sender_address',array(
		'type' => 'varchar',
		'precision' => '256'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_cache','fmail_to_name',array(
		'type' => 'varchar',
		'precision' => '256'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_cache','fmail_to_address',array(
		'type' => 'varchar',
		'precision' => '256'
	));

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.3.001';
}


function felamimail_upgrade1_3_001()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('fm_accounts','egw_felamimail_accounts');

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.3.002';
}


function felamimail_upgrade1_3_002()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_felamimail_signatures',array(
		'fd' => array(
			'fm_signatureid' => array('type' => 'auto'),
			'fm_accountid' => array('type' => 'int','precision' => '11'),
			'fm_signature' => array('type' => 'text'),
			'fm_description' => array('type' => 'varchar','precision' => '255')
		),
		'pk' => array('fm_signatureid'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('fm_signatureid','fm_accountid'))
	));

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.3.003';
}


function felamimail_upgrade1_3_003()
{
	#$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_ic_encryption',array(
	#	'type' => 'int',
	#	'precision' => '4',
	#	'nullable' => False
	#));

	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_felamimail_accounts',array(
 		'fd' => array(
				'fm_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'fm_id' => array('type' => 'auto'),
				'fm_realname' => array('type' => 'varchar','precision' => '128'),
				'fm_organization' => array('type' => 'varchar','precision' => '128'),
				'fm_emailaddress' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'fm_ic_hostname' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'fm_ic_port' => array('type' => 'int','precision' => '4','nullable' => False),
				'fm_ic_username' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'fm_ic_password' => array('type' => 'varchar','precision' => '128'),
				'fm_ic_encryption' => array('type' => 'int','precision' => '4'),
				'fm_og_hostname' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'fm_og_port' => array('type' => 'int','precision' => '4','nullable' => False),
				'fm_og_smtpauth' => array('type' => 'bool','nullable' => False),
				'fm_og_username' => array('type' => 'varchar','precision' => '128'),
				'fm_og_password' => array('type' => 'varchar','precision' => '128'),
				'fm_active' => array('type' => 'bool','nullable' => False),
				'fm_ic_validatecertificate' => array('type' => 'bool','nullable' => False),
			),
		'pk' => array('fm_id'),
		'fk' => array(),
		'ix' => array('fm_owner'),
		'uc' => array()
		), array(
			'fm_ic_encryption' => "CASE WHEN fm_ic_encryption THEN 1 ELSE 0 END",
		)
	);

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.3.004';
}


function felamimail_upgrade1_3_004()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_signatures','fm_defaultsignature',array(
		'type' => 'bool'
	));

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.3.005';
}


function felamimail_upgrade1_3_005()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_ic_enable_sieve',array(
		'type' => 'bool',
		'precision' => '255'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_ic_sieve_server',array(
		'type' => 'varchar',
		'precision' => '128'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_ic_sieve_port',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.3.006';
}


function felamimail_upgrade1_3_006()
{
	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.4';
}


function felamimail_upgrade1_4()
{
	$GLOBALS['egw_setup']->oProc->DropTable('egw_felamimail_cache');
	$GLOBALS['egw_setup']->oProc->DropTable('egw_felamimail_displayfilter');
	$GLOBALS['egw_setup']->oProc->DropTable('egw_felamimail_folderstatus');
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_signatureid',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.5.001';
}


function felamimail_upgrade1_5_001()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_felamimail_displayfilter',
            Array(
                'fd' => array(
                    'fmail_filter_accountid'     => array('type' => 'int', 'precision' => 4, 'nullable' => false),
                    'fmail_filter_data'    => array('type' => 'text')
                ),
                'pk' => array('fmail_filter_accountid'),
                'fk' => array(),
                'ix' => array(),
                'uc' => array()
            )
	);
	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.5.002';
}


function felamimail_upgrade1_5_002()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_ic_encryption',array(
		'type' => 'int',
		'precision' => '4',
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_ic_hostname',array(
		'type' => 'varchar',
		'precision' => '128',
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_ic_port',array(
		'type' => 'int',
		'precision' => '4',
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_ic_username',array(
		'type' => 'varchar',
		'precision' => '128',
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_ic_validatecertificate',array(
		'type' => 'bool',
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_og_hostname',array(
		'type' => 'varchar',
		'precision' => '128',
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_og_port',array(
		'type' => 'int',
		'precision' => '4',
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_felamimail_accounts','fm_og_smtpauth',array(
		'type' => 'bool',
	));
	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.5.003';
}


function felamimail_upgrade1_5_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_ic_folderstoshowinhome',  array('type' => 'text'));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_ic_sentfolder', array('type' => 'varchar','precision' => '128'));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_ic_trashfolder',  array('type' => 'varchar','precision' => '128'));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_ic_draftfolder',  array('type' => 'varchar','precision' => '128'));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_felamimail_accounts','fm_ic_templatefolder',  array('type' => 'varchar','precision' => '128'));
	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.6.001';
}


function felamimail_upgrade1_6_001()
{
	// no real changes here
	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.7.001';
}


function felamimail_upgrade1_7_001()
{
	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.8';
}


/**
 * Setting new index url with "&ajax=true" appended (via setup.inc.php)
 */
function felamimail_upgrade1_8()
{
	return $GLOBALS['setup_info']['felamimail']['currentver'] = '1.9';
}
