<?php
/**************************************************************************\
* phpGroupWare - addressbook                                               *
* http://www.phpgroupware.org                                              *
* Written by Joseph Engo <jengo@mail.com>                                  *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

	// Perform acl check, set $rights
	if(!isset($owner)) { $owner = 0; } 

	$grants = $phpgw->acl->get_grants('addressbook');
  
	if(!isset($owner) || !$owner) {
		$owner = $phpgw_info['user']['account_id'];
		$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
	} else {
		if($grants[$owner]) {
			$rights = $grants[$owner];
			if (!($rights & PHPGW_ACL_READ)) {
				$owner = $phpgw_info['user']['account_id'];
				$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
			}
		}
	}

	// this cleans up the fieldnames for display
	function display_name($column) {
		$abc = array(
			"fn"				=> "full name",        //'firstname lastname'
			"sound"				=> "",
			"org_name"			=> "company name",  //company
			"org_unit"			=> "department",  //division
			"title"				=> "title",
			"n_prefix"			=> "prefix",
			"n_given"			=> "first name",   //firstname
			"n_middle"			=> "middle name",
			"n_family"			=> "last name",  //lastname
			"n_suffix"			=> "suffix",
			"label"				=> "label",
			"adr_street"		=> "street",
			"adr_locality"		=> "city",   //city
			"adr_region"		=> "state",     //state
			"adr_postalcode"	=> "zip code", //zip
			"adr_countryname"	=> "country",
			"adr_work"			=> "",   //yn
			"adr_home"			=> "",   //yn
			"adr_parcel"		=> "", //yn
			"adr_postal"		=> "", //yn
			"tz"				=> "time zone",
			"geo"				=> "geo",
			"a_tel"				=> "home phone",
			"a_tel_work"		=> "",   //yn
			"a_tel_home"		=> "",   //yn
			"a_tel_voice"		=> "",  //yn
			"a_tel_msg"			=> "",    //yn
			"a_tel_fax"			=> "",    //yn
			"a_tel_prefer"		=> "", //yn
			"b_tel"				=> "work phone",
			"b_tel_work"		=> "",   //yn
			"b_tel_home"		=> "",   //yn
			"b_tel_voice"		=> "",  //yn
			"b_tel_msg"			=> "",    //yn
			"b_tel_fax"			=> "",    //yn
			"b_tel_prefer"		=> "", //yn
			"c_tel"				=> "fax",
			"c_tel_work"		=> "",   //yn
			"c_tel_home"		=> "",   //yn
			"c_tel_voice"		=> "",  //yn
			"c_tel_msg"			=> "",    //yn
			"c_tel_fax"			=> "",    //yn
			"c_tel_prefer"		=> "", //yn
			"d_email"			=> "email",
			"d_emailtype"		=> "email type",   //'INTERNET','CompuServe',etc...
			"d_email_work"		=> "",  //yn
			"d_email_home"		=> "",  //yn
			//"access"			=> "access"
			"pager"				=> "Pager",
			"mphone"			=> "mobile phone",
			"ophone"			=> "other phone",
			"address2"			=> "address2",
			"bday"				=> "birthday",
			"url"				=> "url",
			"note"				=> "notes"
		);

		while($name = each($abc) ) {
			if ($column == $name[0]) { return lang($name[1]); }
		}
	}

	function addressbook_read_entries($start,$offset,$qcols,$query,$qfilter,$sort,$order,$userid="") {
		global $this,$rights;
		$readrights = $rights & PHPGW_ACL_READ;
		$entries = $this->read($start,$offset,$qcols,$query,$qfilter,$sort,$order,$readrights);
		return $entries;
	}

	function addressbook_read_entry($id,$fields,$userid="") {
		global $this,$rights;
		if ($rights & PHPGW_ACL_READ) {
			$entry = $this->read_single_entry($id,$fields);
			return $entry;
		} else {
			$rtrn = array("No access" => "No access");
			return $rtrn;
		}
	}

	function addressbook_add_entry($userid,$fields) {
		global $this,$rights;
		if ($rights & PHPGW_ACL_ADD) {
			$this->add($userid,$fields);
		}
		return;
	}

	function addressbook_get_lastid() {
		global $this;
	 	$entry = $this->read_last_entry();
		$ab_id = $entry[0]["id"];
		return $ab_id;
	}
	
	function addressbook_update_entry($id,$userid,$fields) {
		global $this,$rights;
		if ($rights & PHPGW_ACL_EDIT) {
			$this->update($id,$userid,$fields);
		}
		return;
	}

	function addressbook_form($format,$action,$title="",$fields="") { // used for add/edit
		global $phpgw, $phpgw_info;
     
		#$t = new Template($phpgw_info["server"]["app_tpl"]);
		$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
		$t->set_file(array( "form"	=> "form.tpl"));
		
		$email        = $fields["d_email"];
		$emailtype    = $fields["d_emailtype"];
		$firstname    = $fields["n_given"];
		$middle       = $fields["n_middle"];
		$prefix       = $fields["n_prefix"];
		$suffix       = $fields["n_suffix"];
		$lastname     = $fields["n_family"];
		$title        = $fields["title"];
		$hphone       = $fields["a_tel"];
		$wphone       = $fields["b_tel"];
		$fax          = $fields["c_tel"];
		$pager        = $fields["pager"];
		$mphone       = $fields["mphone"];
		$ophone       = $fields["ophone"];
		$street       = $fields["adr_street"];
		$address2     = $fields["address2"];
		$city         = $fields["adr_locality"];
		$state        = $fields["adr_region"];
		$zip          = $fields["adr_postalcode"];
		$country      = $fields["adr_countryname"];
		$timezone     = $fields["tz"];
		$bday         = $fields["bday"];
		$notes        = stripslashes($fields["note"]);
		$company      = $fields["org_name"];
		$department   = $fields["org_unit"];
		$url          = $fields["url"];
		//$access       = $fields["access"];

		if ($format != "view") {
			$email 	   = "<input name=\"email\" value=\"$email\">";
			$firstname = "<input name=\"firstname\" value=\"$firstname\">";
			$lastname  = "<input name=\"lastname\" value=\"$lastname\">";
			$middle    = "<input name=\"middle\" value=\"$middle\">";
			$prefix    = "<input name=\"prefix\" value=\"$prefix\" size=\"10\">";
			$suffix    = "<input name=\"suffix\" value=\"$suffix\" size=\"10\">";
			$title     = "<input name=\"title\" value=\"$title\">";
			$hphone    = "<input name=\"hphone\" value=\"$hphone\">";
			$wphone	   = "<input name=\"wphone\" value=\"$wphone\">";
			$fax       = "<input name=\"fax\" value=\"$fax\">";
			$pager     = "<input name=\"pager\" value=\"$pager\">";
			$mphone    = "<input name=\"mphone\" value=\"$mphone\">";
			$ophone	   = "<input name=\"ophone\" value=\"$ophone\">";
			$street	   = "<input name=\"street\" value=\"$street\">";
			$address2  = "<input name=\"address2\" value=\"$address2\">";
			$city      = "<input name=\"city\" value=\"$city\">";
			$state     = "<input name=\"state\" value=\"$state\">";
			$zip       = "<input name=\"zip\" value=\"$zip\">";
			$country   = "<input name=\"country\" value=\"$country\">";

/*
      if($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $company  = '<select name="company">';
	if (!$company) {
          $company .= '<option value="0" SELECTED>'. lang("none").'</option>';
        } else {
          $company .= '<option value="0">'. lang("none").'</option>';
        }
        $phpgw->db->query("select company_id,company_name from customers order by company_name");
        while ($phpgw->db->next_record()) {
          $ncust = $phpgw->db->f("company_id");
          $company .= '<option value="' . $ncust . '"';
          if ( $company_id == $ncust ) {
            $company .= " selected";
          }
          $company .= ">" . $phpgw->db->f("company_name") . "</option>";
        }
        $company .=  "</select>";
      } else { */
			$company = "<input name=\"company\" value=\"$company\">";
			$department = "<input name=\"department\" value=\"$department\">";
