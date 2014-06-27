<?php
/**
 * EGroupware EMailAdmin - DB schema
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Ralf Becker <rb@stylite.de>
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

function emailadmin_upgrade1_9_002()
{
	// convert serialized stationery templates setting to eTemplate store style
	foreach($GLOBALS['egw_setup']->db->query('SELECT ea_profile_id,ea_stationery_active_templates FROM egw_emailadmin
		WHERE ea_stationery_active_templates IS NOT NULL',__LINE__,__FILE__) as $row)
	{
		if(is_array(($templates=unserialize($row['ea_stationery_active_templates']))))
		{
			$GLOBALS['egw_setup']->db->query('UPDATE egw_emailadmin SET ea_stationery_active_templates="'.implode(',',$templates).'"'
				.' WHERE ea_profile_id='.(int)$row['ea_profile_id'],__LINE__,__FILE__);
		}
		unset($templates);
	}

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.003';
}

function emailadmin_upgrade1_9_003()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_emailadmin','ea_smtp_auth_username',array(
		'type' => 'varchar',
		'precision' => '128',
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.004';
}

function emailadmin_upgrade1_9_004()
{
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.005';
}

function emailadmin_upgrade1_9_005()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_mailaccounts',array(
		'fd' => array(
			'mail_id' => array('type' => 'auto','nullable' => False),
			'account_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'mail_type' => array('type' => 'int','precision' => '1','nullable' => False,'comment' => '0=active, 1=alias, 2=forward, 3=forwardOnly, 4=quota'),
			'mail_value' => array('type' => 'varchar','precision' => '128','nullable' => False)
		),
		'pk' => array('mail_id'),
		'fk' => array(),
		'ix' => array('mail_value',array('account_id','mail_type')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.006';
}


function emailadmin_upgrade1_9_006()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_ea_accounts',array(
		'fd' => array(
			'acc_id' => array('type' => 'auto','nullable' => False),
			'acc_name' => array('type' => 'varchar','precision' => '80','comment' => 'description'),
			'ident_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'standard identity'),
			'acc_imap_host' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment' => 'imap hostname'),
			'acc_imap_ssl' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_imap_port' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '143','comment' => 'imap port'),
			'acc_sieve_enabled' => array('type' => 'bool','default' => '0','comment' => 'sieve enabled'),
			'acc_sieve_host' => array('type' => 'varchar','precision' => '128','comment' => 'sieve host, default imap_host'),
			'acc_sieve_port' => array('type' => 'int','precision' => '4','default' => '4190'),
			'acc_folder_sent' => array('type' => 'varchar','precision' => '128','comment' => 'sent folder'),
			'acc_folder_trash' => array('type' => 'varchar','precision' => '128','comment' => 'trash folder'),
			'acc_folder_draft' => array('type' => 'varchar','precision' => '128','comment' => 'draft folder'),
			'acc_folder_template' => array('type' => 'varchar','precision' => '128','comment' => 'template folder'),
			'acc_smtp_host' => array('type' => 'varchar','precision' => '128','comment' => 'smtp hostname'),
			'acc_smtp_ssl' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_smtp_port' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '25','comment' => 'smtp port'),
			'acc_smtp_type' => array('type' => 'varchar','precision' => '32','default' => 'emailadmin_smtp','comment' => 'smtp class to use'),
			'acc_imap_type' => array('type' => 'varchar','precision' => '32','default' => 'emailadmin_imap','comment' => 'imap class to use'),
			'acc_imap_logintype' => array('type' => 'varchar','precision' => '20','comment' => 'standard, vmailmgr, admin, uidNumber'),
			'acc_domain' => array('type' => 'varchar','precision' => '100','comment' => 'domain name'),
			'acc_further_identities' => array('type' => 'bool','nullable' => False,'default' => '1','comment' => '0=no, 1=yes'),
			'acc_user_editable' => array('type' => 'bool','nullable' => False,'default' => '1','comment' => '0=no, 1=yes'),
			'acc_sieve_ssl' => array('type' => 'int','precision' => '1','default' => '1','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_modified' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'acc_modifier' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'acc_smtp_auth_session' => array('type' => 'bool','comment' => '0=no, 1=yes, use username/pw from current user')
		),
		'pk' => array('acc_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.007';
}


function emailadmin_upgrade1_9_007()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_ea_credentials',array(
		'fd' => array(
			'cred_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'into egw_ea_accounts'),
			'cred_type' => array('type' => 'int','precision' => '1','nullable' => False,'comment' => '&1=imap, &2=smtp, &4=admin'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'account_id or 0=all'),
			'cred_username' => array('type' => 'varchar','precision' => '80','nullable' => False,'comment' => 'username'),
			'cred_password' => array('type' => 'varchar','precision' => '80','comment' => 'password encrypted'),
			'cred_pw_enc' => array('type' => 'int','precision' => '1','default' => '0','comment' => '0=not, 1=user pw, 2=system')
		),
		'pk' => array('cred_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('acc_id','account_id','cred_type'))
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.008';
}


function emailadmin_upgrade1_9_008()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_ea_identities',array(
		'fd' => array(
			'ident_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'for which account'),
			'ident_realname' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment' => 'real name'),
			'ident_email' => array('type' => 'varchar','precision' => '128','comment' => 'email address'),
			'ident_org' => array('type' => 'varchar','precision' => '128','comment' => 'organisation'),
			'ident_signature' => array('type' => 'text','comment' => 'signature text'),
			'account_id' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False,'default' => '0','comment' => '0=all users of give mail account')
		),
		'pk' => array('ident_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.009';
}


function emailadmin_upgrade1_9_009()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_ea_valid',array(
		'fd' => array(
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False)
		),
		'pk' => array(),
		'fk' => array(),
		'ix' => array(array('account_id','acc_id')),
		'uc' => array(array('acc_id','account_id'))
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.010';
}


/**
 * Migrate eMailAdmin profiles to accounts
 */
