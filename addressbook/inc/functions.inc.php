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
			"label"               => "label",
			"adr_one_street"      => "business street",
			"adr_one_locality"    => "business city",   //city
			"adr_one_region"      => "business state",     //state
			"adr_one_postalcode"  => "business zip code", //zip
			"adr_one_countryname" => "business country",
			"adr_two_street"      => "home street",
			"adr_two_locality"    => "home city",   //city
			"adr_two_region"      => "home state",     //state
			"adr_two_postalcode"  => "home zip code", //zip
			"adr_two_countryname" => "home country",
			"tz"				=> "time zone",
			"geo"				=> "geo",
			"tel_work"		    => "business phone",   //yn
			"tel_home"		    => "home phone",   //yn
			"tel_voice"		    => "voice phone",  //yn
			"tel_msg"			=> "message phone",    //yn
			"tel_fax"			=> "fax",    //yn
			"tel_pager"			=> "pager",
			"tel_cell"          => "mobile phone",
			"tel_bbs"			=> "bbs phone",
			"tel_modem"			=> "modem phone",
			"tel_isdn"			=> "isdn phone",
			"tel_car"			=> "car phone",
			"tel_video"			=> "video phone",

			"tel_prefer"		=> "prefer", //yn
			"email"			    => "business email",
			"email_type"		=> "business email type",   //'INTERNET','CompuServe',etc...
			"email_home"		=> "home email",  //yn
			"email_home_type"   => "home email type",
			"address2"			=> "address line 2",
			"address3"          => "address line 3",
			"bday"				=> "birthday",
			"url"				=> "url",
			"pubkey"            => "public key",
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
     
		$t = new Template($phpgw->common->get_tpl_dir("addressbook"));
		$t->set_file(array( "form"	=> "form.tpl"));
		
		$email        = $fields["email"];
		$emailtype    = $fields["email_type"];
		$hemail       = $fields["email_home"];
		$hemailtype   = $fields["email_home_type"];
		$firstname    = $fields["n_given"];
		$middle       = $fields["n_middle"];
		$prefix       = $fields["n_prefix"];
		$suffix       = $fields["n_suffix"];
		$lastname     = $fields["n_family"];
		$title        = $fields["title"];
		$wphone       = $fields["tel_work"];
		$hphone       = $fields["tel_home"];
		$fax          = $fields["tel_fax"];
		$pager        = $fields["tel_pager"];
		$mphone       = $fields["tel_cell"];
		$ophone       = $fields["ophone"];
		$msgphone     = $fields["tel_msg"];
		$isdnphone    = $fields["tel_isdn"];
		$carphone     = $fields["tel_car"];
		$vidphone     = $fields["tel_video"];
		$preferred    = $fields["tel_prefer"];

		$bstreet      = $fields["adr_one_street"];
		$address2     = $fields["address2"];
		$address3     = $fields["address3"];
		$bcity        = $fields["adr_one_locality"];
		$bstate       = $fields["adr_one_region"];
		$bzip         = $fields["adr_one_postalcode"];
		$bcountry     = $fields["adr_one_countryname"];
		$one_dom      = $fields["one_dom"];
		$one_intl     = $fields["one_intl"];
		$one_parcel   = $fields["one_parcel"];
		$one_postal   = $fields["one_postal"];

		$hstreet      = $fields["adr_two_street"];
		$hcity        = $fields["adr_two_locality"];
		$hstate       = $fields["adr_two_region"];
		$hzip         = $fields["adr_two_postalcode"];
		$hcountry     = $fields["adr_two_countryname"];
		$btype        = $fields["adr_two_type"];
		$two_dom      = $fields["two_dom"];
		$two_intl     = $fields["two_intl"];
		$two_parcel   = $fields["two_parcel"];
		$two_postal   = $fields["two_postal"];

		$timezone     = $fields["tz"];
		$bday         = $fields["bday"];
		$notes        = stripslashes($fields["note"]);
		$company      = $fields["org_name"];
		$department   = $fields["org_unit"];
		$url          = $fields["url"];
		$pubkey       = $fields["pubkey"];

		$this = CreateObject("phpgwapi.contacts");

		if ($format != "view") {
			$email 	    = "<input name=\"email\" value=\"$email\">";
			$firstname  = "<input name=\"firstname\" value=\"$firstname\">";
			$lastname   = "<input name=\"lastname\" value=\"$lastname\">";
			$middle     = "<input name=\"middle\" value=\"$middle\">";
			$prefix     = "<input name=\"prefix\" value=\"$prefix\" size=\"10\">";
			$suffix     = "<input name=\"suffix\" value=\"$suffix\" size=\"10\">";
			$title      = "<input name=\"title\" value=\"$title\">";

			while (list($name,$val) = each($this->tel_types)) {
				$str[$name] = "\n".'<INPUT type="radio" name="tel_prefer" value="'.$name.'"';
				if ($name == $preferred) {
					$str[$name] .= ' checked';
				}
				$str[$name] .= '>';
			}
			$hphone     = "<input name=\"hphone\"    value=\"$hphone\"> ".$str['home'];
			$wphone	    = "<input name=\"wphone\"    value=\"$wphone\"> ".$str['work'];
			$msgphone   = "<input name=\"msgphone\"  value=\"$msgphone\"> ".$str['msg'];
			$isdnphone	= "<input name=\"isdnphone\" value=\"$isdnphone\"> ".$str['isdn'];
			$carphone   = "<input name=\"carphone\"  value=\"$carphone\"> ".$str['car'];
			$vidphone	= "<input name=\"vidphone\"  value=\"$vidphone\"> ".$str['video'];
			$fax        = "<input name=\"fax\"       value=\"$fax\"> ".$str['fax'];
			$pager      = "<input name=\"pager\"     value=\"$pager\"> ".$str['pager'];
			$mphone     = "<input name=\"mphone\"    value=\"$mphone\"> ".$str['cell'];

			$ophone	    = "<input name=\"ophone\" value=\"$ophone\">";
			$bstreet    = "<input name=\"bstreet\" value=\"$bstreet\">";
			$address2   = "<input name=\"address2\" value=\"$address2\">";
			$address3   = "<input name=\"address3\" value=\"$address3\">";
			$bcity      = "<input name=\"bcity\" value=\"$bcity\">";
			$bstate     = "<input name=\"bstate\" value=\"$bstate\">";
			$bzip       = "<input name=\"bzip\" value=\"$bzip\">";
			$bcountry   = "<input name=\"bcountry\" value=\"$bcountry\">";
			$company    = "<input name=\"company\" value=\"$company\">";
			$department = "<input name=\"department\" value=\"$department\">";

			$hemail      = "<input name=\"hemail\" value=\"$hemail\">";
			$hstreet    = "<input name=\"hstreet\" value=\"$hstreet\">";
			$hcity      = "<input name=\"hcity\" value=\"$hcity\">";
			$hstate     = "<input name=\"hstate\" value=\"$hstate\">";
			$hzip       = "<input name=\"hzip\" value=\"$hzip\">";
			$hcountry   = "<input name=\"hcountry\" value=\"$hcountry\">";

			if (strlen($bday) > 2) {
				list( $month, $day, $year ) = split( '/', $bday );
				$temp_month[$month] = "SELECTED";
				$bday_month = "<select name=bday_month>"
							. "<option value=\"\" $temp_month[0]> </option>"
							. "<option value=1  $temp_month[1]>"  . lang("january")   . "</option>" 
							. "<option value=2  $temp_month[2]>"  . lang("february")  . "</option>"
							. "<option value=3  $temp_month[3]>"  . lang("march")     . "</option>"
							. "<option value=4  $temp_month[4]>"  . lang("april")     . "</option>"
							. "<option value=5  $temp_month[5]>"  . lang("may")       . "</option>"
							. "<option value=6  $temp_month[6]>"  . lang("june")      . "</option>" 
							. "<option value=7  $temp_month[7]>"  . lang("july")      . "</option>"
							. "<option value=8  $temp_month[8]>"  . lang("august")    . "</option>"
							. "<option value=9  $temp_month[9]>"  . lang("september") . "</option>"
							. "<option value=10 $temp_month[10]>" . lang("october")   . "</option>"
							. "<option value=11 $temp_month[11]>" . lang("november")  . "</option>"
							. "<option value=12 $temp_month[12]>" . lang("december")  . "</option>"
							. "</select>";
				$bday_day   = '<input maxlength="2" name="bday_day" value="' . $day . '" size="2">';
				$bday_year  = '<input maxlength="4" name="bday_year" value="' . $year . '" size="4">';
			} else {
				$bday_month = "<select name=bday_month>"
							. "<option value=\"\" SELECTED> </option>"
							. "<option value=1>"  . lang("january")   . "</option>"
							. "<option value=2>"  . lang("february")  . "</option>"
							. "<option value=3>"  . lang("march")     . "</option>"
							. "<option value=4>"  . lang("april")     . "</option>"
							. "<option value=5>"  . lang("may")       . "</option>"
							. "<option value=6>"  . lang("june")      . "</option>"
							. "<option value=7>"  . lang("july")      . "</option>"
							. "<option value=8>"  . lang("august")    . "</option>"
							. "<option value=9>"  . lang("september") . "</option>"
							. "<option value=10>" . lang("october")   . "</option>"
							. "<option value=11>" . lang("november")  . "</option>"
							. "<option value=12>" . lang("december")  . "</option>"
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

			$email_type = '<select name=email_type>';
			while ($type = each($this->email_types)) {
				$email_type .= '<option value="'.$type[0].'"';
				if ($type[0] == $emailtype) { $email_type .= ' selected'; }
					$email_type .= '>'.$type[1].'</option>';
			}
			$email_type .= "</select>";

			reset($this->email_types);
    		$hemail_type = '<select name=hemail_type>';
			while ($type = each($this->email_types)) {
				$hemail_type .= '<option value="'.$type[0].'"';
				if ($type[0] == $hemailtype) { $hemail_type .= ' selected'; }
					$hemail_type .= '>'.$type[1].'</option>';
			}
			$hemail_type .= "</select>";

			reset($this->adr_types);
			while (list($type,$val) = each($this->adr_types)) {
				$badrtype .= "\n".'<INPUT type="checkbox" name="one_'.$type.'"';
				$ot = 'one_'.$type;
				eval("
					if (\$$ot=='on') {
						\$badrtype \.= ' value=\"on\" checked';
					}
				");
				$badrtype .= '>'.$val;
			}

			reset($this->adr_types);
			while (list($type,$val) = each($this->adr_types)) {
				$hadrtype .= "\n".'<INPUT type="checkbox" name="two_'.$type.'"';
				$tt = 'two_'.$type;
				eval("
					if (\$$tt=='on') {
						\$hadrtype \.= ' value=\"on\" checked';
					}
				");
				$hadrtype .= '>'.$val;
			}

			$notes	 = '<TEXTAREA cols="60" name="notes" rows="4">' . $notes . '</TEXTAREA>';
			$pubkey  = '<TEXTAREA cols="60" name="notes" rows="6">' . $pubkey . '</TEXTAREA>';
		} else {
			$notes	= "<form><TEXTAREA cols=\"60\" name=\"notes\" rows=\"4\">"
					. $notes . "</TEXTAREA></form>";
			if ($bday == "//")
				$bday = "";
		}

		if ($action) {
			echo "<FORM action=\"".$phpgw->link('/addressbook/' . $action)."\" method=\"post\">\n";
		}

		if (! ereg("^http://",$url)) {
			$url = "http://". $url;
		} 

		$birthday = $phpgw->common->dateformatorder($bday_year,$bday_month,$bday_day)
					. '<font face="'.$theme["font"].'" size="-2">(e.g. 1969)</font>';

		if ($format == "view")
			$create .= '<tr><td><font size="-1">'.lang("Created by").':</font></td>'
					. '<td colspan="3"><font size="-1">'
					. grab_owner_name($fields["owner"]);
		else
			$create = '';
  
		$t->set_var("lang_home",lang("Home"));
		$t->set_var("lang_business",lang("Business"));
		$t->set_var("lang_personal",lang("Personal"));

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
		$t->set_var("lang_birthday",lang("Birthday"));
		$t->set_var("birthday",$birthday);

		$t->set_var("lang_company",lang("Company Name"));
		$t->set_var("company",$company);
		$t->set_var("lang_department",lang("Department"));
		$t->set_var("department",$department);
		$t->set_var("lang_title",lang("Title"));
		$t->set_var("title",$title);
		$t->set_var("lang_email",lang("Business Email"));
		$t->set_var("email",$email);
		$t->set_var("lang_email_type",lang("Business EMail Type"));
		$t->set_var("email_type",$email_type);
		$t->set_var("lang_url",lang("URL"));
		$t->set_var("url",$url);
		$t->set_var("lang_timezone",lang("time zone offset"));
		$t->set_var("timezone",$time_zone);
		$t->set_var("lang_fax",lang("Business Fax"));
		$t->set_var("fax",$fax);
		$t->set_var("lang_wphone",lang("Business Phone"));
		$t->set_var("wphone",$wphone);
		$t->set_var("lang_pager",lang("Pager"));
		$t->set_var("pager",$pager);
		$t->set_var("lang_mphone",lang("Cell Phone"));
		$t->set_var("mphone",$mphone);
		$t->set_var("lang_msgphone",lang("Message Phone"));
		$t->set_var("msgphone",$msgphone);
		$t->set_var("lang_isdnphone",lang("ISDN Phone"));
		$t->set_var("isdnphone",$isdnphone);
		$t->set_var("lang_carphone",lang("Car Phone"));
		$t->set_var("carphone",$carphone);
		$t->set_var("lang_vidphone",lang("Video Phone"));
		$t->set_var("vidphone",$vidphone);

		$t->set_var("lang_ophone",lang("Other Number"));
		$t->set_var("ophone",$ophone);
		$t->set_var("lang_bstreet",lang("Business Street"));
		$t->set_var("bstreet",$bstreet);
		$t->set_var("lang_address2",lang("Address Line 2"));
		$t->set_var("address2",$address2);
		$t->set_var("lang_address3",lang("Address Line 3"));
		$t->set_var("address3",$address3);
		$t->set_var("lang_bcity",lang("Business City"));
		$t->set_var("bcity",$bcity);
		$t->set_var("lang_bstate",lang("Business State"));
		$t->set_var("bstate",$bstate);
		$t->set_var("lang_bzip",lang("Business Zip Code"));
		$t->set_var("bzip",$bzip);
		$t->set_var("lang_bcountry",lang("Business Country"));
		$t->set_var("bcountry",$bcountry);
		$t->set_var("lang_badrtype",lang("Address Type"));
		$t->set_var("badrtype",$badrtype);
		
		$t->set_var("lang_hphone",lang("Home Phone"));
		$t->set_var("hphone",$hphone);
		$t->set_var("lang_hemail",lang("Home Email"));
		$t->set_var("hemail",$hemail);
		$t->set_var("lang_hemail_type",lang("Home EMail Type"));
		$t->set_var("hemail_type",$hemail_type);
		$t->set_var("lang_hstreet",lang("Home Street"));
		$t->set_var("hstreet",$hstreet);
		$t->set_var("lang_hcity",lang("Home City"));
		$t->set_var("hcity",$hcity);
		$t->set_var("lang_hstate",lang("Home State"));
		$t->set_var("hstate",$hstate);
		$t->set_var("lang_hzip",lang("Home Zip Code"));
		$t->set_var("hzip",$hzip);
		$t->set_var("lang_hcountry",lang("Home Country"));
		$t->set_var("hcountry",$hcountry);
		$t->set_var("lang_hadrtype",lang("Address Type"));
		$t->set_var("hadrtype",$hadrtype);

		$t->set_var("create",$create);
		$t->set_var("lang_notes",lang("notes"));
		$t->set_var("notes",$notes);
		$t->set_var("lang_pubkey",lang("Public Key"));
		$t->set_var("pubkey",$pubkey);
		
		$t->parse("out","form");
		$t->pparse("out","form");
	} //end form function

	function parsevcard($filename,$access='') {
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

			// Add this entry to the addressbook before moving on to the next one.
			fillab($varray);
		} // while(!feof($vcard))

		fclose($vcard);
		return TRUE;
	}


	function fillab($varray,$access='') {
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
					case "url":
						$url = $v[0];
						// Fix the result of exploding on ':' above
						if (substr($url,0,5) == 'http/') {
							$url = ereg_replace('http//','http://',$url);
						} elseif (substr($url,0,6) == 'https/') {
							$url = ereg_replace('https//','https://',$url);
						} elseif (substr($url,0,7) != 'http://') {
							$url = 'http://' . $url;
						}
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

		$fields["owner"]              = $phpgw_info["user"]["account_id"];
		$fields["n_given"]            = addslashes($firstname);
		$fields["n_family"]           = addslashes($lastname);
		$fields["fn"]                 = addslashes($firstname . " " . $lastname);
		$fields["title"]              = addslashes($title);
		$fields["d_email"]            = addslashes($email);
		$fields["tel_work"]           = addslashes($wphone);
		$fields["tel_home"]           = addslashes($hphone);
		$fields["tel_fax"]            = addslashes($fax);
		$fields["tel_pager"]          = addslashes($pager);
		$fields["tel_cell"]           = addslashes($mphone);
		$fields["tel_msg"]            = addslashes($ophone);
		$fields["adr_one_street"]     = addslashes($street);
		$fields["address2"]           = addslashes($address2);
		$fields["adr_one_locality"]   = addslashes($city);
		$fields["adr_one_region"]     = addslashes($state);
		$fields["adr_one_postalcode"] = addslashes($zip);
		$fields["bday"]               = addslashes($bday);
		$fields["url"]                = $url;
		$fields["note"]               = addslashes($notes);
		$fields["org_name"]           = addslashes($company);

		$this = CreateObject("phpgwapi.contacts");
		$this->add($phpgw_info["user"]["account_id"],$fields);
	}

?>
