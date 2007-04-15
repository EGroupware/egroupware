<?php
	/**************************************************************************\
	* EGroupWare                                                               *
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

	$phpgw_baseline = array(
		'egw_emailadmin' => array(
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
		)
	);
?>