function emailadmin_upgrade1_9_010()
{
	static $prot2ssl = array('ssl' => 3, 'tls' => 2, 'starttls' => 1);
	$acc_ids = array();
	// migrate personal felamimail accounts, identities and signatures
	try {
		$db = $GLOBALS['egw_setup']->db;
		foreach($db->select('egw_emailadmin', '*', array('ea_active' => 1), __LINE__, __FILE__, false,
			// order general profiles first, then group profiles and user specific ones last
			'ORDER BY ea_group IS NULL AND ea_user IS NULL DESC,ea_group IS NULL,ea_user', 'emailadmin') as $row)
		{
			$owner = $row['ea_user'] > 0 ? (int)$row['ea_user'] : ($row['ea_group'] ? -abs($row['ea_group']) : 0);

			// always store general profiles and group profiles
			// only store user profiles if they contain at least an imap_host
			// otherwise store just the credentials for that user and account of his primary group or first general account
			if ($owner <= 0 || $owner > 0 && !empty($row['ea_imap_host']) ||
				!($acc_id = ($primary_group = accounts::id2name($owner, 'account_primary_group')) &&
					isset($acc_ids[$primary_group]) ? $acc_ids[$primary_group] : $acc_ids['0']))
			{
				$prefs = new preferences($owner);
				$all_prefs = $prefs->read_repository();
				$pref_values = $all_prefs['felamimail'];

				// create standard identity for account
				$identity = array(
					'acc_id' => 0,
					'ident_realname' => '',
					'ident_email' => '',
					'ident_org' => $row['ea_organisation_name'],
					'ident_signature' => $row['ea_default_signature'],
				);
				$db->insert('egw_ea_identities', $identity, false, __LINE__, __FILE__, 'emailadmin');
				$ident_id = $db->get_last_insert_id('egw_ea_identities', 'ident_id');

				// create account
				$smtp_ssl = 0;
				if (strpos($row['ea_smtp_server'], '://') !== false)
				{
					list($prot, $row['ea_smtp_server']) = explode('://', $row['ea_smtp_server']);
					$smtp_ssl = (int)$prot2ssl[$prot];
				}
				$account = array(
					'acc_name' => $row['ea_description'],
					'ident_id' => $ident_id,
					'acc_imap_type' => emailadmin_account::getIcClass($row['ea_imap_type']),
					'acc_imap_logintype' => $row['ea_imap_login_type'],
					'acc_imap_host' => $row['ea_imap_server'],
					'acc_imap_ssl' => $row['ea_imap_tsl_encryption'] | ($row['ea_imap_tsl_auth'] === 'yes' ? 8 : 0),
					'acc_imap_port' => $row['ea_imap_port'],
					'acc_sieve_enabled' => $row['ea_imap_enable_sieve'] == 'yes',
					'acc_sieve_host' => $row['ea_imap_sieve_server'],
					'acc_sieve_ssl' => $row['fm_ic_sieve_port'] == 5190,
					'acc_sieve_port' => $row['ea_imap_sieve_port'],
					'acc_folder_sent' => $pref_values['sentFolder'],
					'acc_folder_trash' => $pref_values['trashFolder'],
					'acc_folder_draft' => $pref_values['draftFolder'],
					'acc_folder_template' => $pref_values['templateFolder'],
					'acc_smtp_type' => $row['ea_smtp_type'],
					'acc_smtp_host' => $row['ea_smtp_server'],
					'acc_smtp_ssl' => $smtp_ssl,
					'acc_smtp_port' => $row['ea_smtp_port'],
					'acc_domain' => $row['ea_default_domain'],
					'acc_further_identities' => $row['ea_user_defined_identities'] == 'yes' ||
						$row['ea_user_defined_signatures'] == 'yes',	// both together are now called identities
					'acc_user_editable' => false,
					'acc_smtp_auth_session' => $row['ea_smtp_auth'] == 'ann' ||
						$row['ea_smtp_auth'] == 'yes' && empty($row['ea_smtp_auth_username']),
				);
				$db->insert('egw_ea_accounts', $account, false, __LINE__, __FILE__, 'emailadmin');
				$acc_id = $db->get_last_insert_id('egw_ea_accounts', 'acc_id');

				// remember acc_id by owner, to be able to base credential only profiles on them
				if ($owner <= 0 && !isset($acc_ids[$owner])) $acc_ids[(string)$owner] = $acc_id;

				// update above created identity with account acc_id
				$db->update('egw_ea_identities', array('acc_id' => $acc_id), array('ident_id' => $ident_id), __LINE__, __FILE__, 'emailadmin');

				// make account valid for given owner
				$db->insert('egw_ea_valid', array(
					'acc_id' => $acc_id,
					'account_id' => $owner,
				), false, __LINE__, __FILE__, 'emailadmin');
			}
			// credentials are either stored for specific user or all users (account_id=0), not group-specific
			if (!($owner > 0)) $owner = 0;
			// add imap credentials if necessary
			$cred_type = $row['ea_smpt_auth'] != 'no' && $row['ea_imap_auth_username'] && $row['ea_smtp_auth_username'] &&
				$row['ea_imap_auth_username'] == $row['ea_smtp_auth_username'] &&
				$row['ea_imap_auth_password'] == $row['ea_smtp_auth_password'] ? 3 : 1;
			if ($row['ea_imap_auth_username'])
			{
				emailadmin_credentials::write($acc_id, $row['ea_imap_auth_username'], $row['ea_imap_auth_password'], $cred_type, $owner);
			}
			// add smtp credentials if necessary and different from imap
			if ($row['ea_smpt_auth'] != 'no' && !empty($row['ea_smtp_auth_username']) && $cred_type != 3)
			{
				emailadmin_credentials::write($acc_id, $row['ea_smtp_auth_username'], $row['ea_smtp_auth_password'], 2, $owner);
			}
			// add admin credentials
			if ($row['ea_imap_enable_cyrus'] == 'yes' && !empty($row['ea_imap_admin_user']))
			{
				emailadmin_credentials::write($acc_id, $row['ea_imap_admin_user'], $row['ea_imap_admin_pw'], 8, $owner);
			}
		}
		// ToDo: migrate all not yet via personal fmail profiles migrated signatures
	}
	catch(Exception $e) {
		// ignore all errors, eg. because FMail is not installed
		echo "<p>".$e->getMessage()."</p>\n";
	}
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.011';
}

