<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$test[] = '0.9.1';
	function addressbook_upgrade0_9_1()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('addressbook', 'ab_id', array('type' => 'auto', 'nullable' => false));
		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_company_id', array('type' => 'int', 'precision' => 4));
		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_title', array('type' => 'varchar', 'precision' => 60));
		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_address2', array('type' => 'varchar', 'precision' => 60));

		$setup_info['addressbook']['currentver'] = '0.9.2';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	function addressbook_v0_9_2to0_9_3update_owner($table, $field)
	{
		global $phpgw_setup, $phpgw_setup;

		$phpgw_setup->oProc->query("select distinct($field) from $table");
		if ($phpgw_setup->oProc->num_rows())
		{
			while ($phpgw_setup->oProc->next_record())
			{
				$owner[count($owner)] = $phpgw_setup->oProc->f($field);
			}
			if($phpgw_setup->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.10pre4'))
			{
				$acctstbl = 'accounts';
			}
			else
			{
				$acctstbl = 'phpgw_accounts';
			}
			for($i=0;$i<count($owner);$i++)
			{
				$phpgw_setup->oProc->query("SELECT account_id FROM $acctstbl WHERE account_lid='".$owner[$i]."'");
				$phpgw_setup->oProc->next_record();
				$phpgw_setup->oProc->query("UPDATE $table SET $field=".$phpgw_setup->oProc->f("account_id")." WHERE $field='".$owner[$i]."'");
			}
		}
		$phpgw_setup->oProc->AlterColumn($table, $field, array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => 0));
	}

	$test[] = '0.9.2';
	function addressbook_upgrade0_9_2()
	{
		global $setup_info;
		$setup_info['addressbook']['currentver'] = '0.9.3pre1';
		return $setup_info['addressbook']['currentver'];
	}

	$test[] = '0.9.3pre1';
	function addressbook_upgrade0_9_3pre1()
	{
		global $setup_info;

		addressbook_v0_9_2to0_9_3update_owner('addressbook','ab_owner');
		$setup_info['addressbook']['currentver'] = '0.9.3pre2';
		return $setup_info['addressbook']['currentver'];
	}

	$test[] = '0.9.3pre2';
	function addressbook_upgrade0_9_3pre2()
	{
		global $setup_info;

		$setup_info['addressbook']['currentver'] = '0.9.3pre6';
		return $setup_info['addressbook']['currentver'];
	}

	$test[] = '0.9.3pre6';
	function addressbook_upgrade0_9_3pre6()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_url', array('type' => 'varchar', 'precision' => 255));

		$setup_info['addressbook']['currentver'] = '0.9.3pre7';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.3pre7';
	function addressbook_upgrade0_9_3pre7()
	{
		global $setup_info;

		$setup_info['addressbook']['currentver'] = '0.9.8pre5';
		return $setup_info['addressbook']['currentver'];
	}

	$test[] = "0.9.8pre5";
	function addressbook_upgrade0_9_8pre5()
	{
		global $setup_info;
		$setup_info['addressbook']['currentver'] = '0.9.10pre4';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre4";
	function addressbook_upgrade0_9_10pre4()
	{
		global $setup_info, $phpgw_setup;

		$db2 = $phpgw_setup->oProc;
		$db3 = $phpgw_setup->oProc;

		$phpgw_setup->oProc->query('SELECT oldid,newid FROM phpgw_temp_groupmap',__LINE__,__FILE__);
		if($phpgw_setup->oProc->num_rows())
		{
			while($phpgw_setup->oProc->next_record())
			{
				$old_group_id = $phpgw_setup->oProc->f(0);
				$new_group_id = $phpgw_setup->oProc->f(1);
				$db2->query("SELECT ab_access,ab_id FROM addressbook WHERE ab_access LIKE '%,".$old_group_id.",%'",__LINE__,__FILE__);
				if($db2->num_rows())
				{
					while($db2->next_record())
					{
						$access = $db2->f('cat_access');
						$id     = $db2->f('cat_id');
						$access = str_replace(','.$old_group_id.',' , ','.$new_group_id.',' , $access);
						$db3->query("UPDATE phpgw_categories SET cat_access='".$access."' WHERE cat_id=".$id,__LINE__,__FILE__);
					}
				}
			}
		}

		$setup_info["addressbook"]["currentver"] = "0.9.10pre5";
		return $setup_info['addressbook']['currentver'];
		//return True;
	}
 
	$test[] = "0.9.10pre5";
	function addressbook_upgrade0_9_10pre5()
	{
		global $setup_info;
		$setup_info["addressbook"]["currentver"] = "0.9.10pre6";
		return $setup_info['addressbook']['currentver'];
		//return True;
	}
       
	$test[] = "0.9.10pre6";
	function addressbook_upgrade0_9_10pre6()
	{
		global $setup_info;
		$setup_info["addressbook"]["currentver"] = "0.9.10pre7";
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre7";
	function addressbook_upgrade0_9_10pre7()
	{
		global $setup_info;
		$setup_info["addressbook"]["currentver"] = "0.9.10pre8";
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre8";
	function addressbook_upgrade0_9_10pre8()
	{
		global $setup_info;

		$setup_info["addressbook"]["currentver"] = "0.9.10pre9";
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre9";
	function addressbook_upgrade0_9_10pre9()
	{
		global $setup_info;

		$setup_info["addressbook"]["currentver"] = "0.9.10pre10";
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre10";
	function addressbook_upgrade0_9_10pre10()
	{
		global $setup_info;

		$setup_info["addressbook"]["currentver"] = "0.9.10pre11";
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = "0.9.10pre11";
	function addressbook_upgrade0_9_10pre11()
	{
		global $setup_info;

		$setup_info["addressbook"]["currentver"] = "0.9.10pre12";
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre12';
	function addressbook_upgrade0_9_10pre12()
	{
		global $setup_info, $phpgw_setup;

		$db1 = $phpgw_setup->db;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_addressbook', array(
				'fd' => array(
					'id'           => array('type' => 'auto', 'default' => '0', 'nullable' => False),
					'lid'          => array('type' => 'varchar', 'precision' => 32),
					'tid'          => array('type' => 'char', 'precision' => 1),
					'owner'        => array('type' => 'int', 'precision' => 4),
					'fn'           => array('type' => 'varchar', 'precision' => 64),
					'sound'        => array('type' => 'varchar', 'precision' => 64),
					'org_name'     => array('type' => 'varchar', 'precision' => 64),
					'org_unit'     => array('type' => 'varchar', 'precision' => 64),
					'title'        => array('type' => 'varchar', 'precision' => 64),
					'n_family'     => array('type' => 'varchar', 'precision' => 64),
					'n_given'      => array('type' => 'varchar', 'precision' => 64),
					'n_middle'     => array('type' => 'varchar', 'precision' => 64),
					'n_prefix'     => array('type' => 'varchar', 'precision' => 64),
					'n_suffix'     => array('type' => 'varchar', 'precision' => 64),
					'label'        => array('type' => 'text'),
					'adr_poaddr'   => array('type' => 'varchar', 'precision' => 64),
					'adr_extaddr'  => array('type' => 'varchar', 'precision' => 64),
					'adr_street'   => array('type' => 'varchar', 'precision' => 64),
					'adr_locality' => array('type' => 'varchar', 'precision' => 32),
					'adr_region'   => array('type' => 'varchar', 'precision' => 32),
					'adr_postalcode'  => array('type' => 'varchar', 'precision' => 32),
					'adr_countryname' => array('type' => 'varchar', 'precision' => 32),
					'adr_work'     => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'adr_home'     => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'adr_parcel'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'adr_postal'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'tz'           => array('type' => 'varchar', 'precision' => 8),
					'geo'          => array('type' => 'varchar', 'precision' => 32),
					'a_tel'        => array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'a_tel_work'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_home'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_voice'  => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_msg'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_fax'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'a_tel_prefer' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel'        => array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'b_tel_work'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_home'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_voice'  => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_msg'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_fax'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'b_tel_prefer' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel'        => array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'c_tel_work'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_home'   => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_voice'  => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_msg'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_fax'    => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'c_tel_prefer' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'd_emailtype'  => array('type' => 'varchar', 'precision' => 32),
					'd_email'      => array('type' => 'varchar', 'precision' => 64),
					'd_email_work' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False),
					'd_email_home' => array('type' => 'char', 'precision' => '1', 'default' => 'n', 'nullable' => False)
				),
				'pk' => array('id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('id')
			)
		);

		$phpgw_setup->oProc->CreateTable(
			'phpgw_addressbook_extra', array(
				'fd' => array(
					'contact_id'    => array('type' => 'int',     'precision' => 4),
					'contact_owner' => array('type' => 'int',     'precision' => 4),
					'contact_name'  => array('type' => 'varchar', 'precision' => 255),
					'contact_value' => array('type' => 'varchar', 'precision' => 255)
				),
				'pk' => array(),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$phpgw_setup->oProc->query("SELECT * FROM addressbook");
		echo '<br>numrows: ' . $phpgw_setup->oProc->num_rows;

		while ($phpgw_setup->oProc->next_record())
		{
			$fields = $extra = array();

			$fields['id']         = $phpgw_setup->oProc->f('ab_id');
			$fields['owner']      = addslashes($phpgw_setup->oProc->f('ab_owner'));
			$fields['n_given']    = addslashes($phpgw_setup->oProc->f('ab_firstname'));
			$fields['n_family']   = addslashes($phpgw_setup->oProc->f('ab_lastname'));
			$fields['d_email']    = addslashes($phpgw_setup->oProc->f('ab_email'));
			$fields['b_tel']      = addslashes($phpgw_setup->oProc->f('ab_hphone'));
			$fields['a_tel']      = addslashes($phpgw_setup->oProc->f('ab_wphone'));
			$fields['c_tel']      = addslashes($phpgw_setup->oProc->f('ab_fax'));
			$fields['fn']         = addslashes($phpgw_setup->oProc->f('ab_firstname').' '.$phpgw_setup->oProc->f('ab_lastname'));
			$fields['a_tel_work'] = 'y';
			$fields['b_tel_home'] = 'y';
			$fields['c_tel_fax']  = 'y';
			$fields['org_name']   = addslashes($phpgw_setup->oProc->f('ab_company'));
			$fields['title']      = addslashes($phpgw_setup->oProc->f('ab_title'));
			$fields['adr_street'] = addslashes($phpgw_setup->oProc->f('ab_street'));
			$fields['adr_locality']   = addslashes($phpgw_setup->oProc->f('ab_city'));
			$fields['adr_region']     = addslashes($phpgw_setup->oProc->f('ab_state'));
			$fields['adr_postalcode'] = addslashes($phpgw_setup->oProc->f('ab_zip'));

			$extra['pager']       = $phpgw_setup->oProc->f('ab_pager');
			$extra['mphone']      = $phpgw_setup->oProc->f('ab_mphone');
			$extra['ophone']      = $phpgw_setup->oProc->f('ab_ophone');
			$extra['bday']        = $phpgw_setup->oProc->f('ab_bday');
			$extra['notes']       = $phpgw_setup->oProc->f('ab_notes');
			$extra['address2']    = $phpgw_setup->oProc->f('ab_address2');
			$extra['url']         = $phpgw_setup->oProc->f('ab_url');

			$sql = "INSERT INTO phpgw_addressbook (org_name,n_given,n_family,fn,d_email,title,a_tel,a_tel_work,"
				. "b_tel,b_tel_home,c_tel,c_tel_fax,adr_street,adr_locality,adr_region,adr_postalcode,owner)"
				. " VALUES ('".$fields['org_name']."','".$fields['n_given']."','".$fields['n_family']."','"
				. $fields['fn']."','".$fields['d_email']."','".$fields['title']."','".$fields['a_tel']."','"
				. $fields['a_tel_work']."','".$fields['b_tel']."','".$fields['b_tel_home']."','"
				. $fields['c_tel']."','".$fields['c_tel_fax']."','".$fields['adr_street']."','"
				. $fields['adr_locality']."','".$fields['adr_region']."','".$fields['adr_postalcode']."','"
				. $fields['owner'] ."')";

			$db1->query($sql);

			while (list($name,$value) = each($extra))
			{
				$sql = "INSERT INTO phpgw_addressbook_extra VALUES ('".$fields['id']."','" . $fields['owner'] . "','"
					. addslashes($name) . "','" . addslashes($value) . "')";
				$db1->query($sql);
			}
		}
		$setup_info['addressbook']['currentver'] = '0.9.10pre13';
		return $setup_info['addressbook']['currentver'];
		//return True;
		// Note we are still leaving the old addressbook table alone here... for third party apps if they need it
	}

	$test[] = '0.9.10pre13';
	function addressbook_upgrade0_9_10pre13()
	{
		global $setup_info, $phpgw_setup;

		$db1 = $phpgw_setup->oProc;

		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'url',  array('type' => 'varchar', 'precision' => 128));
		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'bday', array('type' => 'varchar', 'precision' => 32));
		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'note', array('type' => 'text'));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook_extra', 'contact_value', array('type' => 'text'));

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='url'";
		$phpgw_setup->oProc->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->oProc->next_record())
		{
			$cid   = $phpgw_setup->oProc->f('contact_id');
			$cvalu = $phpgw_setup->oProc->f('contact_value');
			if ($cid && $cvalu)
			{
				$update = "UPDATE phpgw_addressbook set url='" . $cvalu . "' WHERE id=" . $cid;
				$phpgw_setup->oProc->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='url'";
				$phpgw_setup->oProc->query($delete);
			}
		}

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='bday'";
		$phpgw_setup->oProc->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->oProc->next_record())
		{
			$cid   = $phpgw_setup->oProc->f('contact_id');
			$cvalu = $phpgw_setup->oProc->f('contact_value');
			if ($cid && $cvalu)
			{
				$update = "UPDATE phpgw_addressbook set bday='" . $cvalu . "' WHERE id=" . $cid;
				$phpgw_setup->oProc->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='bday'";
				$phpgw_setup->oProc->query($delete);
			}
		}

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='notes'";
		$phpgw_setup->oProc->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->oProc->next_record())
		{
			$cid   = $phpgw_setup->oProc->f('contact_id');
			$cvalu = $phpgw_setup->oProc->f('contact_value');
			if ($cvalu)
			{
				$update = "UPDATE phpgw_addressbook set note='" . $cvalu . "' WHERE id=" . $cid;
				$phpgw_setup->oProc->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='notes'";
				$phpgw_setup->oProc->query($delete);
			}
		}
		$setup_info['addressbook']['currentver'] = '0.9.10pre14';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre14';
	function addressbook_upgrade0_9_10pre14()
	{
		global $setup_info;
		$setup_info['addressbook']['currentver'] = '0.9.10pre15';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre15';
	function addressbook_upgrade0_9_10pre15()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'adr_work', 'char',     array('precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'adr_home', 'char',     array('precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'adr_parcel', 'char',   array('precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'adr_postal', 'char',   array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_work', 'char',   array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_home', 'char',   array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_voice', 'char',  array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_msg', 'char',    array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_fax', 'char',    array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'a_tel_prefer', 'char', array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_work', 'char',   array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_home', 'char',   array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_voice', 'char',  array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_msg', 'char',    array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_fax', 'char',    array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'b_tel_prefer', 'char', array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_work', 'char',   array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_home', 'char',   array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_voice', 'char',  array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_msg', 'char',    array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_fax', 'char',    array('precision' => 1, 'default' => 'n', 'nullable' => False));
 		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'c_tel_prefer', 'char', array('precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'd_email_work', 'char', array('precision' => 1, 'default' => 'n', 'nullable' => False));
		$phpgw_setup->oProc->AlterColumn('phpgw_addressbook', 'd_email_home', 'char', array('precision' => 1, 'default' => 'n', 'nullable' => False));

		$setup_info['addressbook']['currentver'] = '0.9.10pre16';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre16';
	function addressbook_upgrade0_9_10pre16()
	{
		global $setup_info, $phpgw_setup;

		$db1 = $phpgw_setup->db;

		$phpgw_setup->oProc->RenameTable('phpgw_addressbook', 'phpgw_addressbook_old');
		$phpgw_setup->oProc->CreateTable(
			'phpgw_addressbook', array(
				'fd' => array(
					'id' =>                  array('type' => 'auto'),
					'lid' =>                 array('type' => 'varchar', 'precision' => 32),
					'tid' =>                 array('type' => 'char', 'precision' => 1),
					'owner' =>               array('type' => 'int', 'precision' => 4),
					'fn' =>                  array('type' => 'varchar', 'precision' => 64),
					'n_family' =>            array('type' => 'varchar', 'precision' => 64),
					'n_given' =>             array('type' => 'varchar', 'precision' => 64),
					'n_middle' =>            array('type' => 'varchar', 'precision' => 64),
					'n_prefix' =>            array('type' => 'varchar', 'precision' => 64),
					'n_suffix' =>            array('type' => 'varchar', 'precision' => 64),
					'sound' =>               array('type' => 'varchar', 'precision' => 64),
					'bday' =>                array('type' => 'varchar', 'precision' => 32),
					'note' =>                array('type' => 'text'),
					'tz' =>                  array('type' => 'varchar', 'precision' => 8),
					'geo' =>                 array('type' => 'varchar', 'precision' => 32),
					'url' =>                 array('type' => 'varchar', 'precision' => 128),
					'org_name' =>            array('type' => 'varchar', 'precision' => 64),
					'org_unit' =>            array('type' => 'varchar', 'precision' => 64),
					'title' =>               array('type' => 'varchar', 'precision' => 64),
					'adr_one_street' =>      array('type' => 'varchar', 'precision' => 64),
					'adr_one_locality' =>    array('type' => 'varchar', 'precision' => 32),
					'adr_one_region' =>      array('type' => 'varchar', 'precision' => 32),
					'adr_one_postalcode' =>  array('type' => 'varchar', 'precision' => 32),
					'adr_one_countryname' => array('type' => 'varchar', 'precision' => 32),
					'adr_one_type' =>        array('type' => 'varchar', 'precision' => 64),
					'label' =>               array('type' => 'text'),
					'adr_two_street' =>      array('type' => 'varchar', 'precision' => 64),
					'adr_two_locality' =>    array('type' => 'varchar', 'precision' => 32),
					'adr_two_region' =>      array('type' => 'varchar', 'precision' => 32),
					'adr_two_postalcode' =>  array('type' => 'varchar', 'precision' => 32),
					'adr_two_type' =>        array('type' => 'varchar', 'precision' => 64),
					'tel_work' =>            array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_home' =>            array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_voice' =>           array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_fax' =>             array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_msg' =>             array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_cell' =>            array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_pager' =>           array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_bbs' =>             array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_modem' =>           array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_car' =>             array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_isdn' =>            array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_video' =>           array('type' => 'varchar', 'precision' => 40, 'default' => '+1 (000) 000-0000', 'nullable' => False),
					'tel_prefer' =>          array('type' => 'varchar', 'precision' => 32),
					'email' =>               array('type' => 'varchar', 'precision' => 64),
					'email_type' =>          array('type' => 'varchar', 'precision' => 32, 'default' => 'INTERNET'),
					'email_home' =>          array('type' => 'varchar', 'precision' => 64),
					'email_home_type' =>     array('type' => 'varchar', 'precision' => 32, 'default' => 'INTERNET')
				),
				'pk' => array('id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

        $phpgw_setup->oProc->query("SELECT * FROM phpgw_addressbook_old");
        while ($phpgw_setup->oProc->next_record())
		{
			$fields['id']                  = $phpgw_setup->oProc->f("id");
			$fields['owner']               = $phpgw_setup->oProc->f("owner");
			$fields['n_given']             = $phpgw_setup->oProc->f("firstname");
			$fields['n_family']            = $phpgw_setup->oProc->f("lastname");
			$fields['email']               = $phpgw_setup->oProc->f("d_email");
			$fields['email_type']          = $phpgw_setup->oProc->f("d_emailtype");
			$fields['tel_home']            = $phpgw_setup->oProc->f("b_tel");
			$fields['tel_work']            = $phpgw_setup->oProc->f("a_tel");
			$fields['tel_fax']             = $phpgw_setup->oProc->f("c_tel");
			$fields['fn']                  = $phpgw_setup->oProc->f("fn");
			$fields['org_name']            = $phpgw_setup->oProc->f("org_name");
			$fields['title']               = $phpgw_setup->oProc->f("title");
			$fields['adr_one_street']      = $phpgw_setup->oProc->f("adr_street");
			$fields['adr_one_locality']    = $phpgw_setup->oProc->f("adr_locality");
			$fields['adr_one_region']      = $phpgw_setup->oProc->f("adr_region");
			$fields['adr_one_postalcode']  = $phpgw_setup->oProc->f("adr_postalcode");
			$fields['adr_one_countryname'] = $phpgw_setup->oProc->f("adr_countryname");
			$fields['bday']                = $phpgw_setup->oProc->f("bday");
			$fields['note']                = $phpgw_setup->oProc->f("note");
			$fields['url']                 = $phpgw_setup->oProc->f("url");

			$sql="INSERT INTO phpgw_addressbook (org_name,n_given,n_family,fn,email,email_type,title,tel_work,"
				. "tel_home,tel_fax,adr_one_street,adr_one_locality,adr_one_region,adr_one_postalcode,adr_one_countryname,"
				. "owner,bday,url,note)"
				. " VALUES ('".$fields["org_name"]."','".$fields["n_given"]."','".$fields["n_family"]."','"
				. $fields["fn"]."','".$fields["email"]."','".$fields["email_type"]."','".$fields["title"]."','".$fields["tel_work"]."','"
				. $fields["tel_home"]."','".$fields["tel_fax"] ."','".$fields["adr_one_street"]."','"
				. $fields["adr_one_locality"]."','".$fields["adr_one_region"]."','".$fields["adr_one_postalcode"]."','"
				. $fields["adr_one_countryname"]."','".$fields["owner"] ."','".$fields["bday"]."','".$fields["url"]."','".$fields["note"]."')";

			$db1->query($sql,__LINE__,__FILE__);
		}
 
		$phpgw_setup->oProc->query("DROP TABLE phpgw_addressbook_old");
 
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_home=''   where tel_home='n'   OR tel_home='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_work=''   where tel_work='n'   OR tel_work='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_cell=''   where tel_cell='n'   OR tel_cell='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_voice=''  where tel_voice='n'  OR tel_voice='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_fax=''    where tel_fax='n'    OR tel_fax='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_car=''    where tel_car='n'    OR tel_car='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_pager=''  where tel_pager='n'  OR tel_pager='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_msg=''    where tel_msg='n'    OR tel_msg='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_bbs=''    where tel_bbs='n'    OR tel_bbs='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_modem=''  where tel_modem='n'  OR tel_modem='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_prefer='' where tel_prefer='n' OR tel_prefer='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_video=''  where tel_video='n'  OR tel_video='y'");
		$phpgw_setup->oProc->query("update phpgw_addressbook set tel_isdn=''   where tel_isdn='n'   OR tel_isdn='y'");

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='mphone'";
		$phpgw_setup->oProc->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->oProc->next_record())
		{
			$cid   = $phpgw_setup->oProc->f('contact_id');
			$cvalu = $phpgw_setup->oProc->f('contact_value');
			if ($cvalu)
			{
				$update = "UPDATE phpgw_addressbook set tel_cell='" . $cvalu . "' WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='mphone'";
				$db1->query($delete);
			}
		}
		$setup_info['addressbook']['currentver'] = '0.9.10pre17';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre17';
	function addressbook_upgrade0_9_10pre17()
	{
		global $phpgw_info, $phpgw_setup;

		$setup_info['addressbook']['currentver'] = '0.9.10pre18';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre18';
	function addressbook_upgrade0_9_10pre18()
	{
		global $setup_info;
		$setup_info['addressbook']['currentver'] = '0.9.10pre19';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre19';
	function addressbook_upgrade0_9_10pre19()
	{
		global $setup_info;
		$setup_info['addressbook']['currentver'] = '0.9.10pre20';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre20';
	function addressbook_upgrade0_9_10pre20()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'access', array('type' => 'char', 'precision' => 7));

		$setup_info['addressbook']['currentver'] = '0.9.10pre21';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre21';
	function addressbook_upgrade0_9_10pre21()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_addressbook', 'cat_id', array('type' => 'varchar', 'precision' => 32));

		$setup_info['addressbook']['currentver'] = '0.9.10pre22';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre22';
	function addressbook_upgrade0_9_10pre22()
	{
		global $setup_info;
		$setup_info['addressbook']['currentver'] = '0.9.10pre23';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}

	$test[] = '0.9.10pre23';
	function addressbook_upgrade0_9_10pre23()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->query("UPDATE phpgw_addressbook SET tid='n' WHERE tid is null");

		$setup_info['addressbook']['currentver'] = '0.9.10pre24';
		return $setup_info['addressbook']['currentver'];
		//return True;
	}
?>