/*    } */

			if (strlen($bday) > 2) {
				list( $month, $day, $year ) = split( '/', $bday );
				$temp_month[$month] = "SELECTED";
				$bday_month = "<select name=bday_month>"
							. "<option value=\"\" $temp_month[0]> </option>"
							. "<option value=1 $temp_month[1]>January</option>" 
							. "<option value=2 $temp_month[2]>February</option>"
							. "<option value=3 $temp_month[3]>March</option>"
							. "<option value=4 $temp_month[4]>April</option>"
							. "<option value=5 $temp_month[5]>May</option>"
							. "<option value=6 $temp_month[6]>June</option>" 
							. "<option value=7 $temp_month[7]>July</option>"
							. "<option value=8 $temp_month[8]>August</option>"
							. "<option value=9 $temp_month[9]>September</option>"
							. "<option value=10 $temp_month[10]>October</option>"
							. "<option value=11 $temp_month[11]>November</option>"
							. "<option value=12 $temp_month[12]>December</option>"
							. "</select>";
				$bday_day   = '<input maxlength="2" name="bday_day" value="' . $day . '" size="2">';
				$bday_year  = '<input maxlength="4" name="bday_year" value="' . $year . '" size="4">';
			} else {
				$bday_month = "<select name=bday_month>"
							. "<option value=\"\" SELECTED> </option>"
							. "<option value=1>January</option>" 
							. "<option value=2>February</option>"
							. "<option value=3>March</option>"
							. "<option value=4>April</option>"
							. "<option value=5>May</option>"
							. "<option value=6>June</option>" 
							. "<option value=7>July</option>"
							. "<option value=8>August</option>"
							. "<option value=9>September</option>"
							. "<option value=10>October</option>"
							. "<option value=11>November</option>"
							. "<option value=12>December</option>"
							. "</select>";
				$bday_day  = '<input name="bday_day" size="2" maxlength="2">';
				$bday_year = '<input name="bday_year" size="4" maxlength="4">';
			}

			$time_zone = "<select name=\"timezone\">\n";
			for ($i = -23; $i<24; $i++) {
				$time_zone .= "<option value=\"$i\"";
				if ($i == $timezone)
					$time_zone .= " selected";
				if ($i < 1)
					$time_zone .= ">$i</option>\n";
				else
					$time_zone .= ">+$i</option>\n";
			}
			$time_zone .= "</select>\n";

			$this = CreateObject("phpgwapi.contacts");
			$email_type = '<select name=email_type>';
			while ($type = each($this->email_types)) {
				$email_type .= '<option value="'.$type[0].'"';
				if ($type[0] == $emailtype) { $email_type .= ' selected'; }
					$email_type .= '>'.$type[1].'</option>';
			}
			$email_type .= "</select>";
    
			$notes	 = '<TEXTAREA cols="60" name="notes" rows="4">' . $notes . '</TEXTAREA>';
		} else {
			$notes	= "<form><TEXTAREA cols=\"60\" name=\"notes\" rows=\"4\">"
					. $notes . "</TEXTAREA></form>";
			if ($bday == "//")
				$bday = "";

/*
      if($phpgw_info["apps"]["timetrack"]["enabled"]) {
        $company = $company_name;
      } else { */
			$company = $company;
/*    } */
		}

		if ($action) {
			echo "<FORM action=\"".$phpgw->link($action)."\" method=\"post\">\n";
		}

		// test:
		//echo "Time track app status = " . $phpgw_info["apps"]["timetrack"]["enabled"];

		if (! ereg("^http://",$url)) {
			$url = "http://". $url;
		} 

		$birthday = $phpgw->common->dateformatorder($bday_year,$bday_month,$bday_day)
					. '<font face="'.$theme["font"].'" size="-2">(e.g. 1969)</font>';
