<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_baseline = array(
		'config' => array(
			'fd' => array(
				'config_name' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'config_value' => array('type' => 'varchar', 'precision' => 100)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('config_name')
		),
		'applications' => array(
			'fd' => array(
				'app_name' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'app_title' => array('type' => 'varchar', 'precision' => 50),
				'app_enabled' => array('type' => 'int', 'precision' => 4),
				'app_order' => array('type' => 'int', 'precision' => 4),
				'app_tables' => array('type' => 'varchar', 'precision' => 255),
				'app_version' => array('type' => 'varchar', 'precision' => 20, 'nullable' => false, 'default' => '0.0')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('app_name')
		),
		'accounts' => array(
			'fd' => array(
				'account_id' => array('type' => 'auto', 'nullable' => false),
				'account_lid' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'account_pwd' => array('type' => 'varchar', 'precision' => 32, 'nullable' => false),
				'account_firstname' => array('type' => 'varchar', 'precision' => 50),
				'account_lastname' => array('type' => 'varchar', 'precision' => 50),
				'account_permissions' => array('type' => 'text'),
				'account_groups' => array('type' => 'varchar', 'precision' => 30),
				'account_lastlogin' => array('type' => 'int', 'precision' => 4),
				'account_lastloginfrom' => array('type' => 'varchar', 'precision' => 255),
				'account_lastpwd_change' => array('type' => 'int', 'precision' => 4),
				'account_status' => array('type' => 'char', 'precision' => 1, 'nullable' => false, 'default' => 'A')
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		),
		'groups' => array(
			'fd' => array(
				'group_id' => array('type' => 'auto', 'nullable' => false),
				'group_name' => array('type' => 'varchar', 'precision' => 255),
				'group_apps' => array('type' => 'varchar', 'precision' => 255)
			),
			'pk' => array('group_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'preferences' => array(
			'fd' => array(
				'preference_owner' => array('type' => 'varchar', 'precision' => 20, 'nullable' => false),
				'preference_name' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
				'preference_value' => array('type' => 'varchar', 'precision' => 50),
				'preference_appname' => array('type' => 'varchar', 'precision' => 50)
			),
			'pk' => array('preference_owner', 'preference_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'sessions' => array(
			'fd' => array(
				'session_id' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
				'session_lid' => array('type' => 'varchar', 'precision' => 20),
				'session_pwd' => array('type' => 'varchar', 'precision' => 255),
				'session_ip' => array('type' => 'varchar', 'precision' => 255),
				'session_logintime' => array('type' => 'varchar', 'precision' => 4),
				'session_dla' => array('type' => 'varchar', 'precision' => 4)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('session_id')
		),
		'app_sessions' => array(
			'fd' => array(
				'sessionid' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
				'loginid' => array('type' => 'varchar', 'precision' => 20),
				'app' => array('type' => 'varchar', 'precision' => 20),
				'content' => array('type' => 'text')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'access_log' => array(
			'fd' => array(
				'sessionid' => array('type' => 'varchar', 'precision' => 30),
				'loginid' => array('type' => 'varchar', 'precision' => 30),
				'ip' => array('type' => 'varchar', 'precision' => 30),
				'li' => array('type' => 'int', 'precision' => 4),
				'lo' => array('type' => 'int', 'precision' => 4)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'profiles' => array(
			'fd' => array(
				'con' => array('type' => 'auto', 'nullable' => false),
				'owner' => array('type' => 'varchar', 'precision' => 20),
				'title' => array('type' => 'varchar', 'precision' => 255),
				'phone_number' => array('type' => 'varchar', 'precision' => 255),
				'comments' => array('type' => 'text'),
				'picture_format' => array('type' => 'varchar', 'precision' => 255),
				'picture' => array('type' => 'blob')
			),
			'pk' => array('con'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'lang' => array(
			'fd' => array(
				'message_id' => array('type' => 'varchar', 'precision' => 150, 'nullable' => false, 'default' => ''),
				'app_name' => array('type' => 'varchar', 'precision' => 100, 'nullable' => false, 'default' => 'common'),
				'lang' => array('type' => 'varchar', 'precision' => 5, 'nullable' => false, 'default' => ''),
				'content' => array('type' => 'text')
			),
			'pk' => array('message_id', 'app_name', 'lang'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'addressbook' => array(
			'fd' => array(
				'ab_id' => array('type' => 'auto', 'nullable' => false),
				'ab_owner' => array('type' => 'varchar', 'precision' => 25),
				'ab_access' => array('type' => 'varchar', 'precision' => 10),
				'ab_firstname' => array('type' => 'varchar', 'precision' => 255),
				'ab_lastname' => array('type' => 'varchar', 'precision' => 255),
				'ab_email' => array('type' => 'varchar', 'precision' => 255),
				'ab_hphone' => array('type' => 'varchar', 'precision' => 255),
				'ab_wphone' => array('type' => 'varchar', 'precision' => 255),
				'ab_fax' => array('type' => 'varchar', 'precision' => 255),
				'ab_pager' => array('type' => 'varchar', 'precision' => 255),
				'ab_mphone' => array('type' => 'varchar', 'precision' => 255),
				'ab_ophone' => array('type' => 'varchar', 'precision' => 255),
				'ab_street' => array('type' => 'varchar', 'precision' => 255),
				'ab_city' => array('type' => 'varchar', 'precision' => 255),
				'ab_state' => array('type' => 'varchar', 'precision' => 255),
				'ab_zip' => array('type' => 'varchar', 'precision' => 255),
				'ab_bday' => array('type' => 'varchar', 'precision' => 255),
				'ab_notes' => array('type' => 'text'),
				'ab_company' => array('type' => 'varchar', 'precision' => 255)
			),
			'pk' => array('ab_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
?>
