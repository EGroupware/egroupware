<?
	function upgrade_addressbook {
		global $phpgw_info, $phpgw_setup;

		// create new contacts class tables
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
			UNIQUE id (id),
		)";

		$phpgw_setup->db->query($sql);

		$sql = "CREATE TABLE phpgw_addressbook_extra (
			contact_id int(11),
			contact_owner int(11),
			contact_name varchar(255),
			contact_value varchar(255)
		)";
  
		$phpgw_setup->db->query($sql);  

		// read in old addressbook
		$phpgw_setup->db->query("select * from addressbook");

		// create a couple of extra db objects for the two nested queries below
		$db2 = $phpgw_setup->db;
		$db3 = $phpgw_setup->db;

		while ( $phpgw_setup->db->nextrecord() ) {
			$fields["org_name"]			= $phpgw->db->f("ab_company");
			$fields["n_given"]			= $phpgw->db->f("ab_firstname");
			$fields["n_family"]			= $phpgw->db->f("ab_lastname");
			$fields["fn"]				= $phpgw->db->f("ab_firstname")." ".$phpgw->db->f("ab_lastname");
			$fields["d_email"]			= $phpgw->db->f("ab_email");
			$fields["title"]			= $phpgw->db->f("ab_title");
			$fields["a_tel"]			= $phpgw->db->f("ab_wphone");
			$fields["a_tel_work"]		= "y";
			$fields["b_tel"]			= $phpgw->db->f("ab_hphone");
			$fields["b_tel_home"]		= "y";
			$fields["c_tel"]			= $phpgw->db->f("ab_fax");
			$fields["c_tel_fax"]		= "y";
			$fields["adr_street"]		= $phpgw->db->f("ab_street");
			$fields["adr_locality"]		= $phpgw->db->f("ab_city");
			$fields["adr_region"]		= $phpgw->db->f("ab_state");
			$fields["adr_postalcode"]	= $phpgw->db->f("ab_zip");
			$fields["owner"]			= $phpgw->db->f("owner");

			$extra["pager"]				= $phpgw->db->f("ab_pager");
			$extra["mphone"]			= $phpgw->db->f("ab_mphone");
			$extra["ophone"]			= $phpgw->db->f("ab_ophone");
			$extra["address2"]			= $phpgw->db->f("ab_address2");
			$extra["bday"]				= $phpgw->db->f("ab_bday");
			$extra["url"]				= $phpgw->db->f("ab_url");
			$extra["notes"]				= $phpgw->db->f("ab_notes");

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

			$phpgw_setup->db2->query($sql);

			// fetch the id just inserted
			$phpgw_setup->db2->query("SELECT max(id) FROM phpgw_addressbook ",__LINE__,__FILE__);
			$phpgw_setup->db2->next_record();
			$id = $phpgw_setup->db2->f(0);

			// insert extra data for this record into extra fields table
			while (list($name,$value) = each($extra)) {
				$phpgw_setup->db3->query("INSERT INTO phpgw_addressbook_extra VALUES ('$id','" . $$fields["owner"] . "','"
					. addslashes($name) . "','" . addslashes($value) . "')",__LINE__,__FILE__);
			}
		}
	}
?>