/*
		// This is now handled by acl code, and should go away
		if ($format == "Edit") {
			if ($access != "private" && $access != "public") {
				$access_link .= '<td><font size="-1">'.lang("Group access").':</font></td>'
							. '<td colspan="3"><font size="-1">'
							. $phpgw->accounts->convert_string_to_names($access);
			} else {
				$access_link .=  '<td><font size="-1">'.lang("Access").':</font></td>'
							. '<td colspan="3"><font size="-1">' . $access;
			}
		} else {
			$access_link .= '<td><font size="-1">'.lang("Access").':</font></td>
    <td colspan="3">
      <font size="-1">
      <select name="access">
       <option value="private"';

			if ($access == "private") $access_link .= ' selected>'.lang("private").'</option>';
			else $access_link .= '>'.lang("private").'</option>';

			$access_link .= '<option value="public"
	';

			if ($access == "public")
				$access_link .= ' selected>'.lang("Global Public").'</option>';
			else $access_link .= '>'.lang("Global Public").'</option>';
        
			$access_link .= '<option value="group"
        ';

			if ($access != "public" && $access != "private" && $access != "")
				$access_link .= ' selected>'.lang("Group Public").'</option></select>';
			else
				$access_link .= '>'.lang("Group Public").'</option></select>';

			$access_link .= '</tr>
        ';
		}

		if ($format != "view") {
			$access_link .= '<tr><td><font size="-1">' . lang("Which groups")
						. ':</font></td><td colspan="3"><select name="n_groups[]" '
						. 'multiple size="5">';

			$user_groups = $phpgw->accounts->read_group_names($fields["owner"]);
			for ($i=0;$i<count($user_groups);$i++) {
				$access_link .= '<option value="'.$user_groups[$i][0].'"';
				if (ereg(",".$user_groups[$i][0].",",$access))
					$access_link .= ' selected';

					$access_link .= '>'.$user_groups[$i][1].'</option>
	    ';
			}
			$access_link .= '</select></font></td></tr>';
			$t->set_var("lang_access",lang("access"));
		} else {
			$access_link = '';
			$t->set_var("lang_access",'');
		}
*/
		if ($format == "view")
			$create .= '<tr><td><font size="-1">'.lang("Created by").':</font></td>'
					. '<td colspan="3"><font size="-1">'
					. grab_owner_name($fields["owner"]);
		else
			$create = '';
  
		$t->set_var("lang_lastname",lang("Last Name"));
		$t->set_var("lastname",$lastname);
		$t->set_var("lang_firstname",lang("First Name"));
		$t->set_var("firstname",$firstname);
		$t->set_var("lang_middle",lang("Middle Name"));
		$t->set_var("middle",$middle);
		$t->set_var("lang_prefix",lang("Prefix"));
		$t->set_var("prefix",$prefix);
		$t->set_var("lang_suffix",lang("Suffix"));
		$t->set_var("suffix",$suffix);
		$t->set_var("lang_company",lang("Company Name"));
		$t->set_var("company",$company);
		$t->set_var("lang_department",lang("Department"));
		$t->set_var("department",$department);
		$t->set_var("lang_title",lang("Title"));
		$t->set_var("title",$title);
		$t->set_var("lang_email",lang("Email"));
		$t->set_var("email",$email);
		$t->set_var("lang_email_type",lang("EMail Type"));
		$t->set_var("email_type",$email_type);
		$t->set_var("lang_url",lang("URL"));
		$t->set_var("url",$url);
		$t->set_var("lang_timezone",lang("time zone offset"));
		$t->set_var("timezone",$time_zone);
		$t->set_var("lang_hphone",lang("Home Phone"));
		$t->set_var("hphone",$hphone);
		$t->set_var("lang_fax",lang("fax"));
		$t->set_var("fax",$fax);
		$t->set_var("lang_wphone",lang("Work Phone"));
		$t->set_var("wphone",$wphone);
		$t->set_var("lang_pager",lang("Pager"));
		$t->set_var("pager",$pager);
		$t->set_var("lang_mphone",lang("Mobile"));
		$t->set_var("mphone",$mphone);
		$t->set_var("lang_ophone",lang("Other Number"));
		$t->set_var("ophone",$ophone);
		$t->set_var("lang_street",lang("Street"));
		$t->set_var("street",$street);
		$t->set_var("lang_birthday",lang("Birthday"));
		$t->set_var("birthday",$birthday);
		$t->set_var("lang_address2",lang("Line 2"));
		$t->set_var("address2",$address2);
		$t->set_var("lang_city",lang("city"));
		$t->set_var("city",$city);
		$t->set_var("lang_state",lang("state"));
		$t->set_var("state",$state);
		$t->set_var("lang_zip",lang("Zip Code"));
		$t->set_var("zip",$zip);
		$t->set_var("lang_country",lang("Country"));
		$t->set_var("country",$country);
		$t->set_var("access_link",$access_link);
		$t->set_var("create",$create);
		$t->set_var("lang_notes",lang("notes"));
		$t->set_var("notes",$notes);
		
		$t->parse("out","form");
		$t->pparse("out","form");
	} //end form function

	function parsevcard($filename,$access) {
		global $phpgw;
		global $phpgw_info;

		$vcard = fopen($filename, "r");
		// Make sure we have a file to read.
		if (!$vcard) {
			fclose($vcard);
			return FALSE;
		}

		// Keep running through this to support vcards
		// with multiple entries.
		while (!feof($vcard)) {
			if(!empty($varray))
				unset($varray);

			// Make sure our file is a vcard.
			// I should deal with empty line at the
			// begining of the file. Those will fail here.
			$vline = fgets($vcard,20);
			$vline = strtolower($vline);
			if(strcmp("begin:vcard", substr($vline, 0, strlen("begin:vcard")) ) != 0) {	
				fclose($vcard);
				return FALSE;
			}
			
			// Write the vcard into an array.
			// You can have multiple vcards in one file.
			// I only deal with halve of that. :)
			// It will only return values from the 1st vcard.
			$varray[0] = "begin";
			$varray[1] = "vcard";
		$i=2;
			while(!feof($vcard) && strcmp("end:vcard", strtolower(substr($vline, 0, strlen("end:vcard"))) ) !=0 ) {
					$vline = fgets($vcard,4096);
				// Check for folded lines and escaped colons '\:'
				$la = explode(":", $vline);

				if (count($la) > 1) {
					$varray[$i] = strtolower($la[0]);
					$i++;

					for($j=1;$j<=count($la);$j++) {
						$varray[$i] .= $la[$j];
					}
					$i++;
				} else { // This is the continuation of a folded line.
					$varray[$i-1] .= $la[0];
				}
			}

			fillab($varray,$access); // Add this entry to the addressbook before
								// moving on to the next one.
		} // while(!feof($vcard))

		fclose($vcard);
		return TRUE;
	}


	function fillab($varray,$access) {
		global $phpgw;
		global $phpgw_info;

		$i=0;
		// incremented by 2
		while($i < count($varray)) {
			$k = explode(";",$varray[$i]); // Key
			$v = explode(";",$varray[$i+1]); // Values
			for($h=0;$h<count($k);$h++) {
				switch($k[$h]) {
					case "fn":
						$formattedname = $v[0];
						break;
					case "n":
						$lastname  = $v[0];
						$firstname = $v[1];
						break;
					case "bday":
						$bday = $v[0];
						break;
					case "adr": // This one is real ugly. :(
						$street   = $v[2];
						$address2 = $v[1] . " " . $v[0];
						$city     = $v[3];
						$state    = $v[4];
						$zip      = $v[5];
						$country  = $v[6];
						break;
					case "tel": // Check to see if there another phone entry.
						if(!ereg("home",$varray[$i])  && !ereg("work",$varray[$i]) &&
							!ereg("fax",$varray[$i])   && !ereg("cell",$varray[$i]) &&
							!ereg("pager",$varray[$i]) && !ereg("bbs",$varray[$i])  &&
							!ereg("modem",$varray[$i]) && !ereg("car",$varray[$i])  &&
							!ereg("isdn",$varray[$i])  && !ereg("video",$varray[$i]) ) {
							// There isn't a seperate home entry.
							// Use this number.
							$hphone = $v[0];
						}
						break;
					case "home":
						$hphone = $v[0];
						break;
					case "work":
						$wphone = $v[0];
						break;
					case "fax":
						$fax = $v[0];
						break;
					case "pager":
						$pager = $v[0];
						break;
					case "cell":
						$mphone = $v[0];
						break;
					case "pref":
						$notes .= "Preferred phone number is ";
						$notes .= $v[0] . "\n";
						break;
					case "msg":
						$notes .= "Messaging service on number "; 
						$notes .= $v[0] . "\n";
						break;
					case "bbs":
						$notes .= "BBS phone number ";
						$notes .= $v[0] . "\n";
						break;
					case "modem":
						$notes .= "Modem phone number ";
						$notes .= $v[0] . "\n";
						break;
					case "car":
						$notes .= "Car phone number ";
						$notes .= $v[0] . "\n";
						break;
					case "isdn":
						$notes .= "ISDN number ";
						$notes .= $v[0] . "\n";
						break;
					case "video":
						$notes .= "Video phone number ";
						$notes .= $v[0] . "\n";
						break;
					case "email":
						if(!ereg("internet",$varray[$i])) {
							$email = $v[0];
						}
						break;
					case "internet":
						$email = $v[0];
						break;
					case "title":
						$title = $v[0];
						break;
						case "org":
						$company = $v[0];
						if(count($v) > 1) {
							$notes .= $v[0] . "\n";
							for($j=1;$j<count($v);$j++) {
								$notes .= $v[$j] . "\n";
							}
						}
						break;
					default: // Throw most other things into notes.
						break;
				} // switch
			} // for
			$i++;
		} // All of the values that are getting filled are.

/*    if($phpgw_info["apps"]["timetrack"]["enabled"]) {
       $sql = "insert into addressbook (ab_owner,ab_access,ab_firstname,ab_lastname,ab_title,ab_email,"
        . "ab_hphone,ab_wphone,ab_fax,ab_pager,ab_mphone,ab_ophone,ab_street,ab_address2,ab_city,"
        . "ab_state,ab_zip,ab_bday,"
        . "ab_notes,ab_company_id) values ('" . $phpgw_info["user"]["account_id"] . "','$access','"
        . addslashes($firstname). "','"
        . addslashes($lastname) . "','"
        . addslashes($title)  . "','"
        . addslashes($email)  . "','"
        . addslashes($hphone) . "','"
        . addslashes($wphone) . "','"
        . addslashes($fax)    . "','"
        . addslashes($pager)  . "','"
        . addslashes($mphone) . "','"
        . addslashes($ophone) . "','"
        . addslashes($street) . "','"
        . addslashes($address2) . "','"
        . addslashes($city)   . "','"
        . addslashes($state)  . "','"
        . addslashes($zip)    . "','"
        . addslashes($bday)   . "','"
        . addslashes($notes)  . "','"
        . addslashes($company). "')";
     } else {
*/

		$fields["owner"]          = $phpgw_info["user"]["account_id"];
		$fields["access"]         = $access;
		$fields["n_given"]        = addslashes($firstname);
		$fields["n_family"]       = addslashes($lastname);
		$fields["fn"]             = addslashes($firstname . " " . $lastname);
		$fields["title"]          = addslashes($title);
		$fields["d_email"]        = addslashes($email);
		$fields["a_tel"]          = addslashes($hphone);
		$fields["b_tel"]          = addslashes($wphone);
		$fields["c_tel"]          = addslashes($fax);
		$fields["pager"]          = addslashes($pager);
		$fields["mphone"]         = addslashes($mphone);
		$fields["ophone"]         = addslashes($ophone);
		$fields["adr_street"]     = addslashes($street);
		$fields["address2"]       = addslashes($address2);
		$fields["adr_locality"]   = addslashes($city);
		$fields["adr_region"]     = addslashes($state);
		$fields["adr_postalcode"] = addslashes($zip);
		$fields["bday"]           = addslashes($bday);
		$fields["notes"]          = addslashes($notes);
		$fields["org_name"]       = addslashes($company);
		
		$this = CreateObject("phpgwapi.contacts");
		$this->add($phpgw_info["user"]["account_id"],$fields);
	}

?>