/**
 * Helper function for 1.9.011 upgrade to query standard identity of a given user
 *
 * There's no defined standard identity, we simply pick first one we find
 * sorting results by:
 * - prefering identical email or same-domain
 * - prefering personal accounts over all-user ones
 * - for all-user ones we prefer a personal identitiy (account_id!=0)
 *
 * @param int $account_id
 * @param string $email optional email to be used to find matching account/identity with identical email or same domain
 * @return array with ident_* and acc_id
 */
function emailadmin_std_identity($account_id, $email=null)
{
	if ($email) list(, $domain) = explode('@', $email);
	return $GLOBALS['egw_setup']->db->select('egw_ea_accounts', 'egw_ea_identities.*',
		'egw_ea_valid.account_id IN (0,'.(int)$account_id.') AND egw_ea_identities.account_id IN (0,'.(int)$account_id.')',
		__LINE__, __FILE__, 0,
		'ORDER BY '.($email ?
			'ident_email='.$GLOBALS['egw_setup']->db->quote($email).' DESC,'.
			'ident_email LIKE '.$GLOBALS['egw_setup']->db->quote('%@'.$domain).' DESC,' : '').
			'egw_ea_identities.account_id DESC,egw_ea_valid.account_id DESC', 'emailadmin', 1,
		'JOIN egw_ea_valid ON egw_ea_accounts.acc_id=egw_ea_valid.acc_id '.
		'JOIN egw_ea_identities ON  egw_ea_accounts.acc_id=egw_ea_identities.acc_id')->fetch();
}

