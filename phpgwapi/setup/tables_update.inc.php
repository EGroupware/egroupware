<?php
	/**************************************************************************\
	* eGroupWare - Setup                                                       *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id$

	/* Include older eGroupWare update support */
	include('tables_update_0_9_9.inc.php');
	include('tables_update_0_9_10.inc.php');
	include('tables_update_0_9_12.inc.php');
	include('tables_update_0_9_14.inc.php');
	include('tables_update_1_0.inc.php');

	// updates from the stable 1.2 branch
	$test[] = '1.2.007';
	function phpgwapi_upgrade1_2_007()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.001';
	}
	
	$test[] = '1.2.008';
	function phpgwapi_upgrade1_2_008()
	{
		// fixing the lang change from zt -> zh-tw for existing installations
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.002';
	}

	$test[] = '1.2.100';
	function phpgwapi_upgrade1_2_100()
	{
		// final 1.2 release
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.002';
	}

	$test[] = '1.2.101';
	function phpgwapi_upgrade1_2_101()
	{
		// 1. 1.2 bugfix-release: egw_accounts.account_lid is varchar(64)
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.004';
	}

	$test[] = '1.2.102';
	function phpgwapi_upgrade1_2_102()
	{
		// 2. 1.2 bugfix-release: egw_accesslog.sessionid is varchar(128)
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.004';
	}

	// updates in HEAD / 1.3
	$test[] = '1.3.001';
	function phpgwapi_upgrade1_3_001()
	{
		// fixing the lang change from zt -> zh-tw for existing installations
		$GLOBALS['egw_setup']->db->update('egw_languages',array('lang_id' => 'zh-tw'),array('lang_id' => 'zt'),__LINE__,__FILE__);

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.002';
	}
	
	$test[] = '1.3.002';
	function phpgwapi_upgrade1_3_002()
	{
		/*************************************************************************\
		 *      add addressbook-type contact into type definition table           *
		\*************************************************************************/
		if ($GLOBALS['DEBUG'])
		{
			echo "<br>\n<b>initiating to create the default type 'contact' for addressbook";
		}
		
		$newconf = array('n' => array(
			'name' => 'contact',
			'options' => array(
				'template' => 'addressbook.edit',
				'icon' => 'navbar.png'
		)));
		$GLOBALS['egw_setup']->oProc->query("INSERT INTO egw_config (config_app,config_name,config_value) VALUES ('addressbook','types','". serialize($newconf). "')",__LINE__,__FILE__);

		if ($GLOBALS['DEBUG'])
		{
			echo " DONE!</b>";
		}
		/*************************************************************************/
		
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.003';
	}


	$test[] = '1.3.003';
	function phpgwapi_upgrade1_3_003()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_accounts','account_lid',array(
			'type' => 'varchar',
			'precision' => '64',
			'nullable' => False
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.004';
	}


	$test[] = '1.3.004';
	function phpgwapi_upgrade1_3_004()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_vfs','vfs_created',array(
			'type' => 'timestamp',
			'nullable' => False,
			'default' => 'current_timestamp'
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_vfs','vfs_modified',array(
			'type' => 'timestamp'
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_vfs','vfs_content',array(
			'type' => 'blob'
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.005';
	}


	$test[] = '1.3.005';
	function phpgwapi_upgrade1_3_005()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_access_log','sessionid',array(
			'type' => 'char',
			'precision' => '128',
			'nullable' => False
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.006';
	}


	$test[] = '1.3.006';
	function phpgwapi_upgrade1_3_006()
	{
		$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_name,config_value',array(
			'config_app'  => 'phpgwapi',
			"(config_name LIKE '%ldap%' OR config_name IN ('auth_type','account_repository'))",
		),__LINE__,__FILE__);
		while (($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			$config[$row['config_name']] = $row['config_value'];
		}
		// the update is only for accounts in ldap
		if ($config['account_repository'] == 'ldap' || !$config['account_repository'] && $config['auth_type'] == 'ldap')
		{
			$GLOBALS['egw_setup']->setup_account_object();
			if (!is_object($GLOBALS['egw']->acl))
			{
				$GLOBALS['egw']->acl =& CreateObject('phpgwapi.acl');
			}
			$ds = $GLOBALS['egw']->common->ldapConnect();
			$phpgwAccountAttributes = array(
				'phpgwaccounttype','phpgwaccountexpires','phpgwaccountstatus',
				'phpgwaccountlastlogin','phpgwaccountlastloginfrom','phpgwaccountlastpasswdchange',
			);
			foreach(array($config['ldap_context'],$config['ldap_group_context']) as $context)
			{
				if (!$context) continue;

				$sri = ldap_search($ds,$context,'(objectclass=phpgwaccount)',
					array_merge(array('gidnumber','objectclass'),$phpgwAccountAttributes));
				
				foreach(ldap_get_entries($ds, $sri) as $key => $entry)
				{
					if ($key === 'count') continue;
					
					// remove the phpgwAccounts objectclass
					$objectclass = $entry['objectclass'];
					unset($objectclass['count']);
					foreach($objectclass as $n => $class) $objectclass[$n] = strtolower($class);
					unset($objectclass[array_search('phpgwaccount',$objectclass)]);
					if ($entry['phpgwaccounttype'][0] == 'g')
					{
						if (!in_array('posixgroup',$objectclass)) $objectclass[] = 'posixgroup';
						$to_write = array('objectclass' => array_values($objectclass));
						// make sure all group-memberships are correctly set in LDAP
						if (($uids = $GLOBALS['egw']->acl->get_ids_for_location($entry['gidnumber'][0],1,'phpgw_group')))
						{
							foreach ($uids as $uid)
							{
								$to_write['memberuid'] = $GLOBALS['egw']->accounts->id2name($uid);
							}
						}
					}
					else	// user
					{
						if (!in_array('posixaccount',$objectclass)) $objectclass[] = 'posixaccount';
						if (!in_array('shadowaccount',$objectclass)) $objectclass[] = 'shadowaccount';
						$to_write = array('objectclass' => array_values($objectclass));
						// store the important values of the phpgwaccount schema in the shadowAccount schema
						if (!$entry['phpgwaccountstatus'][0] || $entry['phpgwaccountexpires'][0] != -1)
						{
							$to_write['shadowexpire'] = $entry['phpgwaccountexpires'][0] != -1 && 
								($entry['phpgwaccountstatus'][0] || 
								!$entry['phpgwaccountstatus'][0] && $entry['phpgwaccountexpires'][0] < time()) ? 
								$entry['phpgwaccountexpires'][0] / (24*3600) : 0;
						}
						if ($entry['phpgwlastpasswdchange'][0])
						{
							$to_write['shadowlastchange'] = $entry['phpgwlastpasswdchange'][0] / (24*3600);
						}
					}
					foreach($phpgwAccountAttributes as $attr)
					{
						if (isset($entry[$attr])) $to_write[$attr] = array();
					}
					echo $entry['dn']; _debug_array($to_write);
					ldap_modify($ds,$entry['dn'],$to_write);
				}
			}
		}
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.007';
	}
