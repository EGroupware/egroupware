<?
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

	function upgradeaddr() {
		global $phpgw_info, $phpgw_setup;
		$phpgw_setup->loaddb();

		// create new contacts class tables
		$sql = "DROP TABLE IF EXISTS phpgw_addressbook";
		$phpgw_setup->db->query($sql);

		$sql = "DROP TABLE IF EXISTS phpgw_addressbook_extra";
		$phpgw_setup->db->query($sql);

		$sql = "CREATE TABLE phpgw_addressbook (
			id int(8) DEFAULT '0' NOT NULL,
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
			adr_work enum('y','n') DEFAULT 'n' NOT NULL,
			adr_home enum('y','n') DEFAULT 'n' NOT NULL,
			adr_parcel enum('y','n') DEFAULT 'n' NOT NULL,
			adr_postal enum('y','n') DEFAULT 'n' NOT NULL,
			tz varchar(8),
			geo varchar(32),
			a_tel varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
			a_tel_work enum('y','n') DEFAULT 'n' NOT NULL,
			a_tel_home enum('y','n') DEFAULT 'n' NOT NULL,
			a_tel_voice enum('y','n') DEFAULT 'n' NOT NULL,
			a_tel_msg enum('y','n') DEFAULT 'n' NOT NULL,
			a_tel_fax enum('y','n') DEFAULT 'n' NOT NULL,
			a_tel_prefer enum('y','n') DEFAULT 'n' NOT NULL,
			b_tel varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
			b_tel_work enum('y','n') DEFAULT 'n' NOT NULL,
			b_tel_home enum('y','n') DEFAULT 'n' NOT NULL,
			b_tel_voice enum('y','n') DEFAULT 'n' NOT NULL,
			b_tel_msg enum('y','n') DEFAULT 'n' NOT NULL,
			b_tel_fax enum('y','n') DEFAULT 'n' NOT NULL,
			b_tel_prefer enum('y','n') DEFAULT 'n' NOT NULL,
			c_tel varchar(40) DEFAULT '+1 (000) 000-0000' NOT NULL,
			c_tel_work enum('y','n') DEFAULT 'n' NOT NULL,
			c_tel_home enum('y','n') DEFAULT 'n' NOT NULL,
			c_tel_voice enum('y','n') DEFAULT 'n' NOT NULL,
			c_tel_msg enum('y','n') DEFAULT 'n' NOT NULL,
			c_tel_fax enum('y','n') DEFAULT 'n' NOT NULL,
			c_tel_prefer enum('y','n') DEFAULT 'n' NOT NULL,
			d_emailtype enum('INTERNET','CompuServe','AOL','Prodigy','eWorld','AppleLink','AppleTalk','PowerShare','IBMMail','ATTMail','MCIMail','X.400','TLX') DEFAULT 'INTERNET' NOT NULL,
			d_email varchar(64),
			d_email_work enum('y','n') DEFAULT 'n' NOT NULL,
			d_email_home enum('y','n') DEFAULT 'n' NOT NULL,
			PRIMARY KEY (id),
			UNIQUE id (id)
		)";

		$phpgw_setup->db->query($sql);

		$sql = "CREATE TABLE phpgw_addressbook_extra (
			contact_id int(11),
			contact_owner int(11),
			contact_name varchar(255),
			contact_value varchar(255)
		)";
  
		$phpgw_setup->db->query($sql);  

		// create an extra db object for the two nested queries below
		$db1 = $db2 = $db3 = $phpgw_setup->db;
		
		// read in old addressbook
		$db1->query("select * from addressbook");

		while ( $db1->next_record() ) {
			$fields["org_name"]			= $db1->f("ab_company");
			$fields["n_given"]			= $db1->f("ab_firstname");
			$fields["n_family"]			= $db1->f("ab_lastname");
			$fields["fn"]				= $db1->f("ab_firstname")." ".$phpgw_setup->db->f("ab_lastname");
			$fields["d_email"]			= $db1->f("ab_email");
			$fields["title"]			= $db1->f("ab_title");
			$fields["a_tel"]			= $db1->f("ab_wphone");
			$fields["a_tel_work"]		= "y";
			$fields["b_tel"]			= $db1->f("ab_hphone");
			$fields["b_tel_home"]		= "y";
			$fields["c_tel"]			= $db1->f("ab_fax");
			$fields["c_tel_fax"]		= "y";
			$fields["adr_street"]		= $db1->f("ab_street");
			$fields["adr_locality"]		= $db1->f("ab_city");
			$fields["adr_region"]		= $db1->f("ab_state");
			$fields["adr_postalcode"]	= $db1->f("ab_zip");
			$fields["owner"]			= $db1->f("ab_owner");

			$extra["pager"]				= $db1->f("ab_pager");
			$extra["mphone"]			= $db1->f("ab_mphone");
			$extra["ophone"]			= $db1->f("ab_ophone");
			$extra["address2"]			= $db1->f("ab_address2");
			$extra["bday"]				= $db1->f("ab_bday");
			$extra["url"]				= $db1->f("ab_url");
			$extra["notes"]				= $db1->f("ab_notes");

			// add this record's standard with current entry's owner as owner
			$sql="INSERT INTO phpgw_addressbook ("
				. "org_name,n_given,n_family,fn,d_email,title,a_tel,a_tel_work,"
				. "b_tel,b_tel_home,c_tel,c_tel_fax,adr_street,adr_locality,adr_region,adr_postalcode,owner)"
				. " VALUES ('".$fields["org_name"]."','".$fields["n_given"]."','".$fields["n_family"]."','"
				. $fields["fn"]."','".$fields["d_email"]."','".$fields["title"]."','".$fields["a_tel"]."','"
				. $fields["a_tel_work"]."','".$fields["b_tel"]."','".$fields["b_tel_home"]."','"
				. $fields["c_tel"]."','".$fields["c_tel_fax"]."','".$fields["adr_street"]."','"
				. $fields["adr_locality"]."','".$fields["adr_region"]."','".$fields["adr_postalcode"]."',"
				. $fields["owner"].")";

			$db1->query($sql);

			// fetch the id just inserted
			$db2->query("SELECT max(id) FROM phpgw_addressbook ",__LINE__,__FILE__);
			$db2->next_record();
			$id = $db2->f(0);

			// insert extra data for this record into extra fields table
			while (list($name,$value) = each($extra)) {
				$db3->query("INSERT INTO phpgw_addressbook_extra VALUES ('$id','" . $$fields["owner"] . "','"
					. addslashes($name) . "','" . addslashes($value) . "')",__LINE__,__FILE__);
			}
		}
	}
	upgradeaddr();
?>
