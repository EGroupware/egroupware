<?php
	/**************************************************************************\
	* EGroupWare - EMailadmin                                                  *
	* http://www.egroupware.org                                                *
	* http://www.phpgw.de                                                      *
	* Author: lkneschke@phpgw.de                                               *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$test[] = '0.0.3';
	function emailadmin_upgrade0_0_3()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','smtpType', array('type' => 'int', 'precision' => 4));		

		return $setup_info['emailadmin']['currentver'] = '0.0.4';
	}

	$test[] = '0.0.4';
	function emailadmin_upgrade0_0_4()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','defaultDomain', array('type' => 'varchar', 'precision' => 100));		

		return $setup_info['emailadmin']['currentver'] = '0.0.5';
	}

	$test[] = '0.0.5';
	function emailadmin_upgrade0_0_5()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','organisationName', array('type' => 'varchar', 'precision' => 100));		
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','userDefinedAccounts', array('type' => 'varchar', 'precision' => 3));		

		return $setup_info['emailadmin']['currentver'] = '0.0.6';
	}
	


	$test[] = '0.0.6';
	function emailadmin_upgrade0_0_6()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','oldimapcclient',array(
			'type' => 'varchar',
			'precision' => '3'
		));

		return $GLOBALS['setup_info']['emailadmin']['currentver'] = '0.0.007';
	}


	$test[] = '0.0.007';
	function emailadmin_upgrade0_0_007()
	{
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_emailadmin','oldimapcclient','imapoldcclient');

		return $GLOBALS['setup_info']['emailadmin']['currentver'] = '0.0.008';
	}
	

	$test[] = '0.0.008';
	function emailadmin_upgrade0_0_008()
	{
		return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.0.0';
	}

	$test[] = '1.0.0';
	function emailadmin_upgrade1_0_0()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','editforwardingaddress',array(
			'type' => 'varchar',
			'precision' => '3'
		));

		return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.0.1';
	}

	$test[] = '1.0.1';
	function emailadmin_upgrade1_0_1()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','ea_order', array('type' => 'int', 'precision' => 4));		

		return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.0.2';
	}

	$test[] = '1.0.2';
	function emailadmin_upgrade1_0_2()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','ea_appname', array('type' => 'varchar','precision' => '80'));
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_emailadmin','ea_group', array('type' => 'varchar','precision' => '80'));

		return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.0.3';
	}

	$test[] = '1.0.3';
	function emailadmin_upgrade1_0_3()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_emailadmin','egw_emailadmin');

		return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.2';
	}
	
	$test[] = '1.2';
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


	$test[] = '1.2.001';
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


	$test[] = '1.2.002';
	function emailadmin_upgrade1_2_002()
	{
		return $GLOBALS['setup_info']['emailadmin']['currentver'] = '1.4';
	}
?>
