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
		global $phpgw_info, $oProc;

		$oProc->AlterColumn('addressbook', 'ab_id', array('type' => 'auto', 'nullable' => false));
		$oProc->AddColumn('addressbook', 'ab_company_id', array('type' => 'int', 'precision' => 4));
		$oProc->AddColumn('addressbook', 'ab_title', array('type' => 'varchar', 'precision' => 60));
		$oProc->AddColumn('addressbook', 'ab_address2', array('type' => 'varchar', 'precision' => 60));

		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.2';
	}

	function addressbook_v0_9_2to0_9_3update_owner($table, $field)
	{
		global $phpgw_setup, $oProc;
	
		$oProc->m_odb->query("select distinct($field) from $table");
		if ($oProc->m_odb->num_rows())
		{
			while ($oProc->m_odb->next_record())
			{
				$owner[count($owner)] = $phpgw_setup->db->f($field);
			}
			for($i=0;$i<count($owner);$i++)
			{
				$oProc->m_odb->query("select account_id from accounts where account_lid='".$owner[$i]."'");
				$oProc->m_odb->next_record();
				$oProc->m_odb->query("update $table set $field=".$oProc->m_odb->f("account_id")." where $field='".$owner[$i]."'");
			}
		}
		$oProc->AlterColumn($table, $field, array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => 0));
	}

	$test[] = '0.9.3pre1';
	function addressbook_upgrade0_9_3pre1()
	{
		global $phpgw_info;
		v0_9_2to0_9_3update_owner('addressbook','ab_owner');
		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.3pre2';
	}

	$test[] = '0.9.3pre6';
	function addressbook_upgrade0_9_3pre6()
	{
		global $phpgw_info, $oProc;

		$oProc->AddColumn('addressbook', 'ab_url', array('type' => 'varchar', 'precision' => 255));

		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.3pre7';
	}

	$test[] = '0.9.10pre12';
	function addressbook_upgrade0_9_10pre12()
	{
		global $phpgw_info, $phpgw_setup;
		$db1 = $phpgw_setup->db;
		$db2 = $phpgw_setup->db;

		$sql = "CREATE TABLE phpgw_addressbook (
			id int(8) PRIMARY KEY DEFAULT '0' NOT NULL auto_increment,
			lid varchar(32),
			tid char(1),
			owner int(8),
			fn varchar(64),
			sound varchar(64),
			org_name varchar(64),
			org_unit varchar(64),
			title varchar(64),
			n_family varchar(64),
			n_given varchar(64),
			n_middle varchar(64),
			n_prefix varchar(64),
			n_suffix varchar(64),
			label text,
			adr_poaddr varchar(64),
			adr_extaddr varchar(64),
			adr_street varchar(64),
			adr_locality varchar(32),
			adr_region varchar(32),
			adr_postalcode varchar(32),
			adr_countryname varchar(32),
			adr_work enum('n','y') NOT NULL,
			adr_home enum('n','y') NOT NULL,
			adr_parcel enum('n','y') NOT NULL,
			adr_postal enum('n','y') NOT NULL,
			tz varchar(8),
			geo varchar(32),
			a_tel varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
			a_tel_work enum('n','y') NOT NULL,
			a_tel_home enum('n','y') NOT NULL,
			a_tel_voice enum('n','y') NOT NULL,
			a_tel_msg enum('n','y') NOT NULL,
			a_tel_fax enum('n','y') NOT NULL,
			a_tel_prefer enum('n','y') NOT NULL,
			b_tel varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
			b_tel_work enum('n','y') NOT NULL,
			b_tel_home enum('n','y') NOT NULL,
			b_tel_voice enum('n','y') NOT NULL,
			b_tel_msg enum('n','y') NOT NULL,
			b_tel_fax enum('n','y') NOT NULL,
			b_tel_prefer enum('n','y') NOT NULL,
			c_tel varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
			c_tel_work enum('n','y') NOT NULL,
			c_tel_home enum('n','y') NOT NULL,
			c_tel_voice enum('n','y') NOT NULL,
			c_tel_msg enum('n','y') NOT NULL,
			c_tel_fax enum('n','y') NOT NULL,
			c_tel_prefer enum('n','y') NOT NULL,
			d_emailtype enum('INTERNET','CompuServe','AOL','Prodigy','eWorld','AppleLink','AppleTalk','PowerShare','IBMMail','ATTMail','MCIMail','X.400','TLX') NOT NULL,
			d_email varchar(64),
			d_email_work enum('n','y') NOT NULL,
			d_email_home enum('n','y') NOT NULL,
			UNIQUE (id)
		)";

		$db1->query($sql,__LINE__,__FILE__);

		$sql = "CREATE TABLE phpgw_addressbook_extra (
			contact_id int(11),
			contact_owner int(11),
			contact_name varchar(255),
			contact_value varchar(255)
		)";

		$db1->query($sql,__LINE__,__FILE__);

		$db1->query("SELECT * FROM addressbook",__LINE__,__FILE__);

		$fields = $extra = array();

		while ($db1->next_record())
		{
			$fields['id']	  = $db1->f("ab_id");
			$fields['owner']      = addslashes($db1->f("ab_owner"));
			$fields['n_given']    = addslashes($db1->f("ab_firstname"));
			$fields['n_family']   = addslashes($db1->f("ab_lastname"));
			$fields['d_email']    = addslashes($db1->f("ab_email"));
			$fields['b_tel']      = addslashes($db1->f("ab_hphone"));
			$fields['a_tel']      = addslashes($db1->f("ab_wphone"));
			$fields['c_tel']      = addslashes($db1->f("ab_fax"));
			$fields['fn']         = addslashes($db1->f("ab_firstname")." ".$db1->f("ab_lastname"));
			$fields["a_tel_work"] = "y";
			$fields["b_tel_home"] = "y";
			$fields["c_tel_fax"]  = "y";
			$fields['org_name']   = addslashes($db1->f("ab_company"));
			$fields['title']      = addslashes($db1->f("ab_title"));
			$fields['adr_street'] = addslashes($db1->f("ab_street"));
			$fields['adr_locality']       = addslashes($db1->f("ab_city"));
			$fields['adr_region']         = addslashes($db1->f("ab_state"));
			$fields['adr_postalcode']     = addslashes($db1->f("ab_zip"));

			$extra['pager']       = $db1->f("ab_pager");
			$extra['mphone']      = $db1->f("ab_mphone");
			$extra['ophone']      = $db1->f("ab_ophone");
			$extra['bday']        = $db1->f("ab_bday");
			$extra['notes']       = $db1->f("ab_notes");
			$extra['address2']    = $db1->f("ab_address2");
			$extra['url']         = $db1->f("ab_url");

			$sql="INSERT INTO phpgw_addressbook (org_name,n_given,n_family,fn,d_email,title,a_tel,a_tel_work,"
				. "b_tel,b_tel_home,c_tel,c_tel_fax,adr_street,adr_locality,adr_region,adr_postalcode,owner)"
				. " VALUES ('".$fields["org_name"]."','".$fields["n_given"]."','".$fields["n_family"]."','"
				. $fields["fn"]."','".$fields["d_email"]."','".$fields["title"]."','".$fields["a_tel"]."','"
				. $fields["a_tel_work"]."','".$fields["b_tel"]."','".$fields["b_tel_home"]."','"
				. $fields["c_tel"]."','".$fields["c_tel_fax"]."','".$fields["adr_street"]."','"
				. $fields["adr_locality"]."','".$fields["adr_region"]."','".$fields["adr_postalcode"]."','"
				. $fields["owner"] ."')";

			$db2->query($sql,__LINE__,__FILE__);

			while (list($name,$value) = each($extra))
			{
				$sql = "INSERT INTO phpgw_addressbook_extra VALUES ('".$fields["id"]."','" . $$fields["owner"] . "','"
					. addslashes($name) . "','" . addslashes($value) . "')";
				$db2->query($sql,__LINE__,__FILE__);
			}
		}
		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.10pre13';
	}

	$test[] = '0.9.10pre13';
	function addressbook_upgrade0_9_10pre13()
	{
		global $phpgw_info, $phpgw_setup;
		$db1 = $phpgw_setup->db;

		$phpgw_setup->db->query("alter table phpgw_addressbook add url varchar(128)");
		$phpgw_setup->db->query("alter table phpgw_addressbook add bday varchar(32)");
		$phpgw_setup->db->query("alter table phpgw_addressbook add note text");
		$phpgw_setup->db->query("alter table phpgw_addressbook_extra change contact_value contact_value text");

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='url'";
		$phpgw_setup->db->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->db->next_record())
		{
			$cid   = $phpgw_setup->db->f("contact_id");
			$cvalu = $phpgw_setup->db->f("contact_value");
			if ($cvalu)
			{
				$update = "UPDATE phpgw_addressbook set url=" . $cvalu . " WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='url'";
				$db1->query($delete);
			}
		}

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='bday'";
		$phpgw_setup->db->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->db->next_record())
		{
			$cid   = $phpgw_setup->db->f("contact_id");
			$cvalu = $phpgw_setup->db->f("contact_value");
			if ($cvalu)
			{
				$update = "UPDATE phpgw_addressbook set bday=" . $cvalu . " WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='bday'";
				$db1->query($delete);
			}
		}

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='notes'";
		$phpgw_setup->db->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->db->next_record())
		{
			$cid   = $phpgw_setup->db->f("contact_id");
			$cvalu = $phpgw_setup->db->f("contact_value");
			if ($cvalu)
			{
				$update = "UPDATE phpgw_addressbook set note=" . $cvalu . " WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='notes'";
				$db1->query($delete);
			}
		}

		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.10pre14';
	}

	$test[] = '0.9.10pre15';
	function addressbook_upgrade0_9_10pre15()
	{
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_work adr_work char(1) DEFAULT 'n' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_home adr_home char(1) DEFAULT 'n' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_parcel adr_parcel char(1) DEFAULT 'n' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_postal adr_postal char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_work a_tel_work char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_home a_tel_home char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_voice a_tel_voice char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_msg a_tel_msg char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_fax a_tel_fax char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_prefer a_tel_prefer char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_work b_tel_work char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_home b_tel_home char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_voice b_tel_voice char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_msg b_tel_msg char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_fax b_tel_fax char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_prefer b_tel_prefer char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change c_tel_work c_tel_work char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change c_tel_home c_tel_home char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change c_tel_voice c_tel_voice char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change c_tel_msg c_tel_msg char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change c_tel_fax c_tel_fax char(1) DEFAULT 'n' NOT NULL");
 		$phpgw_setup->db->query("alter table phpgw_addressbook change c_tel_prefer c_tel_prefer char(1) DEFAULT 'n' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change d_email_work d_email_work char(1) DEFAULT 'n' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change d_email_home d_email_home char(1) DEFAULT 'n' NOT NULL");

		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.10pre16';
	}
	
	$test[] = '0.9.10pre16';
	function addressbook_upgrade0_9_10pre16()
	{
		global $phpgw_info, $phpgw_setup;
		$db1 = $phpgw_setup->db;

		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel tel_work varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel tel_home varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change c_tel tel_fax  varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_work tel_msg varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_home tel_cell varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_voice tel_voice varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_msg tel_pager varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_fax tel_bbs varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_work tel_modem varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_home tel_car varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_voice tel_isdn varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change b_tel_msg tel_video varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change a_tel_prefer tel_prefer varchar(32)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change d_email email varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change d_emailtype email_type varchar(32) DEFAULT 'INTERNET' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook change d_email_work email_home varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change d_email_home email_home_type varchar(32) DEFAULT 'INTERNET' NOT NULL");
		$phpgw_setup->db->query("alter table phpgw_addressbook drop b_tel_prefer");
		$phpgw_setup->db->query("alter table phpgw_addressbook drop c_tel_prefer");
		$phpgw_setup->db->query("alter table phpgw_addressbook drop b_tel_fax");
		$phpgw_setup->db->query("alter table phpgw_addressbook drop c_tel_work");
		$phpgw_setup->db->query("alter table phpgw_addressbook drop c_tel_home");
		$phpgw_setup->db->query("alter table phpgw_addressbook drop c_tel_voice");
		$phpgw_setup->db->query("alter table phpgw_addressbook drop c_tel_msg");
		$phpgw_setup->db->query("alter table phpgw_addressbook drop c_tel_fax");

		$phpgw_setup->db->query("update phpgw_addressbook set tel_home=''   where tel_home='n'   OR tel_home='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_work=''   where tel_work='n'   OR tel_work='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_cell=''   where tel_cell='n'   OR tel_cell='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_voice=''  where tel_voice='n'  OR tel_voice='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_fax=''    where tel_fax='n'    OR tel_fax='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_car=''    where tel_car='n'    OR tel_car='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_pager=''  where tel_pager='n'  OR tel_pager='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_msg=''    where tel_msg='n'    OR tel_msg='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_bbs=''    where tel_bbs='n'    OR tel_bbs='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_modem=''  where tel_modem='n'  OR tel_modem='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_prefer='' where tel_prefer='n' OR tel_prefer='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_video=''  where tel_video='n'  OR tel_video='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set tel_isdn=''   where tel_isdn='n'   OR tel_isdn='y'");

		$sql = "SELECT * FROM phpgw_addressbook_extra WHERE contact_name='mphone'";
		$phpgw_setup->db->query($sql,__LINE__,__FILE__);

		while($phpgw_setup->db->next_record())
		{
			$cid   = $phpgw_setup->db->f("contact_id");
			$cvalu = $phpgw_setup->db->f("contact_value");
			if ($cvalu)
			{
				$update = "UPDATE phpgw_addressbook set tel_cell='" . $cvalu . "' WHERE id=" . $cid;
				$db1->query($update);
				$delete = "DELETE FROM phpgw_addressbook_extra WHERE contact_id=" . $cid . " AND contact_name='mphone'";
				$db1->query($delete);
			}
		}
		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.10pre17';
	}

	$test[] = '0.9.10pre17';
	function addressbook_upgrade0_9_10pre17()
	{
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->db->query("alter table phpgw_addressbook add pubkey text");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_street adr_one_street varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_locality adr_one_locality varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_region adr_one_region varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_postalcode adr_one_postalcode varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_countryname adr_one_countryname varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_work adr_one_type varchar(32)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_home adr_two_type varchar(32)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_poaddr adr_two_street varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_extaddr adr_two_locality varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_parcel adr_two_region varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook change adr_postal adr_two_postalcode varchar(64)");
		$phpgw_setup->db->query("alter table phpgw_addressbook add adr_two_countryname varchar(64)");

		$phpgw_setup->db->query("update phpgw_addressbook set adr_one_type=''       where adr_one_type='n' OR adr_one_type='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set adr_two_type=''       where adr_two_type='n' OR adr_two_type='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set adr_two_region=''     where adr_two_region='n' OR adr_two_region='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set adr_two_postalcode='' where adr_two_postalcode='n' OR adr_two_postalcode='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set email_home=''         where email_home='n' OR email_home='y'");
		$phpgw_setup->db->query("update phpgw_addressbook set email_home_type=''    where email_home_type='n' OR  email_home_type='y'");

		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.10pre18';
	}

	$test[] = '0.9.10pre20';
	function addressbook_upgrade0_9_10pre20()
	{
		global $phpgw_info, $oProc;

		$oProc->AddColumn('phpgw_addressbook', 'access', array('type' => 'char', 'precision' => 7));

		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.10pre21';
	}

	$test[] = '0.9.10pre21';
	function addressbook_upgrade0_9_10pre21()
	{
		global $phpgw_info, $oProc;

		$oProc->AddColumn('phpgw_addressbook', 'cat_id', array('type' => 'varchar', 'precision' => 32));

		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.10pre22';
	}

	$test[] = '0.9.10pre23';
	function addressbook_upgrade0_9_10pre23()
	{
		global $phpgw_info, $oProc;

		$oProc->m_odb->query("UPDATE phpgw_addressbook SET tid='n' WHERE tid is null");

		$phpgw_info['setup']['currentver']['addressbook'] = '0.9.10pre24';
	}
?>
