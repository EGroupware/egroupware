<?php
/**
 * EGroupware EMailAdmin - DB schema
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke
 * @author Klaus Leithoff <kl@stylite.de>
 * @package emailadmin
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function emailadmin_upgrade0_0_3()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','smtpType', array('type' => 'int', 'precision' => 4));

	return $setup_info['emailadmin']['currentver'] = '0.0.4';
}


function emailadmin_upgrade0_0_4()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','defaultDomain', array('type' => 'varchar', 'precision' => 100));

	return $setup_info['emailadmin']['currentver'] = '0.0.5';
}


function emailadmin_upgrade0_0_5()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','organisationName', array('type' => 'varchar', 'precision' => 100));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','userDefinedAccounts', array('type' => 'varchar', 'precision' => 3));

	return $setup_info['emailadmin']['currentver'] = '0.0.6';
}


function emailadmin_upgrade0_0_6()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','oldimapcclient',array(
		'type' => 'varchar',
		'precision' => '3'
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '0.0.007';
}


function emailadmin_upgrade0_0_007()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_emailadmin','oldimapcclient','imapoldcclient');

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '0.0.008';
}


function emailadmin_upgrade0_0_008()
{
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.0.0';
}


function emailadmin_upgrade1_0_0()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','editforwardingaddress',array(
		'type' => 'varchar',
		'precision' => '3'
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.0.1';
}


function emailadmin_upgrade1_0_1()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','ea_order', array('type' => 'int', 'precision' => 4));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.0.2';
}


function emailadmin_upgrade1_0_2()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','ea_appname', array('type' => 'varchar','precision' => '80'));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','ea_group', array('type' => 'varchar','precision' => '80'));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.0.3';
}


function emailadmin_upgrade1_0_3()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_emailadmin','egw_emailadmin');

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.2';
}


function emailadmin_upgrade1_2()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','profileID','ea_profile_id');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpServer','ea_smtp_server');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpType','ea_smtp_type');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpPort','ea_smtp_port');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpAuth','ea_smtp_auth');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','editforwardingaddress','ea_editforwardingaddress');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpLDAPServer','ea_smtp_ldap_server');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpLDAPBaseDN','ea_smtp_ldap_basedn');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpLDAPAdminDN','ea_smtp_ldap_admindn');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpLDAPAdminPW','ea_smtp_ldap_adminpw');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','smtpLDAPUseDefault','ea_smtp_ldap_use_default');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapServer','ea_imap_server');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapType','ea_imap_type');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapPort','ea_imap_port');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapLoginType','ea_imap_login_type');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapTLSAuthentication','ea_imap_tsl_auth');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapTLSEncryption','ea_imap_tsl_encryption');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapEnableCyrusAdmin','ea_imap_enable_cyrus');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapAdminUsername','ea_imap_admin_user');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapAdminPW','ea_imap_admin_pw');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapEnableSieve','ea_imap_enable_sieve');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapSieveServer','ea_imap_sieve_server');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapSievePort','ea_imap_sieve_port');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','description','ea_description');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','defaultDomain','ea_default_domain');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','organisationName','ea_organisation_name');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','userDefinedAccounts','ea_user_defined_accounts');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_emailadmin','imapoldcclient','ea_imapoldcclient');

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.2.001';
}


function emailadmin_upgrade1_2_001()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_smtp_auth_username',array(
		'type' => 'varchar',
		'precision' => '80'
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_smtp_auth_password',array(
		'type' => 'varchar',
		'precision' => '80'
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_emailadmin',array(
		'fd' => array(
			'ea_profile_id' => array('type' => 'auto','nullable' => False),
			'ea_smtp_server' => array('type' => 'varchar','precision' => '80'),
			'ea_smtp_type' => array('type' => 'int','precision' => '4'),
			'ea_smtp_port' => array('type' => 'int','precision' => '4'),
			'ea_smtp_auth' => array('type' => 'varchar','precision' => '3'),
			'ea_editforwardingaddress' => array('type' => 'varchar','precision' => '3'),
			'ea_smtp_ldap_server' => array('type' => 'varchar','precision' => '80'),
			'ea_smtp_ldap_basedn' => array('type' => 'varchar','precision' => '200'),
			'ea_smtp_ldap_admindn' => array('type' => 'varchar','precision' => '200'),
			'ea_smtp_ldap_adminpw' => array('type' => 'varchar','precision' => '30'),
			'ea_smtp_ldap_use_default' => array('type' => 'varchar','precision' => '3'),
			'ea_imap_server' => array('type' => 'varchar','precision' => '80'),
			'ea_imap_type' => array('type' => 'int','precision' => '4'),
			'ea_imap_port' => array('type' => 'int','precision' => '4'),
			'ea_imap_login_type' => array('type' => 'varchar','precision' => '20'),
			'ea_imap_tsl_auth' => array('type' => 'varchar','precision' => '3'),
			'ea_imap_tsl_encryption' => array('type' => 'varchar','precision' => '3'),
			'ea_imap_enable_cyrus' => array('type' => 'varchar','precision' => '3'),
			'ea_imap_admin_user' => array('type' => 'varchar','precision' => '40'),
			'ea_imap_admin_pw' => array('type' => 'varchar','precision' => '40'),
			'ea_imap_enable_sieve' => array('type' => 'varchar','precision' => '3'),
			'ea_imap_sieve_server' => array('type' => 'varchar','precision' => '80'),
			'ea_imap_sieve_port' => array('type' => 'int','precision' => '4'),
			'ea_description' => array('type' => 'varchar','precision' => '200'),
			'ea_default_domain' => array('type' => 'varchar','precision' => '100'),
			'ea_organisation_name' => array('type' => 'varchar','precision' => '100'),
			'ea_user_defined_accounts' => array('type' => 'varchar','precision' => '3'),
			'ea_imapoldcclient' => array('type' => 'varchar','precision' => '3'),
			'ea_order' => array('type' => 'int','precision' => '4'),
			'ea_appname' => array('type' => 'varchar','precision' => '80'),
			'ea_group' => array('type' => 'varchar','precision' => '80'),
			'ea_smtp_auth_username' => array('type' => 'varchar','precision' => '80'),
			'ea_smtp_auth_password' => array('type' => 'varchar','precision' => '80')
		),
		'pk' => array('ea_profile_id'),
		'fk' => array(),
		'ix' => array('ea_appname','ea_group'),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.2.002';
}


function emailadmin_upgrade1_2_002()
{
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.4';
}


function emailadmin_upgrade1_4()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_user_defined_signatures',array(
		'type' => 'varchar',
		'precision' => '3'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_default_signature',array(
		'type' => 'varchar',
		'precision' => '255'
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.4.001';
}


function emailadmin_upgrade1_4_001()
{
    $GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_user_defined_identities',array(
        'type' => 'varchar',
        'precision' => '3'
    ));

    return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.5.001';
}


function emailadmin_upgrade1_5_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_user',array(
		'type' => 'varchar',
		'precision' => '80'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_active',array(
		'type' => 'int',
		'precision' => '4'
	));
	$GLOBALS['phpgw_setup']->oProc->query("UPDATE egw_emailadmin set ea_user='0', ea_active=1",__LINE__,__FILE__);
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.5.002';
}


function emailadmin_upgrade1_5_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_imap_auth_username',array(
		'type' => 'varchar',
		'precision' => '80'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_imap_auth_password',array(
		'type' => 'varchar',
		'precision' => '80'
	));
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.5.003';
}


function emailadmin_upgrade1_5_003()
{
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.5.004';
}


function emailadmin_upgrade1_5_004()
{
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.6';
}


function emailadmin_upgrade1_6()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_emailadmin','ea_default_signature',array(
		'type' => 'text'
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.6.001';
}

function emailadmin_upgrade1_6_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_emailadmin','ea_stationery_active_templates',array(
		'type' => 'text'
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.8';	// was '1.7.003';
}

function emailadmin_upgrade1_7_003()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_emailadmin','ea_imap_type',array(
		'type' => 'varchar',
		'precision' => 56,
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_emailadmin','ea_smtp_type',array(
		'type' => 'varchar',
		'precision' => 56,
	));
	foreach (array('1'=>'defaultsmtp', '2'=>'postfixldap', '3'=>'postfixinetorgperson', '4'=>'smtpplesk', '5' =>'postfixdbmailuser') as $id => $newtype)
	{
		$GLOBALS['egw_setup']->oProc->query('update egw_emailadmin set ea_smtp_type=\''.$newtype.'\' where ea_smtp_type=\''.$id.'\'',__LINE__,__FILE__);
	}
	foreach (array('2'=>'defaultimap', '3'=>'cyrusimap', '4'=>'dbmailqmailuser', '5'=>'pleskimap', '6' =>'dbmaildbmailuser') as $id => $newtype)
	{
		$GLOBALS['egw_setup']->oProc->query('update egw_emailadmin set ea_imap_type=\''.$newtype.'\' where ea_imap_type=\''.$id.'\'',__LINE__,__FILE__);
	}
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.001';	// was '1.7.004';
}

function emailadmin_upgrade1_8()
{
	emailadmin_upgrade1_7_003();
	
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.001';
}
	
function emailadmin_upgrade1_7_004()
{
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.001';
}

function emailadmin_upgrade1_9_001()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_emailadmin',array(
		'fd' => array(
				'ea_profile_id' => array('type' => 'auto','nullable' => False),
				'ea_smtp_server' => array('type' => 'varchar','precision' => '80'),
				'ea_smtp_type' => array('type' => 'varchar','precision' => '56'),
				'ea_smtp_port' => array('type' => 'int','precision' => '4'),
				'ea_smtp_auth' => array('type' => 'varchar','precision' => '3'),
				'ea_editforwardingaddress' => array('type' => 'varchar','precision' => '3'),
				'ea_smtp_ldap_server' => array('type' => 'varchar','precision' => '80'),
				'ea_smtp_ldap_basedn' => array('type' => 'varchar','precision' => '200'),
				'ea_smtp_ldap_admindn' => array('type' => 'varchar','precision' => '200'),
				'ea_smtp_ldap_adminpw' => array('type' => 'varchar','precision' => '30'),
				'ea_smtp_ldap_use_default' => array('type' => 'varchar','precision' => '3'),
				'ea_imap_server' => array('type' => 'varchar','precision' => '80'),
				'ea_imap_type' => array('type' => 'varchar','precision' => '56'),
				'ea_imap_port' => array('type' => 'int','precision' => '4'),
				'ea_imap_login_type' => array('type' => 'varchar','precision' => '20'),
				'ea_imap_tsl_auth' => array('type' => 'varchar','precision' => '3'),
				'ea_imap_tsl_encryption' => array('type' => 'varchar','precision' => '3'),
				'ea_imap_enable_cyrus' => array('type' => 'varchar','precision' => '3'),
				'ea_imap_admin_user' => array('type' => 'varchar','precision' => '40'),
				'ea_imap_admin_pw' => array('type' => 'varchar','precision' => '40'),
				'ea_imap_enable_sieve' => array('type' => 'varchar','precision' => '3'),
				'ea_imap_sieve_server' => array('type' => 'varchar','precision' => '80'),
				'ea_imap_sieve_port' => array('type' => 'int','precision' => '4'),
				'ea_description' => array('type' => 'varchar','precision' => '200'),
				'ea_default_domain' => array('type' => 'varchar','precision' => '100'),
				'ea_organisation_name' => array('type' => 'varchar','precision' => '100'),
				'ea_user_defined_identities' => array('type' => 'varchar','precision' => '3'),
				'ea_user_defined_accounts' => array('type' => 'varchar','precision' => '3'),
				'ea_order' => array('type' => 'int','precision' => '4'),
				'ea_appname' => array('type' => 'varchar','precision' => '80'),
				'ea_group' => array('type' => 'varchar','precision' => '80'),
				'ea_user' => array('type' => 'varchar','precision' => '80'),
				'ea_active' => array('type' => 'int','precision' => '4'),
				'ea_smtp_auth_username' => array('type' => 'varchar','precision' => '80'),
				'ea_smtp_auth_password' => array('type' => 'varchar','precision' => '80'),
				'ea_user_defined_signatures' => array('type' => 'varchar','precision' => '3'),
				'ea_default_signature' => array('type' => 'text'),
				'ea_imap_auth_username' => array('type' => 'varchar','precision' => '80'),
				'ea_imap_auth_password' => array('type' => 'varchar','precision' => '80'),
				'ea_stationery_active_templates' => array('type' => 'text')
		),
		'pk' => array('ea_profile_id'),
		'fk' => array(),
		'ix' => array('ea_appname','ea_group'),
		'uc' => array()
	));
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.002';
}