/**
 * Migrate (personal) FMail accounts to eMailAdmin accounts
 */
function emailadmin_upgrade1_9_011()
{
	static $prot2ssl = array('ssl' => 3, 'tls' => 2, 'starttls' => 1);
	$std_identity = null;
	// migrate personal felamimail accounts, identities and signatures
	$db = $GLOBALS['egw_setup']->db;
	if (in_array('egw_felamimail_accounts', $db->table_names(true)))
	{
		try {
			// migrate real fmail accounts, but not yet identities (fm_ic_hostname is NULL)
			foreach($db->select('egw_felamimail_accounts', '*', 'fm_ic_hostname IS NOT NULL', __LINE__, __FILE__, false, '', 'felamimail') as $row)
			{
				$prefs = new preferences($row['fm_owner']);
				$all_prefs = $prefs->read_repository();
				$pref_values = $all_prefs['felamimail'];

				// create standard identity for account
				$identity = array(
					'acc_id' => 0,
					'ident_realname' => $row['fm_realname'],
					'ident_email' => $row['fm_emailaddress'],
					'ident_org' => $row['fm_organisation'],
					'ident_signature' => $db->select('egw_felamimail_signatures', 'fm_signature', array(
						'fm_signatureid' => $row['fm_signatureid'],
					), __LINE__, __FILE__, false, '', 'felamimail')->fetchColumn(),
				);
				$db->insert('egw_ea_identities', $identity, false, __LINE__, __FILE__, 'emailadmin');
				$ident_id = $db->get_last_insert_id('egw_ea_identities', 'ident_id');

				// create account
				$og_ssl = 0;
				if (strpos($row['fm_og_hostname'], '://') !== false)
				{
					list($prot, $row['fm_og_hostname']) = explode('://', $row['fm_og_hostname']);
					$og_ssl = (int)$prot2ssl[$prot];
				}
				$account = array(
					'acc_name' => $row['fm_emailaddress'],
					'ident_id' => $ident_id,
					'acc_imap_host' => $row['fm_ic_hostname'],
					'acc_imap_ssl' => $row['fm_ic_encryption'] | ($row['fm_ic_validatecertificate'] ? 8 : 0),
					'acc_imap_port' => $row['fm_ic_port'],
					'acc_sieve_enabled' => $row['fm_ic_enable_sieve'],
					'acc_sieve_host' => $row['fm_ic_sieve_server'],
					'acc_sieve_ssl' => $row['fm_ic_sieve_port'] == 5190,
					'acc_sieve_port' => $row['fm_ic_sieve_port'],
					'acc_folder_sent' => $row['fm_ic_sentfolder'] ? $row['fm_ic_sentfolder'] : $pref_values['sentFolder'],
					'acc_folder_trash' => $row['fm_ic_trashfolder'] ? $row['fm_ic_trashfolder'] : $pref_values['trashFolder'],
					'acc_folder_draft' => $row['fm_ic_draftfolder'] ? $row['fm_ic_draftfolder'] : $pref_values['draftFolder'],
					'acc_folder_template' => $row['fm_ic_templatefolder'] ? $row['fm_ic_templatefolder'] : $pref_values['templateFolder'],
					'acc_smtp_host' => $row['fm_og_hostname'],
					'acc_smtp_ssl' => $og_ssl,
					'acc_smtp_port' => $row['fm_og_port'],
				);
				$db->insert('egw_ea_accounts', $account, false, __LINE__, __FILE__, 'emailadmin');
				$acc_id = $db->get_last_insert_id('egw_ea_accounts', 'acc_id');
				$identity['acc_id'] = $acc_id;
				if (!isset($std_identity[$row['fm_owner']])) $std_identity[$row['fm_owner']] = $identity;
				// update above created identity with account acc_id
				$db->update('egw_ea_identities', array('acc_id' => $acc_id), array('ident_id' => $ident_id), __LINE__, __FILE__, 'emailadmin');

				// make account valid for given owner
				$db->insert('egw_ea_valid', array(
					'acc_id' => $acc_id,
					'account_id' => $row['fm_owner'],
				), false, __LINE__, __FILE__, 'emailadmin');

				// add imap credentials
				$cred_type = $row['fm_og_smtpauth'] && $row['fm_ic_username'] == $row['fm_og_username'] &&
					$row['fm_ic_password'] == $row['fm_og_password'] ? 3 : 1;
				emailadmin_credentials::write($acc_id, $row['fm_ic_username'], $row['fm_ic_password'], $cred_type, $row['fm_owner']);
				// add smtp credentials if necessary and different from imap
				if ($row['fm_og_smtpauth'] && $cred_type != 3)
				{
					emailadmin_credentials::write($acc_id, $row['fm_og_username'], $row['fm_og_password'], 2, $row['fm_owner']);
				}
			}

			// migrate fmail identities (fm_ic_hostname is NULL), not real fmail account done above
			foreach($db->select('egw_felamimail_accounts', '*', 'fm_ic_hostname IS NULL', __LINE__, __FILE__, false, '', 'felamimail') as $row)
			{
				if (!($std_identity = emailadmin_std_identity($row['fm_owner'])))
				{
					continue;	// no account found to add identity to
				}
				// create standard identity for account
				$identity = array(
					'acc_id' => $std_identity['acc_id'],
					'ident_realname' => $row['fm_realname'],
					'ident_email' => $row['fm_emailaddress'],
					'ident_org' => $row['fm_organisation'],
					'ident_signature' => $db->select('egw_felamimail_signatures', 'fm_signature', array(
						'fm_signatureid' => $row['fm_signatureid'],
					), __LINE__, __FILE__, false, '', 'felamimail')->fetchColumn(),
					'account_id' => $row['fm_owner'],
				);
				$db->insert('egw_ea_identities', $identity, false, __LINE__, __FILE__, 'emailadmin');
			}

			// migrate all not yet as standard-signatures migrated signatures to identities of first migrated fmail profile
			// completing them with realname, email and org from standard-signature of given account
			foreach($db->select('egw_felamimail_signatures', '*',
				"fm_signatureid NOT IN (SELECT fm_signatureid FROM egw_felamimail_accounts)",
				__LINE__, __FILE__, false, '', 'felamimail') as $row)
			{
				if (!($std_identity = emailadmin_std_identity($row['fm_accountid'])))
				{
					continue;	// ignore signatures for whos owner (fm_accountid!) we have no personal profile
				}
				$identity = $std_identity; unset($identity['ident_id']);
				$identity['ident_realname'] = $std_identity['ident_realname'].' ('.$row['fm_description'].')';
				$identity['ident_signature'] = $row['fm_signature'];
				$identity['account_id'] = $row['fm_accountid'];
				$db->insert('egw_ea_identities', $identity, false, __LINE__, __FILE__, 'emailadmin');
			}
		}
		catch(Exception $e) {
			// ignore all errors, eg. because FMail is not installed
			echo "<p>".$e->getMessage()."</p>\n";
		}
	}
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.015';
}

