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

	$test[] = '1.2.103';
	function phpgwapi_upgrade1_2_103()
	{
		// 3. 1.2 bugfix-release: link-stuff, cal-layout, ...
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.004';
	}

	$test[] = '1.2.104';
	function phpgwapi_upgrade1_2_104()
	{
		// 4. 1.2 bugfix-release: SyncML, ...
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.004';
	}

	$test[] = '1.2.105';
	function phpgwapi_upgrade1_2_105()
	{
		// 5. 1.2 bugfix-release
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.004';
	}

 	$test[] = '1.2.106';
        function phpgwapi_upgrade1_2_106()
	{
		// 6. 1.2 bugfix-release
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
					if (!ldap_modify($ds,$entry['dn'],$to_write))
					{
						echo $entry['dn']; _debug_array($to_write);
						echo '<p style="color: red;">'.'LDAP error: '.ldap_error($ds)."</p>\n";
					}
				}
			}
		}
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.007';
	}

	/**
	 * Updates the addressbook table to the new addressbook in 1.3
	 * 
	 * The addressbook table was moved to the addressbook, but has to be moved back,
	 * as the addressdata of the accounts is no stored only in the addressbook!
	 * 
	 * It is called, if needed, from phpgwap_upgrade1_3_007 function 
	 * 
	 * + changes / renamed fields in 1.3+:
	 *   - access           --> private (already done by Ralf)
	 *   - tel_msg          --> tel_assistent
	 *   - tel_modem        --> tel_fax_home
	 *   - tel_isdn         --> tel_cell_private
	 *   - tel_voice/ophone --> tel_other
	 *   - address2         --> adr_one_street2
	 *   - address3         --> adr_two_street2
	 *   - freebusy_url     --> freebusy_uri (i instead l !)
	 *   - fn               --> n_fn
	 *   - last_mod         --> modified
	 * + new fields in 1.3+:
	 *   - n_fileas
	 *   - role
	 *   - assistent
	 *   - room
	 *   - calendar_uri
	 *   - url_home
	 *   - created
	 *   - creator (preset with owner)
	 *   - modifier
	 *   - jpegphoto
	 */
	function addressbook_upgrade1_2()
	{
		$GLOBALS['egw_setup']->oProc->RefreshTable('egw_addressbook',array(
			'fd' => array(
				'contact_id' => array('type' => 'auto','nullable' => False),
				'contact_tid' => array('type' => 'char','precision' => '1','default' => 'n'),
				'contact_owner' => array('type' => 'int','precision' => '8','nullable' => False),
				'contact_private' => array('type' => 'int','precision' => '1','default' => '0'),
				'cat_id' => array('type' => 'varchar','precision' => '32'),
				'n_family' => array('type' => 'varchar','precision' => '64'),
				'n_given' => array('type' => 'varchar','precision' => '64'),
				'n_middle' => array('type' => 'varchar','precision' => '64'),
				'n_prefix' => array('type' => 'varchar','precision' => '64'),
				'n_suffix' => array('type' => 'varchar','precision' => '64'),
				'n_fn' => array('type' => 'varchar','precision' => '128'),
				'n_fileas' => array('type' => 'varchar','precision' => '255'),
				'contact_bday' => array('type' => 'varchar','precision' => '10'),
				'org_name' => array('type' => 'varchar','precision' => '64'),
				'org_unit' => array('type' => 'varchar','precision' => '64'),
				'contact_title' => array('type' => 'varchar','precision' => '64'),
				'contact_role' => array('type' => 'varchar','precision' => '64'),
				'contact_assistent' => array('type' => 'varchar','precision' => '64'),
				'contact_room' => array('type' => 'varchar','precision' => '64'),
				'adr_one_street' => array('type' => 'varchar','precision' => '64'),
				'adr_one_street2' => array('type' => 'varchar','precision' => '64'),
				'adr_one_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_one_region' => array('type' => 'varchar','precision' => '64'),
				'adr_one_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_one_countryname' => array('type' => 'varchar','precision' => '64'),
				'contact_label' => array('type' => 'text'),
				'adr_two_street' => array('type' => 'varchar','precision' => '64'),
				'adr_two_street2' => array('type' => 'varchar','precision' => '64'),
				'adr_two_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_two_region' => array('type' => 'varchar','precision' => '64'),
				'adr_two_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_two_countryname' => array('type' => 'varchar','precision' => '64'),
				'tel_work' => array('type' => 'varchar','precision' => '40'),
				'tel_cell' => array('type' => 'varchar','precision' => '40'),
				'tel_fax' => array('type' => 'varchar','precision' => '40'),
				'tel_assistent' => array('type' => 'varchar','precision' => '40'),
				'tel_car' => array('type' => 'varchar','precision' => '40'),
				'tel_pager' => array('type' => 'varchar','precision' => '40'),
				'tel_home' => array('type' => 'varchar','precision' => '40'),
				'tel_fax_home' => array('type' => 'varchar','precision' => '40'),
				'tel_cell_private' => array('type' => 'varchar','precision' => '40'),
				'tel_other' => array('type' => 'varchar','precision' => '40'),
				'tel_prefer' => array('type' => 'varchar','precision' => '32'),
				'contact_email' => array('type' => 'varchar','precision' => '64'),
				'contact_email_home' => array('type' => 'varchar','precision' => '64'),
				'contact_url' => array('type' => 'varchar','precision' => '128'),
				'contact_url_home' => array('type' => 'varchar','precision' => '128'),
				'contact_freebusy_uri' => array('type' => 'varchar','precision' => '128'),
				'contact_calendar_uri' => array('type' => 'varchar','precision' => '128'),
				'contact_note' => array('type' => 'text'),
				'contact_tz' => array('type' => 'varchar','precision' => '8'),
				'contact_geo' => array('type' => 'varchar','precision' => '32'),
				'contact_pubkey' => array('type' => 'text'),
				'contact_created' => array('type' => 'int','precision' => '8'),
				'contact_creator' => array('type' => 'int','precision' => '4','nullable' => False),
				'contact_modified' => array('type' => 'int','precision' => '8','nullable' => False),
				'contact_modifier' => array('type' => 'int','precision' => '4'),
				'contact_jpegphoto' => array('type' => 'blob'),
			),
			'pk' => array('contact_id'),
			'fk' => array(),
			'ix' => array('cat_id','contact_owner','n_fileas',array('n_family','n_given'),array('n_given','n_family'),array('org_name','n_family','n_given')),
			'uc' => array()
		),array(
			// new colum prefix
			'contact_id' => 'id',
			'contact_tid' => 'tid',
			'contact_owner' => 'owner',
			'contact_private' => "CASE access WHEN 'private' THEN 1 ELSE 0 END",
			'n_fn' => 'fn',
			'contact_title' => 'title',
			'contact_bday' => 'bday',
			'contact_note' => 'note',
			'contact_tz' => 'tz',
			'contact_geo' => 'geo',
			'contact_url' => 'url',
			'contact_pubkey' => 'pubkey',
			'contact_label' => 'label',
			'contact_email' => 'email',
			'contact_email_home' => 'email_home',
			'contact_modified' => 'last_mod',
			// remove stupid old default values, rename phone-numbers, tel_bbs and tel_video are droped
			'tel_work' => "CASE tel_work WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_work END",
			'tel_cell' => "CASE tel_cell WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_cell END",
			'tel_fax' => "CASE tel_fax WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_fax END",
			'tel_assistent' => "CASE tel_msg WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_msg END",
			'tel_car' => "CASE tel_car WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_car END",
			'tel_pager' => "CASE tel_pager WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_pager END",
			'tel_home' => "CASE tel_home WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_home END",
			'tel_fax_home' => "CASE tel_modem WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_modem END",
			'tel_cell_private' => "CASE tel_isdn WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_isdn END",
			'tel_other' => "CASE tel_voice WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_voice END",
			'tel_prefer' => "CASE tel_prefer WHEN 'tel_voice' THEN 'tel_other' WHEN 'tel_msg' THEN 'tel_assistent' WHEN 'tel_modem' THEN 'tel_fax_home' WHEN 'tel_isdn' THEN 'tel_cell_private' WHEN 'ophone' THEN 'tel_other' ELSE tel_prefer END",
			// set creator from owner
			'contact_creator' => 'owner',
			// set contact_fileas from org_name, n_family and n_given
			'n_fileas' => "CASE WHEN org_name='' THEN (".
				($name_sql = "CASE WHEN n_given='' THEN n_family ELSE ".$GLOBALS['egw_setup']->db->concat('n_family',"', '",'n_given').' END').
				") ELSE (CASE WHEN n_family='' THEN org_name ELSE ".$GLOBALS['egw_setup']->db->concat('org_name',"': '",$name_sql).' END) END',

		));

		// migrate values saved in custom fields to the new table
		$db2 = clone($GLOBALS['egw_setup']->db);
		$GLOBALS['egw_setup']->db->select('egw_addressbook_extra','contact_id,contact_name,contact_value',
			"contact_name IN ('ophone','address2','address3','freebusy_url') AND contact_value != '' AND NOT contact_value IS NULL"
			,__LINE__,__FILE__,false,'','addressbook');
		$old2new = array(
			'ophone'   => 'tel_other',
			'address2' => 'adr_one_street2',
			'address3' => 'adr_two_street2',
			'freebusy_url' => 'contact_freebusy_uri',
		);
		while (($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			$db2->update('egw_addressbook',array($old2new[$row['contact_name']] => $row['contact_value']),array(
				'contact_id' => $row['contact_id'],
				'('.$old2new[$row['contact_name']].'IS NULL OR '.$old2new[$row['contact_name']]."='')",
			),__LINE__,__FILE__,'addressbook');
		}
		// delete the not longer used custom fields plus rubish from old bugs
		$GLOBALS['egw_setup']->db->delete('egw_addressbook_extra',"contact_name IN ('ophone','address2','address3','freebusy_url','cat_id','tid','lid','id','ab_id','access','owner','rights')".
			" OR contact_value='' OR contact_value IS NULL".
			($db2->capabilities['subqueries'] ? " OR contact_id NOT IN (SELECT contact_id FROM egw_addressbook)" : ''),
			__LINE__,__FILE__,'addressbook');
			
		// change the m/d/Y birthday format to Y-m-d
		$GLOBALS['egw_setup']->db->select('egw_addressbook','contact_id,contact_bday',"contact_bday != ''",
			__LINE__,__FILE__,false,'','addressbook');
		while (($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			list($m,$d,$y) = explode('/',$row['contact_bday']);
			$db2->update('egw_addressbook',array(
				'contact_bday' => sprintf('%04d-%02d-%02d',$y,$m,$d)
			),array(
				'contact_id' => $row['contact_id'],
			),__LINE__,__FILE__,'addressbook');
		}
		return $GLOBALS['setup_info']['addressbook']['currentver'] = '1.3.001';
	}

	$test[] = '1.3.007';
	function phpgwapi_upgrade1_3_007()
	{
		// check for a pre 1.3 addressbook, and run the upgrade if needed
		if ((float) $GLOBALS['setup_info']['addressbook']['currentver'] < 1.3)
		{
			addressbook_upgrade1_2();
		}
		$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook','account_id',array(
			'type' => 'int',
			'precision' => '4',
			'default' => '0'
		));
		$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_value,config_name',array(
			'config_app' => 'phpgwapi',
			"(config_name LIKE '%_repository' OR config_name='auth_type')",
		),__LINE__,__FILE__);
		while (($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			$config[$row['config_name']] = $row['config_value'];
		}
		// migrate account_{firstname|lastname|email} to the contacts-repository
		if (!$config['account_repository'] || $config['account_repository'] == 'sql')
		{
			$accounts = array();
			$GLOBALS['egw_setup']->db->select('egw_accounts','*',array('account_type' => 'u'),__LINE__,__FILE__);
			while (($account = $GLOBALS['egw_setup']->db->row(true)))
			{
				$accounts[] = $account;
			}
			foreach($accounts as $account)
			{
				$contact = array(
					'n_given'       => $account['account_firstname'],
					'n_family'      => $account['account_lastname'],
					'n_fn'          => $account['account_firstname'].' '.$account['account_lastname'],
					'contact_email' => $account['account_email'],
					'account_id'    => $account['account_id'],
					'contact_owner' => 0,
					'contact_modified' => time(),
					'contact_modifier' => 0,	// no user
				);
				if (!(int)$account['person_id'])	// account not already linked with a contact
				{
					$contact['contact_created'] = time();
					$contact['contact_creator'] = 0; // no user
					$GLOBALS['egw_setup']->db->insert('egw_addressbook',$contact,false,__LINE__,__FILE__);
				}
				else
				{
					$GLOBALS['egw_setup']->db->update('egw_addressbook',$contact,array(
						'contact_id' => $data['person_id'],
						'(n_given != '.$GLOBALS['egw_setup']->db->quote($account['account_firstname']).
						' OR n_family != '.$GLOBALS['egw_setup']->db->quote($account['account_lastname']).
						' OR contact_email != '.$GLOBALS['egw_setup']->db->quote($account['account_email']).')',
					),__LINE__,__FILE__);
				}
			}
		}
		// dropping the no longer used account_{firstname|lastname|email} columns
		$GLOBALS['egw_setup']->oProc->DropColumn('egw_accounts',array(
			'fd' => array(
				'account_id' => array('type' => 'auto','nullable' => False),
				'account_lid' => array('type' => 'varchar','precision' => '64','nullable' => False),
				'account_pwd' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'account_lastname' => array('type' => 'varchar','precision' => '50'),
				'account_lastlogin' => array('type' => 'int','precision' => '4'),
				'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
				'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
				'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
				'account_expires' => array('type' => 'int','precision' => '4'),
				'account_type' => array('type' => 'char','precision' => '1'),
				'person_id' => array('type' => 'int','precision' => '4'),
				'account_primary_group' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'account_email' => array('type' => 'varchar','precision' => '100')
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		),'account_firstname');
		$GLOBALS['egw_setup']->oProc->DropColumn('egw_accounts',array(
			'fd' => array(
				'account_id' => array('type' => 'auto','nullable' => False),
				'account_lid' => array('type' => 'varchar','precision' => '64','nullable' => False),
				'account_pwd' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'account_lastlogin' => array('type' => 'int','precision' => '4'),
				'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
				'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
				'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
				'account_expires' => array('type' => 'int','precision' => '4'),
				'account_type' => array('type' => 'char','precision' => '1'),
				'person_id' => array('type' => 'int','precision' => '4'),
				'account_primary_group' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'account_email' => array('type' => 'varchar','precision' => '100')
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		),'account_lastname');
		$GLOBALS['egw_setup']->oProc->DropColumn('egw_accounts',array(
			'fd' => array(
				'account_id' => array('type' => 'auto','nullable' => False),
				'account_lid' => array('type' => 'varchar','precision' => '64','nullable' => False),
				'account_pwd' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'account_lastlogin' => array('type' => 'int','precision' => '4'),
				'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
				'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
				'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
				'account_expires' => array('type' => 'int','precision' => '4'),
				'account_type' => array('type' => 'char','precision' => '1'),
				'account_primary_group' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'account_email' => array('type' => 'varchar','precision' => '100')
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		),'person_id');
		$GLOBALS['egw_setup']->oProc->DropColumn('egw_accounts',array(
			'fd' => array(
				'account_id' => array('type' => 'auto','nullable' => False),
				'account_lid' => array('type' => 'varchar','precision' => '64','nullable' => False),
				'account_pwd' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'account_lastlogin' => array('type' => 'int','precision' => '4'),
				'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
				'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
				'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
				'account_expires' => array('type' => 'int','precision' => '4'),
				'account_type' => array('type' => 'char','precision' => '1'),
				'account_primary_group' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		),'account_email');

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.008';
	}

	$test[] = '1.3.008';
	function phpgwapi_upgrade1_3_008()
	{
		// reverse change-password ACL from 'changepassword' to 'nopasswordchange',
		// to allow users created in LDAP to be automatic full eGW users
		$acocunts = $change_passwd_acls = array();
		// get all accounts with acl settings
		$GLOBALS['egw_setup']->db->select('egw_acl','DISTINCT acl_account','acl_account > 0',__LINE__,__FILE__);
		while(($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			$accounts[] = $row['acl_account'];
		}
		// get all accounts with change password acl (allowance to change the password)
		$GLOBALS['egw_setup']->db->select('egw_acl','DISTINCT acl_account',array(
			'acl_appname'  => 'preferences',
			'acl_location' => 'changepassword',
			'acl_rights'   => 1,
			'acl_account > 0',
		),__LINE__,__FILE__);
		while(($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			$change_passwd_acls[] = $row['acl_account'];
		}
		$GLOBALS['egw_setup']->db->delete('egw_acl',array(
			'acl_appname'  => 'preferences',
			'acl_location' => 'changepassword',
		),__LINE__,__FILE__);
		
		// set the acl now for everyone NOT allowed to change the password
		foreach(array_diff($accounts,$change_passwd_acls) as $account_id)
		{
			$GLOBALS['egw_setup']->db->insert('egw_acl',array(
				'acl_appname'  => 'preferences',
				'acl_location' => 'nopasswordchange',
				'acl_rights'   => 1,
				'acl_account'  => $account_id,
			),false,__LINE__,__FILE__);
		}
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.009';
	}

	$test[] = '1.3.009';
	function phpgwapi_upgrade1_3_009()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_links','link_remark',array(
			'type' => 'varchar',
			'precision' => '100'
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.010';
	}


	$test[] = '1.3.010';
	function phpgwapi_upgrade1_3_010()
	{
		// account_id should be unique, the (unique) index also speed up the joins with the account-table
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','account_id',array(
			'type' => 'int',
			'precision' => '4'
		));
		$GLOBALS['egw_setup']->db->query('UPDATE egw_addressbook SET account_id=NULL WHERE account_id=0',__LINE__,__FILE__);
		
		$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook',array('account_id'),true);

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.011';
	}


	$test[] = '1.3.011';
	function phpgwapi_upgrade1_3_011()
	{
		// moving the sync-ml table in to the (new) syncml app and marking the syncml app installed, if not already installed
		if (!isset($GLOBALS['setup_info']['syncml']['currentver']))
		{
			$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->applications_table,array(
				'app_enabled' => 3,
				'app_order'   => 99,
				'app_tables'  => 'egw_contentmap,egw_syncmldevinfo,egw_syncmlsummary',
				'app_version' => $GLOBALS['setup_info']['syncml']['currentver'] = '0.9.0',
			),array('app_name' => 'syncml'),__LINE__,__FILE__);

			// we can't do the syncml update in the same go, as it would only set the version, but not run any updates!
			unset($GLOBALS['setup_info']['syncml']);
		}

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.012';
	}


	$test[] = '1.3.012';
	function phpgwapi_upgrade1_3_012()
	{
		// setting old contacts without cat to cat_id=NULL, they cat be '0' or ''
		$GLOBALS['egw_setup']->db->query("UPDATE egw_addressbook SET cat_id=NULL WHERE cat_id IN ('','0')",__LINE__,__FILE__);

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.013';
	}

	$test[] = '1.3.013';
	function phpgwapi_upgrade1_3_013()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','cat_id',array(
			'type' => 'varchar',
			'precision' => '255'
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.014';
	}
?>