function emailadmin_upgrade1_9_015()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_folder_junk',array(
		'type' => 'varchar',
		'precision' => '128',
		'comment' => 'junk folder'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_imap_default_quota',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'default quota, if no user specific one set'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_imap_timeout',array(
		'type' => 'int',
		'precision' => '2',
		'comment' => 'timeout for imap connection'
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.016';
}


function emailadmin_upgrade1_9_016()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_identities','ident_name',array(
		'type' => 'varchar',
		'precision' => '128',
		'comment' => 'name of identity to display'
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.017';
}


function emailadmin_upgrade1_9_017()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_ea_notifications',array(
		'fd' => array(
			'notif_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'mail account'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'user account'),
			'notif_folder' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'folder name')
		),
		'pk' => array('notif_id'),
		'fk' => array(),
		'ix' => array(array('account_id','acc_id')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.018';
}


function emailadmin_upgrade1_9_018()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_ea_accounts','acc_smtp_type',array(
		'type' => 'varchar',
		'precision' => '32',
		'default' => 'emailadmin_smtp',
		'comment' => 'smtp class to use'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_ea_accounts','acc_imap_type',array(
		'type' => 'varchar',
		'precision' => '32',
		'default' => 'emailadmin_imap',
		'comment' => 'imap class to use'
	));

	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.9.019';
}


function emailadmin_upgrade1_9_019()
{
	return $GLOBALS['setup_info']['emailadmin']['currentver'] = '14.1';
}
